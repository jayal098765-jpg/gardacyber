<?php

namespace App\Http\Controllers;

use App\Jobs\RunSurfaceScan;
use App\Models\AssetGroup;
use App\Models\Scan;
use App\Services\BackgroundScanStarter;
use App\Services\ScanProgressService;
use App\Services\SecurityRatingService;
use App\Services\SurfaceScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function create(Request $request): View
    {
        return view('app.scans.new', [
            'groups' => $request->user()->groups()->get(),
        ]);
    }

    public function store(Request $request, SurfaceScanner $scanner, BackgroundScanStarter $starter): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'domain' => ['nullable', 'string', 'max:255', 'regex:/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i'],
            'group_id' => ['nullable', 'integer'],
            'authorized' => ['required', 'accepted'],
            'type' => ['required', 'in:'.Scan::TYPE_FULL.','.Scan::TYPE_VULNERABILITY.','.Scan::TYPE_INCIDENT],
            'single_host' => ['nullable', 'boolean'],
        ], [], [
            'domain' => 'domain',
            'group_id' => 'asset group',
        ]);

        $validator->validate();

        $user = $request->user();
        $singleHost = $request->boolean('single_host');

        if ($request->filled('group_id')) {
            $group = $user->groups()->findOrFail($request->integer('group_id'));
            if ($request->has('single_host')) {
                $group->update(['single_host' => $singleHost]);
            }
        } else {
            $domain = strtolower(trim($request->string('domain')->toString()));

            if ($domain === '') {
                return back()->withErrors(['domain' => 'Enter a domain or pick an existing asset group.']);
            }

            $scanner->assertAllowed($domain);

            $group = AssetGroup::firstOrCreate(
                ['user_id' => $user->id, 'root_domain' => $domain],
                [
                    'slug' => $user->id.'-'.str($domain)->replace('.', '-')->toString(),
                    'single_host' => $singleHost,
                ]
            );

            if ($request->has('single_host') && $group->single_host !== $singleHost) {
                $group->update(['single_host' => $singleHost]);
            }
        }

        $scanner->assertAllowed($group->root_domain);

        if ($group->scans()->whereIn('status', [Scan::STATUS_QUEUED, Scan::STATUS_RUNNING])->exists()) {
            return back()->withErrors(['domain' => 'A scan is already queued or running for this group.']);
        }

        $type = $request->string('type')->toString();
        $stageCount = $type === Scan::TYPE_INCIDENT ? 4 : 8;

        $scan = Scan::create([
            'asset_group_id' => $group->id,
            'type' => $type,
            'status' => Scan::STATUS_QUEUED,
            'options' => [
                'single_host' => $group->single_host,
                'stage' => 0,
            ],
            'stage_count' => $stageCount,
        ]);

        $starter->ensureAsyncProcessing($scan->id);

        return redirect()->route('scans.show', $scan);
    }

    public function show(Request $request, Scan $scan, ScanProgressService $progress): View
    {
        abort_unless($scan->group->user_id === $request->user()->id, 404);

        return view('app.scans.show', ['scan' => $scan, 'initial' => $progress->progress($scan)]);
    }

    public function progress(Request $request, Scan $scan, ScanProgressService $progress, SurfaceScanner $scanner, BackgroundScanStarter $starter): JsonResponse
    {
        abort_unless($scan->group->user_id === $request->user()->id, 404);

        if ($scan->status === Scan::STATUS_QUEUED) {
            $this->driveQueuedScan($scan, $scanner, $starter);
        }

        return response()->json($progress->progress($scan));
    }

    /**
     * Make sure a queued scan moves forward. On a sync queue connection the
     * background worker gets a short grace window to claim the job; if it
     * has not, the scan runs inline so it can never be stuck queued forever.
     */
    private function driveQueuedScan(Scan $scan, SurfaceScanner $scanner, BackgroundScanStarter $starter): void
    {
        if (config('queue.default') !== 'sync') {
            // A real queue worker is expected to claim the job.
            return;
        }

        if (config('scanner.async_worker', true)) {
            $grace = max(0, (int) config('scanner.async_worker_grace', 8));
            $queuedFor = abs(now()->diffInSeconds($scan->created_at ?? $scan->updated_at));

            if ($queuedFor <= $grace) {
                $starter->startIfNeeded();

                return;
            }
        }

        (new RunSurfaceScan($scan->id))->handle($scanner, app(SecurityRatingService::class));
        $scan->refresh();
    }

    public function stop(Request $request, Scan $scan, ScanProgressService $progress): RedirectResponse
    {
        abort_unless($scan->group->user_id === $request->user()->id, 404);

        $progress->stop($scan);

        return redirect()->route('scans.show', $scan)->with('status', 'Scan stopped.');
    }
}
