<?php

namespace App\Models;

use App\Models\Scopes\UnitScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Registration extends Model
{
    use HasFactory;

    public const STATUSES = ['pending','verified','accepted','rejected'];

    protected $fillable = [
        'student_id','gelombang','program','status','tanggal_daftar',
        'tahun_ajaran','attachment_path',
        'tahap','kelas','gol','kd','spp',
        'guru','kode_jadwal','hari_jam',
        'bimba_unit','no_cabang',
        'kwitansi','via','bulan','tahun','tanggal_penerimaan',
        'daftar','voucher','spp_rp','spp_keterangan',
        'kaos','kpk','sertifikat','stpb','tas','event','lain_lain',
        'total',
    ];

    protected $casts = [
        'tanggal_daftar'     => 'date',
        'tanggal_penerimaan' => 'date',
        'tahun'              => 'integer',
        'spp'                => 'integer',
        'spp_rp'             => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    // GLOBAL SCOPE: Hanya user biasa yang dibatasi unitnya
    public static function currentAcademicYear(): string
    {
        $now   = now();
        $year  = (int) $now->format('Y');
        $start = $now->month >= 7 ? $year : $year - 1;
        return "{$start}/" . ($start + 1);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'pending'   => 'bg-warning text-dark',
            'verified'  => 'bg-primary text-white',
            'accepted'  => 'bg-success text-white',
            'rejected'  => 'bg-danger text-white',
            default     => 'bg-secondary',
        };
    }

    protected static function booted()
{
    static::addGlobalScope(new UnitScope);
}

}