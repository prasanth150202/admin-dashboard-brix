<x-layout :title="$app->name.' checklist'">
    <a href="{{ route('apps.index') }}" class="text-sm text-ink-2 hover:text-ink">← Apps</a>
    <h1 class="mt-2 text-xl font-semibold">{{ $app->name }} setup checklist</h1>
    <p class="mt-1 max-w-2xl text-sm text-ink-2">
        Every new {{ $app->name }} install gets a setup with these steps. One step per line, in order.
        Changing the list only affects setups started after you save.
    </p>

    <form method="POST" action="{{ route('apps.checklist.update', $app) }}" class="card mt-6 max-w-2xl p-4">
        @csrf @method('PUT')
        <label for="steps" class="block text-sm font-medium">Steps</label>
        <textarea id="steps" name="steps" rows="14" class="field mt-1 w-full font-mono"
                  placeholder="Enable the app embed in the theme editor&#10;Configure settings with the merchant&#10;Place a test order&#10;Send the welcome email">{{ old('steps', $app->setupSteps->pluck('title')->implode("\n")) }}</textarea>
        @error('steps')<p class="mt-1 text-sm" style="color: var(--critical)">{{ $message }}</p>@enderror
        <button class="btn btn-primary mt-3">Save checklist</button>
    </form>
</x-layout>
