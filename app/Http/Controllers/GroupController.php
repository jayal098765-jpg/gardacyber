<?php

namespace App\Http\Controllers;

use App\Models\AssetGroup;
use App\Models\Finding;
use App\Services\AiService;
use App\Services\AttackStoryboardService;
use App\Services\SecurityRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function aiSummary(Request $request, AssetGroup $group, AiService $ai): JsonResponse
    {
        abort_unless($group->user_id === $request->user()->id, 404);

        $summary = $ai->summarizeGroup($group, $request->user());

        return response()->json($summary);
    }

    public function index(Request $request, SecurityRatingService $rating): View
    {
        $groups = $request->user()->groups()->withCount('assets')->get();

        return view('app.groups.index', [
            'groups' => $groups,
            'ratings' => $rating->rateGroups($groups),
        ]);
    }

    public function show(Request $request, AssetGroup $group, SecurityRatingService $rating): View
    {
        abort_unless($group->user_id === $request->user()->id, 404);

        $tab = in_array($request->query('tab'), ['assets', 'vulnerabilities', 'incidents', 'history'], true)
            ? $request->query('tab')
            : 'assets';

        $assets = $group->assets()->withCount([
            'findings as open_findings_count' => fn ($q) => $q->where('status', Finding::STATUS_OPEN),
        ])->get();

        return view('app.groups.show', [
            'group' => $group,
            'assets' => $assets,
            'ratings' => $rating->rateGroup($group),
            'openVulnerabilities' => $group->findings()->with('asset')->where('kind', Finding::KIND_VULNERABILITY)->where('status', Finding::STATUS_OPEN)->get(),
            'openIncidents' => $group->findings()->with('asset')->where('kind', Finding::KIND_INCIDENT)->where('status', Finding::STATUS_OPEN)->get(),
            'scans' => $group->scans()->orderByDesc('started_at')->take(8)->get(),
            'tab' => $tab,
        ]);
    }

    public function destroy(Request $request, AssetGroup $group): RedirectResponse
    {
        abort_unless($group->user_id === $request->user()->id, 404);

        $group->delete();

        return redirect()->route('groups.index')->with('status', 'Asset group deleted.');
    }

    public function storyboard(Request $request, AssetGroup $group, AttackStoryboardService $storyboard): View
    {
        abort_unless($group->user_id === $request->user()->id, 404);

        return view('app.groups.storyboard', [
            'group' => $group,
            'storyboard' => $storyboard->build($group),
            'assetsCount' => $group->assets()->count(),
            'ratings' => app(SecurityRatingService::class)->rateGroup($group),
        ]);
    }
}
