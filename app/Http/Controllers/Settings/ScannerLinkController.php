<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ScannerLink;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ScannerLinkController extends Controller
{
    /**
     * Display the scanner links management page.
     */
    public function index(Request $request): Response
    {
        $scannerLinks = $request->user()
            ->scannerLinks()
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('settings/scanner-links', [
            'scannerLinks' => $scannerLinks->map(fn (ScannerLink $link) => [
                'id' => $link->id,
                'name' => $link->name,
                'token' => $link->token,
                'is_active' => $link->is_active,
                'last_used_at' => $link->last_used_at?->toISOString(),
                'created_at' => $link->created_at->toISOString(),
                'scan_count' => $link->scanEvents()->count(),
            ]),
        ]);
    }

    /**
     * Create a new scanner link.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->scannerLinks()->create([
            'name' => $validated['name'],
            'token' => Str::random(40),
        ]);

        return back()->with('success', 'Scanner link created successfully.');
    }

    /**
     * Toggle a scanner link's active status.
     */
    public function update(Request $request, ScannerLink $scannerLink)
    {
        $this->authorize('update', $scannerLink);

        $scannerLink->update([
            'is_active' => ! $scannerLink->is_active,
        ]);

        $status = $scannerLink->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Scanner link {$status} successfully.");
    }

    /**
     * Delete a scanner link.
     */
    public function destroy(Request $request, ScannerLink $scannerLink)
    {
        $this->authorize('delete', $scannerLink);

        $scannerLink->delete();

        return back()->with('success', 'Scanner link deleted successfully.');
    }
}
