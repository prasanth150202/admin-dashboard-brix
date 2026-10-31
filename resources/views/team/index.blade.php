<x-layout title="Team">
    <h1 class="text-xl font-semibold">Team</h1>
    <p class="mt-1 text-sm text-ink-2"><b>Admins</b> see everything. <b>Team</b> members see tickets, setups and stores, but no earnings, payouts or settings.</p>

    <div class="card mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
            <tr><th>Name</th><th>Role</th><th class="r">Open tickets</th><th class="r">Open setups</th><th>Change role / reset password</th><th></th></tr>
            </thead>
            <tbody>
            @foreach ($users as $user)
                <tr>
                    <td class="min-w-48">
                        <div class="font-medium">{{ $user->name }}@if ($user->is(auth()->user()))<span class="text-xs font-normal text-muted"> · you</span>@endif</div>
                        <div class="text-xs text-muted">{{ $user->email }}</div>
                    </td>
                    <td>{{ \App\Models\User::ROLES[$user->role] ?? $user->role }}</td>
                    <td class="r"><a href="{{ route('tickets.index', ['assignee' => $user->id]) }}" class="hover:underline">{{ $user->open_tickets }}</a></td>
                    <td class="r"><a href="{{ route('setups.index', ['assignee' => $user->id]) }}" class="hover:underline">{{ $user->open_setups }}</a></td>
                    <td>
                        <form method="POST" action="{{ route('team.update', $user) }}" class="flex flex-wrap items-center gap-2">
                            @csrf @method('PATCH')
                            <select name="role" class="field" aria-label="Role for {{ $user->name }}">
                                @foreach (\App\Models\User::ROLES as $value => $label)
                                    <option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input name="password" type="password" minlength="10" placeholder="New password (optional)" autocomplete="new-password" class="field w-52" aria-label="New password for {{ $user->name }}">
                            <button class="btn">Save</button>
                        </form>
                    </td>
                    <td class="text-right">
                        @unless ($user->is(auth()->user()))
                            <form method="POST" action="{{ route('team.destroy', $user) }}" onsubmit="return confirm(@js('Remove '.$user->name.'? Their tickets and setups become unassigned.'))">
                                @csrf @method('DELETE')
                                <button class="text-sm text-ink-2 hover:text-ink">Remove</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('team.store') }}" class="card mt-4 max-w-3xl p-4">
        @csrf
        <h2 class="font-semibold">Add a teammate</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div>
                <label for="name" class="block text-sm text-ink-2">Name</label>
                <input id="name" name="name" value="{{ old('name') }}" required class="field mt-1 w-full">
            </div>
            <div>
                <label for="email" class="block text-sm text-ink-2">Email (their login)</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="field mt-1 w-full">
            </div>
            <div>
                <label for="role" class="block text-sm text-ink-2">Role</label>
                <select id="role" name="role" class="field mt-1 w-full">
                    @foreach (\App\Models\User::ROLES as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', 'team') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="password" class="block text-sm text-ink-2">Password (min 10 characters)</label>
                <input id="password" name="password" type="password" required minlength="10" autocomplete="new-password" class="field mt-1 w-full">
            </div>
        </div>
        @if ($errors->any())<p class="mt-3 text-sm" style="color: var(--critical)">{{ $errors->first() }}</p>@endif
        <button class="btn btn-primary mt-4">Add teammate</button>
    </form>
</x-layout>
