@extends('layouts.app')

@section('title', 'Teratasi')

@section('content')
  <div class="page-head row">
    <div>
      <h1>Teratasi &amp; False Positives</h1>
      <p>Temuan tertutup tetap tertutup sampai kenyataan berubah.</p>
    </div>
  </div>

  <div class="tabs">
    <a class="tab {{ $tab === 'all' ? 'active' : '' }}" href="{{ route('resolved.index', ['tab' => 'all']) }}">Semua</a>
    <a class="tab {{ $tab === 'resolved' ? 'active' : '' }}" href="{{ route('resolved.index', ['tab' => 'resolved']) }}">Teratasi</a>
    <a class="tab {{ $tab === 'false-positive' ? 'active' : '' }}" href="{{ route('resolved.index', ['tab' => 'false-positive']) }}">False positive</a>
  </div>

  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Severitas</th><th>Judul</th><th>Host</th><th>Status</th><th>Tindakan</th></tr></thead>
      <tbody>
        @forelse ($findings as $finding)
          <tr>
            <td><span class="badge sev-{{ $finding->severity }}"><span class="dot"></span>{{ ucfirst($finding->severity) }}</span></td>
            <td>{{ $finding->title }}</td>
            <td class="mono">{{ $finding->asset?->hostname ?: $finding->group->root_domain }}</td>
            <td>
              <span class="pill {{ $finding->status === 'resolved' ? 'ok' : 'warn' }}">
                {{ $finding->status === 'resolved' ? 'Teratasi' : 'False positive' }}
              </span>
            </td>
            <td>
              <div class="row-actions">
                <form method="POST" action="{{ route('findings.restore', $finding) }}">
                  @csrf
                  <button class="btn btn-ghost btn-sm" type="submit">Pulihkan ke terbuka</button>
                </form>
                <form method="POST" action="{{ route('findings.destroy', $finding) }}" onsubmit="return confirm('Hapus permanen?')">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-ghost btn-sm danger" type="submit">Hapus</button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="muted">Belum ada apa pun di sini.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pagination">{{ $findings->links() }}</div>
@endsection
