<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileSignaturePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_drawn_signature_is_available_when_the_setup_page_renders(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'user_id' => 'MCR-001',
            'role' => User::ROLE_HEAD_OFFICE,
            'status' => User::STATUS_ACTIVE,
        ]);
        $path = "private/signatures/{$user->id}/signature.png";
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9pdWcAAAAASUVORK5CYII=', true);

        Storage::disk('local')->put($path, $image);
        $user->update([
            'signature_style' => 'drawn',
            'signature_image_path' => $path,
        ]);

        $this->actingAs($user)
            ->get(route('profile.signature.edit'))
            ->assertOk()
            ->assertSee('data-signature-canvas-preview', false)
            ->assertSee('data:image/png;base64,'.base64_encode($image), false);
    }

    public function test_large_drawn_signature_uses_the_private_image_route(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'user_id' => 'MCR-001',
            'role' => User::ROLE_HEAD_OFFICE,
            'status' => User::STATUS_ACTIVE,
        ]);
        $path = "private/signatures/{$user->id}/signature.png";

        Storage::disk('local')->put($path, str_repeat('x', 256 * 1024 + 1));
        $user->update([
            'signature_style' => 'drawn',
            'signature_image_path' => $path,
        ]);

        $this->actingAs($user)
            ->get(route('profile.signature.edit'))
            ->assertOk()
            ->assertSee('data-signature-canvas-preview', false)
            ->assertDontSee('data:image/png;base64,', false)
            ->assertSee(route('profile.signature.image.show', $user), false);
    }
}
