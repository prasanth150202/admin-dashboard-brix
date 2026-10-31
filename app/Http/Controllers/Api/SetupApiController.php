<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SetupResource;
use App\Models\Setup;
use App\Models\Shop;
use App\Services\Support\SetupPlanner;
use App\Services\Support\TicketIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SetupApiController extends Controller
{
    private const RELATIONS = ['shop', 'app', 'assignee', 'tasks'];

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in([...array_keys(Setup::STATUSES), 'active'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $setups = Setup::query()
            ->with(self::RELATIONS)
            ->when($request->input('status') === 'active', fn ($q) => $q->active())
            ->when(array_key_exists((string) $request->input('status'), Setup::STATUSES), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('shop_domain'), fn ($q) => $q->whereHas('shop', fn ($q) => $q->where('myshopify_domain', TicketIntake::normalizeDomain($request->input('shop_domain')))))
            ->when($request->filled('app'), fn ($q) => $q->where('partner_app_id', app(TicketIntake::class)->resolveApp($request->input('app'))?->id ?? 0))
            ->when($request->filled('assignee_id'), fn ($q) => $q->where('assignee_id', $request->integer('assignee_id')))
            ->latest('id')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return SetupResource::collection($setups);
    }

    /** Starts a setup (with the app's checklist) for a synced store; returns the existing one if there is one. */
    public function store(Request $request, SetupPlanner $planner, TicketIntake $intake): JsonResponse
    {
        $data = $request->validate([
            'shop_domain' => ['required', 'string', 'max:190'],
            'app' => ['required', 'string', 'max:190'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $shop = Shop::where('myshopify_domain', TicketIntake::normalizeDomain($data['shop_domain']))->first();
        $app = $intake->resolveApp($data['app']);

        if (! $shop) {
            throw ValidationException::withMessages(['shop_domain' => 'No synced store with this domain.']);
        }
        if (! $app) {
            throw ValidationException::withMessages(['app' => 'No app with this name or ID.']);
        }

        $setup = $planner->start($shop, $app);
        $created = $setup->wasRecentlyCreated;

        if ($created) {
            $setup->update(collect($data)->only(['assignee_id', 'due_on', 'notes'])->all());
        }

        return SetupResource::make($setup->fresh(self::RELATIONS))
            ->additional(['created' => $created])
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    public function show(Setup $setup): SetupResource
    {
        return SetupResource::make($setup->load(self::RELATIONS));
    }

    /** Partial update: send only the fields to change. */
    public function update(Request $request, Setup $setup): SetupResource
    {
        $data = $request->validate([
            'status' => ['sometimes', 'required', Rule::in(array_keys(Setup::STATUSES))],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'due_on' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        if (isset($data['status'])) {
            $data['completed_at'] = $data['status'] === 'done' ? ($setup->completed_at ?? now()) : null;
        }

        $setup->fill($data)->save();

        return SetupResource::make($setup->fresh(self::RELATIONS));
    }

    public function destroy(Setup $setup): JsonResponse
    {
        $setup->delete();

        return response()->json(null, 204);
    }
}
