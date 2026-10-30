<?php

namespace App\Models;

use App\Models\Traits\FioluxImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperFioluxInspection
 */
class FioluxInspection extends Model
{
    use FioluxImmutable;

    protected $guarded = ['id'];

    protected $casts = ['snapshot' => 'array'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(FioluxCycle::class, 'cycle_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FioluxDocument::class, 'inspection_id');
    }
}
