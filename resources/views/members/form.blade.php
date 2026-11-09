@extends('layouts.app')

@section('title', ($member->exists ? 'Edit Member' : 'Add Member').' — Library Management System')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="mb-4">
            <h4 class="fw-bold text-dark mb-1">{{ $member->exists ? 'Edit Member' : 'Register Member' }}</h4>
            <p class="text-muted mb-0">Manage member contact details and membership information.</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="card form-card border-0">
            <div class="card-body p-4">
                <form method="POST" action="{{ $member->exists ? route('members.update', $member) : route('members.store') }}">
                    @csrf
                    @if ($member->exists)
                        @method('PUT')
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name *</label>
                            <input id="name" name="name" class="form-control" value="{{ old('name', $member->name) }}" maxlength="100" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email *</label>
                            <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $member->email) }}" required>
                        </div>
                        @unless ($member->exists)
                            <div class="col-md-6">
                                <label for="password" class="form-label">Initial Password *</label>
                                <input id="password" name="password" type="password" class="form-control" minlength="8" required autocomplete="new-password">
                                <div class="form-text">Share the initial password securely with the member.</div>
                            </div>
                        @endunless
                        <div class="col-md-6">
                            <label for="cnic" class="form-label">CNIC / ID *</label>
                            <input id="cnic" name="cnic" class="form-control" value="{{ old('cnic', $member->cnic) }}" maxlength="30" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Contact Number *</label>
                            <input id="phone" name="phone" type="tel" class="form-control" value="{{ old('phone', $member->phone) }}" maxlength="30" required>
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label">Address</label>
                            <input id="address" name="address" class="form-control" value="{{ old('address', $member->address) }}" maxlength="255">
                        </div>
                        <div class="col-md-4">
                            <label for="membership_type" class="form-label">Membership Type *</label>
                            <select id="membership_type" name="membership_type" class="form-select" required>
                                @foreach (['Student', 'Faculty', 'Public'] as $type)
                                    <option value="{{ $type }}" @selected(old('membership_type', $member->membership_type) === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="membership_status" class="form-label">Status *</label>
                            <select id="membership_status" name="membership_status" class="form-select" required>
                                @foreach (['active', 'suspended'] as $status)
                                    <option value="{{ $status }}" @selected(old('membership_status', $member->membership_status) === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="membership_expires_at" class="form-label">Membership Expires *</label>
                            <input id="membership_expires_at" name="membership_expires_at" type="date" class="form-control" value="{{ old('membership_expires_at', $member->membership_expires_at?->format('Y-m-d')) }}" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route('members.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-submit text-white">{{ $member->exists ? 'Save Changes' : 'Create Member' }}</button>
                    </div>
                </form>

                @if ($member->exists)
                    <hr class="my-4">
                    <h5 class="fw-semibold">Renew Membership</h5>
                    <form action="{{ route('members.membership.update', $member) }}" method="POST" class="row g-2 align-items-end">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="action" value="renew">
                        <div class="col-md-6">
                            <label for="renew_expires_at" class="form-label">New Expiry Date</label>
                            <input id="renew_expires_at" name="membership_expires_at" type="date" class="form-control" min="{{ now()->addDay()->toDateString() }}" required>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-outline-primary">Renew and Activate</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
