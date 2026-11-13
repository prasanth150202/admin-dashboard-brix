@props(['users', 'selected' => null, 'first' => null, 'name' => 'assignee_id', 'empty' => 'Unassigned'])
@php
    // Everyone is listed, grouped by team; the team that usually does this work comes first.
    $groups = $users->groupBy(fn ($u) => $u->team ?? '')
        ->sortBy(fn ($_, $team) => $team === $first ? 0 : ($team === '' ? 2 : 1));
@endphp
<select name="{{ $name }}" {{ $attributes->merge(['class' => 'field']) }}>
    <option value="">{{ $empty }}</option>
    @foreach ($groups as $team => $members)
        <optgroup label="{{ $team === '' ? 'No team' : (\App\Models\User::TEAMS[$team] ?? $team).' team' }}">
            @foreach ($members->sortBy('name') as $user)
                <option value="{{ $user->id }}" @selected((string) $selected === (string) $user->id)>{{ $user->name }}</option>
            @endforeach
        </optgroup>
    @endforeach
</select>
