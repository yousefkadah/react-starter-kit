<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pass\StorePassRequest;
use App\Http\Requests\Pass\UpdatePassRequest;
use App\Jobs\MarkOnboardingStepJob;
use App\Models\Pass;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PassController extends Controller
{
    /**
     * Display a listing of the user's passes.
     */
    public function index(Request $request): Response
    {
        $query = $request->user()->passes()->with('template');

        // Apply filters
        if ($request->filled('platform')) {
            $query->whereJsonContains('platforms', $request->platform);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('pass_type', $request->type);
        }

        $passes = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('passes/index', [
            'passes' => $passes,
            'filters' => $request->only(['platform', 'status', 'type']),
        ]);
    }

    /**
     * Show the form for creating a new pass.
     */
    public function create(Request $request): Response
    {
        $templates = $request->user()->passTemplates()->latest()->get();

        return Inertia::render('passes/create', [
            'templates' => $templates,
        ]);
    }

    /**
     * Store a newly created pass.
     */
    public function store(StorePassRequest $request)
    {
        $validated = $request->validated();

        if (! empty($validated['pass_template_id'])) {
            $template = $request->user()->passTemplates()->findOrFail($validated['pass_template_id']);
            $rawInput = json_decode($request->getContent(), true);
            $rawPassData = is_array($rawInput['pass_data'] ?? null) ? $rawInput['pass_data'] : [];
            $validated['pass_data'] = $this->applyTemplateDefaults(
                (array) $template->design_data,
                (array) ($validated['pass_data'] ?? []),
                $rawPassData,
            );
        }

        $pass = $request->user()->passes()->create([
            ...$validated,
            'serial_number' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => 'active',
        ]);

        MarkOnboardingStepJob::dispatch($request->user()->id, 'first_pass');

        return to_route('passes.show', $pass)->with('success', 'Pass created successfully.');
    }

    /**
     * Display the specified pass.
     */
    public function show(Request $request, Pass $pass): Response
    {
        $this->authorize('view', $pass);

        $pass->load('template');

        return Inertia::render('passes/show', [
            'pass' => $pass,
        ]);
    }

    /**
     * Show the form for editing the specified pass.
     */
    public function edit(Request $request, Pass $pass): Response
    {
        $this->authorize('update', $pass);

        $templates = $request->user()->passTemplates()->latest()->get();

        return Inertia::render('passes/edit', [
            'pass' => $pass->load('template'),
            'templates' => $templates,
        ]);
    }

    /**
     * Update the specified pass.
     */
    public function update(UpdatePassRequest $request, Pass $pass)
    {
        $this->authorize('update', $pass);

        $pass->update($request->validated());

        return to_route('passes.show', $pass)->with('success', 'Pass updated successfully.');
    }

    /**
     * Remove the specified pass.
     */
    public function destroy(Request $request, Pass $pass)
    {
        $this->authorize('delete', $pass);

        $pass->delete();

        return to_route('passes.index')->with('success', 'Pass deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function applyTemplateDefaults(array $defaults, array $overrides, array $rawOverrides = []): array
    {
        $merged = $defaults;

        foreach ($overrides as $key => $value) {
            if ($value === null && array_key_exists($key, $rawOverrides) && $rawOverrides[$key] === '') {
                $merged[$key] = '';

                continue;
            }

            if ($value === null) {
                continue;
            }

            if (is_array($value) && ! array_is_list($value)) {
                $merged[$key] = $this->applyTemplateDefaults(
                    (array) ($defaults[$key] ?? []),
                    $value,
                    is_array($rawOverrides[$key] ?? null) ? $rawOverrides[$key] : [],
                );

                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }
}
