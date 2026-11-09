@extends('layouts.app')

@section('title', 'Audit Log')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold text-dark mb-1">Audit Log</h4>
    <p class="text-muted mb-0">Successful changes made by signed-in users. Sensitive form values are not stored.</p>
</div>

<div class="card form-card border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date and time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Record</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($auditLogs as $auditLog)
                    <tr>
                        <td>{{ $auditLog->created_at->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $auditLog->user?->name ?? 'Deleted user' }}</td>
                        <td><span class="badge text-bg-light">{{ $auditLog->event }}</span></td>
                        <td>
                            @if ($auditLog->subject_type)
                                {{ class_basename($auditLog->subject_type) }} #{{ $auditLog->subject_id }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No changes have been recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $auditLogs->links() }}</div>
</div>
@endsection
