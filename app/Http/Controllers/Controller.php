<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Validator as ValidatorInstance;

abstract class Controller
{
    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function validateOr422(Request $request, array $rules, string $view, array $data = []): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            $this->throwInvalid($view, $data, $validator);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function throwInvalid(string $view, array $data, ValidatorInstance $validator): never
    {
        $bag = new ViewErrorBag;
        $bag->put('default', $validator->errors());

        throw new HttpResponseException(
            response()->view($view, array_merge($data, ['errors' => $bag]), 422),
        );
    }
}
