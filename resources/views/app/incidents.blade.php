@extends('layouts.app')

@section('title', 'Incidents')

@section('content')
  <div class="page-head row">
    <div>
      <h1>Incidents</h1>
      <p>Suspicious content signals: inspect source evidence before confirming an incident.</p>
    </div>
  </div>

  <form class="filter-bar" method="GET" action="{{ route('incidents.index') }}">
    <div class="field field-inline">
      <label for="group">Asset group</label>
      <select id="group" name="group" class="input">
        <option value="">All groups</option>
        @foreach ($groups as $g)
          <option value="{{ $g->id }}" @selected($group?->id === $g->id)>{{ $g->root_domain }}</option>
        @endforeach
      </select>
    </div>
    <button class="btn btn-primary btn-sm" type="submit">Filter</button>
  </form>

  @if ($findings->isEmpty())
    <div class="empty-state compact">
      <p class="muted">No open incident findings in this workspace. Run a scan to check your authorized scope.</p>
    </div>
  @else
    <div class="incident-cards">
      @foreach ($findings as $finding)
        <div class="incident-tile">
          <div class="incident-evidence"><span class="evidence-label">Recorded evidence</span><pre>{{ $finding->evidence ?: 'No text evidence recorded. Review scan details before confirming this signal.' }}</pre></div>
          <div class="incident-tile-body">
            <span class="badge sev-{{ $finding->severity }}"><span class="dot"></span>{{ ucfirst($finding->severity) }}</span>
            <div class="incident-title"><a href="{{ route('findings.show', $finding) }}" style="color: inherit; text-decoration: underline; text-underline-offset: 3px;">{{ $finding->title }}</a></div>
            @if ($finding->description)<p class="incident-sub">{{ $finding->description }}</p>@endif
            <div class="incident-sub"><span class="mono">{{ $finding->asset?->hostname ?: $finding->group->root_domain }}</span> · {{ $finding->category }}</div>
            <div class="incident-sub">group <a href="{{ route('groups.show', $finding->group) }}">{{ $finding->group->root_domain }}</a> · first seen {{ $finding->first_seen_at?->diffForHumans() }}</div>
            <a class="text-link" href="{{ route('findings.show', $finding) }}">View evidence &amp; playbook →</a>
          </div>
        </div>
      @endforeach
    </div>
  @endif
@endsection
