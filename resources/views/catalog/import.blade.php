@extends('layouts.app')

@section('title', 'Import Catalog CSV')

@section('content')
<div class="row justify-content-center"><div class="col-lg-8">
    <div class="mb-4"><h4 class="fw-bold mb-1">Bulk Catalog Import</h4><p class="text-muted mb-0">Upload a CSV file to add multiple books and create their physical copy records.</p></div>
    @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="card form-card border-0"><div class="card-body p-4">
        <p>Required columns: <code>isbn,title,authors,category,total_copies</code>. Optional columns: <code>publisher,publication_year,edition</code>. Category names must match an active catalog category.</p>
        <p class="small text-muted">Imports are all-or-nothing. Maximum 500 rows, 2 MB, and 10,000 copies per import. Duplicate ISBNs and invalid categories are rejected.</p>
        <a class="btn btn-outline-secondary mb-3" href="{{ route('catalog.admin.import-template') }}">Download CSV template</a>
        <form method="POST" enctype="multipart/form-data" action="{{ route('catalog.admin.import') }}">
            @csrf
            <label for="file" class="form-label">CSV file *</label>
            <input id="file" name="file" type="file" class="form-control mb-3" accept=".csv,text/csv" required>
            <div class="d-flex justify-content-end gap-2"><a href="{{ route('catalog.admin.index') }}" class="btn btn-outline-secondary">Cancel</a><button type="submit" class="btn btn-primary btn-submit text-white">Validate and import</button></div>
        </form>
    </div></div>
</div></div>
@endsection
