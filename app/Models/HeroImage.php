<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroImage extends Model
{
    protected $fillable = [
        'path',
        'sort_order',
    ];

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
