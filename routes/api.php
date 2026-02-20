<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\ProductionApprovalController;
use App\Http\Controllers\Api\PassApiController;
use App\Http\Controllers\Api\PassUpdateController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\Scanner\RedeemPassController;
use App\Http\Controllers\Scanner\ValidatePassController;
use Illuminate\Support\Facades\Route;

// Public signup endpoint
Route::post('/signup', [AccountController::class, 'store'])
    ->middleware('throttle:5,1');

Route::patch('/passes/{pass}/fields', [PassUpdateController::class, 'update']);

Route::middleware('auth:sanctum')->group(function () {
    // Account endpoints
    Route::get('/account', [AccountController::class, 'show']);
    Route::put('/account', [AccountController::class, 'update']);

    // Tier progression endpoints
    Route::post('/tier/request-production', [ProductionApprovalController::class, 'requestProduction'])
        ->name('account.tier.request-production');
    Route::post('/tier/request-live', [ProductionApprovalController::class, 'requestLive'])
        ->name('account.tier.request-live');
    Route::post('/tier/go-live', [ProductionApprovalController::class, 'goLive'])
        ->name('account.tier.go-live');

    // Certificate endpoints
    Route::get('/certificates/apple/csr', [CertificateController::class, 'downloadAppleCSR'])
        ->middleware('throttle:10,1');
    Route::post('/certificates/apple', [CertificateController::class, 'uploadAppleCertificate'])
        ->middleware('throttle:10,1');
    Route::delete('/certificates/apple/{certificate}', [CertificateController::class, 'deleteAppleCertificate']);
    Route::get('/certificates/apple/{certificate}/renew', [CertificateController::class, 'renewAppleCertificate']);

    Route::post('/certificates/google', [CertificateController::class, 'uploadGoogleCredential']);
    Route::delete('/certificates/google/{credential}', [CertificateController::class, 'deleteGoogleCredential']);
    Route::get('/certificates/google/{credential}/rotate', [CertificateController::class, 'rotateGoogleCredential']);

    // Create pass from template with custom data
    Route::post('/passes', [PassApiController::class, 'store']);

    // Get pass details
    Route::get('/passes/{pass}', [PassApiController::class, 'show']);

    // List user's passes
    Route::get('/passes', [PassApiController::class, 'index']);

    // Pass update history
    Route::get('/passes/{pass}/updates', [PassUpdateController::class, 'history']);
});

// Scanner API routes (authenticated via scanner token, no user login required)
Route::middleware('scanner.token')->prefix('scanner')->group(function () {
    Route::post('/validate', ValidatePassController::class)->name('scanner.validate');
    Route::post('/redeem', RedeemPassController::class)->name('scanner.redeem');
});
