<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp;

use Laravel\Mcp\Server;
use Pepperfm\Flashboard\Ai\Mcp\Tools\CreateResourceTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\InspectModelTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\ListModelsTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\ListResourcesTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\PreviewResourceTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\UpdateResourceTool;

final class FlashboardMcpServer extends Server
{
    protected string $name = 'Flashboard AI Harness';

    protected string $version = '0.1.0';

    protected string $instructions = 'Trusted local MCP server for Flashboard. Inspect Eloquent models, preview source changes, and create or update only with exact preview hashes. Never overwrite a manually edited resource. This server does not expose database record mutations.';

    protected array $tools = [
        ListModelsTool::class,
        InspectModelTool::class,
        ListResourcesTool::class,
        PreviewResourceTool::class,
        CreateResourceTool::class,
        UpdateResourceTool::class,
    ];
}
