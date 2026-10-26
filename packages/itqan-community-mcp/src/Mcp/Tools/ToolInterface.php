<?php

declare(strict_types=1);

namespace Itqan\CommunityMcp\Mcp\Tools;

use Flarum\User\User;

/**
 * Contract implemented by every MCP tool exposed by this extension.
 */
interface ToolInterface
{
    /**
     * Unique tool name as advertised in `tools/list`.
     */
    public function getName(): string;

    /**
     * Human and model readable description of what the tool does.
     */
    public function getDescription(): string;

    /**
     * JSON Schema describing the tool arguments.
     */
    public function getInputSchema(): array;

    /**
     * MCP tool annotations (readOnlyHint, destructiveHint, ...).
     */
    public function getAnnotations(): array;

    /**
     * Run the tool for the acting user and return the text result.
     *
     * @param array<string, mixed> $arguments
     */
    public function execute(array $arguments, User $actor): string;
}
