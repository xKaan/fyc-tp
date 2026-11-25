<?php

declare(strict_types=1);

namespace App\Application\Agent;

use App\Domain\Ports\ToolDefinition;

/**
 * Les outils que l'agent a le droit de demander, et rien d'autre. Un nom
 * d'outil inventé par le modèle ne correspond à aucune classe : find()
 * renvoie null.
 */
final class Toolbox
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function __construct(Tool ...$tools)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->definition()->name] = $tool;
        }
    }

    /** @return list<ToolDefinition> */
    public function definitions(): array
    {
        return array_values(array_map(fn (Tool $tool): ToolDefinition => $tool->definition(), $this->tools));
    }

    public function find(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->tools);
    }
}
