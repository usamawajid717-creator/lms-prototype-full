@extends('layouts.app')

@section('title', 'Member Directory — Library Management System')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">{{ $isAdmin ? 'Member Management' : 'Member Directory' }}</h4>
        <p class="text-muted mb-0">{{ $isAdmin ? 'Search, register, and manage library memberships.' : 'Browse member names and membership types.' }}</p>
    </div>
    @if ($isAdmin)
        <a href="{{ route('members.create') }}" class="btn btn-primary btn-submit text-white">
            <i class="bi bi-person-plus me-1"></i>Add Member
        </a>
    @endif
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card form-card border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('members.index') }}" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="search" class="form-label">Search</label>
                <input id="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name{{ $isAdmin ? ', email, or library card' : '' }}">
            </div>
            @if ($isAdmin)
                <div class="col-md-2">
                    <label for="membership_type" class="form-label">Type</label>
                    <select id="membership_type" name="membership_type" class="form-select">
                        <option value="">All types</option>
                        @foreach (['Student', 'Faculty', 'Public'] as $type)
                            <option value="{{ $type }}" @selected(request('membership_type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="membership_status" class="form-label">Status</label>
                    <select id="membership_status" name="membership_status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach (['active', 'suspended', 'expired'] as $status)
                            <option value="{{ $status }}" @selected(request('membership_status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2 d-grid">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </div>
        </form>
    </div>
</div>

<div class="card form-card border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Membership Type</th>
                    @if ($isAdmin)
                        <th>Library Card</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th>Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($members as $member)
                    <tr>
                        <td>{{ $member->name }}</td>
                        <td>{{ $member->membership_type }}</td>
                        @if ($isAdmin)
                            <td>{{ $member->library_card_number }}</td>
                            <td>{{ $member->email }}</td>
                            <td>{{ $member->phone ?: '—' }}</td>
                            <td><span class="badge {{ $member->effective_membership_status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($member->effective_membership_status) }}</span></td>
                            <td>{{ $member->membership_expires_at?->format('Y-m-d') ?: '—' }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('members.edit', $member) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form action="{{ route('members.membership.update', $member) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    @if ($member->membership_status === 'active')
                                        <input type="hidden" name="action" value="suspend">
                                        <button class="btn btn-sm btn-outline-warning" type="submit">Suspend</button>
                                    @else
                                        <input type="hidden" name="action" value="activate">
                                        <button class="btn btn-sm btn-outline-success" type="submit">Activate</button>
                                    @endif
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isAdmin ? 8 : 2 }}" class="text-center text-muted py-4">No members found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $members->links() }}</div>
</div>
@endsection
