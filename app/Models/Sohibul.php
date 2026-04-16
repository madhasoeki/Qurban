<?php

namespace App\Models;

use App\Events\DashboardUpdated;
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

    protected static function booted(): void
    {
        static::saved(fn () => DashboardUpdated::dispatch());
        static::deleted(fn () => DashboardUpdated::dispatch());
    }

    public function hewan(): HasMany
    {
        return $this->hasMany(Hewan::class);
    }
}
