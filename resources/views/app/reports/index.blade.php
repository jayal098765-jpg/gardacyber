@extends('layouts.app')

@section('title', 'Laporan')

@section('content')
  <div class="page-head row">
    <div>
      <h1>Laporan</h1>
      <p>Ekspor temuan terbuka sebagai CSV atau HTML siap cetak.</p>
    </div>
  </div>

  <div class="scan-card">
    <form method="POST" action="{{ route('reports.store') }}">
      @csrf
      <div class="opt-grid">
        <div class="field">
          <label for="scope">Ruang lingkup</label>
          <select id="scope" name="scope" class="input">
            <option value="organization">Organisasi (semua grup)</option>
            <option value="group">Grup aset</option>
            <option value="vulnerabilities">Vulnerabilitas</option>
            <option value="incidents">Insiden</option>
          </select>
        </div>
        <div class="field">
          <label for="kind">Format</label>
          <select id="kind" name="kind" class="input">
            <option value="csv">CSV</option>
            <option value="html">HTML siap cetak</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label for="group_id">Grup aset (saat ruang lingkup = grup)</label>
        <select id="group_id" name="group_id" class="input">
          <option value="">—</option>
          @foreach ($groups as $g)
            <option value="{{ $g->id }}">{{ $g->root_domain }}</option>
          @endforeach
        </select>
      </div>
      <button class="btn btn-primary" type="submit">Buat laporan</button>
    </form>
  </div>

  <div class="page-head small-head">
    <h2>Riwayat Laporan</h2>
  </div>

  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Ruang lingkup</th><th>Jenis</th><th>Item</th><th>Dibuat</th><th></th></tr></thead>
      <tbody>
        @forelse ($reports as $report)
          <tr>
            <td class="mono">{{ $report->group?->root_domain ?: ucfirst($report->scope) }}</td>
            <td><span class="pill">{{ strtoupper($report->kind) }}</span></td>
            <td>{{ $report->item_count }}</td>
            <td>{{ $report->generated_at?->diffForHumans() }}</td>
            <td>
              <div class="row-actions">
                <a class="btn btn-ghost btn-sm" href="{{ route('reports.download', $report) }}">Unduh</a>
                <form method="POST" action="{{ route('reports.destroy', $report) }}" onsubmit="return confirm('Hapus laporan ini?')">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-ghost btn-sm danger" type="submit">Hapus</button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="muted">Belum ada laporan. Buat satu di atas.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="pagination">{{ $reports->links() }}</div>
@endsection
