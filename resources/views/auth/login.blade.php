@extends('layouts.marketing')

@section('title', 'Sign in · GardaCyber')

@section('content')
<section class="auth-section">
  <div class="container">
    <div class="auth-card">
      <h1 class="auth-title">Sign in</h1>
      <p class="auth-sub">Back to your scans and findings.</p>

      <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf
        <div class="field">
          <label for="email">Email</label>
          <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" class="input" type="password" name="password" required autocomplete="current-password" />
        </div>
        <label class="chk-row">
          <input type="checkbox" name="remember" value="1" @checked(old('remember')) />
          <span>Keep me signed in</span>
        </label>
        @if (config('services.recaptcha.site_key'))
          <div class="field">
            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}" style="transform: scale(0.92); transform-origin: left center;"></div>
          </div>
        @endif
        <button type="submit" class="btn btn-primary auth-submit">Sign in</button>
      </form>

      <p class="auth-foot">New to GardaCyber? <a href="{{ route('signup') }}">Create an account</a></p>
    </div>
  </div>
</section>

@if (config('services.recaptcha.site_key'))
@push('scripts')
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endpush
@endif
@endsection
