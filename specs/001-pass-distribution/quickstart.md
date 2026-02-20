# Quickstart: Pass Distribution System

**Date**: February 12, 2026  
**Feature**: Pass Distribution System  
**Output of**: `/speckit.plan` Phase 1

---

## Overview

This guide helps developers get up and running with the Pass Distribution System feature, from local setup through deployment.

---

## Prerequisites

- Laravel 11 installed and running
- PHP 8.3+
- Node.js 18+
- Existing PassKit database with `passes` table
- Composer and npm dependencies already installed

---

## Part 1: Local Development Setup

### Step 1: Create Migration

```bash
php artisan make:migration create_pass_distribution_links_table
```

Edit `database/migrations/YYYY_MM_DD_HHMMSS_create_pass_distribution_links_table.php`:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pass_distribution_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 36)->unique();
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('accessed_count')->default(0);
            $table->timestamps();

            $table->index('slug');
            $table->index('pass_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_distribution_links');
    }
};
```

Run migration:

```bash
php artisan migrate
```

### Step 2: Create Eloquent Model

```bash
php artisan make:model PassDistributionLink
```

Edit `app/Models/PassDistributionLink.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PassDistributionLink extends Model
{
    use HasFactory;

    protected $fillable = ['pass_id', 'slug', 'status', 'last_accessed_at', 'accessed_count'];
    protected $casts = ['last_accessed_at' => 'datetime'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($link) => $link->slug ??= Str::uuid()->toString());
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    public function isActive(): bool { return $this->status === 'active'; }
    public function isDisabled(): bool { return $this->status === 'disabled'; }

    public function recordAccess(): void
    {
        $this->update([
            'last_accessed_at' => now(),
            'accessed_count' => $this->accessed_count + 1,
        ]);
    }

    public function url(): string
    {
        return route('passes.show-by-link', ['slug' => $this->slug]);
    }
}
```

Update `app/Models/Pass.php` to add relationship:

```php
// In Pass model, add:
public function distributionLinks()
{
    return $this->hasMany(PassDistributionLink::class);
}
```

### Step 3: Create Factory (for Testing)

```bash
php artisan make:factory PassDistributionLinkFactory --model=PassDistributionLink
```

Edit `database/factories/PassDistributionLinkFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Pass;
use App\Models\PassDistributionLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PassDistributionLinkFactory extends Factory
{
    protected $model = PassDistributionLink::class;

    public function definition(): array
    {
        return [
            'pass_id' => Pass::factory(),
            'slug' => Str::uuid(),
            'status' => 'active',
            'last_accessed_at' => null,
            'accessed_count' => 0,
        ];
    }

    public function disabled() { return $this->state(['status' => 'disabled']); }
    public function accessed($count = 1) { return $this->state(['last_accessed_at' => now(), 'accessed_count' => $count]); }
}
```

### Step 4: Create Form Requests

```bash
php artisan make:request StorePassDistributionLinkRequest
php artisan make:request UpdatePassDistributionLinkRequest
```

Edit `app/Http/Requests/StorePassDistributionLinkRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePassDistributionLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createDistributionLink', $this->route('pass'));
    }

    public function rules(): array { return []; }
}
```

Edit `app/Http/Requests/UpdatePassDistributionLinkRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePassDistributionLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateDistributionLink', $this->route('link'));
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['active', 'disabled'])]];
    }
}
```

### Step 5: Create Controller

```bash
php artisan make:controller PassDistributionController
```

Edit `app/Http/Controllers/PassDistributionController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePassDistributionLinkRequest;
use App\Http\Requests\UpdatePassDistributionLinkRequest;
use App\Http\Resources\PassDistributionLinkResource;
use App\Models\Pass;
use App\Models\PassDistributionLink;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PassDistributionController extends Controller
{
    // GET /p/{slug} - Public pass link view
    public function show(string $slug)
    {
        $link = PassDistributionLink::where('slug', $slug)->firstOrFail();

        if ($link->isDisabled()) {
            return response()->json(['message' => 'This link has been disabled.'], 403);
        }

        $pass = $link->pass;
        if ($pass->isVoided()) {
            return response()->json(['message' => 'This pass is no longer valid.'], 410);
        }

        $link->recordAccess();

        return Inertia::render('PassLink', [
            'pass' => $pass->load('passTemplate'),
            'device' => $this->detectDevice(request()),
            'link_status' => $pass->isExpired() ? 'expired' : 'active',
            'add_to_wallet_url' => [
                'apple' => route('passes.download', ['pass' => $pass->id, 'format' => 'pkpass']),
                'google' => route('passes.google-save', ['pass' => $pass->id]),
            ],
            'qr_code_data' => ['text' => route('passes.show-by-link', ['slug' => $slug])],
        ]);
    }

    // GET /dashboard/passes/{pass}/distribution-links
    public function index(Pass $pass)
    {
        $this->authorize('viewDistributionLinks', $pass);

        return Inertia::render('Passes/DistributionPanel', [
            'pass' => $pass,
            'links' => PassDistributionLinkResource::collection(
                $pass->distributionLinks()->latest()->paginate(15)
            ),
        ]);
    }

    // POST /dashboard/passes/{pass}/distribution-links
    public function store(Pass $pass, StorePassDistributionLinkRequest $request)
    {
        $link = PassDistributionLink::create(['pass_id' => $pass->id]);

        return response()->json(['data' => new PassDistributionLinkResource($link)], 201);
    }

    // PATCH /dashboard/passes/{pass}/distribution-links/{link}
    public function update(Pass $pass, PassDistributionLink $link, UpdatePassDistributionLinkRequest $request)
    {
        $link->update($request->validated());

        return response()->json(['data' => new PassDistributionLinkResource($link)]);
    }

    private function detectDevice(Request $request): string
    {
        $ua = $request->header('User-Agent', '');
        if (preg_match('/iPhone|iPad|iPod/', $ua)) return 'ios';
        if (preg_match('/Android/', $ua)) return 'android';
        return 'unknown';
    }
}
```

### Step 6: Create Resource

```bash
php artisan make:resource PassDistributionLinkResource
```

Edit `app/Http/Resources/PassDistributionLinkResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PassDistributionLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pass_id' => $this->pass_id,
            'slug' => $this->slug,
            'status' => $this->status,
            'url' => $this->url(),
            'last_accessed_at' => $this->last_accessed_at?->toIso8601String(),
            'accessed_count' => $this->accessed_count,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
```

### Step 7: Register Routes

Add to `routes/web.php` (or appropriate route file):

```php
Route::middleware(['auth:sanctum', 'verified'])->prefix('dashboard/passes')->group(function () {
    Route::get('/{pass}/distribution-links', [PassDistributionController::class, 'index'])
        ->name('passes.distribution-links.index');
    Route::post('/{pass}/distribution-links', [PassDistributionController::class, 'store'])
        ->name('passes.distribution-links.store');
    Route::patch('/{pass}/distribution-links/{link}', [PassDistributionController::class, 'update'])
        ->name('passes.distribution-links.update');
});

Route::get('/p/{slug}', [PassDistributionController::class, 'show'])
    ->where('slug', '[a-f0-9\-]{36}')
    ->name('passes.show-by-link');
```

### Step 8: Update Pass Policy

Edit `app/Policies/PassPolicy.php`:

```php
public function createDistributionLink(User $user, Pass $pass): bool
{
    return $user->id === $pass->user_id;
}

public function updateDistributionLink(User $user, PassDistributionLink $link): bool
{
    return $user->id === $link->pass->user_id;
}
```

### Step 9: Create React Components

Create `resources/js/pages/PassLink.tsx`:

```tsx
import { PageProps } from '@inertiajs/core';
import QRCodeDisplay from '@/components/QRCodeDisplay';

interface Props extends PageProps {
    pass: any;
    device: 'ios' | 'android' | 'desktop' | 'unknown';
    link_status: 'active' | 'expired';
    add_to_wallet_url: { apple: string; google: string };
    qr_code_data: { text: string };
}

export default function PassLink({ pass, device, link_status, add_to_wallet_url, qr_code_data }: Props) {
    return (
        <div className="p-6">
            <h1>{pass.name}</h1>

            {link_status === 'expired' && (
                <div className="alert alert-warning">
                    This pass has expired and is no longer valid for enrollment.
                </div>
            )}

            {(device === 'ios' || device === 'unknown') && (
                <a href={add_to_wallet_url.apple} className="btn btn-primary">
                    Add to Apple Wallet
                </a>
            )}

            {(device === 'android' || device === 'unknown') && (
                <a href={add_to_wallet_url.google} className="btn btn-primary">
                    Add to Google Pay
                </a>
            )}

            <QRCodeDisplay url={qr_code_data.text} />
        </div>
    );
}
```

Create `resources/js/components/QRCodeDisplay.tsx`:

```tsx
import { useEffect, useRef } from 'react';
import QRCode from 'qrcode';

interface Props {
    url: string;
    width?: number;
    height?: number;
}

export default function QRCodeDisplay({ url, width = 200, height = 200 }: Props) {
    const container = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (container.current) {
            QRCode.toCanvas(container.current, url, {
                width,
                height,
                margin: 2,
                color: { dark: '#000', light: '#fff' },
            });
        }
    }, [url, width, height]);

    return <div ref={container} />;
}
```

Install QRCode library:

```bash
npm install qrcode
```

### Step 10: Run Tests

Create `tests/Feature/PassDistribution/CreatePassDistributionLinkTest.php`:

```php
<?php

namespace Tests\Feature\PassDistribution;

use App\Models\Pass;
use App\Models\User;
use Tests\TestCase;

class CreatePassDistributionLinkTest extends TestCase
{
    public function test_user_can_create_distribution_link()
    {
        $user = User::factory()->create();
        $pass = Pass::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(
            route('passes.distribution-links.store', ['pass' => $pass->id])
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('pass_distribution_links', [
            'pass_id' => $pass->id,
            'status' => 'active',
        ]);
    }
}
```

Run tests:

```bash
php artisan test tests/Feature/PassDistribution/ --filter="PassDistribution"
```

---

## Part 2: Testing

### Manual Testing

1. Create a pass via dashboard
2. Generate a distribution link
3. Copy the link and open on iOS device/emulator
4. Verify Apple Wallet button appears
5. Disable the link
6. Try to access link (verify 403 protection)
7. Re-enable link
8. Verify link works again

### Automated Testing

```bash
# Run all distribution feature tests
php artisan test tests/Feature/PassDistribution/

# Run with coverage
php artisan test tests/Feature/PassDistribution/ --coverage

# Run specific test
php artisan test tests/Feature/PassDistribution/CreatePassDistributionLinkTest
```

---

## Part 3: Deployment

### Pre-Deployment Checklist

- [ ] All tests passing
- [ ] Database migration tested locally
- [ ] Environment variables configured
- [ ] Policy gates authorized correctly
- [ ] Wayfinder routes registered
- [ ] Frontend components built (`npm run build`)

### Deployment Steps

1. **Pull code to production**

   ```bash
   git pull origin 001-pass-distribution
   ```

2. **Install dependencies**

   ```bash
   composer install --no-dev
   npm install
   npm run build
   ```

3. **Run database migration**

   ```bash
   php artisan migrate --force
   ```

4. **Clear caches**

   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

5. **Restart queue workers (if applicable)**

   ```bash
   php artisan queue:restart
   ```

### Rollback Plan

If issues occur:

```bash
# Rollback database migration
php artisan migrate:rollback --force

# Re-clear caches
php artisan cache:clear
php artisan route:clear
```

---

## Example Usage

### For Issuers (Authenticated Users)

1. Navigate to Pass details page
2. Click "Share Pass" → "Generate Link"
3. Copy link or scan QR code
4. Share via email, SMS, social media, or QR code
5. Monitor engagement in "Distribution Links" panel

### For End Users (Unauthenticated)

1. Receive pass link (via email, text, QR code)
2. Click link on mobile device
3. Device type detected automatically
4. See appropriate "Add to Wallet" button (Apple or Google)
5. Click button to add pass to wallet

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Migration fails | Check database permissions and existing tables |
| Routes not registered | Verify routes/web.php includes new routes; run `php artisan route:list` |
| QR code not rendering | Ensure qrcode npm package installed; check browser console for errors |
| Device detection wrong | Review User-Agent string in browser dev tools; may need jenssegers/agent library later |
| Pass not accessible | Check Pass policy and user authorization |

---

## Performance Optimization (Future)

- Add HTTP caching headers to public pass link (1-hour TTL)
- Cache device detection for repeat visitors
- Add CDN caching for QR code images
- Monitor slug lookup performance with slow-query logging

---

## Documentation

For full technical details, see:
- [data-model.md](../data-model.md) — Entity schema
- [contracts/routes.md](../contracts/routes.md) — API routes
- [contracts/models.md](../contracts/models.md) — TypeScript interfaces
- [research.md](../research.md) — Technical decisions

---

## Next Steps

After deployment:
1. Monitor error logs for issues
2. Track link access metrics in analytics
3. Gather user feedback on UX
4. Plan Phase 2 features (email distribution, SMS, etc.)

---

Deployment complete! 🎉
