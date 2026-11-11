<?php

namespace App\Http\Controllers;

use App\Models\Finding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResolvedController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'all');

        $query = Finding::query()
            ->with(['group', 'asset'])
            ->whereIn('asset_group_id', $request->user()->groups()->pluck('id'))
            ->where('status', '!=', Finding::STATUS_OPEN);

        if ($tab === 'resolved') {
            $query->where('status', Finding::STATUS_RESOLVED);
        } elseif ($tab === 'false-positive') {
            $query->where('status', Finding::STATUS_FALSE_POSITIVE);
        }

        return view('app.resolved', [
            'findings' => $query->orderByDesc('last_seen_at')->paginate(12)->withQueryString(),
            'tab' => $tab,
        ]);
    }

    public function restore(Request $request, Finding $finding): RedirectResponse
    {
        abort_unless($finding->group->user_id === $request->user()->id, 404);

        $finding->update([
            'status' => Finding::STATUS_OPEN,
            'last_seen_at' => now(),
        ]);

        return back()->with('status', 'Finding restored to open.');
    }

    public function destroy(Request $request, Finding $finding): RedirectResponse
    {
        abort_unless($finding->group->user_id === $request->user()->id, 404);

        $finding->delete();

        return back()->with('status', 'Finding deleted permanently.');
    }
}
