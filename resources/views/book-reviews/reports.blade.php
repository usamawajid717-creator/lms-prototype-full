@extends('layouts.app')

@section('title', 'Review Moderation')

@section('content')
<div class="mb-4"><h4 class="fw-bold mb-1">Review Moderation</h4><p class="text-muted mb-0">Review member reports and hide or restore reviews as appropriate.</p></div>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card form-card border-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Reported review</th><th>Report</th><th>Reported by</th><th>Status / action</th></tr></thead>
        <tbody>
        @forelse ($reports as $report)
            <tr>
                <td>
                    <a href="{{ route('catalog.show', $report->review->book) }}">{{ $report->review->book->title }}</a>
                    <div><strong>{{ $report->review->member->name }}</strong> · {{ $report->review->rating }}/5</div>
                    @if ($report->review->comment)<div class="small text-muted" style="white-space: pre-line">{{ $report->review->comment }}</div>@endif
                    @if ($report->review->hidden_at)<span class="badge text-bg-secondary mt-1">Hidden</span>@endif
                </td>
                <td>{{ $report->reason }}</td>
                <td>{{ $report->reporter->name }}<div class="small text-muted">{{ $report->created_at->format('M j, Y') }}</div></td>
                <td>
                    @if (! $report->resolved_at)
                        <form method="POST" action="{{ route('book-reviews.reports.resolve', $report) }}" class="d-flex flex-wrap gap-2">
                            @csrf @method('PATCH')
                            <button name="action" value="hide" class="btn btn-sm btn-outline-danger" type="submit">Hide review</button>
                            <button name="action" value="keep" class="btn btn-sm btn-outline-primary" type="submit">Keep visible</button>
                        </form>
                    @else
                        <span class="badge text-bg-success">Resolved</span>
                        <div class="small text-muted">{{ $report->resolver?->name }} · {{ $report->resolved_at->format('M j, Y') }}</div>
                        <form method="POST" action="{{ route('book-reviews.moderate', $report->review) }}" class="mt-2">
                            @csrf @method('PATCH')
                            @if ($report->review->hidden_at)
                                <input type="hidden" name="action" value="restore">
                                <button class="btn btn-sm btn-outline-success" type="submit">Restore review</button>
                            @else
                                <input type="hidden" name="action" value="hide">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Hide review</button>
                            @endif
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted py-4">No review reports have been filed.</td></tr>
        @endforelse
        </tbody>
    </table>
</div><div class="card-body">{{ $reports->links() }}</div></div>
@endsection
