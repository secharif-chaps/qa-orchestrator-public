<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

/**
 * Validates N8N node versions and configuration.
 *
 * Requirements:
 * 1. @n8n/n8n-nodes-langchain.agent nodes must be version >= 3
 * 2. @n8n/n8n-nodes-langchain.lmChatOpenAi child nodes must be version >= 1.3
 * 3. LLM nodes must have responseApiEnabled = false
 * 4. @n8n/n8n-nodes-langchain.toolWorkflow nodes must be version >= 2.2
 */
final class NodeVersionValidator extends AbstractValidator
{
    private const MIN_AGENT_VERSION = 3.0;
    private const MIN_LLM_VERSION = 1.3;
    private const MIN_CALL_WORKFLOW_TOOL_VERSION = 2.2;

    /** @var array<string, array<string, mixed>> */
    private array $nodesByName = [];

    /** @var array<string, mixed> */
    private array $currentConnections = [];

    public function getName(): string
    {
        return 'N8N Node Version Requirements';
    }

    protected function validateWorkflow(string $filename, array $workflow): void
    {
        $this->currentConnections = $workflow['connections'];
        $this->nodesByName = [];

        // Index nodes by name for quick lookup
        foreach ($workflow['nodes'] as $node) {
            $nodeInfo = WorkflowLoader::extractNodeInfo($node);
            $this->nodesByName[$nodeInfo['name']] = $node;
        }

        foreach ($workflow['nodes'] as $node) {
            $nodeInfo = WorkflowLoader::extractNodeInfo($node);

            // Validate Agent nodes
            if ('@n8n/n8n-nodes-langchain.agent' === $nodeInfo['type']) {
                ++$this->totalNodes;
                $this->validateAgentNodeVersion($filename, $workflow['name'], $node);
            }

            // Validate LLM nodes
            if ('@n8n/n8n-nodes-langchain.lmChatOpenAi' === $nodeInfo['type']) {
                ++$this->totalNodes;
                $this->validateLlmNodeVersion($filename, $workflow['name'], $node);
            }

            // Validate Call Workflow Tool nodes
            if ('@n8n/n8n-nodes-langchain.toolWorkflow' === $nodeInfo['type']) {
                ++$this->totalNodes;
                $this->validateCallWorkflowToolNodeVersion($filename, $workflow['name'], $node);
            }
        }
    }

    /**
     * Validate Agent node version.
     *
     * @param array<string, mixed> $node
     */
    private function validateAgentNodeVersion(string $filename, string $workflowName, array $node): void
    {
        $nodeInfo = WorkflowLoader::extractNodeInfo($node);

        // Check if validation should be skipped
        if ($this->shouldSkipValidation($nodeInfo['notes'])) {
            return;
        }

        // Check typeVersion >= 3
        $typeVersion = $node['typeVersion'] ?? null;
        if (null === $typeVersion || !is_numeric($typeVersion)) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'AGENT_MISSING_VERSION',
                message: \sprintf('Agent node "%s" must have a typeVersion defined', $nodeInfo['name']),
                severity: ValidationSeverity::CRITICAL
            );

            return;
        }

        $versionFloat = (float) $typeVersion;
        if ($versionFloat < self::MIN_AGENT_VERSION) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'AGENT_VERSION_TOO_LOW',
                message: \sprintf(
                    'Agent node "%s" has version %.1f, must be >= %.1f',
                    $nodeInfo['name'],
                    $versionFloat,
                    self::MIN_AGENT_VERSION
                ),
                severity: ValidationSeverity::CRITICAL
            );
        }

        // Validate connected LLM nodes
        $this->validateConnectedLlmNodes($filename, $workflowName, $nodeInfo);
    }

    /**
     * Validate LLM node version and configuration.
     *
     * @param array<string, mixed> $node
     */
    private function validateLlmNodeVersion(string $filename, string $workflowName, array $node): void
    {
        $nodeInfo = WorkflowLoader::extractNodeInfo($node);

        // Check if validation should be skipped
        if ($this->shouldSkipValidation($nodeInfo['notes'])) {
            return;
        }

        // Check typeVersion >= 1.3
        $typeVersion = $node['typeVersion'] ?? null;
        if (null === $typeVersion || !is_numeric($typeVersion)) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'LLM_MISSING_VERSION',
                message: \sprintf('LLM node "%s" must have a typeVersion defined', $nodeInfo['name']),
                severity: ValidationSeverity::CRITICAL
            );

            return;
        }

        $versionFloat = (float) $typeVersion;
        if ($versionFloat < self::MIN_LLM_VERSION) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'LLM_VERSION_TOO_LOW',
                message: \sprintf(
                    'LLM node "%s" has version %.1f, must be >= %.1f',
                    $nodeInfo['name'],
                    $versionFloat,
                    self::MIN_LLM_VERSION
                ),
                severity: ValidationSeverity::CRITICAL
            );
        }

        // Check responseApiEnabled = false
        $parameters = $nodeInfo['parameters'];
        $options = \is_array($parameters['options'] ?? null) ? $parameters['options'] : [];
        $responseApiEnabled = $options['responseApiEnabled'] ?? null;

        if (true === $responseApiEnabled) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'LLM_RESPONSE_API_ENABLED',
                message: \sprintf(
                    'LLM node "%s" must have responseApiEnabled=false (currently: true)',
                    $nodeInfo['name']
                ),
                severity: ValidationSeverity::CRITICAL
            );
        }
    }

    /**
     * Validate Call Workflow Tool node version.
     *
     * @param array<string, mixed> $node
     */
    private function validateCallWorkflowToolNodeVersion(string $filename, string $workflowName, array $node): void
    {
        $nodeInfo = WorkflowLoader::extractNodeInfo($node);

        // Check if validation should be skipped
        if ($this->shouldSkipValidation($nodeInfo['notes'])) {
            return;
        }

        // Check typeVersion >= 2.2
        $typeVersion = $node['typeVersion'] ?? null;
        if (null === $typeVersion || !is_numeric($typeVersion)) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'CALL_WORKFLOW_TOOL_MISSING_VERSION',
                message: \sprintf('Call Workflow Tool node "%s" must have a typeVersion defined', $nodeInfo['name']),
                severity: ValidationSeverity::CRITICAL
            );

            return;
        }

        $versionFloat = (float) $typeVersion;
        if ($versionFloat < self::MIN_CALL_WORKFLOW_TOOL_VERSION) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'CALL_WORKFLOW_TOOL_VERSION_TOO_LOW',
                message: \sprintf(
                    'Call Workflow Tool node "%s" has version %.1f, must be >= %.1f',
                    $nodeInfo['name'],
                    $versionFloat,
                    self::MIN_CALL_WORKFLOW_TOOL_VERSION
                ),
                severity: ValidationSeverity::CRITICAL
            );
        }
    }

    /**
     * Find and validate LLM nodes connected to an Agent.
     *
     * @param array{name: string, id: string, type: string, parameters: array<string, mixed>} $nodeInfo
     */
    private function validateConnectedLlmNodes(string $filename, string $workflowName, array $nodeInfo): void
    {
        // In N8N, LLM nodes are connected to Agents via ai_languageModel connections
        $connections = $this->currentConnections[$nodeInfo['name']] ?? [];
        if (!\is_array($connections)) {
            return;
        }

        // Check ai_languageModel connections
        $aiConnections = $connections['ai_languageModel'] ?? [];
        if (!\is_array($aiConnections)) {
            return;
        }

        // ai_languageModel connections are at index 0
        $llmConnections = $aiConnections[0] ?? null;
        if (null === $llmConnections || !\is_array($llmConnections)) {
            return;
        }

        // Validate each connected LLM node
        foreach ($llmConnections as $connection) {
            if (!\is_array($connection)) {
                continue;
            }

            $targetNodeName = $connection['node'] ?? null;
            if (!\is_string($targetNodeName)) {
                continue;
            }

            $targetNode = $this->nodesByName[$targetNodeName] ?? null;
            if (null === $targetNode) {
                continue;
            }

            $targetNodeInfo = WorkflowLoader::extractNodeInfo($targetNode);

            // Only validate LLM nodes
            if ('@n8n/n8n-nodes-langchain.lmChatOpenAi' !== $targetNodeInfo['type']) {
                continue;
            }

            // The LLM node will be validated by validateLlmNodeVersion
            // This is just to ensure the connection exists
        }
    }
}
