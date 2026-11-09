@extends('layouts.app')

@section('title', 'Job Application Form — Library Management System')

@section('content')

<div class="row justify-content-center">
    <div class="col-lg-7 col-md-9">

        {{-- Page heading --}}
        <div class="text-center mb-4">
            <h4 class="fw-bold text-dark">Job Application Portal</h4>
            <p class="text-muted small mb-0">Fill in the form below to apply for a job online</p>
        </div>

        {{-- Main form card --}}
        <div class="card form-card">
            <div class="card-header">
                <h5 class="text-white mb-0 fw-semibold">
                    <i class="bi bi-file-earmark-person me-2"></i>Application Form
                </h5>
            </div>

            <div class="card-body p-4">

                {{-- Validation errors --}}
                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong><i class="bi bi-exclamation-triangle me-1"></i>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                {{-- Form --}}
                <form action="{{ route('job.store') }}" method="POST" id="jobForm">
                    @csrf

                    {{-- Row 1: User Name + Contact Number --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="User_Name" class="form-label">
                                User Name <span class="required-star">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control @error('User_Name') is-invalid @enderror"
                                id="User_Name"
                                name="User_Name"
                                placeholder="e.g. Ali Raza"
                                value="{{ old('User_Name') }}"
                                maxlength="100"
                            >
                            @error('User_Name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="User_Contact_Number" class="form-label">
                                Contact Number <span class="required-star">*</span>
                            </label>
                            <input
                                type="tel"
                                class="form-control @error('User_Contact_Number') is-invalid @enderror"
                                id="User_Contact_Number"
                                name="User_Contact_Number"
                                placeholder="e.g. 0300-1234567"
                                value="{{ old('User_Contact_Number') }}"
                                maxlength="30"
                            >
                            @error('User_Contact_Number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Row 2: Job ID + Job Title --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="Job_ID" class="form-label">
                                Job ID <span class="required-star">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control @error('Job_ID') is-invalid @enderror"
                                id="Job_ID"
                                name="Job_ID"
                                placeholder="e.g. JOB-001"
                                value="{{ old('Job_ID') }}"
                                maxlength="50"
                            >
                            @error('Job_ID')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="Job_Title" class="form-label">
                                Job Title <span class="required-star">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control @error('Job_Title') is-invalid @enderror"
                                id="Job_Title"
                                name="Job_Title"
                                placeholder="e.g. Software Engineer"
                                value="{{ old('Job_Title') }}"
                                maxlength="50"
                            >
                            @error('Job_Title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Row 2: Company Name + Job Location --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="Company_Name" class="form-label">
                                Company Name <span class="required-star">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control @error('Company_Name') is-invalid @enderror"
                                id="Company_Name"
                                name="Company_Name"
                                placeholder="e.g. Virtual University"
                                value="{{ old('Company_Name') }}"
                                maxlength="50"
                            >
                            @error('Company_Name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="Job_Location" class="form-label">
                                Job Location <span class="required-star">*</span>
                            </label>
                            <select
                                class="form-select @error('Job_Location') is-invalid @enderror"
                                id="Job_Location"
                                name="Job_Location"
                            >
                                <option value="" disabled {{ old('Job_Location') ? '' : 'selected' }}>
                                    — Select City —
                                </option>
                                @foreach ($locations as $city)
                                    <option value="{{ $city }}" {{ old('Job_Location') == $city ? 'selected' : '' }}>
                                        {{ $city }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Job_Location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Row 3: Gender (radio buttons) --}}
                    <div class="mb-3">
                        <label class="form-label">
                            Applicant Gender <span class="required-star">*</span>
                        </label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input
                                    class="form-check-input @error('Applicant_Gender') is-invalid @enderror"
                                    type="radio"
                                    name="Applicant_Gender"
                                    id="genderMale"
                                    value="Male"
                                    {{ old('Applicant_Gender') == 'Male' ? 'checked' : '' }}
                                >
                                <label class="form-check-label" for="genderMale">
                                    <i class="bi bi-gender-male text-primary me-1"></i>Male
                                </label>
                            </div>
                            <div class="form-check">
                                <input
                                    class="form-check-input @error('Applicant_Gender') is-invalid @enderror"
                                    type="radio"
                                    name="Applicant_Gender"
                                    id="genderFemale"
                                    value="Female"
                                    {{ old('Applicant_Gender') == 'Female' ? 'checked' : '' }}
                                >
                                <label class="form-check-label" for="genderFemale">
                                    <i class="bi bi-gender-female text-danger me-1"></i>Female
                                </label>
                            </div>
                        </div>
                        @error('Applicant_Gender')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Row 4: Expected Salary + Terminal Degree --}}
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="Exp_Salary" class="form-label">
                                Expected Salary <span class="required-star">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">PKR</span>
                                <input
                                    type="text"
                                    class="form-control @error('Exp_Salary') is-invalid @enderror"
                                    id="Exp_Salary"
                                    name="Exp_Salary"
                                    placeholder="e.g. 50,000"
                                    value="{{ old('Exp_Salary') }}"
                                    maxlength="50"
                                >
                                @error('Exp_Salary')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="Terminal_Degree" class="form-label">
                                Terminal Degree <span class="required-label">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control @error('Terminal_Degree') is-invalid @enderror"
                                id="Terminal_Degree"
                                name="Terminal_Degree"
                                placeholder="e.g. BS Computer Science"
                                value="{{ old('Terminal_Degree') }}"
                                maxlength="50"
                            >
                            @error('Terminal_Degree')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Divider --}}
                    <hr class="my-3">

                    {{-- Buttons: Submit + Reset --}}
                    <div class="d-flex gap-3 justify-content-end">
                        {{-- Reset Button --}}
                        <button type="reset" class="btn btn-outline-secondary btn-reset" id="resetBtn">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                        </button>

                        {{-- Submit Button --}}
                        <button type="submit" class="btn btn-primary btn-submit text-white">
                            <i class="bi bi-send me-1"></i>Submit Application
                        </button>
                    </div>

                </form>
            </div>{{-- card-body --}}
        </div>{{-- card --}}

        {{-- Info note --}}
        <p class="text-muted small text-center mt-3">
            <i class="bi bi-info-circle me-1"></i>
            Fields marked with <span class="required-star">*</span> are required.
            Data is saved to the <strong>Library</strong> database.
        </p>

    </div>
</div>

@endsection

@section('scripts')
<script>
    // Confirm reset action
    document.getElementById('resetBtn').addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to clear all fields?')) {
            e.preventDefault();
        }
    });
</script>
@endsection
