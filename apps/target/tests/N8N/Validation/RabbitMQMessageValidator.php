<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

/**
 * Validates that RabbitMQ nodes in N8N workflows send messages
 * that match the expected format of the PHP message classes.
 *
 * Checks:
 * - RabbitMQ nodes have 'type' header with valid PHP class
 * - Message JSON includes all required class properties
 * - Warns about unexpected fields (may be intentional metadata)
 * - Queue names: publishers use 'agent_responses', triggers use 'agent_commands'
 */
class RabbitMQMessageValidator extends AbstractValidator
{
    /** @var array<string, ClassPropertyDefinition[]> */
    private array $classCache = [];
    private const PUBLISHER_QUEUE = 'agent_responses';
    private const TRIGGER_QUEUE = 'agent_commands';

    public function getName(): string
    {
        return 'N8N RabbitMQ Message Format';
    }

    protected function validateWorkflow(string $filename, array $workflow): void
    {
        foreach ($workflow['nodes'] as $node) {
            $nodeInfo = WorkflowLoader::extractNodeInfo($node);

            if ('n8n-nodes-base.rabbitmq' === $nodeInfo['type']) {
                ++$this->totalNodes;
                $this->validateRabbitMQNode($filename, $workflow['name'], $node);
            } elseif ('n8n-nodes-base.rabbitmqTrigger' === $nodeInfo['type']) {
                ++$this->totalNodes;
                $this->validateRabbitMQTrigger($filename, $workflow['name'], $node);
            }
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function validateRabbitMQNode(string $filename, string $workflowName, array $node): void
    {
        $nodeInfo = WorkflowLoader::extractNodeInfo($node);
        $parameters = $nodeInfo['parameters'];

        // Check if validation should be skipped for this node
        if ($this->shouldSkipValidation($nodeInfo['notes'])) {
            return;
        }

        // Validate queue name
        $this->validateQueue(
            workflowFile: $filename,
            workflowName: $workflowName,
            nodeName: $nodeInfo['name'],
            nodeId: $nodeInfo['id'],
            parameters: $parameters,
            expectedQueue: self::PUBLISHER_QUEUE,
            nodeType: 'RabbitMQ publisher',
        );

        // Extract the 'type' header
        $options = \is_array($parameters['options'] ?? null) ? $parameters['options'] : [];
        $headersContainer = \is_array($options['headers'] ?? null) ? $options['headers'] : [];
        $headers = \is_array($headersContainer['header'] ?? null) ? $headersContainer['header'] : [];

        $typeHeader = null;

        foreach ($headers as $header) {
            if (!\is_array($header)) {
                continue;
            }

            if (($header['key'] ?? '') === 'type') {
                $typeHeader = \is_string($header['value'] ?? null) ? $header['value'] : null;
                break;
            }
        }

        if (null === $typeHeader) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'RABBITMQ_MISSING_TYPE_HEADER',
                message: "RabbitMQ node '{$nodeInfo['name']}' missing 'type' header",
                severity: ValidationSeverity::CRITICAL
            );

            return;
        }

        // Skip dynamic type headers (N8N expressions)
        if (str_starts_with($typeHeader, '=')) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'RABBITMQ_DYNAMIC_TYPE_HEADER',
                message: "Type header uses N8N expression - cannot validate statically: {$typeHeader}",
                severity: ValidationSeverity::WARNING
            );

            return;
        }

        // Validate the class exists
        if (!class_exists($typeHeader)) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'RABBITMQ_CLASS_NOT_FOUND',
                message: \sprintf("PHP class '%s' not found in codebase", $typeHeader),
                severity: ValidationSeverity::CRITICAL
            );

            return;
        }

        // Get class properties
        $properties = $this->getClassProperties($typeHeader);

        // Extract message template
        $messageTemplate = \is_string($parameters['message'] ?? null) ? $parameters['message'] : '';

        if ('' === $messageTemplate) {
            $this->addError(
                workflowFile: $filename,
                workflowName: $workflowName,
                nodeName: $nodeInfo['name'],
                nodeId: $nodeInfo['id'],
                errorType: 'RABBITMQ_MISSING_MESSAGE',
                message: 'RabbitMQ node has no message defined',
                severity: ValidationSeverity::CRITICAL
            );

            return;
        }

        // Extract fields from JSON template
        $definedFields = $this->extractJsonFields($messageTemplate);

        // Validate properties
        $this->validateProperties(
            workflowFile: $filename,
            workflowName: $workflowName,
            nodeName: $nodeInfo['name'],
            nodeId: $nodeInfo['id'],
            className: $typeHeader,
            properties: $properties,
            definedFields: $definedFields,
            notes: $nodeInfo['notes']
        );

        // Validate message translation for chat actions
        $this->validateChatMessageTranslation(
            workflowFile: $filename,
            workflowName: $workflowName,
            nodeName: $nodeInfo['name'],
            nodeId: $nodeInfo['id'],
            className: $typeHeader,
            messageTemplate: $messageTemplate,
            notes: $nodeInfo['notes']
        );
    }

    /**
     * @return ClassPropertyDefinition[]
     */
    private function getClassProperties(string $className): array
    {
        if (isset($this->classCache[$className])) {
            return $this->classCache[$className];
        }

        if (!class_exists($className)) {
            return [];
        }

        try {
            $reflection = new \ReflectionClass($className);
            $properties = [];

            // Get constructor parameters (promoted properties in PHP 8+)
            $constructor = $reflection->getConstructor();

            if (null !== $constructor) {
                foreach ($constructor->getParameters() as $param) {
                    $type = $param->getType();
                    $typeName = 'mixed';
                    $nullable = false;

                    if ($type instanceof \ReflectionNamedType) {
                        $typeName = $type->getName();
                        $nullable = $type->allowsNull();
                    } elseif ($type instanceof \ReflectionUnionType) {
                        $types = array_map(
                            static fn (\ReflectionIntersectionType|\ReflectionNamedType $t): string => $t instanceof \ReflectionNamedType ? $t->getName() : 'mixed',
                            $type->getTypes()
                        );
                        $typeName = implode('|', $types);
                        $nullable = $type->allowsNull();
                    }

                    $properties[] = new ClassPropertyDefinition(
                        name: $param->getName(),
                        type: $typeName,
                        nullable: $nullable,
                        hasDefault: $param->isDefaultValueAvailable()
                    );
                }
            }

            // Also check for parent class properties
            $parent = $reflection->getParentClass();
            if (false !== $parent) {
                $parentProperties = $this->getClassProperties($parent->getName());
                $properties = array_merge($parentProperties, $properties);
            }

            $this->classCache[$className] = $properties;

            return $properties;
        } catch (\ReflectionException) {
            return [];
        }
    }

    /**
     * Extract field names from a JSON message template (only top-level fields).
     *
     * @return string[]
     */
    private function extractJsonFields(string $messageTemplate): array
    {
        // Remove N8N expressions to simplify parsing (replace {{...}} with placeholder values)
        $cleanedTemplate = preg_replace('/\{\{[^}]+\}\}/', '""', $messageTemplate);

        if (null === $cleanedTemplate) {
            return [];
        }

        // Remove the leading '=' if present (N8N expression prefix)
        $cleanedTemplate = ltrim($cleanedTemplate, '=');

        // Try to extract only top-level fields by tracking brace depth
        $fields = [];
        $depth = 0;
        $inString = false;
        $currentField = '';
        $capturingField = false;

        for ($i = 0; $i < \strlen($cleanedTemplate); ++$i) {
            $char = $cleanedTemplate[$i];

            // Track string state (to ignore braces inside strings)
            if ('"' === $char && (0 === $i || '\\' !== $cleanedTemplate[$i - 1])) {
                $inString = !$inString;

                // If we just closed a string, check if it's followed by ':' (field name)
                if (!$inString && $capturingField) {
                    // Look ahead to check if this is a field name (followed by ':')
                    $nextNonWhitespace = null;
                    for ($j = $i + 1; $j < \strlen($cleanedTemplate); ++$j) {
                        if (!ctype_space($cleanedTemplate[$j])) {
                            $nextNonWhitespace = $cleanedTemplate[$j];
                            break;
                        }
                    }

                    if (':' === $nextNonWhitespace && '' !== trim($currentField)) {
                        $fields[] = $currentField;
                    }

                    $currentField = '';
                    $capturingField = false;
                } elseif ($inString && 1 === $depth) {
                    // Start capturing field name at depth 1
                    $capturingField = true;
                }

                continue;
            }

            if ($inString) {
                // Capture field name characters
                if ($capturingField) {
                    $currentField .= $char;
                }
                continue;
            }

            // Track brace depth
            if ('{' === $char) {
                ++$depth;
            } elseif ('}' === $char) {
                --$depth;
            }
        }

        return array_unique($fields);
    }

    /**
     * @param ClassPropertyDefinition[] $properties
     * @param string[]                  $definedFields
     */
    private function validateProperties(
        string $workflowFile,
        string $workflowName,
        string $nodeName,
        string $nodeId,
        string $className,
        array $properties,
        array $definedFields,
        string $notes = '',
    ): void {
        // Build required and all property names
        $requiredProps = [];
        $allProps = [];

        foreach ($properties as $prop) {
            $allProps[] = $prop->name;
            if (!$prop->nullable && !$prop->hasDefault) {
                $requiredProps[] = $prop->name;
            }
        }

        // Check for missing required fields
        $missingFields = array_diff($requiredProps, $definedFields);

        if (!empty($missingFields) && !$this->shouldSkipValidation($notes, 'RABBITMQ_MISSING_REQUIRED_FIELDS')) {
            $this->addError(
                workflowFile: $workflowFile,
                workflowName: $workflowName,
                nodeName: $nodeName,
                nodeId: $nodeId,
                errorType: 'RABBITMQ_MISSING_REQUIRED_FIELDS',
                message: \sprintf(
                    'Missing required fields: %s (class: %s)',
                    implode(', ', array_values($missingFields)),
                    $className
                ),
                severity: ValidationSeverity::CRITICAL
            );
        }

        // Check for unexpected fields (fields not in the class)
        // Note: This is a WARNING, not an ERROR, as RabbitMQ messages may contain
        // additional metadata that is not mapped to class properties
        $unexpectedFields = array_diff($definedFields, $allProps);

        if (!empty($unexpectedFields) && !$this->shouldSkipValidation($notes, 'RABBITMQ_UNEXPECTED_FIELDS')) {
            $this->addError(
                workflowFile: $workflowFile,
                workflowName: $workflowName,
                nodeName: $nodeName,
                nodeId: $nodeId,
                errorType: 'RABBITMQ_UNEXPECTED_FIELDS',
                message: \sprintf(
                    'Unexpected fields not in class: %s (class: %s) - This may be intentional for metadata',
                    implode(', ', array_values($unexpectedFields)),
                    $className
                ),
                severity: ValidationSeverity::WARNING
            );
        }
    }

    /**
     * Validates that chat action messages have translated message field.
     */
    private function validateChatMessageTranslation(
        string $workflowFile,
        string $workflowName,
        string $nodeName,
        string $nodeId,
        string $className,
        string $messageTemplate,
        string $notes,
    ): void {
        // Check if validation should be skipped for this validator or specific error
        if ($this->shouldSkipValidation($notes) || $this->shouldSkipValidation(
            $notes,
            'RABBITMQ_CHAT_MESSAGE_NOT_TRANSLATED'
        )) {
            return;
        }

        // Define classes that require message translation
        $chatActionClasses = [
            'App\\Application\\Chat\\SystemMessageAction',
            'App\\Application\\Chat\\ModelMessageAction',
        ];

        // Check if this class requires message translation
        if (!\in_array($className, $chatActionClasses, true)) {
            return;
        }

        // Check if message field exists
        $definedFields = $this->extractJsonFields($messageTemplate);
        if (!\in_array('message', $definedFields, true)) {
            $this->addError(
                workflowFile: $workflowFile,
                workflowName: $workflowName,
                nodeName: $nodeName,
                nodeId: $nodeId,
                errorType: 'RABBITMQ_CHAT_MISSING_MESSAGE',
                message: \sprintf(
                    'Chat action "%s" must include "message" field (class: %s)',
                    $nodeName,
                    $className
                ),
                severity: ValidationSeverity::CRITICAL
            );

            return;
        }

        // Extract message field value from template
        $messageFieldValue = $this->extractMessageFieldValue($messageTemplate);

        if (null === $messageFieldValue) {
            return; // Cannot validate dynamic expressions
        }

        // Check if message is translated
        if (!$this->hasTranslationPattern($messageFieldValue)) {
            $this->addError(
                workflowFile: $workflowFile,
                workflowName: $workflowName,
                nodeName: $nodeName,
                nodeId: $nodeId,
                errorType: 'RABBITMQ_CHAT_MESSAGE_NOT_TRANSLATED',
                message: \sprintf(
                    'Chat action "%s" message field should be translated based on language (class: %s)',
                    $nodeName,
                    $className
                ),
                severity: ValidationSeverity::CRITICAL
            );
        }
    }

    /**
     * Extracts the value of the "message" field from JSON template.
     *
     * @return string|null Returns null if cannot extract or is dynamic expression
     */
    private function extractMessageFieldValue(string $messageTemplate): ?string
    {
        // Remove N8N expression prefix if present
        $cleanedTemplate = ltrim($messageTemplate, '=');

        // Try to find the message field and its value
        // Pattern: "message": "value" or 'message': 'value' or message: value
        if (preg_match('/["\']?message["\']?\s*:\s*(.+?)(?:,|\})/s', $cleanedTemplate, $matches)) {
            $messageValue = trim($matches[1]);

            // If it starts with {{ or $, it's a dynamic N8N expression - we can analyze it
            return $messageValue;
        }

        return null;
    }

    /**
     * Checks if a message value contains translation patterns.
     */
    private function hasTranslationPattern(string $messageValue): bool
    {
        // Check for language-based conditions or translation patterns
        return str_contains($messageValue, 'language')
               || str_contains($messageValue, "=== 'en'")
               || str_contains($messageValue, "=== 'fr'")
               || str_contains($messageValue, '=== "en"')
               || str_contains($messageValue, '=== "fr"')
               || (str_contains($messageValue, '?') && str_contains($messageValue, ':'));
    }

    /**
     * @param array<string, mixed> $node
     */
    private function validateRabbitMQTrigger(string $filename, string $workflowName, array $node): void
    {
        $nodeInfo = WorkflowLoader::extractNodeInfo($node);
        $parameters = $nodeInfo['parameters'];

        // Check if validation should be skipped for this node
        if ($this->shouldSkipValidation($nodeInfo['notes'])) {
            return;
        }

        // Validate queue name
        $this->validateQueue(
            workflowFile: $filename,
            workflowName: $workflowName,
            nodeName: $nodeInfo['name'],
            nodeId: $nodeInfo['id'],
            parameters: $parameters,
            expectedQueue: self::TRIGGER_QUEUE,
            nodeType: 'RabbitMQ trigger',
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function validateQueue(
        string $workflowFile,
        string $workflowName,
        string $nodeName,
        string $nodeId,
        array $parameters,
        string $expectedQueue,
        string $nodeType,
    ): void {
        $actualQueue = $parameters['queue'] ?? null;

        if (!\is_string($actualQueue) || '' === $actualQueue) {
            $this->addError(
                workflowFile: $workflowFile,
                workflowName: $workflowName,
                nodeName: $nodeName,
                nodeId: $nodeId,
                errorType: 'RABBITMQ_MISSING_QUEUE',
                message: \sprintf('%s "%s" missing queue parameter', $nodeType, $nodeName),
                severity: ValidationSeverity::CRITICAL,
            );

            return;
        }

        // Skip dynamic queue names (N8N expressions)
        if (str_starts_with($actualQueue, '=')) {
            $this->addError(
                workflowFile: $workflowFile,
                workflowName: $workflowName,
                nodeName: $nodeName,
                nodeId: $nodeId,
                errorType: 'RABBITMQ_DYNAMIC_QUEUE',
                message: \sprintf(
                    '%s "%s" uses dynamic queue - cannot validate statically: %s',
                    $nodeType,
                    $nodeName,
                    $actualQueue,
                ),
                severity: ValidationSeverity::WARNING,
            );

            return;
        }

        if ($actualQueue !== $expectedQueue) {
            $this->addError(
                workflowFile: $workflowFile,
                workflowName: $workflowName,
                nodeName: $nodeName,
                nodeId: $nodeId,
                errorType: 'RABBITMQ_WRONG_QUEUE',
                message: \sprintf(
                    '%s "%s" must use queue "%s" (currently: "%s")',
                    $nodeType,
                    $nodeName,
                    $expectedQueue,
                    $actualQueue,
                ),
                severity: ValidationSeverity::CRITICAL,
            );
        }
    }
}
