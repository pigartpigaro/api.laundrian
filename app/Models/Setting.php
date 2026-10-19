<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToOutlet;

class Setting extends Model
{
    use BelongsToOutlet;

    protected $fillable = ['outlet_id', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
