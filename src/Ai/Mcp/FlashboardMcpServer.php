<?php

declare(strict_types=1);

namespace Pepperfm\Flashboard\Ai\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\ServerContext;
use Pepperfm\Flashboard\Ai\Inspection\PackageVersion;
use Laravel\Mcp\Server\Attributes\Instructions;
use Pepperfm\Flashboard\Ai\Mcp\Tools\GetCapabilitiesTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\InspectResourceTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\ValidateResourceTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\InspectPanelTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\CreateResourceTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\AddFieldTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\AddRelationTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\GetResourceSpecTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\InspectModelTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\ListModelsTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\ListResourcesTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\PreviewResourceTool;
use Pepperfm\Flashboard\Ai\Mcp\Tools\UpdateResourceTool;

#[Name('Flashboard AI Harness')]
#[Instructions('Trusted local MCP generator: inspect models/resources and panel diagnostics, preview changes before writes, and create or update with exact preview hashes. Supports existing typed Eloquent relationships, get_resource_spec and incremental add_field/add_relation (apply=false by default). Never change manual edits, create database relations, or mutate business records via MCP.')]
final class FlashboardMcpServer extends Server
{
    protected array $tools = [
        GetCapabilitiesTool::class,
        InspectResourceTool::class,
        ValidateResourceTool::class,
        InspectPanelTool::class,
        ListModelsTool::class,
        InspectModelTool::class,
        ListResourcesTool::class,
        PreviewResourceTool::class,
        CreateResourceTool::class,
        UpdateResourceTool::class,
        GetResourceSpecTool::class,
        AddFieldTool::class,
        AddRelationTool::class,
    ];

    public function createContext(): ServerContext
    {
        $this->version = PackageVersion::current();

        return parent::createContext();
    }

}
