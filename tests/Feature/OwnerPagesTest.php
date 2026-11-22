<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DummyPatientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_pages_load_successfully(): void
    {
        $this->seed(DummyPatientSeeder::class);

        $owner = User::where('email', 'owner@rumahterapi.test')->firstOrFail();
        $this->actingAs($owner);

        $urls = [
            '/dashboard',
            '/assessments',
            '/sessions',
            '/bills',
            '/payment-history',
            '/finance/records',
        ];

        foreach ($urls as $url) {
            $response = $this->get($url);
            echo "\nTesting URL: $url -> Status: ".$response->status();
            $response->assertOk();
        }
    }
}
