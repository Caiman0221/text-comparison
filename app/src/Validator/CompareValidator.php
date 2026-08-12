<?php

declare(strict_types = 1);

namespace App\Validator;

/**
 * Класс для валидации данных сравнения текстов.
 * 1) проверяем тип поля (должен быть string)
 * 2) проверяем кодировку (должна быть UTF-8)
 * 3) ограничение по длине строки (не более 10000 символов), чтобы сервер не схлопнулся
 */
class CompareValidator
{
	private const MAX_LENGTH = 10000;

	/**
	 * @param mixed $oldText
	 * @param mixed $newText
	 *
	 * @throws ValidationException
	 */
	public function validate(mixed $oldText, mixed $newText): void
	{
		$this->validateField($oldText, 'old_text');
		$this->validateField($newText, 'new_text');
	}

	/**
	 * @param mixed  $value
	 * @param string $fieldName
	 *
	 * @throws ValidationException
	 */
	private function validateField(mixed $value, string $fieldName): void
	{
		if(!is_string($value))
		{
			throw new ValidationException("Поле {$fieldName} должно быть строкой");
		}

		if(!mb_check_encoding($value, 'UTF-8'))
		{
			throw new ValidationException("Поле {$fieldName} содержит некорректную кодировку");
		}

		if(mb_strlen($value) > self::MAX_LENGTH)
		{
			throw new ValidationException(
				"Поле {$fieldName} превышает максимальную длину в " . self::MAX_LENGTH . ' символов'
			);
		}
	}
}