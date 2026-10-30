<?php

namespace App\Http\Requests\Fiolux;

use App\Http\Requests\Request;

class DocumentReviewRequest extends Request
{
    protected $rules = ['confirmed' => 'required|accepted'];

    public function authorize(): bool
    {
        return auth()->check();
    }
}
