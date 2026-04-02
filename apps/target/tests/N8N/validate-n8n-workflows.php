#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * N8N Workflow Validator - Main Orchestrator.
 *
 * This script runs multiple validators on N8N workflows to ensure:
 * 1. Naming Convention: Domain_Role_Action format
 * 2. Agent Configuration: fallback, retry, maxIterations, error output
 * 3. Feedback Nodes: success, message, context.execution.id
 * 4. RabbitMQ Messages: structure matches PHP class definitions
 * 5. Call Workflow Tool Nodes: version >= 2.2, description, workflow exists, input coherence
 * 6. Execute Sub-workflow Nodes: workflow exists, input coherence
 * 7. Node Version Requirements: Agent >= v3, LLM >= v1.3 with responseApiEnabled=false
 * 8. Webhook Path Uniqueness: Webhook_Trigger_TestInput paths must be unique across workflows
 *
 * Usage:
 *     php validate-n8n-workflows.php [--format=console|junit] [--output=file.xml] [--workflows-dir=/path]
 *
 * Exit codes:
 *     0 - All validations passed
 *     1 - Validation errors found
 *     2 - Script error (missing files, invalid arguments, etc.)
 */

// Bootstrap autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Tests\N8N\Validation\AgentNodeValidator;
use App\Tests\N8N\Validation\CallWorkflowToolNodeValidator;
use App\Tests\N8N\Validation\ConsoleReporter;
use App\Tests\N8N\Validation\ExecuteSubWorkflowNodeValidator;
use App\Tests\N8N\Validation\FeedbackNodeValidator;
use App\Tests\N8N\Validation\JUnitReporter;
use App\Tests\N8N\Validation\NamingConventionValidator;
use App\Tests\N8N\Validation\NodeVersionValidator;
use App\Tests\N8N\Validation\RabbitMQMessageValidator;
use App\Tests\N8N\Validation\WebhookPathUniqueValidator;

/**
 * Main execution function.
 *
 * @param array<int, string> $argv
 */
function main(array $argv): int
{
    // Parse command-line arguments
    $format = 'console';
    $outputFile = null;
    $workflowsDir = null;

    for ($i = 1; $i < count($argv); ++$i) {
        if (str_starts_with($argv[$i], '--format=')) {
            $format = substr($argv[$i], 9);
        } elseif (str_starts_with($argv[$i], '--output=')) {
            $outputFile = substr($argv[$i], 9);
        } elseif (str_starts_with($argv[$i], '--workflows-dir=')) {
            $workflowsDir = substr($argv[$i], 16);
        }
    }

    // Validate format option
    if (!in_array($format, ['console', 'junit'], true)) {
        fwrite(\STDERR, sprintf("Error: Invalid format \"%s\". Use \"console\" or \"junit\".\n", $format));

        return 2;
    }

    // JUnit format requires output file
    if ('junit' === $format && null === $outputFile) {
        fwrite(\STDERR, "Error: --output=<file> is required when using --format=junit\n");

        return 2;
    }

    // Determine workflows directory
    if (null === $workflowsDir) {
        $scriptDir = realpath(__DIR__);
        if (false === $scriptDir) {
            fwrite(\STDERR, "Error: Could not determine script directory\n");

            return 2;
        }
        $projectRoot = dirname($scriptDir, 3);
        $workflowsDir = realpath($projectRoot . '/docker/n8n/workflows') ?: $projectRoot . '/docker/n8n/workflows';
    }

    if (!is_dir($workflowsDir)) {
        fwrite(\STDERR, sprintf("Error: Workflows directory not found: %s\n", $workflowsDir));
        fwrite(\STDERR, "Hint: Use --workflows-dir=/path/to/workflows to specify custom path\n");

        return 2;
    }

    // Initialize validators
    $validators = [
        new NamingConventionValidator(),
        new AgentNodeValidator(),
        new FeedbackNodeValidator(),
        new RabbitMQMessageValidator(),
        new CallWorkflowToolNodeValidator(),
        new ExecuteSubWorkflowNodeValidator(),
        new NodeVersionValidator(),
        new WebhookPathUniqueValidator(),
    ];

    // Run all validators
    $results = [];
    foreach ($validators as $validator) {
        $results[] = $validator->validateDirectory($workflowsDir);
    }

    // Generate JUnit report if requested
    // Note: $outputFile is guaranteed to be non-null here due to earlier validation (line 63)
    if ('junit' === $format) {
        /** @var string $outputFile */
        JUnitReporter::generate($results, $outputFile);
        echo sprintf("✅ JUnit report generated: %s\n", $outputFile);
    }

    // Always print console output
    $reporter = new ConsoleReporter();
    $reporter->report($results, basename($workflowsDir));

    // Check if there are any critical errors
    $hasCriticalErrors = false;
    foreach ($results as $result) {
        if ($result->hasCriticalErrors()) {
            $hasCriticalErrors = true;
            break;
        }
    }

    return $hasCriticalErrors ? 1 : 0;
}

exit(main($argv));
