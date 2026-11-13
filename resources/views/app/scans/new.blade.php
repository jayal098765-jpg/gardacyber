@extends('layouts.app')

@section('title', 'New Perimeter Scan · GardaCyber')

@section('content')
  <div class="page-head">
    <h1>Launch Scan</h1>
    <p>Checks one hostname: DNS, open ports, security headers, sensitive paths, and defacement or gambling-injection traces.</p>
  </div>

  <div class="scan-card" style="max-width: 680px;">
    <form method="POST" action="{{ route('scans.store') }}" id="scanForm">
      @csrf

      <div class="field">
        <label for="domain">Target Hostname</label>
        <input id="domain" class="input input-lg mono" type="text" name="domain"
               value="{{ old('domain') }}"
               placeholder="example.com" autocomplete="off" spellcheck="false" required />
      </div>

      <div class="field">
        <label for="type">Inspection Scope</label>
        <select id="type" name="type" class="input">
          <option value="full" @selected(old('type') === 'full')>Full Scan (Defense Headers + Visible Compromise Signatures)</option>
          <option value="vulnerability" @selected(old('type') === 'vulnerability')>Vulnerability Only (Security Headers Audit)</option>
          <option value="incident" @selected(old('type') === 'incident')>Incident Check (Defacement, Gambling &amp; SEO Keyword Heuristics)</option>
        </select>
      </div>

      @if ($groups->isNotEmpty())
        <div class="field">
          <label for="group_id">Or Select Existing Asset Group</label>
          <select id="group_id" name="group_id" class="input" onchange="handleGroupSelect(this)">
            <option value="">— Create new or use domain entered above —</option>
            @foreach ($groups as $g)
              <option value="{{ $g->id }}" data-domain="{{ $g->root_domain }}" @selected(old('group_id') == $g->id)>
                {{ $g->root_domain }} ({{ $g->assets()->count() }} assets)
              </option>
            @endforeach
          </select>
        </div>
      @endif

      <div style="background: var(--bg-surface-2, #0d1527); border: 1px solid var(--border-subtle, rgba(255,255,255,0.08)); border-radius: 10px; padding: 14px; margin: 18px 0;">
        <label class="chk-row" style="margin-bottom: 0;">
          <input type="checkbox" name="authorized" value="1" @checked(old('authorized')) required />
          <span style="font-size: 13px;">
            I certify that I am explicitly authorized to inspect this target.
          </span>
        </label>
      </div>

      <button class="btn btn-primary btn-lg" type="submit" style="width: 100%;">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
          <circle cx="12" cy="12" r="9"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Execute Perimeter Scan
      </button>

      <p style="margin-top: 12px; font-size: 13px; opacity: 0.75; text-align: center;">
        Setelah dijalankan, kamu akan dialihkan ke konsol live yang menampilkan progres
        tahap-demi-tahap beserta estimasi waktu tersisa.
      </p>
    </form>
  </div>
@endsection

@push('scripts')
<script>
function handleGroupSelect(select) {
  const selectedOption = select.options[select.selectedIndex];
  const domain = selectedOption.getAttribute('data-domain');
  if (domain) {
    document.getElementById('domain').value = domain;
  }
}
</script>
@endpush
