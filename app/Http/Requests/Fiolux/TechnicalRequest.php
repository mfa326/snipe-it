<?php

namespace App\Http\Requests\Fiolux;

use App\Http\Requests\Request;

class TechnicalRequest extends Request
{
    protected $rules = [
        'operation_key' => 'required|uuid',
        'result' => 'required|in:available,preparation,maintenance,investigation,pending',
        'notes' => 'required|string|max:10000',
        'confirmed' => 'required|accepted',
    ];

    public function authorize(): bool
    {
        return auth()->check();
    }
}
