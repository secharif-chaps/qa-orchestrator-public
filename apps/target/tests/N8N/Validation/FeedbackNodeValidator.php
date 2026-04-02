<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

/**
 * Validates N8N feedback/response node configuration.
 *
 * Feedback nodes are Set nodes that format final workflow responses.
 * They must have names starting with "Feedback_".
 *
 * Requirements:
 * 1. Must construct a JSON object with:
 *    - success: boolean (true/false)
 *    - message: string (translated based on language)
 *    - context.execution.id: execution identifier for debugging
 */
class FeedbackNodeValidator extends AbstractValidator
{
    /**
     * Prefix to identify feedback nodes.
     */
    private const FEEDBACK_NODE_PREFIX = 'Feedback_';

    public function getName(): string
    {
        return 'N8N Feedback Node Configuration';
    }

    protected function validateWorkflow(string $filename, array $workflow): void
    {
        foreach ($workflow['nodes'] as $node) {
            $nodeInfo = WorkflowLoader::extractNodeInfo($node);

            // Check if node is a feedback node (Set node with specific naming patterns)
            if ('n8n-nodes-base.set' !== $nodeInfo['type']) {
                continue;
            }

            if (!$this->isFeedbackNode($nodeInfo['name'])) {
                continue;
            }

            // Check for @n8n-validate-ignore tag
            if ($this->shouldSkipValidation($nodeInfo['notes'])) {
                continue;
            }

            ++$this->totalNodes;
            $this->validateFeedbackNode($filename, $workflow['name'], $node);
        }
    }

    private function isFeedbackNode(string $nodeName): bool
    {
        return str_starts_with($nodeName, self::FEEDBACK_NODE_PREFIX);
    }

    /**
     * @param array<string, mixed> $node
     */
    private function validateFeedbackNode(string $filename, string $workflowName, array $node): void
    {
        $nodeInfo = WorkflowLoader::extractNodeInfo($node);
        $parameters = $nodeInfo['parameters'];
        $notes = $nodeInfo['notes'];

        // Check mode is "raw"
        $mode = $parameters['mode'] ?? null;
        if ('raw' !== $mode) {
            $modeDisplay = \is_string($mode) ? $mode : 'not set';
            $this->addValidationError(
                $filename,
                $workflowName,
                $nodeInfo,
                $notes,
                'FEEDBACK_WRONG_MODE',
                \sprintf(
                    'Feedback node "%s" should use mode="raw" (currently: %s)',
                    $nodeInfo['name'],
                    $modeDisplay
                ),
                ValidationSeverity::WARNING
            );

            return;
        }

        // Get jsonOutput
        $jsonOutput = $parameters['jsonOutput'] ?? null;
        if (!\is_string($jsonOutput) || '' === $jsonOutput) {
            $this->addValidationError(
                $filename,
                $workflowName,
                $nodeInfo,
                $notes,
                'FEEDBACK_MISSING_JSON_OUTPUT',
                \sprintf('Feedback node "%s" must have jsonOutput defined', $nodeInfo['name']),
                ValidationSeverity::CRITICAL
            );

            return;
        }

        // Remove N8N expression prefix if present
        $jsonContent = ltrim($jsonOutput, '=');

        // Validate required fields
        $this->validateRequiredFields($filename, $workflowName, $nodeInfo, $notes, $jsonContent);
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
        ValidationSeverity $severity = ValidationSeverity::CRITICAL,
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
            severity: $severity,
        );
    }

    /**
     * @param array{name: string, id: string, type: string, parameters: array<string, mixed>} $nodeInfo
     */
    private function validateRequiredFields(
        string $filename,
        string $workflowName,
        array $nodeInfo,
        string $notes,
        string $jsonContent,
    ): void {
        // 1. Check for "success" field
        if (!$this->containsField($jsonContent, 'success')) {
            $this->addValidationError(
                $filename,
                $workflowName,
                $nodeInfo,
                $notes,
                'FEEDBACK_MISSING_SUCCESS_FIELD',
                \sprintf(
                    'Feedback node "%s" must include "success: boolean" field in JSON output',
                    $nodeInfo['name']
                ),
                ValidationSeverity::CRITICAL
            );
        }

        // 2. Check for "message" field
        if (!$this->containsField($jsonContent, 'message')) {
            $this->addValidationError(
                $filename,
                $workflowName,
                $nodeInfo,
                $notes,
                'FEEDBACK_MISSING_MESSAGE_FIELD',
                \sprintf(
                    'Feedback node "%s" must include "message: string" field in JSON output',
                    $nodeInfo['name']
                ),
                ValidationSeverity::CRITICAL
            );
        }

        // 3. Check for "context.execution" or "context.execution.id"
        if (!$this->containsExecutionContext($jsonContent)) {
            $this->addValidationError(
                $filename,
                $workflowName,
                $nodeInfo,
                $notes,
                'FEEDBACK_MISSING_EXECUTION_CONTEXT',
                \sprintf(
                    'Feedback node "%s" must include "context.execution" ($execution) for debugging',
                    $nodeInfo['name']
                ),
                ValidationSeverity::CRITICAL
            );
        }

        // 4. Check for message translation (en/fr)
        if (!$this->hasTranslation($jsonContent)) {
            $this->addValidationError(
                $filename,
                $workflowName,
                $nodeInfo,
                $notes,
                'FEEDBACK_NO_TRANSLATION',
                \sprintf(
                    'Feedback node "%s" should translate message based on language (check for "language === \'en\'" or similar)',
                    $nodeInfo['name']
                ),
                ValidationSeverity::WARNING
            );
        }
    }

    private function containsField(string $jsonContent, string $fieldName): bool
    {
        // Check for field in JSON structure (accounting for whitespace variations)
        return (bool) preg_match('/["\']' . preg_quote($fieldName, '/') . '["\']\\s*:/i', $jsonContent);
    }

    private function containsExecutionContext(string $jsonContent): bool
    {
        // Check for context.execution or $execution variable
        return str_contains($jsonContent, 'execution')
               && (str_contains($jsonContent, '$execution') || str_contains($jsonContent, '"execution"'));
    }

    private function hasTranslation(string $jsonContent): bool
    {
        // Check for language-based conditions or translation patterns
        return str_contains($jsonContent, 'language')
               || str_contains($jsonContent, "=== 'en'")
               || str_contains($jsonContent, "=== 'fr'")
               || str_contains($jsonContent, '? ') && str_contains($jsonContent, ' : ');
    }
}
