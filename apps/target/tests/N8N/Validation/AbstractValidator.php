<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

abstract class AbstractValidator
{
    /** @var ValidationError[] */
    protected array $errors = [];
    protected int $totalWorkflows = 0;
    protected int $totalNodes = 0;

    abstract public function getName(): string;

    /**
     * Validate all workflows in a directory.
     */
    public function validateDirectory(string $directory): ValidationResult
    {
        $workflows = WorkflowLoader::loadAllWorkflows($directory);
        $this->totalWorkflows = \count($workflows);

        foreach ($workflows as $filename => $workflow) {
            $this->validateWorkflow($filename, $workflow);
        }

        return $this->getResult();
    }

    /**
     * Validate a single workflow.
     *
     * @param array{name: string, nodes: array<int, array<string, mixed>>, connections: array<string, mixed>} $workflow
     */
    abstract protected function validateWorkflow(string $filename, array $workflow): void;

    /**
     * Get the validation result.
     */
    public function getResult(): ValidationResult
    {
        return new ValidationResult(
            validatorName: $this->getName(),
            errors: $this->errors,
            totalWorkflows: $this->totalWorkflows,
            totalNodes: $this->totalNodes
        );
    }

    /**
     * Add a validation error.
     */
    protected function addError(
        string $workflowFile,
        string $workflowName,
        string $nodeName,
        string $nodeId,
        string $errorType,
        string $message,
        ValidationSeverity $severity = ValidationSeverity::CRITICAL,
    ): void {
        $this->errors[] = new ValidationError(
            workflowFile: $workflowFile,
            workflowName: $workflowName,
            nodeName: $nodeName,
            nodeId: $nodeId,
            errorType: $errorType,
            message: $message,
            severity: $severity
        );
    }

    /**
     * Check if a node matches a specific type.
     *
     * @param array<string, mixed> $node
     */
    protected function isNodeType(array $node, string $type): bool
    {
        $nodeInfo = WorkflowLoader::extractNodeInfo($node);

        return $nodeInfo['type'] === $type;
    }

    /**
     * Find a workflow by its ID in the loaded workflows.
     *
     * @param array<string, array{name: string, id: string, nodes: array<int, array<string, mixed>>, connections: array<string, mixed>}> $availableWorkflows
     *
     * @return array{name: string, id: string, nodes: array<int, array<string, mixed>>, connections: array<string, mixed>}|null
     */
    protected function findWorkflowById(array $availableWorkflows, string $workflowId): ?array
    {
        foreach ($availableWorkflows as $workflow) {
            if ($workflow['id'] === $workflowId) {
                return $workflow;
            }
        }

        return null;
    }

    /**
     * Check if validation should be skipped for a node based on @n8n-validate-ignore tags.
     *
     * Tags format in node notes:
     * - @n8n-validate-ignore <validator_name>  - Skip all checks from this validator
     * - @n8n-validate-ignore <error_type>      - Skip specific error type
     *
     * Examples:
     * - @n8n-validate-ignore RabbitMQMessageFormat
     * - @n8n-validate-ignore RABBITMQ_CHAT_MESSAGE_NOT_TRANSLATED
     *
     * @param string      $notes     Node notes field
     * @param string|null $errorType Specific error type to check (optional)
     */
    protected function shouldSkipValidation(string $notes, ?string $errorType = null): bool
    {
        if ('' === $notes) {
            return false;
        }

        // Check for validator name (class name without "Validator" suffix)
        $validatorName = str_replace('Validator', '', new \ReflectionClass($this)->getShortName());
        if (preg_match('/@n8n-validate-ignore\s+' . preg_quote($validatorName, '/') . '\b/i', $notes)) {
            return true;
        }

        // Check for specific error type if provided
        if (null !== $errorType && preg_match(
            '/@n8n-validate-ignore\s+' . preg_quote($errorType, '/') . '\b/i',
            $notes
        )) {
            return true;
        }

        return false;
    }

    /**
     * Extract expected input IDs from a trigger node.
     *
     * Handles two cases:
     * 1. inputSource: "jsonExample" - parses JSON and extracts first-level keys
     * 2. explicit workflowInputs definition - extracts IDs/names from the array
     *
     * @param array<string, mixed> $triggerNode
     *
     * @return string[]|null Array of input IDs/names, or null if validation should be skipped
     */
    protected function extractExpectedInputIds(array $triggerNode): ?array
    {
        $triggerNodeInfo = WorkflowLoader::extractNodeInfo($triggerNode);
        $triggerParams = $triggerNodeInfo['parameters'];

        // Handle different input source types
        $inputSource = $triggerParams['inputSource'] ?? null;

        if ('jsonExample' === $inputSource) {
            // Parse JSON example to extract first-level property names
            $jsonExample = $triggerParams['jsonExample'] ?? null;
            if (\is_string($jsonExample)) {
                try {
                    $exampleData = json_decode($jsonExample, true, 512, \JSON_THROW_ON_ERROR);
                    if (\is_array($exampleData)) {
                        // Only validate first-level keys
                        return array_keys($exampleData);
                    }
                } catch (\JsonException) {
                    // Invalid JSON, skip validation
                    return null;
                }
            }
        } else {
            // Use explicit workflowInputs definition
            $workflowInputs = $triggerParams['workflowInputs'] ?? null;
            if (!\is_array($workflowInputs)) {
                return null;
            }

            $expectedInputs = \is_array(
                $workflowInputs['values'] ?? null
            ) ? $workflowInputs['values'] : $workflowInputs;

            return array_column($expectedInputs, 'name');
        }

        return null;
    }
}
