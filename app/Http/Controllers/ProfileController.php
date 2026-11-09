<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_profile_photo' => ['nullable', 'boolean'],
            'sms_notifications_enabled' => ['nullable', 'boolean'],
            'current_password' => ['required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);
        if ($request->boolean('sms_notifications_enabled')) {
            $request->validate([
                'phone' => ['required', 'regex:/^\+[1-9]\d{7,14}$/'],
            ], [
                'phone.regex' => 'Use international E.164 format for SMS, for example +923001234567.',
            ]);
        }

        $photoPath = $request->file('profile_photo')?->storePublicly('profile-photos', 'public');
        if ($request->hasFile('profile_photo') && ! $photoPath) {
            return back()->withErrors(['profile_photo' => 'The profile photo could not be saved. Please try again.'])->withInput();
        }

        $removePhoto = $request->boolean('remove_profile_photo');
        unset($validated['profile_photo'], $validated['remove_profile_photo']);
        unset($validated['current_password']);
        $validated['sms_notifications_enabled'] = $request->boolean('sms_notifications_enabled');
        if ($photoPath || $removePhoto) {
            $validated['profile_photo_path'] = $photoPath;
        }

        $oldPhotoPath = $user->profile_photo_path;
        $user->update($validated);

        if (($photoPath || $removePhoto) && $oldPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return redirect()->route('profile.edit')->with('status', 'Profile updated.');
    }
}
