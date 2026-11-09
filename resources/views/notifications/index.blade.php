@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Notifications</h4>
        <p class="text-muted mb-0">Reservation and fine updates for your library account.</p>
    </div>
    @if (auth()->user()->unreadNotifications()->exists())
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-outline-primary">Mark all as read</button>
        </form>
    @endif
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="card form-card border-0">
    <div class="list-group list-group-flush">
        @forelse ($notifications as $notification)
            <div class="list-group-item p-4 {{ $notification->read_at ? '' : 'bg-light' }}">
                <div class="d-flex flex-wrap justify-content-between gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <strong>{{ $notification->data['title'] ?? 'Library update' }}</strong>
                            @unless ($notification->read_at)<span class="badge text-bg-primary">New</span>@endunless
                        </div>
                        <p class="mb-1">{{ $notification->data['message'] ?? '' }}</p>
                        <small class="text-muted">{{ $notification->created_at->format('Y-m-d H:i') }}</small>
                    </div>
                    @unless ($notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-sm btn-outline-primary">View and mark read</button>
                        </form>
                    @else
                        <a href="{{ $notification->data['url'] ?? route('notifications.index') }}" class="btn btn-sm btn-outline-secondary align-self-center">View</a>
                    @endunless
                </div>
            </div>
        @empty
            <div class="text-center text-muted py-5">You have no notifications.</div>
        @endforelse
    </div>
    <div class="card-body">{{ $notifications->links() }}</div>
</div>
@endsection
