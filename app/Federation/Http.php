<?php

declare(strict_types=1);

namespace Noblogs\Federation;

use Noblogs\Core\Config;
use Noblogs\Core\ErrorHandler;
use Noblogs\Core\Request;
use Noblogs\Models\Blog;

/**
 * HTTP firmato verso il fediverso, e verifica delle firme in ingresso.
 *
 * Le URL remote devono essere HTTPS pubbliche: un actor malevolo non deve
 * farci interrogare la rete locale.
 */
final class Http
{
    private const TIMEOUT = 12;
    private const MAX_BYTES = 1048576;

    /**
     * @return array<string,mixed>|null
     */
    public static function getJson(string $url): ?array
    {
        $response = self::request('GET', $url, '', [
            'Accept'     => 'application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/json',
            'User-Agent' => self::userAgent(),
        ]);
        if ($response === null || $response['status'] < 200 || $response['status'] >= 300) {
            return null;
        }
        $decoded = json_decode($response['body'], true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,mixed> $payload
     * @return int HTTP status, 0 se il trasporto fallisce
     */
    public static function postSigned(Blog $blog, string $inbox, array $payload): int
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($body) || $body === '') {
            return 0;
        }

        $parts = parse_url($inbox);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : 443;
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $digest = 'SHA-256=' . base64_encode(hash('sha256', $body, true));
        $headers = [
            'Host'         => $host . ($port !== 443 ? ':' . $port : ''),
            'Date'         => $date,
            'Digest'       => $digest,
            'Content-Type' => 'application/activity+json',
        ];

        $signature = self::sign($blog, 'post', $path, $headers);
        if ($signature === null) {
            return 0;
        }
        $headers['Signature'] = $signature;
        $headers['User-Agent'] = self::userAgent();
        $headers['Accept'] = 'application/activity+json, application/ld+json, application/json';
        $headers['Expect'] = '';

        $response = self::request('POST', $inbox, $body, $headers, false);
        $status = $response['status'] ?? 0;
        if ($status < 200 || $status >= 300) {
            $hint = is_array($response) ? substr(trim($response['body']), 0, 180) : '';
            ErrorHandler::log(new \RuntimeException(
                'ActivityPub POST ' . $inbox . ' → ' . $status . ($hint !== '' ? ' ' . $hint : '')
            ));
        }

        return $status;
    }

    public static function verify(Request $request, string $publicKeyPem): bool
    {
        $header = $request->header('Signature') ?? '';
        if ($header === '' && str_starts_with($request->header('Authorization') ?? '', 'Signature ')) {
            $header = substr((string) $request->header('Authorization'), 10);
        }
        $params = self::parseSignatureHeader($header);
        if ($params === null) {
            return false;
        }

        $algorithm = strtolower($params['algorithm'] ?? 'rsa-sha256');
        if (!in_array($algorithm, ['rsa-sha256', 'hs2019', 'rsa-sha-256'], true)) {
            return false;
        }

        $names = preg_split('/\s+/', trim($params['headers'] ?? '(created)')) ?: [];
        $lines = [];
        foreach ($names as $name) {
            $name = strtolower($name);
            if ($name === '(request-target)') {
                $lines[] = '(request-target): ' . $request->signatureTarget();
                continue;
            }
            if ($name === 'host') {
                $lines[] = 'host: ' . $request->host;
                continue;
            }
            if ($name === 'digest') {
                $digest = $request->header('Digest') ?? '';
                $expected = 'SHA-256=' . base64_encode(hash('sha256', $request->rawBody, true));
                if (!hash_equals(strtolower($expected), strtolower($digest))
                    && !hash_equals($expected, $digest)) {
                    return false;
                }
                $lines[] = 'digest: ' . $digest;
                continue;
            }
            $value = $request->header($name);
            if ($value === null) {
                return false;
            }
            $lines[] = $name . ': ' . $value;
        }

        $signature = base64_decode($params['signature'] ?? '', true);
        if ($signature === false || $publicKeyPem === '') {
            return false;
        }

        $ok = openssl_verify(implode("\n", $lines), $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);
        return $ok === 1;
    }

    /**
     * @param array<string,string> $headers
     */
    private static function sign(Blog $blog, string $method, string $path, array $headers): ?string
    {
        $keys = Keys::forBlog($blog);
        if ($keys === null) {
            return null;
        }

        $signed = ['(request-target)', 'host', 'date', 'digest'];
        $lines = [
            '(request-target): ' . strtolower($method) . ' ' . $path,
            'host: ' . $headers['Host'],
            'date: ' . $headers['Date'],
            'digest: ' . $headers['Digest'],
        ];

        $private = openssl_pkey_get_private($keys['private']);
        if ($private === false) {
            return null;
        }

        $binary = '';
        if (!openssl_sign(implode("\n", $lines), $binary, $private, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return sprintf(
            'keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
            ActivityPub::keyId($blog),
            implode(' ', $signed),
            base64_encode($binary)
        );
    }

    /**
     * @return array<string,string>|null
     */
    public static function parseSignatureHeader(string $header): ?array
    {
        if (trim($header) === '') {
            return null;
        }
        $params = [];
        if (preg_match_all('/([a-zA-Z]+)=(?:"([^"]*)"|([^\s,]+))/', $header, $matches, PREG_SET_ORDER) === false) {
            return null;
        }
        foreach ($matches as $match) {
            $params[strtolower($match[1])] = $match[2] !== '' ? $match[2] : ($match[3] ?? '');
        }
        return isset($params['signature'], $params['keyid']) ? $params + ['keyId' => $params['keyid']] : null;
    }

    public static function isSafeUrl(string $url): bool
    {
        if (preg_match('#^https://#i', $url) !== 1) {
            return false;
        }
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $port = $parts['port'] ?? 443;
        if ((int) $port !== 443) {
            return false;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if ($ip === $host && filter_var($host, FILTER_VALIDATE_IP) === false && $ip === $host) {
            // gethostbyname ha restituito il nome: DNS fallito.
            if (filter_var($host, FILTER_VALIDATE_IP) === false && !str_contains($ip, '.')) {
                return false;
            }
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long === false) {
                return false;
            }
            foreach ([
                ['0.0.0.0', '0.255.255.255'],
                ['10.0.0.0', '10.255.255.255'],
                ['127.0.0.0', '127.255.255.255'],
                ['169.254.0.0', '169.254.255.255'],
                ['172.16.0.0', '172.31.255.255'],
                ['192.168.0.0', '192.168.255.255'],
            ] as [$from, $to]) {
                $a = ip2long($from);
                $b = ip2long($to);
                if ($a !== false && $b !== false && $long >= $a && $long <= $b) {
                    return false;
                }
            }
            return true;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return !str_starts_with($ip, '::1') && !str_starts_with($ip, 'fc') && !str_starts_with($ip, 'fd')
                && !str_starts_with($ip, 'fe80');
        }

        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string}|null
     */
    private static function request(string $method, string $url, string $body, array $headers, bool $followRedirects = true): ?array
    {
        if (!self::isSafeUrl($url)) {
            return null;
        }

        if (function_exists('curl_init')) {
            return self::curl($method, $url, $body, $headers, $followRedirects);
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $context = stream_context_create([
            'http' => [
                'method'          => $method,
                'header'          => implode("\r\n", $headerLines),
                'content'         => $body,
                'timeout'         => self::TIMEOUT,
                'ignore_errors'   => true,
                'follow_location' => $followRedirects ? 1 : 0,
                'max_redirects'   => $followRedirects ? 3 : 0,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw)) {
            return null;
        }
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m) === 1) {
                $status = (int) $m[1];
            }
        }
        return ['status' => $status, 'body' => substr($raw, 0, self::MAX_BYTES)];
    }

    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string}|null
     */
    private static function curl(string $method, string $url, string $body, array $headers, bool $followRedirects = true): ?array
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $handle = curl_init($url);
        if ($handle === false) {
            return null;
        }
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS      => $followRedirects ? 3 : 0,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => $lines,
            CURLOPT_USERAGENT      => $headers['User-Agent'] ?? self::userAgent(),
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        ];
        if (defined('CURL_IPRESOLVE_V4')) {
            $opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $body;
        } else {
            $opts[CURLOPT_CUSTOMREQUEST] = $method;
            if ($body !== '') {
                $opts[CURLOPT_POSTFIELDS] = $body;
            }
        }
        curl_setopt_array($handle, $opts);
        if (defined('CURLPROTO_HTTPS')) {
            curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            curl_setopt($handle, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
        }
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if ($raw === false) {
            ErrorHandler::log(new \RuntimeException('ActivityPub HTTP: ' . curl_error($handle)));
            curl_close($handle);
            return null;
        }
        curl_close($handle);

        return ['status' => $status, 'body' => substr((string) $raw, 0, self::MAX_BYTES)];
    }

    public static function userAgent(): string
    {
        $domain = (string) Config::get('site.domain', 'localhost');
        return 'Noblogs/' . NOBLOGS_VERSION . ' (ActivityPub; +https://' . $domain . ')';
    }
}
