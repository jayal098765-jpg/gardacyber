<?php

namespace App\Http\Controllers;

use App\Models\Finding;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('app.reports.index', [
            'reports' => $request->user()->reports()->with('group')->orderByDesc('generated_at')->paginate(12),
            'groups' => $request->user()->groups()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|StreamedResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'in:organization,group,vulnerabilities,incidents'],
            'kind' => ['required', 'in:csv,html'],
            'group_id' => ['required_if:scope,group', 'nullable', 'integer'],
        ]);

        $user = $request->user();

        if ($request->filled('group_id')) {
            $group = $user->groups()->findOrFail($request->integer('group_id'));
        }

        $query = Finding::query()
            ->whereIn('asset_group_id', $user->groups()->pluck('id'))
            ->where('status', Finding::STATUS_OPEN);

        match ($validated['scope']) {
            Report::SCOPE_GROUP => $query->where('asset_group_id', $group->id),
            Report::SCOPE_VULNERABILITIES => $query->where('kind', Finding::KIND_VULNERABILITY),
            Report::SCOPE_INCIDENTS => $query->where('kind', Finding::KIND_INCIDENT),
            default => null,
        };

        $items = $query->with('asset')->get()->map(fn (Finding $f) => [
            'host' => $f->asset?->hostname,
            'title' => $f->title,
            'kind' => $f->kind,
            'severity' => $f->severity,
            'category' => $f->category,
            'status' => $f->status,
            'last_seen_at' => $f->last_seen_at?->toDateTimeString(),
        ])->all();

        $report = Report::create([
            'user_id' => $user->id,
            'asset_group_id' => $group->id ?? null,
            'scope' => $validated['scope'],
            'kind' => $validated['kind'],
            'item_count' => count($items),
            'payload' => $items,
            'generated_at' => now(),
        ]);

        if ($validated['kind'] === Report::KIND_CSV) {
            return $this->streamCsv($report);
        }

        return redirect()->route('reports.index')->with('status', 'Report generated.');
    }

    public function download(Request $request, Report $report): StreamedResponse
    {
        abort_unless($report->user_id === $request->user()->id, 404);

        if ($report->kind === Report::KIND_CSV) {
            return $this->streamCsv($report);
        }

        return response()->streamDownload(function () use ($report) {
            echo '<!doctype html><meta charset="utf-8"><title>GardaCyber report</title>';
            echo '<body style="font-family:system-ui;padding:40px;color:#111">';
            echo '<h1>GardaCyber Report</h1><p>'.e(ucfirst($report->scope)).' | '.$report->item_count.' items | '.e($report->generated_at?->toDateTimeString() ?? '').'</p>';
            echo '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%">';
            echo '<tr><th>Host</th><th>Title</th><th>Kind</th><th>Severity</th><th>Category</th><th>Status</th><th>Last seen</th></tr>';
            foreach ($report->payload ?? [] as $item) {
                echo '<tr><td>'.e($item['host'] ?? '').'</td><td>'.e($item['title'] ?? '').'</td><td>'.e($item['kind'] ?? '').'</td><td>'.e($item['severity'] ?? '').'</td><td>'.e($item['category'] ?? '').'</td><td>'.e($item['status'] ?? '').'</td><td>'.e($item['last_seen_at'] ?? '').'</td></tr>';
            }
            echo '</table></body>';
        }, 'report-'.$report->id.'.html', [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "sandbox; default-src 'none'; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, Report $report): RedirectResponse
    {
        abort_unless($report->user_id === $request->user()->id, 404);

        $report->delete();

        return back()->with('status', 'Report deleted.');
    }

    private function streamCsv(Report $report): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Host', 'Title', 'Kind', 'Severity', 'Category', 'Status', 'Last seen'], ',', '"', '');

            foreach ($report->payload ?? [] as $item) {
                $values = [
                    $item['host'] ?? '',
                    $item['title'] ?? '',
                    $item['kind'] ?? '',
                    $item['severity'] ?? '',
                    $item['category'] ?? '',
                    $item['status'] ?? '',
                    $item['last_seen_at'] ?? '',
                ];
                fputcsv($stream, array_map(static function (mixed $value): string {
                    $value = (string) $value;

                    return preg_match('/^[\x00-\x20]*[=+@-]|^[\t\r\n]/', $value) ? "'".$value : $value;
                }, $values), ',', '"', '');
            }

            fclose($stream);
        }, 'gardacyber-'.Str::slug($report->scope).'-'.$report->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }
}
