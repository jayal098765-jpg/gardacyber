@extends('layouts.app')

@section('title', 'Aktivitas')

@section('content')
  <div class="page-head row">
    <div>
      <h1>Aktivitas</h1>
      <p>Log berjalan dari setiap scan: antre, berjalan, atau selesai.</p>
    </div>
  </div>

  @if (session('status'))
    <div class="flash">{{ session('status') }}</div>
  @endif

  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Scan</th><th>Grup</th><th>Status</th><th>Temuan</th><th>Durasi</th><th>Dimulai</th><th>Selesai</th><th>Tindakan</th></tr></thead>
      <tbody>
        @forelse ($scans as $scan)
          @php
            $vulnCount = (int) ($scan->options['vulnerabilities'] ?? 0);
            $incidentCount = (int) ($scan->options['incidents'] ?? 0);
            $duration = null;

            if ($scan->finished_at && $scan->started_at) {
                $duration = $scan->finished_at->diffInSeconds($scan->started_at);
            } elseif ($scan->stopped_at && $scan->started_at) {
                $duration = $scan->stopped_at->diffInSeconds($scan->started_at);
            }

            $statusLabel = match ($scan->status) {
                'queued' => 'Antre',
                'running' => 'Berjalan',
                'completed' => 'Selesai',
                'failed' => 'Gagal',
                'stopped' => 'Dihentikan',
                default => ucfirst($scan->status),
            };
          @endphp
          <tr>
            <td>
              <span class="mode-badge">{{ $scan->modeBadge() }}</span>
              <a href="{{ route('scans.show', $scan) }}" class="view-link">lihat →</a>
            </td>
            <td class="mono"><a href="{{ route('groups.show', $scan->group) }}">{{ $scan->group->root_domain }}</a></td>
            <td><span class="pill status-{{ $scan->status }}">{{ $statusLabel }}</span></td>
            <td class="mono">
              @if ($vulnCount || $incidentCount)
                {{ $vulnCount }} vuln · {{ $incidentCount }} insiden
              @else
                <span class="muted">—</span>
              @endif
            </td>
            <td class="mono">
              @if ($duration !== null)
                @if ($duration < 60){{ $duration }} dtk
                @else{{ intdiv($duration, 60) }}m {{ $duration % 60 }} dtk
                @endif
              @else
                <span class="muted">—</span>
              @endif
            </td>
            <td>
              @if ($scan->started_at)
                {{ $scan->started_at->diffForHumans() }}
              @else
                <span class="muted">antre</span>
              @endif
            </td>
            <td>{{ $scan->finished_at?->diffForHumans() ?? '—' }}</td>
            <td>
              <div class="row-actions">
                @if ($scan->isLive())
                  <form method="POST" action="{{ route('scans.stop', $scan) }}">
                    @csrf
                    <button class="btn btn-ghost btn-sm" type="submit">Hentikan</button>
                  </form>
                @endif
                <form method="POST" action="{{ route('activity.scans.destroy', $scan) }}" onsubmit="return confirm('Hapus catatan scan ini?')">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-ghost btn-sm danger" type="submit">Hapus</button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="muted">Belum ada scan. <a href="{{ route('scans.new') }}">Mulai satu</a>.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pagination">{{ $scans->links() }}</div>
@endsection
