<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index()
    {
        return view('team.index', [
            'users' => User::withCount([
                'assignedTickets as open_tickets' => fn ($q) => $q->active(),
                'assignedSetups as open_setups' => fn ($q) => $q->active(),
            ])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['required', 'string', 'min:10'],
        ]);

        $data['email'] = strtolower($data['email']);
        User::create($data);

        return back()->with('status', "Added {$data['name']}. Share the password with them directly.");
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['nullable', 'string', 'min:10'],
        ]);

        if ($data['role'] !== 'admin' && $this->isLastAdmin($user)) {
            return back()->with('error', 'Keep at least one admin.');
        }

        $user->role = $data['role'];
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }
        $user->save();

        return back()->with('status', "Updated {$user->name}.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You can’t remove yourself.');
        }

        if ($this->isLastAdmin($user)) {
            return back()->with('error', 'Keep at least one admin.');
        }

        $user->delete();

        return back()->with('status', "Removed {$user->name}. Their tickets and setups are now unassigned.");
    }

    private function isLastAdmin(User $user): bool
    {
        return $user->isAdmin() && User::where('role', 'admin')->count() === 1;
    }
}
