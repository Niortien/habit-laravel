<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CamelCaseJsonResponse
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->setData($this->toCamelCase($response->getData(true)));
        }

        return $response;
    }

    private function toCamelCase(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $result = [];
        foreach ($data as $key => $value) {
            $newKey = is_string($key)
                ? lcfirst(str_replace('_', '', ucwords($key, '_')))
                : $key;
            $result[$newKey] = $this->toCamelCase($value);
        }

        return $result;
    }
}
