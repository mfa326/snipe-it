<?php

namespace App\Models\Traits;

use Illuminate\Validation\ValidationException;

trait FioluxImmutable
{
    public static function bootFioluxImmutable(): void
    {
        static::updating(function (): void {
            throw ValidationException::withMessages(['fiolux' => trans('fiolux.immutable')]);
        });
        static::deleting(function (): void {
            throw ValidationException::withMessages(['fiolux' => trans('fiolux.immutable')]);
        });
    }
}
