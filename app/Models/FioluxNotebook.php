<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperFioluxNotebook
 */
class FioluxNotebook extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function lastCycle(): BelongsTo
    {
        return $this->belongsTo(FioluxCycle::class, 'last_cycle_id');
    }
}
