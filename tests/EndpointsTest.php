<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class EndpointsTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $sql = file_get_contents(__DIR__ . '/../database_dump.sql');
        $this->pdo->exec($sql);
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
}
