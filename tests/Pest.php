<?php

declare(strict_types=1);

use Tests\TestCase;

if (! defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}

pest()->extend(TestCase::class)
    ->in('Feature');
