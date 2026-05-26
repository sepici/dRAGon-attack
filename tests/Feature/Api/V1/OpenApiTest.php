<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_spec_is_publicly_accessible_without_a_token(): void
    {
        // ChatGPT Custom GPTs fetch the spec unauthenticated on configure.
        $response = $this->getJson('/api/v1/openapi.json');
        $response->assertOk();
    }

    public function test_spec_has_required_top_level_fields(): void
    {
        $spec = $this->getJson('/api/v1/openapi.json')->json();

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('components', $spec);
        $this->assertSame('dRAGonattack Tracker API', $spec['info']['title']);
    }

    public function test_spec_declares_bearer_auth(): void
    {
        $spec = $this->getJson('/api/v1/openapi.json')->json();

        $this->assertArrayHasKey(
            'bearerAuth',
            $spec['components']['securitySchemes'] ?? [],
        );
        $this->assertSame('http', $spec['components']['securitySchemes']['bearerAuth']['type']);
        $this->assertSame('bearer', $spec['components']['securitySchemes']['bearerAuth']['scheme']);
    }

    public function test_spec_documents_every_endpoint_we_publish(): void
    {
        $spec = $this->getJson('/api/v1/openapi.json')->json();
        $paths = array_keys($spec['paths']);

        $expected = [
            '/me',
            '/clients',
            '/clients/{id}',
            '/projects',
            '/projects/{id}',
            '/deliverables',
            '/deliverables/{id}',
            '/milestones',
            '/milestones/{id}',
            '/plans/weekly',
            '/plans/monthly',
            '/plans/quarterly',
            '/plan-items',
            '/plan-items/{id}',
            '/time-logs',
            '/time-logs/{id}',
        ];

        foreach ($expected as $route) {
            $this->assertContains($route, $paths, "Missing /api/v1{$route} from OpenAPI spec.");
        }
    }

    public function test_time_logs_post_documents_the_fuzzy_name_input(): void
    {
        $spec = $this->getJson('/api/v1/openapi.json')->json();
        $create = $spec['components']['schemas']['TimeLogCreate'];

        $this->assertArrayHasKey('deliverable_name', $create['properties']);
        $this->assertStringContainsString(
            'LIKE',
            $create['properties']['deliverable_name']['description'],
        );
    }

    public function test_spec_uses_data_envelope_on_resource_responses(): void
    {
        $spec = $this->getJson('/api/v1/openapi.json')->json();
        $meResponse = $spec['paths']['/me']['get']['responses']['200']
            ['content']['application/json']['schema'];

        $this->assertArrayHasKey('data', $meResponse['properties']);
    }
}
