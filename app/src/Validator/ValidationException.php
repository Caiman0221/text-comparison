<?php

declare(strict_types = 1);

namespace App\Validator;

use Exception;

/**
 * Ошибка валидации данных.
 */
class ValidationException extends Exception
{
	public function __construct(string $message)
	{
		parent::__construct($message);
	}
}