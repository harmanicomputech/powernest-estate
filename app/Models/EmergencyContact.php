<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmergencyContact extends Model
{
    use BelongsToEstate, HasFactory;

    protected $guarded = ['id'];
}
