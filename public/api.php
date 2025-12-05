<?php

declare(strict_types=1);

// public/api.php

// Simple autoload for now
require_once __DIR__ . '/../app/Config.php';

use Microstream\Config;

// Always return JSON
header('Content-Type: application/json; charset=utf-8');

// Read route parameter
$route = $_GET['route'] ?? null;

// If no route, treat it as 404
if ($route === null) {
    http_response_code(404);
    echo json_encode([
        'protocol' => Config::PROTOCOL_VERSION,
        'status'   => 'error',
        'error'    => [
            'code'    => 'no_route',
            'message' => 'Route parameter is required',
        ],
    ]);
    exit;
}

switch ($route) {
    case 'node':
        handleNode();
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'protocol' => Config::PROTOCOL_VERSION,
            'status'   => 'error',
            'error'    => [
                'code'    => 'unknown_route',
                'message' => 'Unknown route',
            ],
        ]);
        break;
}

/**
 * Handle GET ?route=node
 */
function handleNode(): void
{
    $node = [
        'node_id' => Config::NODE_ID,
        'title'   => Config::TITLE,
        'url'     => Config::BASE_URL,
        'api_base'=> Config::apiBase(),
        'software'=> [
            'name'    => Config::SOFTWARE_NAME,
            'version' => Config::SOFTWARE_VERSION,
        ],
    ];

    echo json_encode([
        'protocol' => Config::PROTOCOL_VERSION,
        'node'     => $node,
    ]);
}