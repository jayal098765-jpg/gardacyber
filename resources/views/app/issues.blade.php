@extends('layouts.app')

@section('title', 'Semua Temuan')

@section('content')
  <div class="page-head row">
    <div>
      <h1>Semua Temuan</h1>
      <p>Semua vulnerabilitas dan insiden terbuka di seluruh grupmu.</p>
    </div>
  </div>

  <form class="filter-bar" method="GET" action="{{ route('issues.index') }}">
    <div class="field field-inline">
      <label for="kind">Jenis</label>
      <select id="kind" name="kind" class="input">
        <option value="">Keduanya</option>
        <option value="vulnerability" @selected($kind === 'vulnerability')>Vulnerabilitas</option>
        <option value="incident" @selected($kind === 'incident')>Insiden</option>
      </select>
    </div>
    <div class="field field-inline">
      <label for="sev">Severitas</label>
      <select id="sev" name="sev" class="input">
        <option value="">Semua severitas</option>
        @foreach (['critical','high','medium','low'] as $s)
          <option value="{{ $s }}" @selected($severity === $s)>{{ ucfirst($s) }}</option>
        @endforeach
      </select>
    </div>
    <button class="btn btn-primary btn-sm" type="submit">Filter</button>
  </form>

  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Severitas</th><th>Temuan / bukti</th><th>Host</th><th>Jenis</th><th>Kategori</th><th>Grup</th><th>Terakhir terlihat</th><th>Keputusan</th></tr></thead>
      <tbody>
        @forelse ($findings as $finding)
          <tr>
            <td><span class="badge sev-{{ $finding->severity }}"><span class="dot"></span>{{ ucfirst($finding->severity) }}</span></td>
            <td><strong><a href="{{ route('findings.show', $finding) }}" style="color: inherit; text-decoration: underline; text-underline-offset: 3px;">{{ $finding->title }}</a></strong>
              @if ($finding->description || $finding->evidence)
                <details class="finding-details"><summary>Periksa bukti</summary>
                  @if ($finding->description)<p>{{ $finding->description }}</p>@endif
                  @if ($finding->evidence)<pre>{{ $finding->evidence }}</pre>@endif
                </details>
              @endif
            </td>
            <td class="mono">{{ $finding->asset?->hostname ?: $finding->group->root_domain }}</td>
            <td><span class="pill pill-kind">{{ $finding->kind === 'incident' ? 'Insiden' : 'Vulnerabilitas' }}</span></td>
            <td>{{ $finding->category }}</td>
            <td class="mono"><a href="{{ route('groups.show', $finding->group) }}">{{ $finding->group->root_domain }}</a></td>
            <td>{{ $finding->last_seen_at?->diffForHumans() }}</td>
            <td>
              <form method="POST" action="{{ route('findings.update', $finding) }}" class="finding-decision">
                @csrf
                @method('PATCH')
                <label class="sr-only" for="finding-status-{{ $finding->id }}">Keputusan untuk {{ $finding->title }}</label>
                <select class="input" id="finding-status-{{ $finding->id }}" name="status" required>
                  <option value="" disabled selected>Pilih keputusan</option>
                  <option value="resolved">Teratasi</option>
                  <option value="false_positive">False positive</option>
                </select>
                <button class="btn btn-ghost btn-sm" type="submit">Simpan keputusan</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="muted">Tidak ada temuan terbuka yang cocok dengan filter ini.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pagination">{{ $findings->links() }}</div>
@endsection
