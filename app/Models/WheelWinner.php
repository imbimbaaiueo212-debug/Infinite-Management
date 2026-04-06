<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WheelWinner extends Model
{
    protected $table = 'wheel_winners';

    // Tambahkan 2 kolom baru ini
    protected $fillable = [
        'name',
        'voucher',
        'voucher_index',
        'row_hash',
        'student_id',
        'won_at',
        'bimba_unit',      // baru
        'no_cabang',  // baru
    ];

    protected $dates = [
        'won_at',
    ];

    // Optional: cast kalau perlu
    protected $casts = [
        'won_at' => 'datetime',
    ];
}