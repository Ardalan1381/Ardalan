<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    private function owner(): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', 'owner')->first()->id,
        ]);
    }

    public function test_owner_can_upload_a_valid_image_to_a_product(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg', 400, 300);

        $response = $this->actingAs($owner)->post(route('admin.products.media.store', $product), ['file' => $file]);

        $response->assertRedirect();
        $this->assertDatabaseCount('media', 1);

        $media = $product->media()->firstOrFail();
        $this->assertTrue($media->is_primary);
        $this->assertSame(400, $media->width);
        $this->assertSame(300, $media->height);
        Storage::disk('public')->assertExists($media->path);
    }

    /**
     * Regression test: the public disk used to build image URLs from
     * APP_URL (e.g. "http://localhost/storage/..."), which silently broke
     * every product/logo image whenever the app was actually served from a
     * different host or port than APP_URL happened to say — a very common
     * self-hosted misconfiguration (this exact bug was reported live: a
     * product photo that uploaded fine simply never displayed). Media::url()
     * must stay host-independent so it works regardless of what APP_URL is
     * set to, or whether it matches the real serving address at all.
     */
    public function test_media_url_is_relative_regardless_of_app_url(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create();
        $this->actingAs($owner)->post(route('admin.products.media.store', $product), [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $media = $product->media()->firstOrFail();

        $this->assertSame('/storage/'.$media->path, $media->url());
    }

    public function test_non_image_file_is_rejected(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create();
        $file = UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf');

        $response = $this->actingAs($owner)->post(route('admin.products.media.store', $product), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_oversized_image_is_rejected(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg')->size(6000);

        $response = $this->actingAs($owner)->post(route('admin.products.media.store', $product), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('media', 0);
    }

    public function test_second_upload_is_not_primary_and_deleting_primary_promotes_it(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create();

        $this->actingAs($owner)->post(route('admin.products.media.store', $product), [
            'file' => UploadedFile::fake()->image('one.jpg'),
        ]);
        $this->actingAs($owner)->post(route('admin.products.media.store', $product), [
            'file' => UploadedFile::fake()->image('two.jpg'),
        ]);

        $product->refresh();
        $first = $product->media()->orderBy('sort_order')->first();
        $second = $product->media()->orderBy('sort_order')->skip(1)->first();

        $this->assertTrue($first->is_primary);
        $this->assertFalse($second->is_primary);

        $this->actingAs($owner)->delete(route('admin.products.media.destroy', [$product, $first]));

        $this->assertTrue($second->fresh()->is_primary);
    }

    public function test_make_primary_endpoint_switches_primary_image(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create();

        $this->actingAs($owner)->post(route('admin.products.media.store', $product), [
            'file' => UploadedFile::fake()->image('one.jpg'),
        ]);
        $this->actingAs($owner)->post(route('admin.products.media.store', $product), [
            'file' => UploadedFile::fake()->image('two.jpg'),
        ]);

        $product->refresh();
        $second = $product->media()->orderBy('sort_order')->skip(1)->first();

        $this->actingAs($owner)->patch(route('admin.products.media.primary', [$product, $second]));

        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame(1, $product->media()->where('is_primary', true)->count());
    }

    public function test_cannot_delete_media_belonging_to_a_different_product_idor(): void
    {
        $owner = $this->owner();
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $this->actingAs($owner)->post(route('admin.products.media.store', $productB), [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ]);
        $media = $productB->media()->firstOrFail();

        $response = $this->actingAs($owner)->delete(route('admin.products.media.destroy', [$productA, $media]));

        $response->assertNotFound();
        $this->assertModelExists($media);
    }

    public function test_waiter_cannot_upload_media(): void
    {
        $waiter = User::factory()->create([
            'role_id' => Role::where('name', 'waiter')->first()->id,
        ]);
        $product = Product::factory()->create();

        $response = $this->actingAs($waiter)->post(route('admin.products.media.store', $product), [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->assertForbidden();
    }
}
