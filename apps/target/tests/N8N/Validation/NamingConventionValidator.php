<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

/**
 * Validates N8N node naming convention: Domain_Role_Action.
 *
 * Examples:
 * - Folder_Agent_UpdateReferenceSubject
 * - Actor_LLM_Detect
 * - RabbitMQ_Send_FolderUpdated
 * - HTTP_Request_OpenAIAPI
 */
class NamingConventionValidator extends AbstractValidator
{
    /**
     * Node types that are exempt from naming convention validation.
     */
    private const EXEMPT_NODE_TYPES = [
        'n8n-nodes-base.start',
        'n8n-nodes-base.set',
        'n8n-nodes-base.if',
        'n8n-nodes-base.switch',
        'n8n-nodes-base.merge',
        'n8n-nodes-base.stopAndError',
        'n8n-nodes-base.noOp',
        'n8n-nodes-base.stickyNote',
    ];

    /**
     * Pattern for valid node names: Domain_Role_Action.
     * - Domain: PascalCase (e.g., Folder, Actor, RabbitMQ)
     * - Role: PascalCase (e.g., Agent, LLM, Send, Request)
     * - Action: PascalCase (e.g., UpdateReferenceSubject, Detect, FolderUpdated).
     */
    private const NAMING_PATTERN = '/^[A-Z][a-zA-Z0-9]*_[A-Z][a-zA-Z0-9]*_[A-Z][a-zA-Z0-9]*$/';

    public function getName(): string
    {
        return 'N8N Naming Convention';
    }

    protected function validateWorkflow(string $filename, array $workflow): void
    {
        foreach ($workflow['nodes'] as $node) {
            ++$this->totalNodes;
            $this->validateNode($filename, $workflow['name'], $node);
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function validateNode(string $filename, string $workflowName, array $node): void
    {
        $nodeInfo = WorkflowLoader::extractNodeInfo($node);

        // Skip exempt node types
        if (\in_array($nodeInfo['type'], self::EXEMPT_NODE_TYPES, true)) {
            return;
        }

        // Validate naming convention
        if (!preg_match(self::NAMING_PATTERN, $nodeInfo['name'])) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'INVALID_NAMING_CONVENTION',
                message: \sprintf(
                    'Node name "%s" does not follow Domain_Role_Action convention (type: %s)',
                    $nodeInfo['name'],
                    $nodeInfo['type']
                ),
                severity: ValidationSeverity::CRITICAL
            );
        }
    }
}
