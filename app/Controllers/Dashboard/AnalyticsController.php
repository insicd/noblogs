<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Dashboard;

use Noblogs\Core\Response;
use Noblogs\Models\Hit;

/**
 * Statistiche di lettura di un blog.
 *
 * Il grafico è SVG generato dal server: nessuna libreria, nessuna richiesta in
 * più, e la pagina resta leggibile anche senza JavaScript.
 */
final class AnalyticsController extends DashboardController
{
    /** Periodi selezionabili, in giorni. */
    private const PERIODS = [7, 30, 90, 365];

    public function index(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $days = $this->request->int('giorni', 30);
        if (!in_array($days, self::PERIODS, true)) {
            $days = 30;
        }

        if (!$found->analytics_active) {
            return $this->panel('dashboard/analytics', [
                'pageTitle' => __('analytics.title'),
                'section'   => 'analytics',
                'blog'      => $found,
                'days'      => $days,
                'periods'   => self::PERIODS,
                'active'    => false,
                'series'    => [],
                'totals'    => ['reads' => 0, 'visitors' => 0],
                'readers'   => 0,
                'top'       => [],
                'breakdown' => [],
            ]);
        }

        $breakdown = [];
        foreach (['referrer', 'device', 'browser', 'country'] as $dimension) {
            $breakdown[$dimension] = Hit::breakdown($found, $dimension, $days, 12);
        }

        return $this->panel('dashboard/analytics', [
            'pageTitle' => __('analytics.title'),
            'section'   => 'analytics',
            'blog'      => $found,
            'days'      => $days,
            'periods'   => self::PERIODS,
            'active'    => true,
            'series'    => Hit::daily($found, $days),
            'totals'    => Hit::totals($found, $days),
            'readers'   => Hit::currentReaders($found, 5),
            'top'       => Hit::topPosts($found, $days, 20),
            'breakdown' => $breakdown,
        ]);
    }
}
