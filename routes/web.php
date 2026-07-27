<?php

use App\Http\Controllers\LegalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public legal pages (outside /api). The mobile About screen links to
// /privacy, /terms and /legal-notice; the App Store / Play Store submission
// requires the first two.
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
// Support page — required as the App Store / Play Store "Support URL".
Route::get('/support', [LegalController::class, 'support'])->name('legal.support');
// Publisher identification (LCEN art. 1-1, recast by loi SREN n° 2024-449 of
// 21 May 2024 — the old art. 6-III no longer exists). Canonical URL follows the
// English scheme of its siblings; the French wording is kept as an alias
// because that is what a French user (or a regulator) types.
Route::get('/legal-notice', [LegalController::class, 'notice'])->name('legal.notice');
Route::get('/mentions-legales', [LegalController::class, 'notice']);
