@extends('layouts.app')

@section('title', 'Sign In — Library Management System')

@section('content')
<div class="row justify-content-center mt-4">
    <div class="col-lg-5 col-md-7">
        <div class="card form-card border-0">
            <div class="card-header">
                <h5 class="text-white mb-0 fw-semibold">Sign in to the LMS</h5>
            </div>
            <div class="card-body p-4">
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('login.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" required autofocus autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input id="password" name="password" type="password" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="mb-3 text-end">
                        <a href="{{ route('password.request') }}">Forgot your password?</a>
                    </div>
                    <div class="form-check mb-4">
                        <input id="remember" name="remember" type="checkbox" class="form-check-input" value="1">
                        <label for="remember" class="form-check-label">Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-submit text-white w-100">Sign In</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
