<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\MediaLibraryAssetController;
use App\Http\Controllers\PassController;
use App\Http\Controllers\PassDistributionController;
use App\Http\Controllers\PassDownloadController;
use App\Http\Controllers\Passes\AppleWebServiceController;
use App\Http\Controllers\Passes\PassUpdateDashboardController;
use App\Http\Controllers\PassImageController;
use App\Http\Controllers\PassTemplateController;
use App\Http\Controllers\PassTypeFieldMapController;
use App\Http\Controllers\PassTypeSampleController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/apple/v1')->group(function () {
    Route::post('devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}', [AppleWebServiceController::class, 'registerDevice'])
        ->name('apple.web-service.register-device');
    Route::delete('devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}/{serialNumber}', [AppleWebServiceController::class, 'unregisterDevice'])
        ->name('apple.web-service.unregister-device');
    Route::get('devices/{deviceLibraryIdentifier}/registrations/{passTypeIdentifier}', [AppleWebServiceController::class, 'getUpdatedPasses'])
        ->name('apple.web-service.get-updated-passes');
    Route::get('passes/{passTypeIdentifier}/{serialNumber}', [AppleWebServiceController::class, 'getLatestPass'])
        ->name('apple.web-service.get-latest-pass');
    Route::post('log', [AppleWebServiceController::class, 'logErrors'])
        ->name('apple.web-service.log-errors');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Pass type samples
    Route::get('passes/samples', [PassTypeSampleController::class, 'index'])
        ->name('passes.samples.index');
    Route::post('passes/samples', [PassTypeSampleController::class, 'store'])
        ->name('passes.samples.store');
    Route::patch('passes/samples/{sample}', [PassTypeSampleController::class, 'update'])
        ->name('passes.samples.update');
    Route::delete('passes/samples/{sample}', [PassTypeSampleController::class, 'destroy'])
        ->name('passes.samples.destroy');

    // Media library assets
    Route::get('passes/media/assets', [MediaLibraryAssetController::class, 'index'])
        ->name('passes.media.assets.index');
    Route::post('passes/media/assets', [MediaLibraryAssetController::class, 'store'])
        ->name('passes.media.assets.store');
    Route::delete('passes/media/assets/{asset}', [MediaLibraryAssetController::class, 'destroy'])
        ->name('passes.media.assets.destroy');

    // Pass type field map
    Route::get('passes/field-map', [PassTypeFieldMapController::class, 'index'])
        ->name('passes.fieldMap.index');

    // Pass management routes
    Route::resource('passes', PassController::class);

    // Apply pass limit middleware only to store action
    Route::post('passes', [PassController::class, 'store'])
        ->name('passes.store')
        ->middleware('enforce.pass.limit');

    // Pass download and generation routes
    Route::post('passes/{pass}/download/apple', [PassDownloadController::class, 'downloadApple'])
        ->name('passes.download.apple');
    Route::post('passes/{pass}/generate/google', [PassDownloadController::class, 'generateGoogleLink'])
        ->name('passes.generate.google');

    Route::patch('passes/{pass}/update', [PassUpdateDashboardController::class, 'update'])
        ->name('passes.update-fields');
    Route::get('passes/{pass}/updates', [PassUpdateDashboardController::class, 'history'])
        ->name('passes.updates.history');
    Route::post('passes/bulk-update', [PassUpdateDashboardController::class, 'bulkUpdate'])
        ->name('passes.bulk-update');
    Route::get('passes/bulk-update/{bulkUpdate}', [PassUpdateDashboardController::class, 'bulkUpdateStatus'])
        ->name('passes.bulk-update.status');

    // Pass image upload
    Route::post('passes/images', [PassImageController::class, 'store'])
        ->name('passes.images.store');

    // Pass template management routes
    Route::resource('templates', PassTemplateController::class);

    // Billing routes
    Route::get('billing', [BillingController::class, 'index'])
        ->name('billing.index');
    Route::post('billing/checkout', [BillingController::class, 'checkout'])
        ->name('billing.checkout');
    Route::get('billing/portal', [BillingController::class, 'portal'])
        ->name('billing.portal');

    // Pass distribution links
    Route::post('passes/{pass}/distribution-links', [PassDistributionController::class, 'store'])
        ->name('passes.distribution-links.store');
    Route::get('passes/{pass}/distribution-links', [PassDistributionController::class, 'index'])
        ->name('passes.distribution-links.index');
    Route::patch('passes/{pass}/distribution-links/{distributionLink}', [PassDistributionController::class, 'update'])
        ->name('passes.distribution-links.update');
});
