<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hewan extends Model
{
    protected $table = 'hewan';

    protected $fillable = [
        'kode',
        'berat_awal',
        'berat_daging',
        'berat_tulang',
        'mulai_jagal',
        'selesai_jagal',
        'mulai_kuliti',
        'selesai_kuliti',
        'mulai_cacah_daging',
        'selesai_cacah_daging',
        'mulai_cacah_tulang',
        'selesai_cacah_tulang',
        'mulai_jeroan',
        'selesai_jeroan',
        'mulai_packing',
        'selesai_packing',
        'kantong_packing',
        'sohibul_id',
        'keterangan',
    ];

    protected $casts = [
        'mulai_jagal' => 'datetime',
        'selesai_jagal' => 'datetime',
        'mulai_kuliti' => 'datetime',
        'selesai_kuliti' => 'datetime',
        'mulai_cacah_daging' => 'datetime',
        'selesai_cacah_daging' => 'datetime',
        'mulai_cacah_tulang' => 'datetime',
        'selesai_cacah_tulang' => 'datetime',
        'mulai_jeroan' => 'datetime',
        'selesai_jeroan' => 'datetime',
        'mulai_packing' => 'datetime',
        'selesai_packing' => 'datetime',
    ];

    public function sohibul(): BelongsTo
    {
        return $this->belongsTo(Sohibul::class);
    }
}
