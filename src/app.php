<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Factory\AppFactory;

function getPdoFromEnv(): PDO
{
    $dsn = getenv('DB_DSN') ?: 'sqlite:' . __DIR__ . '/../database.sqlite';
    $user = getenv('DB_USER') ?: null;
    $password = getenv('DB_PASSWORD') ?: null;

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if (str_starts_with($dsn, 'sqlite:')) {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    return $pdo;
}

function jsonResponse(Response $response, array $payload, int $status = 200): Response
{
    $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
}

function getPaginationParams(Request $request): array
{
    $q = $request->getQueryParams();
    $page = isset($q['page']) ? max(1, (int) $q['page']) : 1;
    $pageSize = isset($q['page_size']) ? max(1, min(100, (int) $q['page_size'])) : 50;
    $offset = ($page - 1) * $pageSize;

    return [$page, $pageSize, $offset];
}

function fetchRows(PDO $pdo, string $sql, array $params = [], ?array $pagination = null): array
{
    if ($pagination !== null) {
        [$page, $pageSize, $offset] = $pagination;
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $paramName = ':' . $key;

        if (is_int($value)) {
            $stmt->bindValue($paramName, $value, PDO::PARAM_INT);
            continue;
        }

        if (is_bool($value)) {
            $stmt->bindValue($paramName, $value, PDO::PARAM_BOOL);
            continue;
        }

        if ($value === null) {
            $stmt->bindValue($paramName, null, PDO::PARAM_NULL);
            continue;
        }

        if (is_float($value)) {
            $stmt->bindValue($paramName, (string) $value, PDO::PARAM_STR);
            continue;
        }

        $stmt->bindValue($paramName, (string) $value, PDO::PARAM_STR);
    }

    if ($pagination !== null) {
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }

    $stmt->execute();
    return $stmt->fetchAll();
}

function getResultsJsonPath(): string
{
    $configuredPath = getenv('RESULTS_JSON_PATH');
    if (is_string($configuredPath) && $configuredPath !== '') {
        return $configuredPath;
    }

    return __DIR__ . '/../storage/esiti.json';
}

function saveResultsToJson(array $payload): string
{
    $path = getResultsJsonPath();
    $dir = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $path;
}

function createApp(?PDO $pdo = null): App
{
    $pdo ??= getPdoFromEnv();

    $app = AppFactory::create();
    $app->addRoutingMiddleware();
    $app->addErrorMiddleware(true, true, true);

    $app->get('/', function (Request $request, Response $response): Response {
        return $response->withHeader('Location', '/q1')->withStatus(302);
    });

    $app->get('/{queryId:q[1-9]|q10}', function (Request $request, Response $response, array $args): Response {
        $queryId = (string) $args['queryId'];
        $queryString = http_build_query($request->getQueryParams());
        $location = '/api/' . $queryId;

        if ($queryString !== '') {
            $location .= '?' . $queryString;
        }

        return $response->withHeader('Location', $location)->withStatus(302);
    });

    $app->get('/api/q1', function (Request $request, Response $response) use ($pdo): Response {
        $rows = fetchRows(
            $pdo,
            'SELECT DISTINCT p.pnome
             FROM Pezzi p
             JOIN Catalogo c ON c.pid = p.pid
             ORDER BY p.pnome',
            [],
            getPaginationParams($request)
        );

        return jsonResponse($response, ['query' => 1, 'data' => $rows]);
    });

    $app->get('/api/q2', function (Request $request, Response $response) use ($pdo): Response {
        $rows = fetchRows(
            $pdo,
            'SELECT f.fnome
             FROM Fornitori f
             WHERE NOT EXISTS (
                 SELECT 1
                 FROM Pezzi p
                 WHERE NOT EXISTS (
                     SELECT 1
                     FROM Catalogo c
                     WHERE c.fid = f.fid AND c.pid = p.pid
                 )
             )
             ORDER BY f.fnome',
            [],
            getPaginationParams($request)
        );

        return jsonResponse($response, ['query' => 2, 'data' => $rows]);
    });

    $app->get('/api/q3', function (Request $request, Response $response) use ($pdo): Response {
        $color = $request->getQueryParams()['colore'] ?? 'rosso';

        $rows = fetchRows(
            $pdo,
            'SELECT f.fnome
             FROM Fornitori f
             WHERE NOT EXISTS (
                 SELECT 1
                 FROM Pezzi p
                 WHERE LOWER(p.colore) = LOWER(:colore)
                   AND NOT EXISTS (
                       SELECT 1
                       FROM Catalogo c
                       WHERE c.fid = f.fid AND c.pid = p.pid
                   )
             )
             ORDER BY f.fnome',
            ['colore' => (string) $color],
            getPaginationParams($request)
        );

        return jsonResponse($response, ['query' => 3, 'colore' => $color, 'data' => $rows]);
    });

    $app->get('/api/q4', function (Request $request, Response $response) use ($pdo): Response {
        $supplierName = $request->getQueryParams()['fornitore'] ?? 'Acme';

        $rows = fetchRows(
            $pdo,
            'SELECT DISTINCT p.pnome
             FROM Pezzi p
             WHERE EXISTS (
                 SELECT 1
                 FROM Catalogo c1
                 JOIN Fornitori f1 ON f1.fid = c1.fid
                 WHERE c1.pid = p.pid
                   AND f1.fnome = :fornitore
             )
             AND NOT EXISTS (
                 SELECT 1
                 FROM Catalogo c2
                 JOIN Fornitori f2 ON f2.fid = c2.fid
                 WHERE c2.pid = p.pid
                   AND f2.fnome <> :fornitore
             )
             ORDER BY p.pnome',
            ['fornitore' => (string) $supplierName],
            getPaginationParams($request)
        );

        return jsonResponse($response, ['query' => 4, 'fornitore' => $supplierName, 'data' => $rows]);
    });

    $app->get('/api/q5', function (Request $request, Response $response) use ($pdo): Response {
        $rows = fetchRows(
            $pdo,
            'SELECT DISTINCT c.fid
             FROM Catalogo c
             JOIN (
                 SELECT pid, AVG(costo) AS costo_medio
                 FROM Catalogo
                 GROUP BY pid
             ) avgp ON avgp.pid = c.pid
             WHERE c.costo > avgp.costo_medio
             ORDER BY c.fid',
            [],
            getPaginationParams($request)
        );

        return jsonResponse($response, ['query' => 5, 'data' => $rows]);
    });

    $app->get('/api/q6', function (Request $request, Response $response) use ($pdo): Response {
        $rows = fetchRows(
            $pdo,
            'SELECT p.pid, p.pnome, f.fnome, c.costo
             FROM Catalogo c
             JOIN (
                 SELECT pid, MAX(costo) AS costo_max
                 FROM Catalogo
                 GROUP BY pid
             ) m ON m.pid = c.pid AND m.costo_max = c.costo
             JOIN Pezzi p ON p.pid = c.pid
             JOIN Fornitori f ON f.fid = c.fid
             ORDER BY p.pid, f.fnome',
            [],
            getPaginationParams($request)
        );

        return jsonResponse($response, ['query' => 6, 'data' => $rows]);
    });

    $app->get('/api/q7', function (Request $request, Response $response) use ($pdo): Response {
        $color = $request->getQueryParams()['colore'] ?? 'rosso';

        $rows = fetchRows(
            $pdo,
            'SELECT f.fid
             FROM Fornitori f
             WHERE EXISTS (
                 SELECT 1
                 FROM Catalogo c
                 WHERE c.fid = f.fid
             )
             AND NOT EXISTS (
                 SELECT 1
                 FROM Catalogo c
                 JOIN Pezzi p ON p.pid = c.pid
                 WHERE c.fid = f.fid
                   AND LOWER(p.colore) <> LOWER(:colore)
             )
             ORDER BY f.fid',
            ['colore' => (string) $color],
            getPaginationParams($request)
        );

        return jsonResponse($response, ['query' => 7, 'colore' => $color, 'data' => $rows]);
    });

    $app->get('/api/q8', function (Request $request, Response $response) use ($pdo): Response {
        $q = $request->getQueryParams();
        $red = $q['colore1'] ?? 'rosso';
        $green = $q['colore2'] ?? 'verde';

        $rows = fetchRows(
            $pdo,
            'SELECT f.fid
             FROM Fornitori f
             WHERE EXISTS (
                 SELECT 1
                 FROM Catalogo c
                 JOIN Pezzi p ON p.pid = c.pid
                 WHERE c.fid = f.fid
                   AND LOWER(p.colore) = LOWER(:colore1)
             )
             AND EXISTS (
                 SELECT 1
                 FROM Catalogo c
                 JOIN Pezzi p ON p.pid = c.pid
                 WHERE c.fid = f.fid
                   AND LOWER(p.colore) = LOWER(:colore2)
             )
             ORDER BY f.fid',
            ['colore1' => (string) $red, 'colore2' => (string) $green],
            getPaginationParams($request)
        );

        return jsonResponse($response, ['query' => 8, 'colore1' => $red, 'colore2' => $green, 'data' => $rows]);
    });

    $app->get('/api/q9', function (Request $request, Response $response) use ($pdo): Response {
        $q = $request->getQueryParams();
        $red = $q['colore1'] ?? 'rosso';
        $green = $q['colore2'] ?? 'verde';

        $rows = fetchRows(
            $pdo,
            'SELECT DISTINCT f.fid
             FROM Fornitori f
             JOIN Catalogo c ON c.fid = f.fid
             JOIN Pezzi p ON p.pid = c.pid
             WHERE LOWER(p.colore) IN (LOWER(:colore1), LOWER(:colore2))
             ORDER BY f.fid',
            ['colore1' => (string) $red, 'colore2' => (string) $green],
            getPaginationParams($request)
        );

        return jsonResponse($response, ['query' => 9, 'colore1' => $red, 'colore2' => $green, 'data' => $rows]);
    });

    $app->get('/api/q10', function (Request $request, Response $response) use ($pdo): Response {
        $minSuppliers = max(2, (int) ($request->getQueryParams()['min_fornitori'] ?? 2));

        $rows = fetchRows(
            $pdo,
            'SELECT c.pid
             FROM Catalogo c
             GROUP BY c.pid
             HAVING COUNT(DISTINCT c.fid) >= :min_fornitori
             ORDER BY c.pid',
            ['min_fornitori' => $minSuppliers],
            getPaginationParams($request)
        );

        return jsonResponse($response, ['query' => 10, 'min_fornitori' => $minSuppliers, 'data' => $rows]);
    });

    $app->get('/api/esiti', function (Request $request, Response $response) use ($pdo): Response {
        $q = $request->getQueryParams();
        $colore = (string) ($q['colore'] ?? 'rosso');
        $fornitore = (string) ($q['fornitore'] ?? 'Acme');
        $colore1 = (string) ($q['colore1'] ?? 'rosso');
        $colore2 = (string) ($q['colore2'] ?? 'verde');
        $minFornitori = max(2, (int) ($q['min_fornitori'] ?? 2));

        $payload = [
            'generated_at' => gmdate('c'),
            'results' => [
                'q1' => fetchRows(
                    $pdo,
                    'SELECT DISTINCT p.pnome
                     FROM Pezzi p
                     JOIN Catalogo c ON c.pid = p.pid
                     ORDER BY p.pnome'
                ),
                'q2' => fetchRows(
                    $pdo,
                    'SELECT f.fnome
                     FROM Fornitori f
                     WHERE NOT EXISTS (
                         SELECT 1
                         FROM Pezzi p
                         WHERE NOT EXISTS (
                             SELECT 1
                             FROM Catalogo c
                             WHERE c.fid = f.fid AND c.pid = p.pid
                         )
                     )
                     ORDER BY f.fnome'
                ),
                'q3' => fetchRows(
                    $pdo,
                    'SELECT f.fnome
                     FROM Fornitori f
                     WHERE NOT EXISTS (
                         SELECT 1
                         FROM Pezzi p
                         WHERE LOWER(p.colore) = LOWER(:colore)
                           AND NOT EXISTS (
                               SELECT 1
                               FROM Catalogo c
                               WHERE c.fid = f.fid AND c.pid = p.pid
                           )
                     )
                     ORDER BY f.fnome',
                    ['colore' => $colore]
                ),
                'q4' => fetchRows(
                    $pdo,
                    'SELECT DISTINCT p.pnome
                     FROM Pezzi p
                     WHERE EXISTS (
                         SELECT 1
                         FROM Catalogo c1
                         JOIN Fornitori f1 ON f1.fid = c1.fid
                         WHERE c1.pid = p.pid
                           AND f1.fnome = :fornitore
                     )
                     AND NOT EXISTS (
                         SELECT 1
                         FROM Catalogo c2
                         JOIN Fornitori f2 ON f2.fid = c2.fid
                         WHERE c2.pid = p.pid
                           AND f2.fnome <> :fornitore
                     )
                     ORDER BY p.pnome',
                    ['fornitore' => $fornitore]
                ),
                'q5' => fetchRows(
                    $pdo,
                    'SELECT DISTINCT c.fid
                     FROM Catalogo c
                     JOIN (
                         SELECT pid, AVG(costo) AS costo_medio
                         FROM Catalogo
                         GROUP BY pid
                     ) avgp ON avgp.pid = c.pid
                     WHERE c.costo > avgp.costo_medio
                     ORDER BY c.fid'
                ),
                'q6' => fetchRows(
                    $pdo,
                    'SELECT p.pid, p.pnome, f.fnome, c.costo
                     FROM Catalogo c
                     JOIN (
                         SELECT pid, MAX(costo) AS costo_max
                         FROM Catalogo
                         GROUP BY pid
                     ) m ON m.pid = c.pid AND m.costo_max = c.costo
                     JOIN Pezzi p ON p.pid = c.pid
                     JOIN Fornitori f ON f.fid = c.fid
                     ORDER BY p.pid, f.fnome'
                ),
                'q7' => fetchRows(
                    $pdo,
                    'SELECT f.fid
                     FROM Fornitori f
                     WHERE EXISTS (
                         SELECT 1
                         FROM Catalogo c
                         WHERE c.fid = f.fid
                     )
                     AND NOT EXISTS (
                         SELECT 1
                         FROM Catalogo c
                         JOIN Pezzi p ON p.pid = c.pid
                         WHERE c.fid = f.fid
                           AND LOWER(p.colore) <> LOWER(:colore)
                     )
                     ORDER BY f.fid',
                    ['colore' => $colore]
                ),
                'q8' => fetchRows(
                    $pdo,
                    'SELECT f.fid
                     FROM Fornitori f
                     WHERE EXISTS (
                         SELECT 1
                         FROM Catalogo c
                         JOIN Pezzi p ON p.pid = c.pid
                         WHERE c.fid = f.fid
                           AND LOWER(p.colore) = LOWER(:colore1)
                     )
                     AND EXISTS (
                         SELECT 1
                         FROM Catalogo c
                         JOIN Pezzi p ON p.pid = c.pid
                         WHERE c.fid = f.fid
                           AND LOWER(p.colore) = LOWER(:colore2)
                     )
                     ORDER BY f.fid',
                    ['colore1' => $colore1, 'colore2' => $colore2]
                ),
                'q9' => fetchRows(
                    $pdo,
                    'SELECT DISTINCT f.fid
                     FROM Fornitori f
                     JOIN Catalogo c ON c.fid = f.fid
                     JOIN Pezzi p ON p.pid = c.pid
                     WHERE LOWER(p.colore) IN (LOWER(:colore1), LOWER(:colore2))
                     ORDER BY f.fid',
                    ['colore1' => $colore1, 'colore2' => $colore2]
                ),
                'q10' => fetchRows(
                    $pdo,
                    'SELECT c.pid
                     FROM Catalogo c
                     GROUP BY c.pid
                     HAVING COUNT(DISTINCT c.fid) >= :min_fornitori
                     ORDER BY c.pid',
                    ['min_fornitori' => $minFornitori]
                ),
            ],
            'parameters' => [
                'colore' => $colore,
                'fornitore' => $fornitore,
                'colore1' => $colore1,
                'colore2' => $colore2,
                'min_fornitori' => $minFornitori,
            ],
        ];

        $savedPath = saveResultsToJson($payload);

        return jsonResponse($response, [
            'message' => 'Esiti generati e salvati su file JSON',
            'saved_to' => $savedPath,
            'data' => $payload,
        ]);
    });

    $app->get('/api/esiti/saved', function (Request $request, Response $response): Response {
        $path = getResultsJsonPath();

        if (!file_exists($path)) {
            return jsonResponse($response, [
                'message' => 'File JSON non ancora generato',
                'path' => $path,
            ], 404);
        }

        $content = file_get_contents($path);
        $decoded = json_decode($content, true);

        return jsonResponse($response, [
            'path' => $path,
            'data' => $decoded,
        ]);
    });

    $app->get('/health', function (Request $request, Response $response): Response {
        return jsonResponse($response, ['status' => 'ok']);
    });

    return $app;
}
