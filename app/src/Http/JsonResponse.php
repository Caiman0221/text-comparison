<?php

declare(strict_types = 1);

namespace App\Http;

/**
 * Класс для формирования стандартного JSON-ответа.
 */
class JsonResponse
{
	public static function send(mixed $data, int $statusCode = 200): void
	{
		http_response_code($statusCode);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data, JSON_UNESCAPED_UNICODE);
	}

	public static function error(string $message, int $statusCode = 400): void
	{
		self::send(['error' => $message], $statusCode);
	}
}