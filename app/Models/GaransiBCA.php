<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GaransiBCA extends Model
{
    use HasFactory;

    protected $table = 'garansi_bca';

    protected $fillable = [
        'virtual_account',
        'nama_murid',
        'tempat_tanggal_lahir',
        'tanggal_masuk',
        'nama_orang_tua_wali',
        'tanggal_diberikan',
    ];

    protected $casts = [
        'tanggal_masuk'     => 'date',
        'tanggal_diberikan' => 'date',
    ];

    // Tambahkan ini untuk blokir semua global scope yang diwarisi
    protected static function booted()
    {
        // Kosongkan atau hapus scope 'unit' kalau ada
        static::withoutGlobalScopes(['unit']); // ← blokir scope bernama 'unit'
        // atau tanpa nama: static::withoutGlobalScopes(); // blokir SEMUA global scope
    }
}