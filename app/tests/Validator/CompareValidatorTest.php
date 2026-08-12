<?php


declare(strict_types = 1);

namespace Tests\Validator;

use App\Validator\CompareValidator;
use App\Validator\ValidationException;
use PHPUnit\Framework\TestCase;

class CompareValidatorTest extends TestCase
{
	private CompareValidator $validator;

	protected function setUp(): void
	{
		$this->validator = new CompareValidator();
	}

	/**
	 * @throws ValidationException
	 */
	public function testValidStringsPassWithoutException(): void
	{
		$this->expectNotToPerformAssertions();

		$this->validator->validate('Старый текст.', 'Новый текст.');
	}

	/**
	 * @throws ValidationException
	 */
	public function testEmptyStringsAreValid(): void
	{
		// По ТЗ пустая строка — допустимый кейс (например, весь текст добавлен)
		$this->expectNotToPerformAssertions();

		$this->validator->validate('', 'Новый текст.');
	}

	public function testNonStringOldTextThrowsException(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('old_text должно быть строкой');

		$this->validator->validate(['not', 'a', 'string'], 'Новый текст.');
	}

	public function testNonStringNewTextThrowsException(): void
	{
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessage('new_text должно быть строкой');

		$this->validator->validate('Старый текст.', 12345);
	}

	public function testTextExceedingMaxLengthThrowsException(): void
	{
		$tooLong = str_repeat('а', 10001);

		$this->expectException(ValidationException::class);

		$this->validator->validate($tooLong, 'Новый текст.');
	}

	public function testTextAtExactMaxLengthIsValid(): void
	{
		$exactLength = str_repeat('а', 10000);

		$this->expectNotToPerformAssertions();

		$this->validator->validate($exactLength, 'Новый текст.');
	}

	public function testInvalidUtf8ThrowsException(): void
	{
		// Битая UTF-8 последовательность (обрезанный многобайтовый символ)
		$invalidUtf8 = "\xB1\x31";

		$this->expectException(ValidationException::class);

		$this->validator->validate($invalidUtf8, 'Новый текст.');
	}
}