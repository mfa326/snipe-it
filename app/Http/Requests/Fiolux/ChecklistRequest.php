<?php

namespace App\Http\Requests\Fiolux;

use App\Http\Requests\Request;

class ChecklistRequest extends Request
{
    protected $rules = [
        'operation_key' => 'required|uuid',
        'kind' => 'required|in:delivery,return',
        'user_id' => 'required_if:kind,delivery|integer|exists_undeleted:users,id',
        'cycle_id' => 'required_if:kind,return|integer|exists:fiolux_cycles,id',
        'answers' => 'required|array:screen,case,charger,cable',
        'answers.screen' => 'required|in:conforme,avaria,nao_verificado',
        'answers.case' => 'required|in:conforme,avaria,nao_verificado',
        'answers.charger' => 'required|in:presente,ausente,nao_aplica',
        'answers.cable' => 'required|in:presente,ausente,nao_aplica',
        'reason' => 'nullable|string|max:5000|required_if:answers.screen,avaria,nao_verificado|required_if:answers.case,avaria,nao_verificado|required_if:answers.charger,ausente|required_if:answers.cable,ausente',
        'notes' => 'nullable|string|max:10000',
        'confirmed' => 'required|accepted',
    ];

    public function authorize(): bool
    {
        return auth()->check();
    }
}
