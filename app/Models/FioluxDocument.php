<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperFioluxDocument
 */
class FioluxDocument extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['checked_at' => 'datetime'];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(FioluxInspection::class, 'inspection_id');
    }
}
