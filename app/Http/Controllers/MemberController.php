<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $isAdmin = $request->user()->isAdmin();
        $members = User::query()
            ->where('role', 'member')
            ->when($request->filled('search'), function ($query) use ($request, $isAdmin) {
                $search = $request->string('search')->trim()->toString();
                $query->where(function ($query) use ($search, $isAdmin) {
                    $query->where('name', 'like', "%{$search}%");

                    if ($isAdmin) {
                        $query->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('library_card_number', 'like', "%{$search}%");
                    }
                });
            })
            ->when($isAdmin && $request->filled('membership_type'), fn ($query) => $query->where('membership_type', $request->input('membership_type')))
            ->when($isAdmin && $request->filled('membership_status'), function ($query) use ($request) {
                $status = $request->input('membership_status');

                if ($status === 'expired') {
                    $query->where('membership_status', 'active')
                        ->whereDate('membership_expires_at', '<', today());
                } elseif ($status === 'active') {
                    $query->where('membership_status', 'active')
                        ->where(function ($query) {
                            $query->whereNull('membership_expires_at')
                                ->orWhereDate('membership_expires_at', '>=', today());
                        });
                } else {
                    $query->where('membership_status', $status);
                }
            })
            ->when(! $isAdmin, fn ($query) => $query->select(['id', 'name', 'membership_type']))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('members.index', compact('members', 'isAdmin'));
    }

    public function create(): View
    {
        return view('members.form', [
            'member' => new User([
                'membership_type' => 'Student',
                'membership_status' => 'active',
                'membership_expires_at' => now()->addYear()->toDateString(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->memberRules());
        $validated['role'] = 'member';

        $member = DB::transaction(function () use ($validated) {
            $member = User::create($validated);
            $member->update([
                'library_card_number' => 'LMS-'.str_pad((string) $member->id, 8, '0', STR_PAD_LEFT),
            ]);

            return $member;
        });

        return redirect()->route('members.index')
            ->with('status', "Member created. Library card: {$member->library_card_number}");
    }

    public function edit(User $member): View
    {
        abort_unless($member->role === 'member', 404);

        return view('members.form', compact('member'));
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        abort_unless($member->role === 'member', 404);

        $validated = $request->validate($this->memberRules($member));
        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $member->update($validated);

        return redirect()->route('members.index')->with('status', 'Member details updated.');
    }

    public function updateMembership(Request $request, User $member): RedirectResponse
    {
        abort_unless($member->role === 'member', 404);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['activate', 'suspend', 'renew'])],
            'membership_expires_at' => ['required_if:action,renew', 'nullable', 'date', 'after:today'],
        ]);

        if ($validated['action'] === 'renew') {
            $member->update([
                'membership_status' => 'active',
                'membership_expires_at' => $validated['membership_expires_at'],
            ]);
        } else {
            $member->update([
                'membership_status' => $validated['action'] === 'activate' ? 'active' : 'suspended',
            ]);
        }

        return redirect()->route('members.index')->with('status', 'Membership status updated.');
    }

    private function memberRules(?User $member = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($member?->id)],
            'password' => [$member ? 'nullable' : 'required', 'string', 'min:8'],
            'cnic' => ['required', 'string', 'max:30', Rule::unique('users', 'cnic')->ignore($member?->id)],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'membership_type' => ['required', Rule::in(['Student', 'Faculty', 'Public'])],
            'membership_status' => ['required', Rule::in(['active', 'suspended'])],
            'membership_expires_at' => ['required', 'date'],
        ];
    }
}
