<?php

use App\Http\Controllers\LegalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public legal pages (outside /api). The mobile About screen links to
// /privacy and /terms; the App Store / Play Store submission requires them.
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
// Support page — required as the App Store / Play Store "Support URL".
Route::get('/support', [LegalController::class, 'support'])->name('legal.support');
