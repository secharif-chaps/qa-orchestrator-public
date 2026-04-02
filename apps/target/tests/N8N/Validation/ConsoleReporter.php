<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

class ConsoleReporter
{
    private ConsoleOutput $output;

    public function __construct()
    {
        $this->output = new ConsoleOutput();

        // Setup output formatter styles
        $formatter = $this->output->getFormatter();
        $formatter->setStyle('error', new OutputFormatterStyle('red', null, ['bold']));
        $formatter->setStyle('comment', new OutputFormatterStyle('yellow'));
        $formatter->setStyle('info', new OutputFormatterStyle('green'));
    }

    /**
     * Report validation results.
     *
     * @param ValidationResult[] $results
     */
    public function report(array $results, string $workflowsDir): void
    {
        $this->output->writeln(\sprintf('<info>🔍 Validating workflows in %s</info>', $workflowsDir));
        $this->output->writeln('');

        $hasErrors = false;
        foreach ($results as $result) {
            if ($result->hasCriticalErrors() || \count($result->getWarnings()) > 0) {
                $hasErrors = true;
                $this->reportValidatorErrors($result);
            } else {
                $this->output->writeln(\sprintf('<info>✅ %s: All checks passed!</info>', $result->validatorName));
            }
        }

        if (!$hasErrors) {
            $this->output->writeln('');
            $this->output->writeln('<info>✅ All validations passed successfully!</info>');

            return;
        }

        $this->output->writeln('');
        $this->printSummary($results);
    }

    private function reportValidatorErrors(ValidationResult $result): void
    {
        $criticalCount = \count($result->getCriticalErrors());
        $warningCount = \count($result->getWarnings());

        $status = $criticalCount > 0 ? '<error>❌</error>' : '<comment>⚠️</comment>';

        $this->output->writeln(\sprintf(
            '%s %s: %d critical, %d warnings',
            $status,
            $result->validatorName,
            $criticalCount,
            $warningCount
        ));

        // Group errors by workflow
        $errorsByWorkflow = [];
        foreach ($result->errors as $error) {
            $errorsByWorkflow[$error->workflowFile][] = $error;
        }

        ksort($errorsByWorkflow);

        foreach ($errorsByWorkflow as $workflowFile => $errors) {
            $this->output->writeln(\sprintf('  <comment>📄 %s</comment>', $workflowFile));

            foreach ($errors as $error) {
                $errorColor = $error->isWarning() ? 'comment' : 'error';

                $this->output->writeln(\sprintf('    Node: %s (ID: %s)', $error->nodeName, $error->nodeId));
                $this->output->writeln(\sprintf(
                    '    [<%s>%s</%s>] %s',
                    $errorColor,
                    $error->errorType,
                    $errorColor,
                    $error->message
                ));
                $this->output->writeln('');
            }
        }

        $this->output->writeln('');
    }

    /**
     * @param ValidationResult[] $results
     */
    private function printSummary(array $results): void
    {
        $this->output->writeln(
            '<comment>═══════════════════════════════════════════════════════════════</comment>'
        );
        $this->output->writeln('<comment>📊 VALIDATION SUMMARY</comment>');
        $this->output->writeln(
            '<comment>═══════════════════════════════════════════════════════════════</comment>'
        );
        $this->output->writeln('');

        $totalCritical = 0;
        $totalWarnings = 0;
        $totalWorkflows = 0;
        $totalNodes = 0;

        foreach ($results as $result) {
            $critical = \count($result->getCriticalErrors());
            $warnings = \count($result->getWarnings());

            $totalCritical += $critical;
            $totalWarnings += $warnings;
            $totalWorkflows = max($totalWorkflows, $result->totalWorkflows);
            $totalNodes += $result->totalNodes;

            if ($critical > 0 || $warnings > 0) {
                $this->output->writeln(\sprintf(
                    '<info>%s:</info> <error>%d critical</error>, <comment>%d warnings</comment>',
                    $result->validatorName,
                    $critical,
                    $warnings
                ));
            }
        }

        $this->output->writeln('');

        if ($totalCritical > 0) {
            $this->output->writeln(\sprintf('<error>🔴 CRITICAL ERRORS: %d (will fail CI)</error>', $totalCritical));
        }

        if ($totalWarnings > 0) {
            $this->output->writeln(
                \sprintf('<comment>🟡 WARNINGS: %d (informational only)</comment>', $totalWarnings)
            );
        }

        $this->output->writeln('');
        $this->output->writeln(\sprintf('📄 WORKFLOWS VALIDATED: %d', $totalWorkflows));
        $this->output->writeln(\sprintf('📊 NODES CHECKED: %d', $totalNodes));
        $this->output->writeln('');
    }
}
