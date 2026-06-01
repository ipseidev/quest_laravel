<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Serves the public legal pages (privacy policy, terms of service) that the
 * mobile app's About screen links to and that the App Store / Play Store
 * submission requires. These live outside the `/api` surface.
 */
class LegalController extends Controller
{
    public function privacy(Request $request): View
    {
        return view('legal.privacy', ['lang' => $this->resolveLocale($request)]);
    }

    public function terms(Request $request): View
    {
        return view('legal.terms', ['lang' => $this->resolveLocale($request)]);
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
