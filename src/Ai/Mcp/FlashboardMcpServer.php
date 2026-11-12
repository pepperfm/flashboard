<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Attributes\Instructions;
use Pepperfm\Flashboard\Ai\Mcp\Tools\CreateResourceTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\InspectModelTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\ListModelsTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\ListResourcesTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\PreviewResourceTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\UpdateResourceTool;

#[Name('Flashboard AI Harness')]
#[Version('0.1.0')]
#[Instructions('Trusted local MCP server for Flashboard. Inspect Eloquent models, preview source changes, and create or update only with exact preview hashes. Never overwrite a manually edited resource. This server does not expose database record mutations.')]
final class FlashboardMcpServer extends Server
{
    protected array $tools = [
        ListModelsTool::class,
        InspectModelTool::class,
        ListResourcesTool::class,
        PreviewResourceTool::class,
        CreateResourceTool::class,
        UpdateResourceTool::class,
    ];
}
