@extends('layouts.app')

@section('title', 'Reset Password — Library Management System')

@section('content')
<div class="row justify-content-center mt-4">
    <div class="col-lg-5 col-md-7">
        <div class="card form-card border-0">
            <div class="card-header">
                <h5 class="text-white mb-0 fw-semibold">Reset your password</h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted">Enter your account email and we will send a password reset link if the account exists.</p>
                @if (session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                @endif
                <form action="{{ route('password.email') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" required autofocus autocomplete="email">
                    </div>
                    <button type="submit" class="btn btn-primary btn-submit text-white w-100">Email Password Reset Link</button>
                </form>
                <a href="{{ route('login') }}" class="d-inline-block mt-3">Back to sign in</a>
            </div>
        </div>
    </div>
</div>
@endsection
