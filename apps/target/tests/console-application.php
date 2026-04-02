<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Console\Application;

$kernel = require __DIR__ . '/kernel-factory.php';

return new Application($kernel);
