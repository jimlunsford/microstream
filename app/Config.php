<?php

// app/Config.php

declare(strict_types=1);

namespace Microstream;

class Config
{
    // Basic site info
    public const NODE_ID    = 'REPLACE_WITH_YOUR_NODE_UUID';
    public const TITLE      = "Jim's Stream";
    public const BASE_URL   = 'https://example.com'; // no trailing slash
    public const API_SCRIPT = '/api.php';

    // Software info
    public const SOFTWARE_NAME    = 'microstream';
    public const SOFTWARE_VERSION = '0.1.0'; // dev version

    // Protocol version
    public const PROTOCOL_VERSION = 'microstream-1.0';

    /**
     * Helper to get the full API base URL.
     */
    public static function apiBase(): string
    {
        return static::BASE_URL . static::API_SCRIPT;
    }
}