<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Mcp;

use RuntimeException;

/**
 * Internal carrier for a JSON-RPC 2.0 error response.
 *
 * The dispatcher converts this into the `error` member of a response, which
 * keeps the request handlers readable: each method validates and then throws
 * the exact JSON-RPC code it wants to report.
 */
final class JsonRpcException extends RuntimeException
{
    private int $rpcCode;

    public function __construct(int $rpcCode, string $message)
    {
        parent::__construct($message);

        $this->rpcCode = $rpcCode;
    }

    public function getRpcCode(): int
    {
        return $this->rpcCode;
    }
}
