<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    use BelongsToEstate, HasFactory;

    protected $guarded = ['id'];

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
