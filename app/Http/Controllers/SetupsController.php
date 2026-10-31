<?php

namespace App\Http\Controllers;

use App\Models\PartnerApp;
use App\Models\Setup;
use App\Models\SetupTask;
use App\Models\Shop;
use App\Models\User;
use App\Services\Support\SetupPlanner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SetupsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'status', 'app', 'assignee']);
        $status = $filters['status'] ?? 'active';
        $assignee = $filters['assignee'] ?? '';

        $setups = Setup::query()
            ->with(['shop.installs', 'app', 'assignee'])
            ->withCount(['tasks', 'tasks as done_count' => fn ($q) => $q->whereNotNull('done_at')])
            ->when($status === 'active', fn ($q) => $q->active())
            ->when(array_key_exists($status, Setup::STATUSES), fn ($q) => $q->where('status', $status))
            ->when($request->integer('app'), fn ($q, $id) => $q->where('partner_app_id', $id))
            ->when($assignee === 'me', fn ($q) => $q->where('assignee_id', $request->user()->id))
            ->when($assignee === 'none', fn ($q) => $q->whereNull('assignee_id'))
            ->when(ctype_digit($assignee), fn ($q) => $q->where('assignee_id', (int) $assignee))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $request->input('q')).'%';
                $q->whereHas('shop', fn ($q) => $q->where('myshopify_domain', 'like', $term)->orWhere('name', 'like', $term));
            })
            // Overdue and soonest-due first; no due date last.
            ->orderByRaw('due_on IS NULL')
            ->orderBy('due_on')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('setups.index', [
            'setups' => $setups,
            'filters' => $filters + ['status' => $status],
            'apps' => PartnerApp::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    /** Starts a setup by hand from a store page (e.g. an older install, or a test store). */
    public function store(Request $request, SetupPlanner $planner)
    {
        $data = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'partner_app_id' => ['required', 'integer', 'exists:partner_apps,id'],
        ]);

        $setup = $planner->start(Shop::findOrFail($data['shop_id']), PartnerApp::findOrFail($data['partner_app_id']));
        $setup->update(['assignee_id' => $setup->assignee_id ?? $request->user()->id]);

        return redirect()->route('setups.show', $setup)->with('status', 'Setup started.');
    }

    public function show(Setup $setup)
    {
        $setup->load(['shop.installs.app', 'app', 'assignee', 'tasks.doneBy']);

        return view('setups.show', [
            'setup' => $setup,
            'install' => $setup->shop->installs->firstWhere('partner_app_id', $setup->partner_app_id),
            'users' => User::orderBy('name')->get(),
            'tickets' => $setup->shop->tickets()->with('app')->latest('last_activity_at')->limit(10)->get(),
        ]);
    }

    public function update(Request $request, Setup $setup)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Setup::STATUSES))],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $data['completed_at'] = $data['status'] === 'done' ? ($setup->completed_at ?? now()) : null;
        $setup->fill($data)->save();

        return back()->with('status', 'Setup updated.');
    }

    public function destroy(Setup $setup)
    {
        $setup->delete();

        return redirect()->route('setups.index')->with('status', "Removed the {$setup->app->name} setup for {$setup->shop->displayName()}.");
    }

    public function toggleTask(Request $request, Setup $setup, SetupTask $task)
    {
        abort_unless($task->setup_id === $setup->id, 404);

        $task->forceFill($task->done_at
            ? ['done_at' => null, 'done_by' => null]
            : ['done_at' => now(), 'done_by' => $request->user()->id])->save();

        // First tick moves a fresh setup along; the last tick doesn't close it — a person decides that.
        if ($setup->status === 'todo' && $task->done_at) {
            $setup->update(['status' => 'in_progress']);
        }

        return back();
    }

    public function addTask(Request $request, Setup $setup)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:250']]);

        $setup->tasks()->create(['title' => $data['title'], 'position' => (int) $setup->tasks()->max('position') + 1]);

        return back();
    }

    public function destroyTask(Setup $setup, SetupTask $task)
    {
        abort_unless($task->setup_id === $setup->id, 404);
        $task->delete();

        return back();
    }
}
