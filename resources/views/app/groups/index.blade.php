@extends('layouts.app')

@section('title', 'Grup Aset')

@section('content')
  <div class="page-head">
    <h1>Grup Aset</h1>
    <p>Satu grup per domain root. Aset dan temuan tetap terhubung di sini.</p>
  </div>

  @if (session('status'))
    <div class="flash">{{ session('status') }}</div>
  @endif

  @if ($groups->isEmpty())
    <div class="empty-state">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
      </div>
      <h2>Belum ada grup aset</h2>
      <p>Scan sebuah domain dan grupmu akan muncul otomatis di sini.</p>
      <a class="btn btn-primary" href="{{ route('scans.new') }}">Mulai scan</a>
    </div>
  @else
    <div class="group-cards">
      @foreach ($ratings as $row)
        <a class="group-card" href="{{ route('groups.show', $row['group']) }}">
          <div class="group-card-top">
            <span class="mono group-domain">{{ $row['group']->root_domain }}</span>
            @if ($row['group']->single_host)
              <span class="pill">host tunggal</span>
            @endif
          </div>
          <div class="group-meta">{{ $row['group']->assets_count }} aset · scan terakhir {{ $row['group']->last_scanned_at?->diffForHumans() ?? 'tidak pernah' }}</div>
          <div class="tag-row">
            @php $worstV = $row['vuln']['grade']; @endphp
            <span class="badge sev-{{ strtolower($worstV) }}"><span class="dot"></span>Vuln {{ $worstV }}</span>
            <span class="badge sev-{{ strtolower($row['incident']['grade']) }}"><span class="dot"></span>Insiden {{ $row['incident']['grade'] }}</span>
          </div>
        </a>
      @endforeach
    </div>
  @endif
@endsection
