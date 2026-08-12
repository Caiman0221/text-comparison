<?php

declare(strict_types = 1);

namespace App\Http;

/**
 * Класс для работы с HTTP-запросом.
 */
readonly class Request
{
	/**
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $server
	 */
	public function __construct(
		private array $post = [],
		private array $server = []
	)
	{
	}

	public static function fromGlobals(): self
	{
		return new self($_POST, $_SERVER);
	}

	public function post(string $key, mixed $default = null): mixed
	{
		return $this->post[$key] ?? $default;
	}

	public function method(): string
	{
		return $this->server['REQUEST_METHOD'] ?? 'GET';
	}

	public function path(): string
	{
		$uri = $this->server['REQUEST_URI'] ?? '/';

		return parse_url($uri, PHP_URL_PATH) ?: '/';
	}
}