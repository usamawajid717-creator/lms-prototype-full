@extends('layouts.app')

@section('title', 'Issue a Book')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card form-card border-0">
            <div class="card-header text-white"><h4 class="mb-0">Issue a Book</h4></div>
            <div class="card-body p-4">
                <p class="text-muted">Enter the member's library card and the available copy's barcode. The loan period is 14 days.</p>
                @if ($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                <form method="POST" action="{{ route('loans.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="member_card" class="form-label">Member library card</label>
                        <input id="member_card" name="member_card" class="form-control" value="{{ old('member_card') }}" required autofocus>
                        @error('member_card')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4">
                        <label for="barcode" class="form-label">Book-copy barcode</label>
                        <input id="barcode" name="barcode" class="form-control" value="{{ old('barcode') }}" required>
                        @error('barcode')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary btn-submit text-white" type="submit">Issue Book</button>
                        <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
