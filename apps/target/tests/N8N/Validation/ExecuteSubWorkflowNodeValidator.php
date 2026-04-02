<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

/**
 * Validates N8N "Execute Sub-workflow" node configuration.
 *
 * Requirements:
 * 1. Referenced workflow must exist in the workflows directory
 * 2. Workflow inputs must be coherent with the called workflow
 *
 * @phpstan-import-type WorkflowType from WorkflowLoader
 */
class ExecuteSubWorkflowNodeValidator extends AbstractValidator
{
    /** @var array<string, WorkflowType> */
    private array $availableWorkflows = [];

    public function getName(): string
    {
        return 'Execute Sub-workflow Configuration';
    }

    public function validateDirectory(string $directory): ValidationResult
    {
        // Store directory and load all workflows for cross-referencing
        $this->availableWorkflows = WorkflowLoader::loadAllWorkflows($directory);

        return parent::validateDirectory($directory);
    }

    protected function validateWorkflow(string $filename, array $workflow): void
    {
        foreach ($workflow['nodes'] as $node) {
            $nodeInfo = WorkflowLoader::extractNodeInfo($node);

            // Check if node is an Execute Workflow type
            if ('n8n-nodes-base.executeWorkflow' !== $nodeInfo['type']) {
                continue;
            }

            // Check for @n8n-validate-ignore tag
            if ($this->shouldSkipValidation($nodeInfo['notes'])) {
                continue;
            }

            ++$this->totalNodes;
            $this->validateExecuteWorkflowNode($filename, $workflow['name'], $node);
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function validateExecuteWorkflowNode(string $filename, string $workflowName, array $node): void
    {
        $nodeInfo = WorkflowLoader::extractNodeInfo($node);
        $parameters = $nodeInfo['parameters'];
        $notes = $nodeInfo['notes'];

        // 1. Check referenced workflow exists
        $workflowIdParam = $parameters['workflowId'] ?? null;
        $workflowId = \is_array($workflowIdParam) ? ($workflowIdParam['value'] ?? null) : null;
        if (!\is_string($workflowId) || empty($workflowId)) {
            $this->addValidationError(
                $filename,
                $workflowName,
                $nodeInfo,
                $notes,
                'EXECUTE_WORKFLOW_MISSING_WORKFLOW_ID',
                \sprintf('Execute Sub-workflow node "%s" must have a workflowId defined', $nodeInfo['name']),
            );

            return;
        }

        $referencedWorkflow = $this->findWorkflowById($this->availableWorkflows, $workflowId);
        if (null === $referencedWorkflow) {
            $this->addValidationError(
                $filename,
                $workflowName,
                $nodeInfo,
                $notes,
                'EXECUTE_WORKFLOW_REFERENCED_WORKFLOW_NOT_FOUND',
                \sprintf(
                    'Execute Sub-workflow node "%s" references workflow ID "%s" which does not exist',
                    $nodeInfo['name'],
                    $workflowId
                ),
            );

            return;
        }

        // 2. Validate workflow inputs coherence
        $this->validateWorkflowInputsCoherence(
            $filename,
            $workflowName,
            $nodeInfo,
            $notes,
            $parameters,
            $referencedWorkflow
        );
    }

    /**
     * Add error only if not ignored by @n8n-validate-ignore tag.
     *
     * @param array{name: string, id: string, type: string, parameters: array<string, mixed>} $nodeInfo
     */
    private function addValidationError(
        string $filename,
        string $workflowName,
        array $nodeInfo,
        string $notes,
        string $errorType,
        string $message,
    ): void {
        if ($this->shouldSkipValidation($notes, $errorType)) {
            return;
        }

        $this->addError(
            workflowFile: $filename,
            workflowName: $workflowName,
            nodeName: $nodeInfo['name'],
            nodeId: $nodeInfo['id'],
            errorType: $errorType,
            message: $message,
        );
    }

    /**
     * Validate that workflow inputs are coherent with the called workflow.
     *
     * @param array{name: string, id: string, type: string, parameters: array<string, mixed>} $nodeInfo
     * @param array<string, mixed>                                                            $parameters
     * @param WorkflowType                                                                    $referencedWorkflow
     */
    private function validateWorkflowInputsCoherence(
        string $filename,
        string $workflowName,
        array $nodeInfo,
        string $notes,
        array $parameters,
        array $referencedWorkflow,
    ): void {
        // Get the workflow inputs definition from the calling node
        $workflowInputsParam = $parameters['workflowInputs'] ?? null;
        $workflowInputs = \is_array($workflowInputsParam) ? ($workflowInputsParam['value'] ?? []) : [];
        if (!\is_array($workflowInputs)) {
            return;
        }

        // Get the schema definition (expected inputs)
        $schema = \is_array($workflowInputsParam) ? ($workflowInputsParam['schema'] ?? []) : [];
        if (!\is_array($schema)) {
            return;
        }

        // Find the trigger node in the referenced workflow to get expected inputs
        $triggerNode = null;
        foreach ($referencedWorkflow['nodes'] as $node) {
            $refNodeInfo = WorkflowLoader::extractNodeInfo($node);
            if ('n8n-nodes-base.executeWorkflowTrigger' === $refNodeInfo['type']) {
                $triggerNode = $node;
                break;
            }
        }

        if (null === $triggerNode) {
            $this->addValidationError(
                $filename,
                $workflowName,
                $nodeInfo,
                $notes,
                'EXECUTE_WORKFLOW_TRIGGER_NODE_NOT_FOUND',
                \sprintf(
                    'Execute Sub-workflow node "%s" references workflow "%s" which does not have a trigger node defined',
                    $nodeInfo['name'],
                    $referencedWorkflow['name'],
                ),
            );

            return;
        }

        $expectedInputIds = $this->extractExpectedInputIds($triggerNode);
        if (null === $expectedInputIds) {
            return;
        }

        $providedInputIds = array_keys($workflowInputs);

        foreach ($expectedInputIds as $expectedInputId) {
            // @phpstan-ignore-next-line - Runtime safety check
            if (\is_string($expectedInputId) && !\in_array($expectedInputId, $providedInputIds, true)) {
                $this->addValidationError(
                    $filename,
                    $workflowName,
                    $nodeInfo,
                    $notes,
                    'EXECUTE_WORKFLOW_MISSING_REQUIRED_INPUT',
                    \sprintf(
                        'Execute Sub-workflow node "%s" is missing required input "%s" for workflow "%s"',
                        $nodeInfo['name'],
                        $expectedInputId,
                        $referencedWorkflow['name'],
                    ),
                );
            }
        }

        // Check for unexpected inputs
        foreach ($providedInputIds as $providedId) {
            if (!\in_array($providedId, $expectedInputIds, true)) {
                $this->addValidationError(
                    $filename,
                    $workflowName,
                    $nodeInfo,
                    $notes,
                    'EXECUTE_WORKFLOW_UNEXPECTED_INPUT',
                    \sprintf(
                        'Execute Sub-workflow node "%s" provides unexpected input "%s" not defined in workflow "%s"',
                        $nodeInfo['name'],
                        $providedId,
                        $referencedWorkflow['name'],
                    ),
                );
            }
        }
    }
}
