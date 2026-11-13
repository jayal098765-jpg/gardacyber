@extends('layouts.app')

@section('title', 'Vulnerabilitas')

@section('content')
  <div class="page-head row">
    <div>
      <h1>Vulnerabilitas</h1>
      <p>Kelemahan dan eksposur terbuka, satu per kecocokan.</p>
    </div>
  </div>

  <form class="filter-bar" method="GET" action="{{ route('vulnerabilities.index') }}">
    <div class="field field-inline">
      <label for="group">Grup aset</label>
      <select id="group" name="group" class="input">
        <option value="">Semua grup</option>
        @foreach ($groups as $g)
          <option value="{{ $g->id }}" @selected($group?->id === $g->id)>{{ $g->root_domain }}</option>
        @endforeach
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
      <thead>
        <tr><th>Severitas</th><th>Judul</th><th>Host</th><th>Kategori</th><th>Grup</th><th>Terakhir terlihat</th></tr>
      </thead>
      <tbody>
        @forelse ($findings as $finding)
          <tr>
            <td><span class="badge sev-{{ $finding->severity }}"><span class="dot"></span>{{ ucfirst($finding->severity) }}</span></td>
            <td>
              <a href="{{ route('findings.show', $finding) }}" style="color: inherit; text-decoration: underline; text-underline-offset: 3px;">{{ $finding->title }}</a>
              @if ($finding->cve_id)
                <div>
                  <a href="https://nvd.nist.gov/vuln/detail/{{ $finding->cve_id }}" target="_blank" rel="noopener" class="badge cve-badge">{{ $finding->cve_id }}</a>
                </div>
              @endif
            </td>
            <td class="mono">{{ $finding->asset?->hostname ?: $finding->group->root_domain }}</td>
            <td>
              {{ $finding->category }}
              @if ($finding->owasp && $owaspLabels->has($finding->owasp))
                <div><span class="badge owasp-badge">{{ $finding->owasp }} — {{ $owaspLabels->get($finding->owasp) }}</span></div>
              @endif
            </td>
            <td class="mono"><a href="{{ route('groups.show', $finding->group) }}">{{ $finding->group->root_domain }}</a></td>
            <td>{{ $finding->last_seen_at?->diffForHumans() }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="muted">Tidak ada vulnerabilitas terbuka yang cocok dengan filter ini.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pagination">{{ $findings->links() }}</div>
@endsection
