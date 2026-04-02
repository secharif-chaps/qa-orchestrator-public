<?php

declare(strict_types=1);

namespace App\UserInterface\Command\Collect;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'collect:check:certificates',
    description: 'Check WebSocket SSL certificate configuration and file accessibility'
)]
class CheckWebSocketCertificatesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('WebSocket SSL Certificate Check');

        $hasErrors = false;

        // Check SSL enabled flag
        $sslEnabledEnv = ($_ENV['BAKUS_WEBSOCKET_SERVER_SSL_ENABLED'] ?? false) ?: getenv(
            'BAKUS_WEBSOCKET_SERVER_SSL_ENABLED'
        );
        $sslEnabled = filter_var(false !== $sslEnabledEnv ? $sslEnabledEnv : 'true', \FILTER_VALIDATE_BOOLEAN);

        $portEnv = ($_ENV['BAKUS_WEBSOCKET_SERVER_PORT'] ?? false) ?: getenv('BAKUS_WEBSOCKET_SERVER_PORT');
        $port = false !== $portEnv ? $portEnv : '5000';

        $io->section('Configuration');
        $io->table(['Setting', 'Value'], [['SSL Enabled', $sslEnabled ? '✓ Yes' : '✗ No'], ['Port', $port]]);

        if (!$sslEnabled) {
            $io->warning('SSL is disabled. Certificate checks are skipped.');

            return Command::SUCCESS;
        }

        // Check certificate paths
        $certPathEnv = ($_ENV['BAKUS_WEBSOCKET_SERVER_SSL_CERT'] ?? false) ?: getenv('BAKUS_WEBSOCKET_SERVER_SSL_CERT');
        $keyPathEnv = ($_ENV['BAKUS_WEBSOCKET_SERVER_SSL_KEY'] ?? false) ?: getenv('BAKUS_WEBSOCKET_SERVER_SSL_KEY');
        $certPath = (false !== $certPathEnv && \is_string($certPathEnv)) ? $certPathEnv : null;
        $keyPath = (false !== $keyPathEnv && \is_string($keyPathEnv)) ? $keyPathEnv : null;

        $io->section('Environment Variables');
        $io->table(
            ['Variable', 'Value', 'Status'],
            [
                [
                    'BAKUS_WEBSOCKET_SERVER_SSL_CERT',
                    $certPath ?: '<not set>',
                    $certPath ? '✓ Set' : '✗ Missing',
                ],
                ['BAKUS_WEBSOCKET_SERVER_SSL_KEY', $keyPath ?: '<not set>', $keyPath ? '✓ Set' : '✗ Missing'],
            ]
        );

        if (!$certPath || !$keyPath) {
            $io->error('Certificate paths are not configured!');

            return Command::FAILURE;
        }

        // Check certificate file
        $io->section('Certificate File');
        $certExists = file_exists($certPath);
        $certReadable = $certExists && is_readable($certPath);

        $certInfo = [];
        $certInfo[] = ['Path', $certPath];
        $certInfo[] = ['Exists', $certExists ? '✓ Yes' : '✗ No'];
        $certInfo[] = ['Readable', $certReadable ? '✓ Yes' : '✗ No'];

        if ($certExists) {
            $certSize = filesize($certPath);
            if (false !== $certSize) {
                $certInfo[] = ['Size', number_format($certSize) . ' bytes'];
            }
            $certInfo[] = ['Permissions', substr(\sprintf('%o', fileperms($certPath)), -4)];
            $certInfo[] = ['Owner', fileowner($certPath) . ':' . filegroup($certPath)];

            // Try to validate certificate
            if ($certReadable) {
                $certContent = file_get_contents($certPath);
                if (false !== $certContent && str_contains($certContent, 'BEGIN CERTIFICATE')) {
                    $certInfo[] = ['Format', '✓ Valid PEM format'];
                    $certValid = @openssl_x509_read($certContent);
                    $certInfo[] = ['Valid', false !== $certValid ? '✓ Valid certificate' : '✗ Invalid certificate'];
                    if (false !== $certValid) {
                        $certDetails = openssl_x509_parse($certValid);
                        if (false !== $certDetails) {
                            $certInfo[] = ['Subject', $certDetails['name'] ?? 'N/A'];
                            $certInfo[] = ['Valid From', date('Y-m-d H:i:s', $certDetails['validFrom_time_t'] ?? 0)];
                            $certInfo[] = ['Valid To', date('Y-m-d H:i:s', $certDetails['validTo_time_t'] ?? 0)];
                        }
                    }
                } else {
                    $certInfo[] = ['Format', '✗ Invalid PEM format'];
                    $hasErrors = true;
                }
            }
        } else {
            $hasErrors = true;
        }

        $io->table(['Property', 'Value'], $certInfo);

        // Check private key file
        $io->section('Private Key File');
        $keyExists = file_exists($keyPath);
        $keyReadable = $keyExists && is_readable($keyPath);

        $keyInfo = [];
        $keyInfo[] = ['Path', $keyPath];
        $keyInfo[] = ['Exists', $keyExists ? '✓ Yes' : '✗ No'];
        $keyInfo[] = ['Readable', $keyReadable ? '✓ Yes' : '✗ No'];

        if ($keyExists) {
            $keySize = filesize($keyPath);
            if (false !== $keySize) {
                $keyInfo[] = ['Size', number_format($keySize) . ' bytes'];
            }
            $keyInfo[] = ['Permissions', substr(\sprintf('%o', fileperms($keyPath)), -4)];
            $keyInfo[] = ['Owner', fileowner($keyPath) . ':' . filegroup($keyPath)];

            // Try to validate private key
            if ($keyReadable) {
                $keyContent = file_get_contents($keyPath);
                if (false !== $keyContent && str_contains($keyContent, 'BEGIN')) {
                    $keyInfo[] = ['Format', '✓ Valid PEM format'];
                    $keyValid = @openssl_pkey_get_private($keyContent);
                    $keyInfo[] = ['Valid', false !== $keyValid ? '✓ Valid private key' : '✗ Invalid private key'];
                    if (false === $keyValid) {
                        $hasErrors = true;
                    }
                } else {
                    $keyInfo[] = ['Format', '✗ Invalid PEM format'];
                    $hasErrors = true;
                }
            }
        } else {
            $hasErrors = true;
        }

        $io->table(['Property', 'Value'], $keyInfo);

        // Check if certificate and key match
        if ($certReadable && $keyReadable) {
            $io->section('Certificate/Key Match');
            $certContent = file_get_contents($certPath);
            $keyContent = file_get_contents($keyPath);

            $certModulus = null;
            $keyModulus = null;

            // Extract modulus from certificate
            if (false !== $certContent) {
                $cert = @openssl_x509_read($certContent);
                if (false !== $cert) {
                    $certDetails = openssl_pkey_get_public($cert);
                    if (false !== $certDetails) {
                        $certDetailsArray = openssl_pkey_get_details($certDetails);
                        if (false !== $certDetailsArray) {
                            $certModulus = $certDetailsArray['rsa']['n'] ?? null;
                        }
                    }
                }
            }

            // Extract modulus from private key
            if (false !== $keyContent) {
                $key = @openssl_pkey_get_private($keyContent);
                if (false !== $key) {
                    $keyDetails = openssl_pkey_get_details($key);
                    if (false !== $keyDetails) {
                        $keyModulus = $keyDetails['rsa']['n'] ?? null;
                    }
                }
            }

            if ($certModulus && $keyModulus) {
                $match = $certModulus === $keyModulus;
                $io->table(['Check', 'Result'], [['Certificate and Key Match', $match ? '✓ Yes' : '✗ No']]);

                if (!$match) {
                    $io->error('Certificate and private key do not match!');
                    $hasErrors = true;
                }
            } else {
                $io->warning('Could not verify certificate/key match (non-RSA key or extraction failed)');
            }
        }

        // Summary
        $io->section('Summary');
        if ($hasErrors) {
            $io->error('Certificate configuration has errors. Please fix the issues above.');

            return Command::FAILURE;
        }

        $io->success('All certificate checks passed!');

        return Command::SUCCESS;
    }
}
