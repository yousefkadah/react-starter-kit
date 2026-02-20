<?php

namespace App\Http\Controllers\Scanner;

use App\Http\Controllers\Controller;
use App\Models\ScannerLink;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScannerController extends Controller
{
    /**
     * Display the web-based scanner interface.
     */
    public function show(Request $request, string $token): Response
    {
        $scannerLink = ScannerLink::where('token', $token)
            ->where('is_active', true)
            ->first();

        if (! $scannerLink) {
            abort(404, 'Invalid or inactive scanner link.');
        }

        $scannerLink->markAsUsed();

        return Inertia::render('scanner/index', [
            'scannerToken' => $scannerLink->token,
            'scannerName' => $scannerLink->name,
        ]);
    }
}
