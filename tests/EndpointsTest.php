<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class EndpointsTest extends TestCase
{
    private PDO $pdo;
    private string $resultsPath;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $sql = file_get_contents(__DIR__ . '/../database_dump.sql');
        $this->pdo->exec($sql);

        $this->resultsPath = sys_get_temp_dir() . '/esiti_test_' . uniqid('', true) . '.json';
        putenv('RESULTS_JSON_PATH=' . $this->resultsPath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->resultsPath)) {
            unlink($this->resultsPath);
        }

        putenv('RESULTS_JSON_PATH');
    }

    public function testQ1ReturnsJsonAndData(): void
    {
        $app = createApp($this->pdo);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/q1?page=1&page_size=10');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['query']);
        $this->assertNotEmpty($payload['data']);
    }

    public function testEsitiEndpointReturnsDataAndSavesJsonFile(): void
    {
        $app = createApp($this->pdo);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/esiti');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);
        $this->assertSame('Esiti generati e salvati su file JSON', $payload['message']);
        $this->assertFileExists($this->resultsPath);

        $saved = json_decode((string) file_get_contents($this->resultsPath), true);
        $this->assertIsArray($saved);
        $this->assertArrayHasKey('results', $saved);
        $this->assertArrayHasKey('q10', $saved['results']);
    }
}
