<?php

namespace App\Http\Controllers;

use App\Models\Finding;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $groups = $user->groups()->get();

        $query = Finding::query()
            ->with(['group', 'asset'])
            ->where('kind', Finding::KIND_INCIDENT)
            ->where('status', Finding::STATUS_OPEN);

        $group = null;

        if ($request->filled('group')) {
            $group = $groups->firstWhere('id', (int) $request->query('group'));
            abort_unless($group !== null, 404);
            $query->where('asset_group_id', $group->id);
        } else {
            $query->whereIn('asset_group_id', $user->groups()->pluck('id'));
        }

        $findings = $query->orderByDesc('last_seen_at')->get();

        return view('app.incidents', [
            'groups' => $groups,
            'group' => $group,
            'findings' => $findings,
        ]);
    }
}
