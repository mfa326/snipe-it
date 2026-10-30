<?php

namespace App\Services\Fiolux;

use App\Models\Asset;
use App\Models\User;

class NotebookChecklist
{
    public static function questions(): array
    {
        $questions = [];
        foreach (['screen', 'case', 'charger', 'cable'] as $key) {
            $values = in_array($key, ['screen', 'case']) ? ['conforme', 'avaria', 'nao_verificado'] : ['presente', 'ausente', 'nao_aplica'];
            $questions[$key] = ['label' => trans('fiolux.'.$key), 'options' => []];
            foreach ($values as $value) {
                $questions[$key]['options'][$value] = trans('fiolux.'.$value);
            }
        }

        return $questions;
    }

    public static function person(User $user): array
    {
        return ['id' => (int) $user->id, 'name' => $user->display_name ?: trim($user->first_name.' '.$user->last_name), 'username' => $user->username];
    }

    public static function snapshot(Asset $asset, User $user, User $operator, array $data): array
    {
        $questions = static::questions();
        foreach ($questions as $key => &$question) {
            $question['answer'] = $data['answers'][$key];
            $question['answer_label'] = $question['options'][$question['answer']];
        }
        unset($question);

        return [
            'asset' => ['id' => (int) $asset->id, 'tag' => $asset->asset_tag, 'serial' => $asset->serial, 'name' => $asset->name, 'model' => $asset->model?->name, 'manufacturer' => $asset->model?->manufacturer?->name],
            'user' => static::person($user),
            'operator' => static::person($operator),
            'recorded_at' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'questions' => $questions,
            'notes' => $data['notes'] ?? '',
            'reason' => $data['reason'] ?? '',
            'confirmed' => true,
        ];
    }
}
