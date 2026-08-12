<?php

declare(strict_types = 1);

namespace App;

use App\Controller\CompareController;
use App\Http\JsonResponse;
use App\Http\Request;

class Router
{
	public function dispatch(): void
	{
		$request = Request::fromGlobals();

		if($request->method() === 'POST' && $request->path() === '/api/compare')
		{
			(new CompareController())->handle($request);

			return;
		}

		JsonResponse::error('Route not found', 404);
	}
}