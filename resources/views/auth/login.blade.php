@extends('layouts.marketing')

@section('title', 'Sign in · GardaCyber')

@@section('content')
<section class="auth-section">
  <div class="container">
    <div class="auth-card">
      <h1 class="auth-title" data-i18n="auth_title">Sign in</h1>
      <p class="auth-sub" data-i18n="auth_sub">Back to your scans and findings.</p>

      <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf
        <div class="field">
          <label for="email" data-i18n="label_email">Email</label>
          <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" />
        </div>
        <div class="field">
          <label for="password" data-i18n="label_password">Password</label>
          <input id="password" class="input" type="password" name="password" required autocomplete="current-password" />
        </div>
        <label class="chk-row">
          <input type="checkbox" name="remember" value="1" @checked(old('remember')) />
          <span data-i18n="label_remember">Keep me signed in</span>
        </label>
        @if (config('services.recaptcha.site_key'))
          <div class="field">
            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}" style="transform: scale(0.92); transform-origin: left center;"></div>
          </div>
        @endif
        <button type="submit" class="btn btn-primary auth-submit" data-i18n="btn_signin">Sign in</button>
      </form>

      <p class="auth-foot"><a href="{{ route('signup') }}" data-i18n="auth_foot_signup">New to GardaCyber? Create an account</a></p>
    </div>
  </div>
</section>

@if (config('services.recaptcha.site_key'))
@push('scripts')
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endpush
@endif
@endsection
