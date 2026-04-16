<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sohibul extends Model
{
    protected $table = 'sohibul';

    protected $fillable = [
        'nama',
        'jenis_qurban',
        'request',
    ];

    protected $casts = [
        'nama' => 'array',
    ];

    public function hewan(): HasMany
    {
        return $this->hasMany(Hewan::class);
    }
}
