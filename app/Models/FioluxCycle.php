<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin IdeHelperFioluxCycle
 */
class FioluxCycle extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['returned_at' => 'datetime'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(FioluxInspection::class, 'cycle_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(FioluxTechnicalEvaluation::class, 'cycle_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FioluxDocument::class, 'cycle_id');
    }

    public function signaturePending(): bool
    {
        foreach (['delivery', 'return'] as $kind) {
            $inspection = $this->inspections()->where('kind', $kind)->first();
            if (! $inspection) {
                return true;
            }
            $valid = $inspection->documents()->where('kind', 'signed')->where('state', 'ready')->whereNotNull('checked_at')->get()
                ->contains(fn ($document) => $document->path && Storage::exists($document->path) && hash('sha256', Storage::get($document->path)) === $document->sha256);
            if (! $valid) {
                return true;
            }
        }

        return false;
    }
}
