<?php

declare(strict_types = 1);

spl_autoload_register(function(string $class): void
{
	$prefix = 'App\\';

	if(!str_starts_with($class, $prefix))
	{
		return;
	}

	$relativeClass = substr($class, strlen($prefix));
	$relativePath  = str_replace('\\', '/', $relativeClass);

	$file = __DIR__ . '/../src/' . $relativePath . '.php';

	if(file_exists($file))
	{
		require_once $file;
	}
});