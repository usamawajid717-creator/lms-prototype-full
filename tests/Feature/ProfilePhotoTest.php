<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_and_remove_own_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['phone' => '555-0100']);
        $photo = UploadedFile::fake()->createWithContent(
            'profile.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l2cAAAAASUVORK5CYII='),
        );

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'profile_photo' => $photo,
        ])->assertRedirect(route('profile.edit'));

        $photoPath = $user->fresh()->profile_photo_path;
        $this->assertNotNull($photoPath);
        Storage::disk('public')->assertExists($photoPath);
        $this->get(route('profile.edit'))->assertOk()->assertSee('Profile photo for '.$user->name);

        $this->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'remove_profile_photo' => '1',
        ])->assertRedirect(route('profile.edit'));

        $this->assertNull($user->fresh()->profile_photo_path);
        Storage::disk('public')->assertMissing($photoPath);
    }

    public function test_profile_photo_must_be_an_allowed_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['phone' => '555-0100']);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'profile_photo' => UploadedFile::fake()->create('profile.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('profile_photo');

        $this->assertNull($user->fresh()->profile_photo_path);
    }
}
