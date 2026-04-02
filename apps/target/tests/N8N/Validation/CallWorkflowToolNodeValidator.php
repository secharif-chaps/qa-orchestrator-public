<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

/**
 * Validates N8N "Call n8n Workflow Tool" node configuration.
 *
 * Requirements:
 * 1. Description must be defined and significant (min 10 characters)
 * 2. Referenced workflow must exist in the workflows directory
 * 3. Workflow inputs must be coherent with the called workflow
 * 4. Auto-defined inputs (fromAI) must have defined and significant descriptions
 *
 * Note: Node version validation (>= 2.2) is handled by NodeVersionValidator
 *
 * @phpstan-import-type WorkflowType from WorkflowLoader
 */
class CallWorkflowToolNodeValidator extends AbstractValidator
{
    private const int MIN_DESCRIPTION_LENGTH = 10;

    /** @var array<string, WorkflowType> */
    private array $availableWorkflows = [];

    public function getName(): string
    {
        return 'Call Workflow Tool Configuration';
    }

    public function validateDirectory(string $directory): ValidationResult
    {
        // Load all workflows for cross-referencing
        $this->availableWorkflows = WorkflowLoader::loadAllWorkflows($directory);

        return parent::validateDirectory($directory);
    }

    protected function validateWorkflow(string $filename, array $workflow): void
    {
        foreach ($workflow['nodes'] as $node) {
            $nodeInfo = WorkflowLoader::extractNodeInfo($node);

            // Check if node is a Call Workflow Tool type
            if ('@n8n/n8n-nodes-langchain.toolWorkflow' !== $nodeInfo['type']) {
                continue;
            }

            ++$this->totalNodes;
            $this->validateToolWorkflowNode($filename, $workflow['name'], $node);
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function validateToolWorkflowNode(string $filename, string $workflowName, array $node): void
    {
        $nodeInfo = WorkflowLoader::extractNodeInfo($node);
        $parameters = $nodeInfo['parameters'];

        // 1. Check description is defined and significant
        $description = $parameters['description'] ?? null;
        if (!\is_string($description) || empty(trim($description))) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'TOOL_WORKFLOW_MISSING_DESCRIPTION',
                message: \sprintf('Call Workflow Tool node "%s" must have a description defined', $nodeInfo['name']),
            );
        } elseif (\strlen(trim($description)) < self::MIN_DESCRIPTION_LENGTH) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'TOOL_WORKFLOW_DESCRIPTION_TOO_SHORT',
                message: \sprintf(
                    'Call Workflow Tool node "%s" has a description that is too short (%d chars), must be at least %d characters',
                    $nodeInfo['name'],
                    \strlen(trim($description)),
                    self::MIN_DESCRIPTION_LENGTH
                ),
            );
        }

        // 2. Check referenced workflow exists
        $workflowIdParam = $parameters['workflowId'] ?? null;
        $workflowId = \is_array($workflowIdParam) ? ($workflowIdParam['value'] ?? null) : null;
        if (!\is_string($workflowId) || empty($workflowId)) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'TOOL_WORKFLOW_MISSING_WORKFLOW_ID',
                message: \sprintf('Call Workflow Tool node "%s" must have a workflowId defined', $nodeInfo['name']),
            );
        } else {
            $referencedWorkflow = $this->findWorkflowById($this->availableWorkflows, $workflowId);
            if (null === $referencedWorkflow) {
                $this->addError(
                    workflowFile: $filename,
                    workflowName: $workflowName,
                    nodeName: $nodeInfo['name'],
                    nodeId: $nodeInfo['id'],
                    errorType: 'TOOL_WORKFLOW_REFERENCED_WORKFLOW_NOT_FOUND',
                    message: \sprintf(
                        'Call Workflow Tool node "%s" references workflow ID "%s" which does not exist',
                        $nodeInfo['name'],
                        $workflowId
                    ),
                );
            } else {
                // 3. Validate workflow inputs coherence
                $this->validateWorkflowInputsCoherence(
                    $filename,
                    $workflowName,
                    $nodeInfo,
                    $parameters,
                    $referencedWorkflow
                );
            }
        }

        // 4. Validate auto-defined inputs have significant descriptions
        $this->validateAutoDefinedInputDescriptions($filename, $workflowName, $nodeInfo, $parameters);
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
            // No trigger node found, can't validate inputs
            return;
        }

        // Extract expected input IDs from trigger node
        $expectedInputIds = $this->extractExpectedInputIds($triggerNode);
        if (null === $expectedInputIds) {
            return;
        }

        $providedInputIds = array_keys($workflowInputs);

        foreach ($expectedInputIds as $expectedInputId) {
            // @phpstan-ignore-next-line - Runtime safety check
            if (\is_string($expectedInputId) && !\in_array($expectedInputId, $providedInputIds, true)) {
                $this->addError(
                    workflowFile: $filename,
                    workflowName: $workflowName,
                    nodeName: $nodeInfo['name'],
                    nodeId: $nodeInfo['id'],
                    errorType: 'TOOL_WORKFLOW_MISSING_REQUIRED_INPUT',
                    message: \sprintf(
                        'Call Workflow Tool node "%s" is missing required input "%s" for workflow "%s"',
                        $nodeInfo['name'],
                        $expectedInputId,
                        $referencedWorkflow['name']
                    ),
                );
            }
        }

        // Check for unexpected inputs
        foreach ($providedInputIds as $providedId) {
            if (!\in_array($providedId, $expectedInputIds, true)) {
                $this->addError(
                    workflowFile: $filename,
                    workflowName: $workflowName,
                    nodeName: $nodeInfo['name'],
                    nodeId: $nodeInfo['id'],
                    errorType: 'TOOL_WORKFLOW_UNEXPECTED_INPUT',
                    message: \sprintf(
                        'Call Workflow Tool node "%s" provides unexpected input "%s" not defined in workflow "%s"',
                        $nodeInfo['name'],
                        $providedId,
                        $referencedWorkflow['name']
                    ),
                );
            }
        }
    }

    /**
     * Validate that auto-defined inputs (fromAI) have significant descriptions.
     *
     * @param array{name: string, id: string, type: string, parameters: array<string, mixed>} $nodeInfo
     * @param array<string, mixed>                                                            $parameters
     */
    private function validateAutoDefinedInputDescriptions(
        string $filename,
        string $workflowName,
        array $nodeInfo,
        array $parameters,
    ): void {
        $workflowInputsParam = $parameters['workflowInputs'] ?? null;
        $workflowInputs = \is_array($workflowInputsParam) ? ($workflowInputsParam['value'] ?? []) : [];
        if (!\is_array($workflowInputs)) {
            return;
        }

        foreach ($workflowInputs as $inputName => $inputValue) {
            if (!\is_string($inputValue)) {
                continue;
            }

            // Check if this is an auto-generated fromAI input
            if (!str_contains($inputValue, '/*n8n-auto-generated-fromAI-override*/')) {
                continue;
            }

            // Extract the $fromAI call parameters using regex
            // Pattern: $fromAI('fieldName', 'description', 'type')
            $pattern = '/\$fromAI\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"`]([^\'"`]+)[\'"`]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/';
            if (preg_match($pattern, $inputValue, $matches)) {
                $fieldName = $matches[1];
                $description = $matches[2];

                // Validate description is significant
                if (empty(trim($description))) {
                    $this->addError(
                        workflowFile: $filename,
                        workflowName: $workflowName,
                        nodeName: $nodeInfo['name'],
                        nodeId: $nodeInfo['id'],
                        errorType: 'TOOL_WORKFLOW_AUTO_INPUT_EMPTY_DESCRIPTION',
                        message: \sprintf(
                            'Call Workflow Tool node "%s" has auto-defined input "%s" with empty description',
                            $nodeInfo['name'],
                            $fieldName
                        ),
                        severity: ValidationSeverity::CRITICAL
                    );
                } elseif (\strlen(trim($description)) < self::MIN_DESCRIPTION_LENGTH) {
                    $this->addError(
                        workflowFile: $filename,
                        workflowName: $workflowName,
                        nodeName: $nodeInfo['name'],
                        nodeId: $nodeInfo['id'],
                        errorType: 'TOOL_WORKFLOW_AUTO_INPUT_SHORT_DESCRIPTION',
                        message: \sprintf(
                            'Call Workflow Tool node "%s" has auto-defined input "%s" with description too short (%d chars), must be at least %d characters',
                            $nodeInfo['name'],
                            $fieldName,
                            \strlen(trim($description)),
                            self::MIN_DESCRIPTION_LENGTH
                        ),
                        severity: ValidationSeverity::CRITICAL
                    );
                }
            }
        }
    }
}
