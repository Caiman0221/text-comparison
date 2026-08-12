<?php

declare(strict_types = 1);

require_once __DIR__ . '/../src/bootstrap.php';

$router = new App\Router();
$router->dispatch();