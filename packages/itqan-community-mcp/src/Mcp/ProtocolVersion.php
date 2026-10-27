<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Mcp;

/**
 * MCP protocol version negotiation.
 *
 * The server advertises the newest protocol revision it understands and falls
 * back to a broadly supported revision when a client asks for something it does
 * not implement.
 */
final class ProtocolVersion
{
    public const LATEST = '2025-06-18';

    public const DEFAULT_FALLBACK = '2025-03-26';

    public const SUPPORTED = [
        '2025-06-18',
        '2025-03-26',
        '2024-11-05',
    ];

    /**
     * Pick the protocol revision to answer with.
     *
     * Returns the client's revision when it is supported, otherwise the
     * documented fallback revision.
     */
    public static function negotiate(?string $clientVersion): string
    {
        if ($clientVersion !== null && in_array($clientVersion, self::SUPPORTED, true)) {
            return $clientVersion;
        }

        return self::DEFAULT_FALLBACK;
    }
}
