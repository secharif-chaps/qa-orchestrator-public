<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

new Dotenv()
    ->bootEnv(__DIR__ . '/../.env');

$env = $_SERVER['APP_ENV'] ?? 'dev';
if (!is_string($env)) {
    throw new RuntimeException('APP_ENV must be a string.');
}

if (!in_array($env, ['dev', 'test'], true)) {
    throw new RuntimeException(sprintf('Invalid environment: %s', $env));
}

$kernel = new Kernel($env, (bool) $_SERVER['APP_DEBUG']);

return $kernel;
