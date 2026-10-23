<?php

namespace App\Models\Concerns;

use App\Models\Estate;
use App\Support\EstateContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estate-scoped records. Route-model binding is constrained to the estate the
 * user is currently operating in, so a foreign ID in a URL resolves to a 404.
 *
 * @mixin Model
 */
trait BelongsToEstate
{
    public function estate(): BelongsTo
    {
        return $this->belongsTo(Estate::class);
    }

    public function scopeForEstate(Builder $query, Estate|int $estate): Builder
    {
        return $query->where($this->qualifyColumn('estate_id'), $estate instanceof Estate ? $estate->getKey() : $estate);
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $query = $this->resolveRouteBindingQuery($this->newQuery(), $value, $field);
        $context = app(EstateContext::class);

        if ($context->hasEstate()) {
            $query->where($this->qualifyColumn('estate_id'), $context->estate()->getKey());
        }

        return $query->first();
    }
}
