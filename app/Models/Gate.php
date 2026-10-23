<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gate extends Model
{
    use BelongsToEstate, HasFactory;

    protected $guarded = ['id'];

    /**
     * Mirror database defaults for freshly created models.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
