<?php

namespace App\Http\Controllers;

use App\Models\PartnerApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Edits an app's setup checklist template — one step per line. */
class ChecklistController extends Controller
{
    public function edit(PartnerApp $app)
    {
        return view('apps.checklist', ['app' => $app->load('setupSteps')]);
    }

    public function update(Request $request, PartnerApp $app)
    {
        $data = $request->validate(['steps' => ['nullable', 'string', 'max:20000']]);

        $steps = collect(preg_split('/\R/', (string) ($data['steps'] ?? '')))
            ->map(fn ($line) => trim(preg_replace('/^\s*(?:[-*•]|\d+[.)])\s*/u', '', $line)))
            ->filter()
            ->map(fn ($line) => mb_substr($line, 0, 250))
            ->values();

        DB::transaction(function () use ($app, $steps) {
            $app->setupSteps()->delete();
            foreach ($steps as $i => $title) {
                $app->setupSteps()->create(['title' => $title, 'position' => $i + 1]);
            }
        });

        return redirect()->route('apps.index')->with('status', "Saved {$steps->count()} setup steps for {$app->name}. Setups already started keep their own list.");
    }
}
