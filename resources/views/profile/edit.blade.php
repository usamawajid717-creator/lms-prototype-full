@extends('layouts.app')

@section('title', 'My Profile — Library Management System')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <h4 class="fw-bold text-dark mb-3">My Profile</h4>
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
                @if ($user->profile_photo_path)
                    <img src="{{ Storage::disk('public')->url($user->profile_photo_path) }}" alt="Profile photo for {{ $user->name }}" class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                @endif
                @if ($user->role === 'member')
                    <p class="mb-1"><strong>Library Card:</strong> {{ $user->library_card_number }}</p>
                    <p class="mb-4"><strong>Membership:</strong> {{ $user->membership_type }} — {{ ucfirst($user->membership_status) }}</p>
                @endif
                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input id="name" name="name" class="form-control" value="{{ old('name', $user->name) }}" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Contact Number</label>
                        <input id="phone" name="phone" type="tel" class="form-control" value="{{ old('phone', $user->phone) }}" maxlength="30" required>
                    </div>
                    <div class="mb-4">
                        <label for="address" class="form-label">Address</label>
                        <input id="address" name="address" class="form-control" value="{{ old('address', $user->address) }}" maxlength="255">
                    </div>
                    <div class="mb-4">
                        <label for="profile_photo" class="form-label">Profile photo</label>
                        <input id="profile_photo" name="profile_photo" type="file" accept=".jpg,.jpeg,.png,.webp" class="form-control">
                        <div class="form-text">Optional. JPG, PNG, or WebP; maximum 5 MB.</div>
                        @if ($user->profile_photo_path)
                            <label class="form-check-label mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_profile_photo" value="1">
                                Remove current photo
                            </label>
                        @endif
                    </div>
                    <div class="form-check mb-4">
                        <input id="sms_notifications_enabled" name="sms_notifications_enabled" type="checkbox" value="1" class="form-check-input" @checked(old('sms_notifications_enabled', $user->sms_notifications_enabled))>
                        <label for="sms_notifications_enabled" class="form-check-label">Receive library alerts by SMS (requires phone number in E.164 format, e.g. +923001234567)</label>
                    </div>
                    <hr class="my-4">
                    <h5 class="fw-semibold">Change password</h5>
                    <p class="text-muted small">Leave these fields blank to keep your current password.</p>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current password</label>
                        <input id="current_password" name="current_password" type="password" class="form-control" autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">New password</label>
                        <input id="password" name="password" type="password" class="form-control" autocomplete="new-password">
                    </div>
                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-submit text-white">Save Changes</button>
                </form>

                @if (config('services.webpush.enabled') && filled(config('services.webpush.public_key')))
                    <hr class="my-4">
                    <h5 class="fw-semibold">Browser push notifications</h5>
                    <p class="text-muted small">Enable push alerts on this browser. You can turn them off from the browser or this page.</p>
                    <button id="push-subscribe" type="button" class="btn btn-outline-primary">Enable browser alerts</button>
                    <button id="push-unsubscribe" type="button" class="btn btn-outline-secondary d-none">Disable browser alerts</button>
                    <span id="push-status" class="small ms-2" role="status"></span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@if (config('services.webpush.enabled') && filled(config('services.webpush.public_key')))
    @section('scripts')
        <script>
            (() => {
                const publicKey = @json(config('services.webpush.public_key'));
                const subscribeButton = document.getElementById('push-subscribe');
                const unsubscribeButton = document.getElementById('push-unsubscribe');
                const status = document.getElementById('push-status');
                const csrfToken = @json(csrf_token());

                const decodeKey = (key) => {
                    const padding = '='.repeat((4 - key.length % 4) % 4);
                    const base64 = (key + padding).replace(/-/g, '+').replace(/_/g, '/');
                    return Uint8Array.from(atob(base64), (character) => character.charCodeAt(0));
                };

                const updateButtons = async () => {
                    const registration = await navigator.serviceWorker.register('/service-worker.js');
                    const subscription = await registration.pushManager.getSubscription();
                    subscribeButton.classList.toggle('d-none', Boolean(subscription));
                    unsubscribeButton.classList.toggle('d-none', !subscription);
                };

                const postSubscription = async (method, body) => {
                    const response = await fetch(@json(route('push-subscriptions.store')), {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(body),
                    });
                    if (!response.ok) {
                        throw new Error('Could not update browser notification settings.');
                    }
                };

                if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                    subscribeButton.disabled = true;
                    status.textContent = 'Browser push is not supported here.';
                } else {
                    updateButtons().catch(() => {
                        subscribeButton.disabled = true;
                        status.textContent = 'Could not register the notification worker.';
                    });
                }

                subscribeButton.addEventListener('click', async () => {
                    subscribeButton.disabled = true;
                    try {
                        const permission = await Notification.requestPermission();
                        if (permission !== 'granted') {
                            throw new Error('Browser notification permission was not granted.');
                        }
                        const registration = await navigator.serviceWorker.ready;
                        const subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: decodeKey(publicKey),
                        });
                        await postSubscription('POST', subscription.toJSON());
                        status.textContent = 'Browser alerts enabled.';
                        await updateButtons();
                    } catch (error) {
                        status.textContent = error.message;
                    } finally {
                        subscribeButton.disabled = false;
                    }
                });

                unsubscribeButton.addEventListener('click', async () => {
                    unsubscribeButton.disabled = true;
                    try {
                        const registration = await navigator.serviceWorker.ready;
                        const subscription = await registration.pushManager.getSubscription();
                        if (subscription) {
                            await postSubscription('DELETE', { endpoint: subscription.endpoint });
                            await subscription.unsubscribe();
                        }
                        status.textContent = 'Browser alerts disabled.';
                        await updateButtons();
                    } catch (error) {
                        status.textContent = error.message;
                    } finally {
                        unsubscribeButton.disabled = false;
                    }
                });
            })();
        </script>
    @endsection
@endif
