@extends('layouts.app')

@section('title', 'All Findings')

@section('content')
  <div class="page-head row">
    <div>
      <h1>All Findings</h1>
      <p>Every open vulnerability and incident across your groups.</p>
    </div>
  </div>

  <form class="filter-bar" method="GET" action="{{ route('issues.index') }}">
    <div class="field field-inline">
      <label for="kind">Kind</label>
      <select id="kind" name="kind" class="input">
        <option value="">Both</option>
        <option value="vulnerability" @selected($kind === 'vulnerability')>Vulnerabilities</option>
        <option value="incident" @selected($kind === 'incident')>Incidents</option>
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
      <thead><tr><th>Severity</th><th>Finding / evidence</th><th>Host</th><th>Kind</th><th>Category</th><th>Group</th><th>Last seen</th><th>Decision</th></tr></thead>
      <tbody>
        @forelse ($findings as $finding)
          <tr>
            <td><span class="badge sev-{{ $finding->severity }}"><span class="dot"></span>{{ ucfirst($finding->severity) }}</span></td>
            <td><strong><a href="{{ route('findings.show', $finding) }}" style="color: inherit; text-decoration: underline; text-underline-offset: 3px;">{{ $finding->title }}</a></strong>
              @if ($finding->description || $finding->evidence)
                <details class="finding-details"><summary>Inspect evidence</summary>
                  @if ($finding->description)<p>{{ $finding->description }}</p>@endif
                  @if ($finding->evidence)<pre>{{ $finding->evidence }}</pre>@endif
                </details>
              @endif
            </td>
            <td class="mono">{{ $finding->asset?->hostname ?: $finding->group->root_domain }}</td>
            <td><span class="pill pill-kind">{{ $finding->kind }}</span></td>
            <td>{{ $finding->category }}</td>
            <td class="mono"><a href="{{ route('groups.show', $finding->group) }}">{{ $finding->group->root_domain }}</a></td>
            <td>{{ $finding->last_seen_at?->diffForHumans() }}</td>
            <td>
              <form method="POST" action="{{ route('findings.update', $finding) }}" class="finding-decision">
                @csrf
                @method('PATCH')
                <label class="sr-only" for="finding-status-{{ $finding->id }}">Decision for {{ $finding->title }}</label>
                <select class="input" id="finding-status-{{ $finding->id }}" name="status" required>
                  <option value="" disabled selected>Select decision</option>
                  <option value="resolved">Resolved</option>
                  <option value="false_positive">False positive</option>
                </select>
                <button class="btn btn-ghost btn-sm" type="submit">Save decision</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="muted">No open findings match these filters.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pagination">{{ $findings->links() }}</div>
@endsection
