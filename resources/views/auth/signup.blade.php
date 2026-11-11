@extends('layouts.marketing')

@section('title', 'Create your account · GardaCyber')

@section('content')
<section class="auth-section">
  <div class="container">
    <div class="auth-card">
      <h1 class="auth-title">Create your account</h1>
      <p class="auth-sub">Scans, findings, and reports stay inside your own workspace.</p>

      <form method="POST" action="{{ route('signup') }}" class="auth-form">
        @csrf
        <div class="field">
          <label for="email">Email</label>
          <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="you@example.com" />
        </div>
        <div class="field">
          <label for="display_name">Display name (optional)</label>
          <input id="display_name" class="input" type="text" name="display_name" value="{{ old('display_name') }}" maxlength="255" />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" class="input" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters" />
        </div>
        <div class="field">
          <label for="password_confirmation">Confirm password</label>
          <input id="password_confirmation" class="input" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>
        @if (config('services.recaptcha.site_key'))
          <div class="field">
            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}" style="transform: scale(0.92); transform-origin: left center;"></div>
          </div>
        @endif
        <button type="submit" class="btn btn-primary auth-submit">Create account</button>
      </form>

      <p class="auth-foot">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
    </div>
  </div>
</section>

@if (config('services.recaptcha.site_key'))
@push('scripts')
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endpush
@endif
@endsection
