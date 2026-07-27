<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Serves the public legal pages (legal notice, privacy policy, terms of
 * service, support) that the mobile app's About screen links to and that the
 * App Store / Play Store submission requires. These live outside the `/api`
 * surface.
 */
class LegalController extends Controller
{
    public function privacy(Request $request): View
    {
        return view('legal.privacy', $this->viewData($request));
    }

    public function terms(Request $request): View
    {
        return view('legal.terms', $this->viewData($request));
    }

    public function support(Request $request): View
    {
        return view('legal.support', $this->viewData($request));
    }

    /**
     * Publisher identification required in France by LCEN art. 1-1 (recast by
     * loi SREN n° 2024-449 of 21 May 2024) for any professionally operated
     * public online service.
     */
    public function notice(Request $request): View
    {
        return view('legal.notice', $this->viewData($request));
    }

    /**
     * Shared payload for every legal page: the resolved display language plus
     * the publisher and hosting facts from `config/legal.php`. No page should
     * hardcode an identification detail — they would drift apart, and a legal
     * notice that contradicts the privacy policy is worse than neither.
     *
     * @return array{lang: string, legal: array<string, mixed>}
     */
    protected function viewData(Request $request): array
    {
        return [
            'lang' => $this->resolveLocale($request),
            'legal' => config('legal'),
        ];
    }

    /**
     * Resolve the display language: an explicit `?lang=` query wins, else the
     * caller's Accept-Language (the in-app browser reflects the device locale),
     * else the English fallback.
     */
    protected function resolveLocale(Request $request): string
    {
        $supported = ['en', 'fr'];

        $requested = $request->query('lang')
            ?: $request->getPreferredLanguage($supported);

        $lang = is_string($requested) ? substr($requested, 0, 2) : 'en';

        return in_array($lang, $supported, true) ? $lang : 'en';
    }
}
