<?php

namespace App\Http\Controllers;

use App\Models\LibraryPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LibraryPolicyController extends Controller
{
    public function edit(): View
    {
        return view('admin.library-policies', ['policy' => LibraryPolicy::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loan_period_days' => ['required', 'integer', 'between:1,90'],
            'max_active_loans' => ['required', 'integer', 'between:1,20'],
            'max_renewals' => ['required', 'integer', 'between:0,5'],
            'fine_per_day_pkr' => ['required', 'integer', 'between:0,1000000'],
            'issue_fine_limit_pkr' => ['required', 'integer', 'between:0,1000000000'],
            'max_active_reservations' => ['required', 'integer', 'between:1,20'],
            'reservation_hold_days' => ['required', 'integer', 'between:1,14'],
            'due_reminder_days' => ['required', 'integer', 'between:1,30'],
        ]);

        LibraryPolicy::current()->update($validated);

        return redirect()->route('library-policies.edit')->with('status', 'Library policies updated.');
    }
}
