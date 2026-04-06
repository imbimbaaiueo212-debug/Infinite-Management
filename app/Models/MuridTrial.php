<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Auth;   // TAMBAHAN

class MuridTrial extends Model
{
    use HasFactory;

    protected $table = 'murid_trials';

    // Nilai status yang dipakai aplikasi
    public const STATUS_AKTIF         = 'aktif';

    public const STATUS_BARU         = 'baru';
    public const STATUS_LANJUT_DAFTAR = 'lanjut_daftar';
    public const STATUS_BATAL         = 'batal';

    protected $fillable = [
        'tgl_mulai',
        'kelas',
        'nama',
        'tgl_lahir',
        'usia',
        'guru_trial',
        'info',
        'orangtua',
        'no_telp',
        'alamat',
        'waktu_submit',
        'nim',
        'status_trial',
        'promoted_at',
        'bimba_unit',
        'no_cabang',
        'tanggal_aktif',
        'tanggal_trial_baru',     // ← tambahkan ini
    ];

    protected $casts = [
        'tanggal_aktif'=> 'date',
        'tanggal_trial_baru'=> 'date',
        'tgl_mulai'    => 'date',
        'tgl_lahir'    => 'date',
        'waktu_submit' => 'datetime',
        'promoted_at'  => 'datetime',
        'usia'         => 'integer',
    ];

    // ===================================================================
    // GLOBAL SCOPE: FILTER OTOMATIS BERDASARKAN UNIT USER YANG LOGIN
    // ===================================================================
    protected static function booted()
{
    static::creating(function ($model) {
        if (!$model->waktu_submit) {
            $model->waktu_submit = now();
        }
    });

    static::addGlobalScope('unit', function (Builder $builder) {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->is_admin) {
                return;
            }

            if ($user->bimba_unit) {
                $builder->where('bimba_unit', $user->bimba_unit);
            } else {
                $builder->whereRaw('1 = 0');
            }
        }
    });
}

    /** Relasi: bila trial sudah punya Student */
    public function student()
    {
        return $this->hasOne(\App\Models\Student::class, 'murid_trial_id', 'id');
    }

    /** Relasi: komitmen orang tua */
    public function commitment()
    {
        return $this->hasOne(\App\Models\ParentCommitment::class, 'murid_trial_id', 'id');
    }

    /** Scope bantu filter status */
    public function scopeStatus($q, ?string $status)
    {
        if ($status === null || $status === '') return $q;
        if ($status === 'kosong') {
            return $q->whereNull('status_trial');
        }
        return $q->where('status_trial', $status);
    }

    /**
     * ACCESSOR: no_cabang otomatis diambil dari dalam teks bimba_unit
     */
    protected function noCabang(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                if (!empty($value)) {
                    return strtoupper(trim($value));
                }

                $unit = $attributes['bimba_unit'] ?? '';
                if (empty($unit)) {
                    return null;
                }

                if (preg_match('/\b([A-Z]{1,3}[\d-]*\d+)\b/i', $unit, $matches)) {
                    $kode = $matches[1];
                    $kode = preg_replace('/[^A-Z0-9]/i', '', strtoupper($kode));

                    if (preg_match('/^[A-Z]{1,3}\d{2,4}$/', $kode)) {
                        return $kode;
                    }
                }

                return null;
            }
        );
    }

    /**
     * MUTATOR: bersihkan bimba_unit saat disimpan
     */
    protected function bimbaUnit(): Attribute
    {
        return Attribute::make(
            set: fn($value) => $value ? trim($value) : null
        );
    }

    // ===================================================================
    // ACCESSOR TAMBAHAN (opsional, biar gampang di blade)
    // ===================================================================
    public function getStatusBadgeAttribute()
    {
        return match ($this->status_trial) {
            self::STATUS_AKTIF         => '<span class="badge bg-primary">Aktif</span>',
            self::STATUS_LANJUT_DAFTAR => '<span class="badge bg-success">Lanjut Daftar</span>',
            self::STATUS_BATAL         => '<span class="badge bg-danger">Batal</span>',
            default                    => '<span class="badge bg-secondary">Belum Diproses</span>',
        };
    }
    public function getTanggalTrialBaruFormattedAttribute(): ?string
{
    return $this->tanggal_trial_baru?->format('d/m/Y');
}

public function getTanggalAktifFormattedAttribute(): ?string
{
    return $this->tanggal_aktif?->format('d/m/Y');
}

}