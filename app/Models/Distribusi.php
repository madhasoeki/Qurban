<?php

namespace App\Models;

use App\Events\DashboardUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Distribusi extends Model
{
    protected $table = 'distribusi';

    protected $fillable = [
        'user_id',
        'jumlah',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => DashboardUpdated::dispatch());
        static::deleted(fn () => DashboardUpdated::dispatch());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
