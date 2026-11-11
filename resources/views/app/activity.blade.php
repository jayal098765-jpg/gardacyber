@extends('layouts.app')

@section('title', 'Activity')

@section('content')
  <div class="page-head row">
    <div>
      <h1>Activity</h1>
      <p>The running log of every scan: queued, live, or finished.</p>
    </div>
  </div>

  @if (session('status'))
    <div class="flash">{{ session('status') }}</div>
  @endif

  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Scan</th><th>Group</th><th>Status</th><th>Started</th><th>Finished</th><th>Actions</th></tr></thead>
      <tbody>
        @forelse ($scans as $scan)
          <tr>
            <td>
              <span class="mode-badge">{{ $scan->modeBadge() }}</span>
              <a href="{{ route('scans.show', $scan) }}" class="view-link">view →</a>
            </td>
            <td class="mono"><a href="{{ route('groups.show', $scan->group) }}">{{ $scan->group->root_domain }}</a></td>
            <td><span class="pill status-{{ $scan->status }}">{{ ucfirst($scan->status) }}</span></td>
            <td>{{ $scan->started_at?->diffForHumans() }}</td>
            <td>{{ $scan->finished_at?->diffForHumans() ?? '—' }}</td>
            <td>
              <div class="row-actions">
                @if ($scan->isLive())
                  <form method="POST" action="{{ route('activity.scans.stop', $scan) }}">
                    @csrf
                    <button class="btn btn-ghost btn-sm" type="submit">Stop</button>
                  </form>
                @endif
                <form method="POST" action="{{ route('activity.scans.destroy', $scan) }}" onsubmit="return confirm('Delete this scan record?')">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-ghost btn-sm danger" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="muted">No scans yet. <a href="{{ route('scans.new') }}">Start one</a>.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pagination">{{ $scans->links() }}</div>
@endsection
