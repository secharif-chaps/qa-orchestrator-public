<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

/**
 * Validates that Webhook_Trigger_TestInput nodes have unique paths across all workflows.
 *
 * This prevents webhook path collisions that could cause routing issues in n8n.
 */
class WebhookPathUniqueValidator extends AbstractValidator
{
    private const string WEBHOOK_NODE_TYPE = 'n8n-nodes-base.webhook';

    /**
     * Collected webhook paths: path => [workflow info].
     *
     * @var array<string, array{workflowFile: string, workflowName: string, nodeName: string, nodeId: string, path: string, isDuplicate: bool}>
     */
    private array $webhookPaths = [];

    public function getName(): string
    {
        return 'N8N Webhook Path Uniqueness';
    }

    public function validateDirectory(string $directory): ValidationResult
    {
        $this->webhookPaths = [];

        $workflows = WorkflowLoader::loadAllWorkflows($directory);
        $this->totalWorkflows = \count($workflows);

        // First pass: collect all webhook paths
        foreach ($workflows as $filename => $workflow) {
            $this->collectWebhookPaths($filename, $workflow);
        }

        // Second pass: check for duplicates and report errors
        $this->checkForDuplicates();

        return $this->getResult();
    }

    /**
     * @param array{name: string, nodes: array<int, array<string, mixed>>, connections: array<string, mixed>} $workflow
     */
    protected function validateWorkflow(string $filename, array $workflow): void
    {
        // Not used - we override validateDirectory for cross-workflow validation
    }

    /**
     * Collect webhook paths from a workflow.
     *
     * @param array{name: string, nodes: array<int, array<string, mixed>>, connections: array<string, mixed>} $workflow
     */
    private function collectWebhookPaths(string $filename, array $workflow): void
    {
        foreach ($workflow['nodes'] as $node) {
            ++$this->totalNodes;

            $nodeInfo = WorkflowLoader::extractNodeInfo($node);

            if (self::WEBHOOK_NODE_TYPE !== $nodeInfo['type']) {
                continue;
            }

            $path = $nodeInfo['parameters']['path'] ?? null;

            if (null === $path || '' === $path || !\is_string($path)) {
                $this->addError(
                    workflowFile: $filename,
                    workflowName: $workflow['name'],
                    nodeName: $nodeInfo['name'],
                    nodeId: $nodeInfo['id'],
                    errorType: 'WEBHOOK_PATH_MISSING',
                    message: 'Webhook_Trigger_TestInput node is missing a path or path is not a string',
                );

                continue;
            }

            $pathString = $path;

            if (isset($this->webhookPaths[$pathString])) {
                // Duplicate found - will be reported in checkForDuplicates
                $this->webhookPaths[$pathString . '_duplicate_' . $nodeInfo['id']] = [
                    'workflowFile' => $filename,
                    'workflowName' => $workflow['name'],
                    'nodeName' => $nodeInfo['name'],
                    'nodeId' => $nodeInfo['id'],
                    'path' => $pathString,
                    'isDuplicate' => true,
                ];
            } else {
                $this->webhookPaths[$pathString] = [
                    'workflowFile' => $filename,
                    'workflowName' => $workflow['name'],
                    'nodeName' => $nodeInfo['name'],
                    'nodeId' => $nodeInfo['id'],
                    'path' => $pathString,
                    'isDuplicate' => false,
                ];
            }
        }
    }

    /**
     * Check for duplicate paths and report errors.
     */
    private function checkForDuplicates(): void
    {
        // Group by path to find duplicates
        $pathGroups = [];
        foreach ($this->webhookPaths as $info) {
            $path = $info['path'];
            $pathGroups[$path][] = $info;
        }

        foreach ($pathGroups as $path => $occurrences) {
            if (\count($occurrences) > 1) {
                $workflowNames = array_map(fn (array $info): string => $info['workflowName'], $occurrences);

                foreach ($occurrences as $info) {
                    $otherWorkflows = array_filter(
                        $workflowNames,
                        fn (string $name): bool => $name !== $info['workflowName']
                    );

                    $this->addError(
                        workflowFile: $info['workflowFile'],
                        workflowName: $info['workflowName'],
                        nodeName: $info['nodeName'],
                        nodeId: $info['nodeId'],
                        errorType: 'WEBHOOK_PATH_DUPLICATE',
                        message: \sprintf(
                            'Webhook path "%s" is duplicated. Also used in: %s',
                            $path,
                            implode(', ', $otherWorkflows)
                        ),
                    );
                }
            }
        }
    }
}
