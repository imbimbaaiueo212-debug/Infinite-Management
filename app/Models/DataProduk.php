<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataProduk extends Model
{
    use HasFactory;

    protected $table = 'data_produk';

    /**
     * Kolom yang boleh diisi secara mass assignment
     */
    protected $fillable = [
        'unit_id',       // Unit biMBA pemilik rekap ini
        'periode',       // Format Y-m (contoh: 2026-01)
        'kode',
        'jenis',
        'label',
        'satuan',
        'harga',
        'min_stok',
        'sld_awal',
        'sld_akhir',     // Kolom ini DISIMPAN di database (bukan hanya calculated)
        'terima',
        'pakai',
        'opname',
        'selisih',
        'nilai',
    ];

    /**
     * Cast attribute agar tipe data sesuai
     */
    protected $casts = [
        'periode'   => 'date:Y-m',   // Membantu manipulasi periode dengan Carbon
        'harga'     => 'integer',
        'min_stok'  => 'integer',
        'sld_awal'  => 'integer',
        'terima'    => 'integer',
        'pakai'     => 'integer',
        'opname'    => 'integer',
        'sld_akhir' => 'integer',
        'selisih'   => 'integer',
        'nilai'     => 'decimal:2',
    ];

    // ========================================
    // RELASI
    // ========================================

    /**
     * Relasi ke Unit biMBA
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Relasi ke Master Produk (berdasarkan kode)
     */
    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'kode', 'kode');
    }

    // ========================================
    // METHOD CALCULATED (bukan accessor)
    // ========================================

    /**
     * Hitung saldo akhir secara manual
     * Digunakan di view/controller jika diperlukan
     * (tidak override attribute sld_akhir di database)
     */
    public function calculateSldAkhir(): int
    {
        return $this->sld_awal + $this->terima - $this->pakai;
    }

    // ========================================
    // ACCESSOR (hanya yang tidak bentrok dengan kolom DB)
    // ========================================

   /**
 * Status stok berdasarkan perhitungan (sama seperti di form edit)
 */
public function getStatusAttribute(): string
{
    $akhir = $this->calculateSldAkhir();
    $min   = $this->min_stok ?? 0;

    if ($akhir <= 0) {
        return 'HABIS_TOTAL';
    }

    if ($akhir >= $min) {
        return 'STOK AMAN';
    } else {
        return 'STOK KURANG';
    }
}
    /**
     * Nilai opname (opname × harga)
     */
    public function getNilaiAttribute(): int
    {
        return $this->opname * $this->harga;
    }

    /**
     * Selisih stok (opname - saldo akhir)
     */
    public function getSelisihAttribute(): int
    {
        return $this->opname - $this->calculateSldAkhir();
    }

    /**
     * Nilai selisih (selisih × harga)
     */
    public function getNilaiSelisihAttribute(): int
    {
        return $this->selisih * $this->harga;
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope: Filter berdasarkan periode
     */
    public function scopePeriode($query, $periode)
    {
        return $query->where('periode', $periode);
    }

    /**
     * Scope: Filter berdasarkan unit
     */
    public function scopeUnit($query, $unitId)
    {
        return $query->where('unit_id', $unitId);
    }

    /**
     * Scope: Hanya produk yang diizinkan (modul biMBA dll)
     */
    public function scopeAllowedJenis($query, array $allowedJenis)
    {
        return $query->where(function ($q) use ($allowedJenis) {
            $q->whereHas('produk', fn($q) => $q->whereIn('jenis', $allowedJenis))
              ->orWhereIn('jenis', $allowedJenis);
        });
    }
    public function setSelisihAttribute($value)
    {
        $this->attributes['selisih'] = ($value === '' || $value === null) 
            ? null 
            : (float) $value;
    }

    /**
 * Mutator: Setiap kali opname di-set, hitung nilai otomatis
 */
public function setOpnameAttribute($value)
{
    $this->attributes['opname'] = $value;

    // Hitung nilai baru berdasarkan opname × harga
    $harga = $this->harga ?? 0;
    $opname = $value ?? 0;

    $this->attributes['nilai'] = $opname * $harga;
}
}