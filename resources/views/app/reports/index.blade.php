@extends('layouts.app')

@section('title', 'Reports')

@section('content')
  <div class="page-head row">
    <div>
      <h1>Reports</h1>
      <p>Export open findings as CSV or print-ready HTML.</p>
    </div>
  </div>

  <div class="scan-card">
    <form method="POST" action="{{ route('reports.store') }}">
      @csrf
      <div class="opt-grid">
        <div class="field">
          <label for="scope">Scope</label>
          <select id="scope" name="scope" class="input">
            <option value="organization">Organization (all groups)</option>
            <option value="group">Asset group</option>
            <option value="vulnerabilities">Vulnerabilities</option>
            <option value="incidents">Incidents</option>
          </select>
        </div>
        <div class="field">
          <label for="kind">Format</label>
          <select id="kind" name="kind" class="input">
            <option value="csv">CSV</option>
            <option value="html">Print-friendly HTML</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label for="group_id">Asset group (when scope = group)</label>
        <select id="group_id" name="group_id" class="input">
          <option value="">—</option>
          @foreach ($groups as $g)
            <option value="{{ $g->id }}">{{ $g->root_domain }}</option>
          @endforeach
        </select>
      </div>
      <button class="btn btn-primary" type="submit">Generate report</button>
    </form>
  </div>

  <div class="page-head small-head">
    <h2>Report History</h2>
  </div>

  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Scope</th><th>Kind</th><th>Items</th><th>Generated</th><th></th></tr></thead>
      <tbody>
        @forelse ($reports as $report)
          <tr>
            <td class="mono">{{ $report->group?->root_domain ?: ucfirst($report->scope) }}</td>
            <td><span class="pill">{{ strtoupper($report->kind) }}</span></td>
            <td>{{ $report->item_count }}</td>
            <td>{{ $report->generated_at?->diffForHumans() }}</td>
            <td>
              <div class="row-actions">
                <a class="btn btn-ghost btn-sm" href="{{ route('reports.download', $report) }}">Download</a>
                <form method="POST" action="{{ route('reports.destroy', $report) }}" onsubmit="return confirm('Delete this report?')">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-ghost btn-sm danger" type="submit">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="muted">No reports yet. Generate one above.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pagination">{{ $reports->links() }}</div>
@endsection
