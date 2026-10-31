<?php

namespace App\Http\Controllers;

use App\Models\PartnerApp;
use App\Services\Support\TicketIntake;
use Illuminate\Http\Request;

/** The public "contact support" page merchants reach from inside each app. */
class SupportFormController extends Controller
{
    public function show(Request $request, TicketIntake $intake)
    {
        return view('support.form', [
            'apps' => PartnerApp::orderBy('name')->get(['id', 'name']),
            // Link from an app as /support?app=<Partner app ID>&shop=<shop>.myshopify.com
            'selectedApp' => $intake->resolveApp($request->query('app'))?->id,
            'shop' => TicketIntake::normalizeDomain($request->query('shop')),
        ]);
    }

    public function store(Request $request, TicketIntake $intake)
    {
        // Bots fill every field; people never see this one.
        if ($request->filled('website')) {
            return redirect()->route('support.thanks');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'shop_domain' => ['required', 'string', 'max:190'],
            'app' => ['nullable', 'integer', 'exists:partner_apps,id'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $ticket = $intake->open('form', $data);

        return redirect()->route('support.thanks')->with('ticket', $ticket->number());
    }

    public function thanks()
    {
        return view('support.thanks');
    }
}
