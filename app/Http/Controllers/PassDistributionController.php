<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePassDistributionLinkRequest;
use App\Http\Requests\UpdatePassDistributionLinkRequest;
use App\Http\Resources\PassDistributionLinkResource;
use App\Models\Pass;
use App\Models\PassDistributionLink;
use App\Services\PassDistributionLinkService;
use Illuminate\Http\Response;
use Inertia\Inertia;

class PassDistributionController extends Controller
{
    public function __construct(private PassDistributionLinkService $service)
    {
    }

    /**
     * Display the specified pass distribution link (public endpoint).
     */
    public function show(string $slug)
    {
        $link = PassDistributionLink::where('slug', $slug)->firstOrFail();

        // Check if link is disabled
        if ($link->isDisabled()) {
            abort(403, 'Link has been disabled');
        }

        // Check if pass is voided
        if ($link->pass->isVoided()) {
            abort(410, 'This pass is no longer available');
        }

        // Record access
        $link->recordAccess();

        // Detect device type
        $device = $this->detectDevice();

        // Determine link status
        $linkStatus = $link->pass->isExpired() ? 'expired' : 'active';

        return Inertia::render('PassLink', [
            'pass' => $link->pass,
            'device' => $device,
            'link_status' => $linkStatus,
            'publicUrl' => $link->url(),
            'add_to_wallet_url' => $this->getAddToWalletUrl($link->pass, $device),
            'qr_code_data' => [
                'text' => $link->url(),
                'width' => 200,
                'height' => 200,
            ],
        ]);
    }

    /**
     * Display a listing of distribution links for a pass.
     */
    public function index(Pass $pass)
    {
        $this->authorize('viewDistributionLinks', $pass);

        $links = $pass->distributionLinks()
            ->latest('created_at')
            ->paginate(15);

        return inertia('passes/distribution-links', [
            'pass' => $pass,
            'links' => PassDistributionLinkResource::collection($links),
        ]);
    }

    /**
     * Store a newly created distribution link.
     */
    public function store(Pass $pass, StorePassDistributionLinkRequest $request)
    {
        $link = $this->service->create($pass);

        return response()->json(
            new PassDistributionLinkResource($link),
            Response::HTTP_CREATED
        );
    }

    /**
     * Update the specified distribution link.
     */
    public function update(Pass $pass, PassDistributionLink $distributionLink, UpdatePassDistributionLinkRequest $request)
    {
        $this->authorize('updateDistributionLink', [$pass, $distributionLink]);

        if ($request->input('status') === 'disabled') {
            $this->service->disable($distributionLink);
        } else {
            $this->service->enable($distributionLink);
        }

        return response()->json(
            new PassDistributionLinkResource($distributionLink->fresh())
        );
    }

    /**
     * Detect the device type from User-Agent.
     */
    private function detectDevice(): string
    {
        $userAgent = request()->userAgent() ?? '';

        // iOS detection (iPhone, iPad, iPod)
        if (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
            return 'ios';
        }

        // Android detection
        if (preg_match('/Android/i', $userAgent)) {
            return 'android';
        }

        return 'unknown';
    }

    /**
     * Get the appropriate add-to-wallet URL based on device type.
     */
    private function getAddToWalletUrl(Pass $pass, string $device): ?string
    {
        return match ($device) {
            'ios' => $pass->pkpass_path ? route('passes.download-apple', $pass) : null,
            'android' => $pass->google_save_url ?? null,
            default => null,
        };
    }
}


