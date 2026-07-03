<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_storage_route_serves_files_from_public_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('employees/test.webp', 'fake-image-content');

        $response = $this->get('/storage/employees/test.webp');

        $response->assertOk();
    }

    public function test_public_storage_route_rejects_path_traversal(): void
    {
        Storage::fake('public');

        $this->get('/storage/../.env')->assertNotFound();
    }

    public function test_public_storage_route_returns_not_found_for_missing_files(): void
    {
        Storage::fake('public');

        $this->get('/storage/employees/missing.webp')->assertNotFound();
    }
}
