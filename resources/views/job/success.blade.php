@extends('layouts.app')

@section('title', 'Application Submitted — Library Management System')

@section('content')

<div class="row justify-content-center mt-4">
    <div class="col-lg-5 col-md-7 text-center">

        <div class="card form-card border-0">
            <div class="card-body p-5">

                {{-- Success icon --}}
                <div class="mb-4">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                         style="width:80px;height:80px;background:#D1FAE5;">
                        <i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem;"></i>
                    </div>
                </div>

                <h4 class="fw-bold text-dark mb-2">Application Submitted!</h4>
                <p class="text-muted mb-1">Your job application has been saved successfully.</p>

                @if(session('job_id'))
                    <p class="text-muted small">
                        Job ID: <strong class="text-dark">{{ session('job_id') }}</strong>
                    </p>
                @endif

                <hr class="my-4">

                <a href="{{ route('job.create') }}" class="btn btn-primary btn-submit text-white">
                    <i class="bi bi-plus-circle me-1"></i>Submit Another Application
                </a>
            </div>
        </div>

    </div>
</div>

@endsection
