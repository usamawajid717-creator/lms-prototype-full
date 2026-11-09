@extends('layouts.app')

@section('title', 'Choose New Password — Library Management System')

@section('content')
<div class="row justify-content-center mt-4">
    <div class="col-lg-5 col-md-7">
        <div class="card form-card border-0">
            <div class="card-header">
                <h5 class="text-white mb-0 fw-semibold">Choose a new password</h5>
            </div>
            <div class="card-body p-4">
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                @endif
                <form action="{{ route('password.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $email) }}" required autocomplete="email">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">New password</label>
                        <input id="password" name="password" type="password" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-submit text-white w-100">Reset Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
