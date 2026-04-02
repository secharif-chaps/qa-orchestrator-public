<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

/**
 * @phpstan-type NodeInfo array{
 *   name: string,
 *   id: string,
 *   type: string,
 *   parameters: array<string, mixed>,
 *   notes: string
 * }
 * @phpstan-type WorkflowType array{
 *   name: string,
 *   id: string,
 *   nodes: array<int, array<string, mixed>>,
 *   connections: array<string, mixed>
 * }
 */
class WorkflowLoader
{
    /**
     * Load and parse a single workflow file.
     *
     * @return WorkflowType|null
     */
    public static function loadWorkflow(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if (false === $content) {
            return null;
        }

        try {
            $workflow = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($workflow)) {
            return null;
        }

        /** @var array<int, array<string, mixed>> $nodes */
        $nodes = \is_array($workflow['nodes'] ?? null) ? $workflow['nodes'] : [];
        /** @var array<string, mixed> $connections */
        $connections = \is_array($workflow['connections'] ?? null) ? $workflow['connections'] : [];

        return [
            'name' => \is_string($workflow['name'] ?? null) ? $workflow['name'] : '<unnamed>',
            'id' => \is_string($workflow['id'] ?? null) ? $workflow['id'] : '',
            'nodes' => $nodes,
            'connections' => $connections,
        ];
    }

    /**
     * Load all workflow files from a directory.
     *
     * @return array<string, WorkflowType>
     */
    public static function loadAllWorkflows(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $workflowFiles = glob($directory . '/*.json');
        if (false === $workflowFiles) {
            return [];
        }

        $workflows = [];
        foreach ($workflowFiles as $filePath) {
            $workflow = self::loadWorkflow($filePath);
            if (null !== $workflow) {
                $workflows[basename($filePath)] = $workflow;
            }
        }

        return $workflows;
    }

    /**
     * Extract node information with safe type checking.
     *
     * @param array<string, mixed> $node
     *
     * @return NodeInfo
     */
    public static function extractNodeInfo(array $node): array
    {
        return [
            'name' => \is_string($node['name'] ?? null) ? $node['name'] : '<unnamed>',
            'id' => \is_string($node['id'] ?? null) ? $node['id'] : '<no-id>',
            'type' => \is_string($node['type'] ?? null) ? $node['type'] : '<unknown>',
            'parameters' => \is_array($node['parameters'] ?? null) ? $node['parameters'] : [],
            'notes' => \is_string($node['notes'] ?? null) ? $node['notes'] : '',
        ];
    }
}
