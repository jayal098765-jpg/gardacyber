@extends('layouts.marketing')

@section('title', 'Scan responsibly · GardaCyber')

@section('content')
<section class="auth-section">
  <div class="container">
    <div class="auth-card gate-card">
      <div class="gate-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.4 8.6 8 11 4.6-2.4 8-6 8-11V5l-8-3Z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
      </div>
      <h1 class="auth-title">Scan responsibly</h1>
      <p class="auth-sub">One last step before you can scan.</p>

      <form method="POST" action="{{ route('terms.accept') }}" class="auth-form">
        @csrf
        <label class="chk-row">
          <input type="checkbox" name="accept_terms" value="1" required />
          <span>I understand this is a hackathon build. Scan results may be incomplete or inaccurate and require review.</span>
        </label>
        <label class="chk-row">
          <input type="checkbox" name="accept_authorized" value="1" required />
          <span>I will only scan targets I own or am authorized to test.</span>
        </label>
        <p class="gate-note">Unauthorized scanning may violate applicable law or service terms. Obtain explicit permission before scanning any target you do not own.</p>
        <button type="submit" class="btn btn-primary auth-submit">Accept and continue</button>
      </form>
    </div>
  </div>
</section>
@endsection
