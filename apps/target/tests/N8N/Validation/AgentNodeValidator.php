<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

/**
 * Validates N8N Agent node configuration.
 *
 * Requirements:
 * 1. needsFallback must be true (fallback model enabled)
 * 2. retryOnFail must be true
 * 3. maxIterations must be defined and < 30
 * 4. onError must be "continueErrorOutput" (error output activated)
 * 5. Error output must be connected to another node
 */
class AgentNodeValidator extends AbstractValidator
{
    private const MAX_ITERATIONS_LIMIT = 30;

    /** @var array<string, mixed> */
    private array $currentConnections = [];

    public function getName(): string
    {
        return 'N8N Agent Configuration';
    }

    protected function validateWorkflow(string $filename, array $workflow): void
    {
        $this->currentConnections = $workflow['connections'];

        foreach ($workflow['nodes'] as $node) {
            // Check if node is an Agent type
            $nodeInfo = WorkflowLoader::extractNodeInfo($node);
            if ('@n8n/n8n-nodes-langchain.agent' !== $nodeInfo['type']) {
                continue;
            }

            ++$this->totalNodes;
            $this->validateAgentNode($filename, $workflow['name'], $node);
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function validateAgentNode(string $filename, string $workflowName, array $node): void
    {
        $nodeInfo = WorkflowLoader::extractNodeInfo($node);
        $parameters = $nodeInfo['parameters'];
        $options = \is_array($parameters['options'] ?? null) ? $parameters['options'] : [];

        // 1. Check needsFallback
        $needsFallback = $parameters['needsFallback'] ?? false;
        if (!\is_bool($needsFallback) || !$needsFallback) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'AGENT_MISSING_FALLBACK',
                message: \sprintf(
                    'Agent node "%s" must have needsFallback=true for fallback model',
                    $nodeInfo['name']
                ),
                severity: ValidationSeverity::CRITICAL
            );
        }

        // 2. Check retryOnFail
        $retryOnFail = $node['retryOnFail'] ?? false;
        if (!\is_bool($retryOnFail) || !$retryOnFail) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'AGENT_RETRY_DISABLED',
                message: \sprintf('Agent node "%s" must have retryOnFail=true', $nodeInfo['name']),
                severity: ValidationSeverity::CRITICAL
            );
        }

        // 3. Check maxIterations
        $maxIterations = $options['maxIterations'] ?? null;
        if (null === $maxIterations) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'AGENT_MISSING_MAX_ITERATIONS',
                message: \sprintf('Agent node "%s" must have maxIterations defined', $nodeInfo['name']),
                severity: ValidationSeverity::CRITICAL
            );
        } elseif (is_numeric($maxIterations)) {
            $maxIterationsInt = (int) $maxIterations;
            if ($maxIterationsInt >= self::MAX_ITERATIONS_LIMIT) {
                $this->addError(
                    workflowFile: $filename,
                    workflowName: $workflowName,
                    nodeName: $nodeInfo['name'],
                    nodeId: $nodeInfo['id'],
                    errorType: 'AGENT_MAX_ITERATIONS_TOO_HIGH',
                    message: \sprintf(
                        'Agent node "%s" has maxIterations=%d, must be < %d',
                        $nodeInfo['name'],
                        $maxIterationsInt,
                        self::MAX_ITERATIONS_LIMIT
                    ),
                    severity: ValidationSeverity::CRITICAL
                );
            }
        }

        // 4. Check onError setting
        $onError = $node['onError'] ?? null;
        if ('continueErrorOutput' !== $onError) {
            $onErrorDisplay = \is_string($onError) ? $onError : 'not set';
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'AGENT_ERROR_OUTPUT_DISABLED',
                message: \sprintf(
                    'Agent node "%s" must have onError="continueErrorOutput" (currently: %s)',
                    $nodeInfo['name'],
                    $onErrorDisplay
                ),
                severity: ValidationSeverity::CRITICAL
            );
        }

        // 5. Check error output connection
        $this->validateErrorOutputConnection($filename, $workflowName, $nodeInfo);
    }

    /**
     * @param array{name: string, id: string, type: string, parameters: array<string, mixed>} $nodeInfo
     */
    private function validateErrorOutputConnection(string $filename, string $workflowName, array $nodeInfo): void
    {
        // Check if the error output (index 1) is connected
        $connections = $this->currentConnections[$nodeInfo['name']] ?? [];
        if (!\is_array($connections)) {
            return;
        }

        $mainConnections = $connections['main'] ?? [];
        if (!\is_array($mainConnections)) {
            return;
        }

        // Error output is at index 1
        $errorOutputConnections = $mainConnections[1] ?? null;

        if (null === $errorOutputConnections || !\is_array($errorOutputConnections) || empty($errorOutputConnections)) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'AGENT_ERROR_OUTPUT_NOT_CONNECTED',
                message: \sprintf(
                    'Agent node "%s" has error output enabled but not connected to any node',
                    $nodeInfo['name']
                ),
                severity: ValidationSeverity::CRITICAL
            );
        }
    }
}
