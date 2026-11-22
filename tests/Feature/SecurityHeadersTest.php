<?php

namespace Tests\Feature;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_baseline_security_headers_are_added_to_responses(): void
    {
        $response = $this->get('/up');

        $response->assertOk();

        $this->assertSecurityHeadersPresent($response);
    }

    public function test_security_headers_are_added_to_not_found_responses(): void
    {
        $response = $this->get('/route-yang-tidak-ada');

        $response->assertNotFound();

        $this->assertSecurityHeadersPresent($response);
    }

    private function assertSecurityHeadersPresent(TestResponse $response): void
    {
        $response
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
    }
}
