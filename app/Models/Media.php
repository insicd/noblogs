<?php

declare(strict_types=1);

namespace Noblogs\Models;

use Noblogs\Core\Config;
use Noblogs\Core\Database;
use Noblogs\Support\Str;

/**
 * File caricati da un blog.
 *
 * Le immagini vengono ricodificate prima di essere salvate: la ricodifica
 * elimina i metadati EXIF (compresa la posizione GPS che molti telefoni
 * scrivono senza avvisare) e riduce le dimensioni fuori scala.
 */
final class Media extends Model
{
    protected static string $table = 'media';

    protected static array $casts = [
        'id'      => 'int',
        'blog_id' => 'int',
        'size'    => 'int',
        'width'   => 'int',
        'height'  => 'int',
    ];

    public int $blog_id = 0;
    public string $filename = '';
    public string $path = '';
    public string $mime = '';
    public int $size = 0;
    public ?int $width = null;
    public ?int $height = null;
    public string $created_at = '';

    /** Estensioni accettate, per categoria. */
    private const ALLOWED = [
        'image'    => ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico'],
        'video'    => ['mp4', 'webm', 'ogv'],
        'audio'    => ['mp3', 'ogg', 'oga', 'opus', 'wav', 'flac', 'm4a'],
        'document' => ['pdf', 'txt', 'md', 'csv', 'epub', 'odt', 'ods', 'zip'],
        'font'     => ['woff', 'woff2', 'ttf', 'otf'],
    ];

    /** @return list<self> */
    public static function forBlog(Blog $blog, int $limit = 500): array
    {
        return self::hydrateAll(Database::instance()->fetchAll(
            'SELECT * FROM {{media}} WHERE blog_id = ? ORDER BY created_at DESC LIMIT ' . max(1, $limit),
            [$blog->id]
        ));
    }

    public static function findForBlog(Blog $blog, int $id): ?self
    {
        return self::hydrateOrNull(Database::instance()->fetch(
            'SELECT * FROM {{media}} WHERE blog_id = ? AND id = ?',
            [$blog->id, $id]
        ));
    }

    public static function countForBlog(Blog $blog): int
    {
        return (int) Database::instance()->fetchColumn(
            'SELECT COUNT(*) FROM {{media}} WHERE blog_id = ?',
            [$blog->id]
        );
    }

    public static function directory(Blog $blog): string
    {
        return NOBLOGS_PUBLIC . '/media/' . $blog->subdomain;
    }

    public function absolutePath(Blog $blog): string
    {
        return self::directory($blog) . '/' . $this->path;
    }

    /**
     * Elabora e salva un file caricato.
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @return array{0:?self,1:?string} Il media creato oppure il messaggio d'errore.
     */
    public static function store(Blog $blog, array $file, bool $optimize = true): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [null, self::uploadErrorMessage((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE))];
        }

        $maxBytes = (int) Config::get('limits.upload_max_bytes', 10485760);
        if ($file['size'] > $maxBytes) {
            return [null, __('media.error.too_large', ['size' => self::humanBytes($maxBytes)])];
        }

        if (self::countForBlog($blog) >= (int) Config::get('limits.files_per_blog', 2000)) {
            return [null, __('media.error.too_many_files')];
        }

        $quota = (int) Config::get('limits.storage_per_blog', 524288000);
        if ($blog->storage_used + $file['size'] > $quota) {
            return [null, __('media.error.quota', ['size' => self::humanBytes($quota)])];
        }

        $extension = mb_strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $category = self::categoryFor($extension);
        if ($category === null) {
            return [null, __('media.error.type_not_allowed', ['ext' => $extension])];
        }

        // Il tipo dichiarato dal browser non è affidabile: si legge dal file.
        $detected = self::detectMime($file['tmp_name'], $extension);
        if ($detected === null) {
            return [null, __('media.error.type_mismatch')];
        }

        $directory = self::directory($blog);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return [null, __('media.error.directory')];
        }

        $baseName = Str::slug(pathinfo($file['name'], PATHINFO_FILENAME)) ?: 'file';
        $baseName = mb_substr($baseName, 0, 80);

        $width = null;
        $height = null;
        $targetExtension = $extension;
        $payload = null;

        if ($category === 'image' && $optimize && !in_array($extension, ['svg', 'gif', 'ico'], true)) {
            $processed = self::processImage($file['tmp_name']);
            if ($processed !== null) {
                [$payload, $targetExtension, $width, $height, $detected] = $processed;
            }
        }

        if ($category === 'image' && $extension === 'svg') {
            $svg = (string) file_get_contents($file['tmp_name']);
            $payload = self::sanitizeSvg($svg);
        }

        $path = self::uniquePath($blog, $baseName, $targetExtension);
        $absolute = $directory . '/' . $path;

        if ($payload !== null) {
            $written = @file_put_contents($absolute, $payload) !== false;
        } else {
            $written = @move_uploaded_file($file['tmp_name'], $absolute)
                || @rename($file['tmp_name'], $absolute);
        }

        if (!$written) {
            return [null, __('media.error.write')];
        }
        @chmod($absolute, 0644);

        if ($width === null && $category === 'image') {
            $info = @getimagesize($absolute);
            if ($info !== false) {
                [$width, $height] = $info;
            }
        }

        $media = new self();
        $media->blog_id = $blog->id;
        $media->filename = $baseName . '.' . $targetExtension;
        $media->path = $path;
        $media->mime = $detected;
        $media->size = (int) filesize($absolute);
        $media->width = $width;
        $media->height = $height;
        $media->created_at = self::now();

        $media->insertRow([
            'blog_id'    => $media->blog_id,
            'filename'   => $media->filename,
            'path'       => $media->path,
            'mime'       => $media->mime,
            'size'       => $media->size,
            'width'      => $media->width,
            'height'     => $media->height,
            'created_at' => $media->created_at,
        ]);

        $blog->update(['storage_used' => $blog->storage_used + $media->size]);

        return [$media, null];
    }

    public function deleteWithFile(Blog $blog): bool
    {
        $absolute = $this->absolutePath($blog);
        if (is_file($absolute)) {
            @unlink($absolute);
        }
        $blog->update(['storage_used' => max(0, $blog->storage_used - $this->size)]);
        return $this->delete();
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function humanSize(): string
    {
        return self::humanBytes($this->size);
    }

    // -----------------------------------------------------------------------
    // Elaborazione
    // -----------------------------------------------------------------------

    /**
     * Ricodifica un'immagine: rimuove i metadati, raddrizza secondo
     * l'orientamento EXIF e riduce la larghezza sopra soglia.
     *
     * @return array{0:string,1:string,2:int,3:int,4:string}|null
     *         Contenuto, estensione, larghezza, altezza, mime.
     */
    private static function processImage(string $tmpPath): ?array
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $raw = @file_get_contents($tmpPath);
        if ($raw === false) {
            return null;
        }

        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            return null;
        }

        $image = self::applyExifOrientation($image, $tmpPath);

        $width = imagesx($image);
        $height = imagesy($image);
        $maxWidth = (int) Config::get('limits.image_max_width', 1600);

        if ($width > $maxWidth) {
            $newHeight = (int) round($height * ($maxWidth / $width));
            $resized = imagecreatetruecolor($maxWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
            $width = $maxWidth;
            $height = $newHeight;
        }

        ob_start();
        if (function_exists('imagewebp')) {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
            imagewebp($image, null, 82);
            $extension = 'webp';
            $mime = 'image/webp';
        } else {
            imagejpeg($image, null, 85);
            $extension = 'jpg';
            $mime = 'image/jpeg';
        }
        $payload = (string) ob_get_clean();
        imagedestroy($image);

        return $payload === '' ? null : [$payload, $extension, $width, $height, $mime];
    }

    private static function applyExifOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 0) : 0;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated instanceof \GdImage) {
            imagedestroy($image);
            return $rotated;
        }
        return $image;
    }

    /**
     * Ripulisce un SVG dagli elementi eseguibili: un SVG è un documento XML e
     * può contenere script che verrebbero eseguiti nel dominio del blog.
     */
    private static function sanitizeSvg(string $svg): string
    {
        $svg = (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg);
        $svg = (string) preg_replace('#<(foreignObject|iframe|embed|object|use)\b[^>]*>.*?</\1>#is', '', $svg);
        $svg = (string) preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg);
        $svg = (string) preg_replace('/(href|xlink:href)\s*=\s*("|\')\s*(javascript|data):[^"\']*\2/i', '', $svg);
        return (string) preg_replace('/<!ENTITY[^>]*>/i', '', $svg);
    }

    private static function categoryFor(string $extension): ?string
    {
        foreach (self::ALLOWED as $category => $extensions) {
            if (in_array($extension, $extensions, true)) {
                return $category;
            }
        }
        return null;
    }

    private static function detectMime(string $path, string $extension): ?string
    {
        $mime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $path);
                finfo_close($finfo);
                $mime = is_string($detected) ? $detected : null;
            }
        }
        $mime ??= self::mimeForExtension($extension);

        // Un file che dice di essere un'immagine deve esserlo davvero.
        if (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'], true)) {
            if (@getimagesize($path) === false) {
                return null;
            }
        }

        return $mime;
    }

    private static function mimeForExtension(string $extension): string
    {
        static $map = [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif',
            'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
            'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogv' => 'video/ogg',
            'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg',
            'opus' => 'audio/opus', 'wav' => 'audio/wav', 'flac' => 'audio/flac',
            'm4a' => 'audio/mp4', 'pdf' => 'application/pdf', 'txt' => 'text/plain',
            'md' => 'text/markdown', 'csv' => 'text/csv', 'epub' => 'application/epub+zip',
            'zip' => 'application/zip', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
            'ttf' => 'font/ttf', 'otf' => 'font/otf',
        ];
        return $map[$extension] ?? 'application/octet-stream';
    }

    private static function uniquePath(Blog $blog, string $baseName, string $extension): string
    {
        $directory = self::directory($blog);
        $candidate = $baseName . '.' . $extension;
        $counter = 2;
        while (file_exists($directory . '/' . $candidate)) {
            $candidate = $baseName . '-' . $counter . '.' . $extension;
            $counter++;
        }
        return $candidate;
    }

    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('media.error.too_large_php'),
            UPLOAD_ERR_PARTIAL   => __('media.error.partial'),
            UPLOAD_ERR_NO_FILE   => __('media.error.no_file'),
            default              => __('media.error.generic'),
        };
    }

    public static function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }
        return ($index === 0 ? (string) (int) $value : number_format($value, 1, ',', '.')) . ' ' . $units[$index];
    }
}
