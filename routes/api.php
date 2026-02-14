<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\ProductionApprovalController;
use App\Http\Controllers\Api\PassApiController;
use App\Http\Controllers\CertificateController;
use Illuminate\Support\Facades\Route;

// Public signup endpoint
Route::post('/signup', [AccountController::class, 'store']);

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
    Route::get('/certificates/apple/csr', [CertificateController::class, 'downloadAppleCSR']);
    Route::post('/certificates/apple', [CertificateController::class, 'uploadAppleCertificate']);
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
});
