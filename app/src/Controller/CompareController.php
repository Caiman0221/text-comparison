<?php

declare(strict_types = 1);

namespace App\Controller;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Validator\CompareValidator;
use App\Validator\ValidationException;
use App\Service\TextComparator;

/**
 * Контроллер для сравнения текстов.
 * Принимает запрос, проверяет данные, отправляет в сервис и возвращает результат сравнения.
 */
class CompareController
{
	public function handle(Request $request): void
	{
		$oldText = $request->post('old_text', '');
		$newText = $request->post('new_text', '');

		try
		{
			(new CompareValidator())->validate($oldText, $newText);
		}
		catch(ValidationException $e)
		{
			JsonResponse::error($e->getMessage(), 422);

			return;
		}

		$comparator = new TextComparator();
		$result     = $comparator->compare($oldText, $newText);

		JsonResponse::send(['result' => $result]);
	}
}