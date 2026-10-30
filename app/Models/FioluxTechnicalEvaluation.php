<?php

namespace App\Models;

use App\Models\Traits\FioluxImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperFioluxTechnicalEvaluation
 */
class FioluxTechnicalEvaluation extends Model
{
    use FioluxImmutable;

    protected $guarded = ['id'];

    protected $casts = ['operator_snapshot' => 'array'];
}
