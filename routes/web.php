<?php

use App\Http\Controllers\PassDistributionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// Public distribution link route (no authentication required)
Route::get('p/{slug}', [PassDistributionController::class, 'show'])
    ->name('passes.show-by-link');

Route::get('dashboard', function () {
    $user = auth()->user();

    $totalPasses = $user->passes()->count();

    $stats = [
        'totalPasses' => $totalPasses,
        'applePasses' => $user->passes()->whereJsonContains('platforms', 'apple')->count(),
        'googlePasses' => $user->passes()->whereJsonContains('platforms', 'google')->count(),
        'used' => $totalPasses,
        'limit' => app(\App\Services\PassLimitService::class)->getPlanConfig($user)['pass_limit'],
    ];

    $recentPasses = $user->passes()
        ->with('template')
        ->latest()
        ->limit(5)
        ->get();

    return Inertia::render('dashboard', [
        'stats' => $stats,
        'recentPasses' => $recentPasses,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/passes.php';
require __DIR__.'/settings.php';
