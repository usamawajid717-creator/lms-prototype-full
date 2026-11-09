@extends('layouts.app')

@section('title', 'Library Policies')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <h4 class="fw-bold text-dark mb-1">Library Policies</h4>
        <p class="text-muted mb-4">Set circulation rules. Changes apply to new loans, renewals, reservations, fines, and upcoming reminders; existing due dates and holds are not changed.</p>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="card form-card border-0">
            <div class="card-body p-4">
                <form action="{{ route('library-policies.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="loan_period_days" class="form-label">Loan period (days)</label>
                            <input id="loan_period_days" name="loan_period_days" type="number" min="1" max="90" class="form-control" value="{{ old('loan_period_days', $policy->loan_period_days) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="max_active_loans" class="form-label">Maximum active loans per member</label>
                            <input id="max_active_loans" name="max_active_loans" type="number" min="1" max="20" class="form-control" value="{{ old('max_active_loans', $policy->max_active_loans) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="max_renewals" class="form-label">Maximum renewals per loan</label>
                            <input id="max_renewals" name="max_renewals" type="number" min="0" max="5" class="form-control" value="{{ old('max_renewals', $policy->max_renewals) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="fine_per_day_pkr" class="form-label">Overdue fine per day (PKR)</label>
                            <input id="fine_per_day_pkr" name="fine_per_day_pkr" type="number" min="0" max="1000000" class="form-control" value="{{ old('fine_per_day_pkr', $policy->fine_per_day_pkr) }}" required>
                            <div class="form-text">Set to 0 to disable overdue fines on future returns.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="issue_fine_limit_pkr" class="form-label">Outstanding fine limit for issuing (PKR)</label>
                            <input id="issue_fine_limit_pkr" name="issue_fine_limit_pkr" type="number" min="0" max="1000000000" class="form-control" value="{{ old('issue_fine_limit_pkr', $policy->issue_fine_limit_pkr) }}" required>
                            <div class="form-text">Members above this unpaid balance are blocked from new loans unless an administrator records an override reason. Zero blocks any positive unpaid balance.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="max_active_reservations" class="form-label">Maximum active reservations per member</label>
                            <input id="max_active_reservations" name="max_active_reservations" type="number" min="1" max="20" class="form-control" value="{{ old('max_active_reservations', $policy->max_active_reservations) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="reservation_hold_days" class="form-label">Reservation pickup hold (days)</label>
                            <input id="reservation_hold_days" name="reservation_hold_days" type="number" min="1" max="14" class="form-control" value="{{ old('reservation_hold_days', $policy->reservation_hold_days) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="due_reminder_days" class="form-label">Due-soon reminder lead time (days)</label>
                            <input id="due_reminder_days" name="due_reminder_days" type="number" min="1" max="30" class="form-control" value="{{ old('due_reminder_days', $policy->due_reminder_days) }}" required>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary btn-submit text-white">Save Policies</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
