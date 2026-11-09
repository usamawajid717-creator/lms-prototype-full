@extends('layouts.app')

@section('title', 'Reservation Management')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold text-dark mb-1">Reservation Management</h4>
    <p class="text-muted mb-0">Review waitlists and copies currently held for pickup.</p>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card form-card border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Member</th><th>Book</th><th>Position / Copy</th><th>Status</th><th>Pickup deadline</th><th>Action</th></tr></thead>
            <tbody>
                @forelse ($reservations as $reservation)
                    <tr>
                        <td>{{ $reservation->member->name }}<br><span class="text-muted small">{{ $reservation->member->library_card_number }}</span></td>
                        <td>{{ $reservation->book->title }}</td>
                        <td>
                            @if ($reservation->status === 'waiting')
                                {{ $queuePositions[$reservation->id] ?? '—' }}
                            @else
                                {{ $reservation->copy?->barcode ?: '—' }}
                            @endif
                        </td>
                        <td><span class="badge {{ $reservation->status === 'ready' ? 'text-bg-success' : ($reservation->status === 'waiting' ? 'text-bg-primary' : 'text-bg-secondary') }}">{{ ucfirst($reservation->status) }}</span></td>
                        <td>{{ $reservation->hold_expires_at?->format('Y-m-d H:i') ?: '—' }}</td>
                        <td>
                            @if (in_array($reservation->status, ['waiting', 'ready'], true))
                                <form method="POST" action="{{ route('reservations.cancel', $reservation) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                                </form>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No reservations found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $reservations->links() }}</div>
</div>
@endsection
