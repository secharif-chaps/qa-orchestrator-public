<?php

declare(strict_types=1);

$kernel = require __DIR__ . '/kernel-factory.php';
$kernel->boot();

return $kernel->getContainer()
    ->get('doctrine')
    ->getManager();
