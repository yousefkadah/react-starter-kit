# Implementation Plan: Pass Distribution System

**Branch**: `001-pass-distribution` | **Date**: February 12, 2026 | **Spec**: [specs/001-pass-distribution/spec.md](spec.md)
**Input**: Feature specification from `/specs/001-pass-distribution/spec.md`

**Note**: This plan is filled in by the `/speckit.plan` command.

## Summary

Add pass distribution system enabling issuers to share unique, device-aware links and QR codes for direct wallet integration. Uses hybrid device detection (server User-Agent + client JavaScript), `/p/{slug}` URL format, and client-side QR generation.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 11  
**Primary Dependencies**: Laravel (Eloquent, Inertia), QRCode.js, Laravel HTTP Client  
**Storage**: PostgreSQL + Eloquent ORM (PassDistributionLink model)  
**Testing**: PHPUnit (feature tests in `tests/Feature`)  
**Target Platform**: Laravel web application (existing PassKit SaaS)  
**Project Type**: Web (monolithic Laravel app with Inertia React frontend)  
**Performance Goals**: Link open within 2s, 95% success rate, 99% QR accuracy  
**Constraints**: Public but unguessable links; slug uniqueness required; pass expiry must show message, not block link  
**Scale/Scope**: Integrated with existing Pass model; no new models beyond PassDistributionLink; supports all existing pass types

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

✅ **Laravel-First Architecture**: PassDistributionLink is an Eloquent model with policy-based authorization. No new base folders or external dependencies for pass generation.

✅ **Type-Safe Routing & Inertia Navigation**: All pass link routes use Wayfinder helpers. Distribution link UI views live under `resources/js/pages/passes`. No hardcoded URLs.

✅ **Test-Backed Changes**: Feature tests in `tests/Feature/PassDistribution/` cover link creation, device detection, disable/enable, and expiry messaging. Minimal test run: `php artisan test tests/Feature/PassDistribution/`.

✅ **Security & Tenant Isolation**: PassDistributionLink is scoped to authenticated user via `Pass` model relationship and tenant policy. All access authorized via `PassPolicy@viewDistributionLinks`.

✅ **Performance & Reliability**: QR generation is client-side (no server load). Link access queries use eager loading to avoid N+1. Slug generation is synchronous and lightweight.

## Project Structure

### Documentation (this feature)

```text
specs/001-pass-distribution/
├── spec.md              # Feature specification
├── plan.md              # This file
├── research.md          # Phase 0 output (to be generated)
├── data-model.md        # Phase 1 output (to be generated)
├── quickstart.md        # Phase 1 output (to be generated)
├── contracts/           # Phase 1 output - API/route contracts (to be generated)
│   ├── routes.md        # Pass distribution link routes
│   └── models.md        # PassDistributionLink data contract
└── CLARIFICATION_REPORT.md
```

### Source Code (repository root - Laravel monolithic structure)

```text
app/
├── Models/
│   ├── Pass.php                    # Existing; add hasMany(PassDistributionLink)
│   └── PassDistributionLink.php    # NEW: shareable link model
├── Http/
│   ├── Controllers/
│   │   └── PassDistributionController.php  # NEW: link CRUD & public link view
│   └── Requests/
│       └── StorePassDistributionLinkRequest.php  # NEW: validation
├── Policies/
│   ├── PassPolicy.php              # Update: add viewDistributionLinks gate
└── Services/
    └── PassDistributionLinkService.php  # NEW: slug generation, link control

resources/
├── js/
│   ├── pages/
│   │   ├── Passes/
│   │   │   └── DistributionPanel.jsx  # NEW: UI for generating/managing links
│   │   └── PassLink.jsx            # NEW: public pass link view (device detection)
│   ├── components/
│   │   └── QRCodeDisplay.jsx       # NEW: wrapper for QRCode.js
│   └── routes.ts                   # Update: add pass link routes
├── css/
└── views/
    └── [Inertia layout - no new blade templates]

routes/
├── web.php                         # Update: add PassDistributionController routes
└── [passes.php or similar - existing]

tests/
├── Feature/
│   └── PassDistribution/
│       ├── CreatePassDistributionLinkTest.php     # NEW
│       ├── ViewPassLinkTest.php                    # NEW
│       ├── DeviceDetectionTest.php                 # NEW
│       ├── DisableEnableLinkTest.php               # NEW
│       └── PassExpiryMessageTest.php               # NEW
└── Unit/
    └── Services/
        └── PassDistributionLinkServiceTest.php    # NEW

database/
├── migrations/
│   └── create_pass_distribution_links_table.php  # NEW
└── factories/
    └── PassDistributionLinkFactory.php           # NEW (for tests)
```

**Structure Decision**: Single Laravel application (Option 1). No new top-level folders. All logic contained within existing app/ and database/ structure, with new models and controllers following established naming conventions.

---

## Phase 0: Research & Clarification Resolutions

✅ **Resolved**: All critical unknowns clarified in `/speckit.clarify` session.

| Unknown | Resolution | Source |
|---------|-----------|--------|
| Device detection method | Hybrid: Server User-Agent parsing + client JavaScript enhancement | Clarification session |
| URL format | `/p/{slug}` with unguessable UUID-based slug | Clarification session |
| QR generation | Client-side JavaScript (QRCode.js library) | Clarification session |
| Pass expiry behavior | Link remains accessible; displays expiry message to user | Clarification session |
| Slug uniqueness | Generate via UUID4, indexed on `pass_distribution_links(slug)` | Data model design |
| Device fallback UX | Show both Apple and Google options when device type indeterminate | Spec acceptance scenarios |

→ See `research.md` for detailed findings and technical patterns research.

---

## Phase 1: Design Artifacts

The following design documents are generated below:

1. **data-model.md** — PassDistributionLink schema, relationships, indexes
2. **contracts/ routes.md** — HTTP routes and request/response contracts
3. **contracts/ models.md** — Data model contracts and validation rules
4. **quickstart.md** — Developer setup, example usage, deployment checklist

---

## Next Steps

- Phase 2 (Task Decomposition): Run `/speckit.tasks` to break into development sprints
- Implementation: Follow tasks in order, running minimal feature test suite after each task
- Deployment: Reference deployment checklist in `quickstart.md`
