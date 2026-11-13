<?php

namespace App\Http\Controllers;

use App\Models\FeatureRequest;
use App\Models\PartnerApp;
use App\Models\Shop;
use App\Models\User;
use App\Services\Support\TicketIntake;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FeatureRequestsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'status', 'app', 'assignee']);
        $status = $filters['status'] ?? 'active';
        $assignee = $filters['assignee'] ?? '';

        $features = FeatureRequest::query()
            ->with(['app', 'shop', 'requester', 'assignee'])
            ->withCount('updates')
            ->when($status === 'active', fn ($q) => $q->active())
            ->when(array_key_exists($status, FeatureRequest::STATUSES), fn ($q) => $q->where('status', $status))
            ->when($request->integer('app'), fn ($q, $id) => $q->where('partner_app_id', $id))
            ->when($assignee === 'me', fn ($q) => $q->where('assignee_id', $request->user()->id))
            ->when($assignee === 'mine', fn ($q) => $q->where('requested_by', $request->user()->id))
            ->when($assignee === 'none', fn ($q) => $q->whereNull('assignee_id'))
            ->when(ctype_digit($assignee), fn ($q) => $q->where('assignee_id', (int) $assignee))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $request->input('q')).'%';
                $q->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('description', 'like', $term));
            })
            // Most urgent first, then the oldest waiting.
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('features.index', [
            'features' => $features,
            'filters' => $filters + ['status' => $status],
            'counts' => FeatureRequest::query()->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status'),
            'apps' => PartnerApp::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('features.create', [
            'apps' => PartnerApp::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'domains' => Shop::orderBy('myshopify_domain')->limit(1000)->pluck('myshopify_domain'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:250'],
            'description' => ['nullable', 'string', 'max:20000'],
            'priority' => ['required', Rule::in(array_keys(FeatureRequest::PRIORITIES))],
            'partner_app_id' => ['nullable', 'integer', 'exists:partner_apps,id'],
            'shop_domain' => ['nullable', 'string', 'max:190'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_on' => ['nullable', 'date'],
        ]);

        $feature = FeatureRequest::create([
            ...collect($data)->except('shop_domain')->all(),
            'shop_id' => $this->resolveShop($data['shop_domain'] ?? null)?->id,
            'requested_by' => $request->user()->id,
        ]);

        if ($feature->assignee_id) {
            $feature->logAssignment($request->user());
        }

        return redirect()->route('features.show', $feature)->with('status', "Requested {$feature->number()}. The development team will pick it up.");
    }

    /** The brand it's for, by domain — created if it hasn't installed anything yet. */
    private function resolveShop(?string $domain): ?Shop
    {
        if (blank($domain)) {
            return null;
        }

        $domain = TicketIntake::normalizeDomain($domain);

        if ($domain === null) {
            throw ValidationException::withMessages(['shop_domain' => 'Enter a store domain like cool-shop.myshopify.com.']);
        }

        return Shop::firstOrCreate(['myshopify_domain' => $domain]);
    }

    public function show(FeatureRequest $feature)
    {
        $feature->load(['app', 'shop', 'requester', 'assignee', 'updates.user']);

        return view('features.show', [
            'feature' => $feature,
            'users' => User::orderBy('name')->get(),
        ]);
    }

    /** Development moves the request along; each status change lands in the history. */
    public function update(Request $request, FeatureRequest $feature)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(FeatureRequest::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(FeatureRequest::PRIORITIES))],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:20000'],
        ]);

        $from = $feature->status;
        $assignee = isset($data['assignee_id']) ? (int) $data['assignee_id'] : null;
        $reassigned = $assignee !== $feature->assignee_id;
        $closed = in_array($data['status'], FeatureRequest::CLOSED, true);

        DB::transaction(function () use ($feature, $data, $from, $assignee, $reassigned, $closed, $request) {
            $feature->fill([
                'status' => $data['status'],
                'priority' => $data['priority'],
                'assignee_id' => $assignee,
                'due_on' => $data['due_on'] ?? null,
                'completed_at' => $closed ? ($feature->completed_at ?? now()) : null,
            ])->save();

            if ($from !== $data['status'] || filled($data['note'] ?? null)) {
                $feature->updates()->create([
                    'user_id' => $request->user()->id,
                    'from_status' => $from !== $data['status'] ? $from : null,
                    'to_status' => $from !== $data['status'] ? $data['status'] : null,
                    'body' => $data['note'] ?? null,
                ]);
            }

            if ($reassigned) {
                $feature->logAssignment($request->user());
            }
        });

        return back()->with('status', $from !== $data['status']
            ? "{$feature->number()} is now ".FeatureRequest::STATUSES[$data['status']].'.'
            : 'Saved.');
    }

    /** Anyone can add context or ask a question. */
    public function comment(Request $request, FeatureRequest $feature)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:20000']]);

        $feature->updates()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        $feature->touch();

        return back();
    }

    public function destroy(FeatureRequest $feature)
    {
        $feature->delete();

        return redirect()->route('features.index')->with('status', "Deleted {$feature->number()}.");
    }
}
