<?php

namespace App\Http\Controllers;

use App\Models\FeatureRequest;
use App\Models\Setup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Who is working on what: every open setup and feature request, grouped by person,
 * with an unassigned pile at the top to hand out.
 */
class PlanController extends Controller
{
    public function index(Request $request)
    {
        $team = $request->input('team');
        $team = array_key_exists($team, User::TEAMS) ? $team : null;

        $setups = Setup::active()->with(['shop', 'app'])
            ->withCount(['tasks', 'tasks as done_count' => fn ($q) => $q->whereNotNull('done_at')])
            ->get();
        $features = FeatureRequest::active()->with('app')->get();

        $items = $this->items($setups, $features);
        $users = User::orderBy('name')->get();
        $byPerson = $items->groupBy(fn ($item) => $item->assignee_id ?? 0);

        $people = $users
            ->when($team, fn ($users) => $users->where('team', $team))
            ->map(fn (User $user) => (object) [
                'user' => $user,
                'items' => $byPerson->get($user->id, collect()),
            ])
            // Busiest first, so overloaded people stand out; people with nothing go last.
            ->sortByDesc(fn ($p) => [$p->items->where('overdue', true)->count(), $p->items->count()])
            ->values();

        return view('plan.index', [
            'people' => $people,
            'unassigned' => $byPerson->get(0, collect()),
            'users' => $users,
            'team' => $team,
        ]);
    }

    /** Hands a setup or feature request to someone, with an optional due date. */
    public function assign(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['setup', 'feature'])],
            'id' => ['required', 'integer'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_on' => ['nullable', 'date'],
        ]);
        $assignee = isset($data['assignee_id']) ? (int) $data['assignee_id'] : null;

        if ($data['type'] === 'setup') {
            $setup = Setup::findOrFail($data['id']);
            $setup->assignTo($assignee, $request->user())->fill(['due_on' => $data['due_on'] ?? null])->save();
            $label = "{$setup->app->name} setup for {$setup->shop->displayName()}";
        } else {
            Gate::authorize('assign-features');
            $feature = FeatureRequest::findOrFail($data['id']);
            $reassigned = $assignee !== $feature->assignee_id;
            $feature->fill(['assignee_id' => $assignee, 'due_on' => $data['due_on'] ?? null])->save();
            if ($reassigned) {
                $feature->logAssignment($request->user());
            }
            $label = $feature->number();
        }

        $who = $assignee ? User::find($assignee)->name : 'nobody';

        return back()->with('status', "Gave the {$label} to {$who}.");
    }

    /** Setups and feature requests as one list, soonest due first, undated last. */
    private function items(Collection $setups, Collection $features): Collection
    {
        $setupItems = $setups->map(fn (Setup $s) => (object) [
            'type' => 'setup',
            'model' => $s,
            'id' => $s->id,
            'title' => "{$s->app->name} · {$s->shop->displayName()}",
            'url' => route('setups.show', $s),
            'assignee_id' => $s->assignee_id,
            'due_on' => $s->due_on,
            'overdue' => $s->isOverdue(),
        ]);

        $featureItems = $features->map(fn (FeatureRequest $f) => (object) [
            'type' => 'feature',
            'model' => $f,
            'id' => $f->id,
            'title' => "{$f->number()} {$f->title}",
            'url' => route('features.show', $f),
            'assignee_id' => $f->assignee_id,
            'due_on' => $f->due_on,
            'overdue' => $f->isOverdue(),
        ]);

        return $setupItems->concat($featureItems)
            ->sortBy(fn ($i) => [$i->due_on === null, $i->due_on?->timestamp ?? 0])
            ->values();
    }
}
