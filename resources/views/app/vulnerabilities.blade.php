@extends('layouts.app')

@section('title', 'Vulnerabilities')

@section('content')
  <div class="page-head row">
    <div>
      <h1>Vulnerabilities</h1>
      <p>Open weaknesses and exposures, one per match.</p>
    </div>
  </div>

  <form class="filter-bar" method="GET" action="{{ route('vulnerabilities.index') }}">
    <div class="field field-inline">
      <label for="group">Asset group</label>
      <select id="group" name="group" class="input">
        <option value="">All groups</option>
        @foreach ($groups as $g)
          <option value="{{ $g->id }}" @selected($group?->id === $g->id)>{{ $g->root_domain }}</option>
        @endforeach
      </select>
    </div>
    <div class="field field-inline">
      <label for="sev">Severity</label>
      <select id="sev" name="sev" class="input">
        <option value="">All severities</option>
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
        <tr><th>Severity</th><th>Title</th><th>Host</th><th>Category</th><th>Group</th><th>Last seen</th></tr>
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
          <tr><td colspan="6" class="muted">No open vulnerabilities match these filters.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pagination">{{ $findings->links() }}</div>
@endsection
