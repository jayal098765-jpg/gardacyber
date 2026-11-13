<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $scans = Scan::query()
            ->with('group')
            ->whereIn('asset_group_id', $request->user()->groups()->pluck('id'))
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('app.activity', ['scans' => $scans]);
    }

    public function destroy(Request $request, Scan $scan): RedirectResponse
    {
        abort_unless($scan->group->user_id === $request->user()->id, 404);

        $scan->delete();

        return back()->with('status', 'Scan record deleted.');
    }
}
