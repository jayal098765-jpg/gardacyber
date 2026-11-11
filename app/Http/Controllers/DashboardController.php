<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Finding;
use App\Models\Scan;
use App\Services\PostureAnalyticsService;
use App\Services\SecurityRatingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SecurityRatingService $rating, PostureAnalyticsService $analytics): View
    {
        $user = $request->user();
        $groupIds = $user->groups()->pluck('id');

        $openVulns = Finding::query()
            ->with(['asset', 'group'])
            ->whereIn('asset_group_id', $groupIds)
            ->where('kind', Finding::KIND_VULNERABILITY)
            ->where('status', Finding::STATUS_OPEN)
            ->get();

        $openIncidents = Finding::query()
            ->with(['asset', 'group'])
            ->whereIn('asset_group_id', $groupIds)
            ->where('kind', Finding::KIND_INCIDENT)
            ->where('status', Finding::STATUS_OPEN)
            ->get();

        $groups = $user->groups()->withCount('assets')->get()->sortByDesc('assets_count');

        $worstVuln = $openVulns->sortBy(fn (Finding $f) => array_search($f->severity, Finding::SEVERITIES, true))->first();
        $worstIncident = $openIncidents->sortBy(fn (Finding $f) => array_search($f->severity, Finding::SEVERITIES, true))->first();

        $recentVulnerabilities = $openVulns->sortByDesc('last_seen_at')->take(6)->values();
        $recentIncidents = $openIncidents->sortByDesc('last_seen_at')->take(4)->values();

        $postureHistory = $analytics->dailyHistory($groups)->sortBy('date')->values();
        $exposureIndex = $analytics->exposureIndex($groups, $openVulns, $openIncidents);

        $postureChart = [
            'vuln' => $analytics->chartSeries($postureHistory->pluck('vuln_score'), 320, 90),
            'incident' => $analytics->chartSeries($postureHistory->pluck('incident_score'), 320, 90),
        ];

        $viewData = [
            'user' => $user,
            'groups' => $groups,
            'rating' => ['vuln' => $rating->rate($openVulns), 'incident' => $rating->rate($openIncidents)],
            'groupRatings' => $rating->rateGroups($groups),
            'totalAssets' => Asset::whereIn('asset_group_id', $groupIds)->count(),
            'openVulns' => $openVulns,
            'openIncidents' => $openIncidents,
            'worstVuln' => $worstVuln,
            'worstIncident' => $worstIncident,
            'scansThisWeek' => Scan::whereIn('asset_group_id', $groupIds)->where('started_at', '>=', now()->subWeek())->count(),
            'recentVulnerabilities' => $recentVulnerabilities,
            'recentIncidents' => $recentIncidents,
            'postureHistory' => $postureHistory,
            'exposureIndex' => $exposureIndex,
            'postureChart' => $postureChart,
        ];

        return view('app.dashboard', $viewData);
    }
}
