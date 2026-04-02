<?php

declare(strict_types=1);

namespace App\Tests\N8N\Validation;

class JUnitReporter
{
    /**
     * Generate JUnit XML report from validation results.
     *
     * @param ValidationResult[] $results
     */
    public static function generate(array $results, string $outputFile): void
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $testsuites = $xml->createElement('testsuites');
        $xml->appendChild($testsuites);

        $totalTests = 0;
        $totalFailures = 0;
        $totalSkipped = 0;

        foreach ($results as $result) {
            $testsuite = self::createTestSuite($xml, $result);
            $testsuites->appendChild($testsuite);

            $totalTests += (int) $testsuite->getAttribute('tests');
            $totalFailures += (int) $testsuite->getAttribute('failures');
            $totalSkipped += (int) $testsuite->getAttribute('skipped');
        }

        $testsuites->setAttribute('tests', (string) $totalTests);
        $testsuites->setAttribute('failures', (string) $totalFailures);
        $testsuites->setAttribute('skipped', (string) $totalSkipped);

        // Create directory if it doesn't exist
        $directory = \dirname($outputFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        $xml->save($outputFile);
    }

    private static function createTestSuite(\DOMDocument $xml, ValidationResult $result): \DOMElement
    {
        $testsuite = $xml->createElement('testsuite');
        $testsuite->setAttribute('name', $result->validatorName);

        $totalTests = \count($result->errors);
        $failures = \count($result->getCriticalErrors());
        $warnings = \count($result->getWarnings());

        // Add successful workflows as passing tests
        $totalTests += $result->totalWorkflows;

        $testsuite->setAttribute('tests', (string) $totalTests);
        $testsuite->setAttribute('failures', (string) $failures);
        $testsuite->setAttribute('errors', '0');
        $testsuite->setAttribute('skipped', (string) $warnings);
        $testsuite->setAttribute('time', '0');

        // Add test cases for errors
        foreach ($result->errors as $error) {
            $testcase = self::createTestCase($xml, $error);
            $testsuite->appendChild($testcase);
        }

        return $testsuite;
    }

    private static function createTestCase(\DOMDocument $xml, ValidationError $error): \DOMElement
    {
        $testcase = $xml->createElement('testcase');
        $testcase->setAttribute(
            'name',
            \sprintf('%s :: %s (%s)', $error->workflowFile, $error->nodeName, $error->nodeId)
        );
        $testcase->setAttribute('classname', \sprintf('N8N.%s', $error->workflowFile));
        $testcase->setAttribute('time', '0');

        if ($error->isWarning()) {
            $skipped = $xml->createElement('skipped');
            $skipped->setAttribute('message', $error->message);
            $skipped->setAttribute('type', $error->errorType);
            $testcase->appendChild($skipped);
        } else {
            $failure = $xml->createElement('failure');
            $failure->setAttribute('message', $error->message);
            $failure->setAttribute('type', $error->errorType);
            $testcase->appendChild($failure);
        }

        return $testcase;
    }
}
