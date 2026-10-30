<?php

namespace App\Http\Requests\Fiolux;

use App\Http\Requests\Request;

class DocumentUploadRequest extends Request
{
    protected $rules = [
        'file' => 'required|file|max:10240|extensions:pdf,png,jpg,jpeg|mimetypes:application/pdf,image/png,image/jpeg',
    ];

    public function authorize(): bool
    {
        return auth()->check();
    }
}
