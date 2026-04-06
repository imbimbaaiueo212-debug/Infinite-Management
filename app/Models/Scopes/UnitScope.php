<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as EloquentScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class UnitScope implements EloquentScope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Hindari error jika tabel tidak punya kolom bimba_unit atau no_cabang
        $table = $model->getTable();

        if (!Schema::hasColumn($table, 'bimba_unit')) {
            return;
        }

        // Jika user belum login (misalnya di route publik), jangan filter apa-apa
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();

        // === Deteksi Admin (fleksibel untuk berbagai cara implementasi) ===
        $isAdmin = false;

        if (method_exists($user, 'isAdminUser') && $user->isAdminUser()) {
            $isAdmin = true;
        } elseif (method_exists($user, 'getIsAdminAttribute') && $user->getIsAdminAttribute()) {
            $isAdmin = true;
        } elseif (isset($user->role) && in_array($user->role, ['admin', 'superadmin', 'kepala_unit'])) {
            $isAdmin = true;
        } elseif (isset($user->jabatan) && str_contains(strtolower($user->jabatan), 'kepala')) {
            $isAdmin = true;
        }

        // Admin / Kepala Unit → boleh lihat semua data
        if ($isAdmin) {
            return;
        }

        // === User Biasa: Filter berdasarkan bimba_unit ===
        if (!empty($user->bimba_unit)) {
            $builder->where("$table.bimba_unit", $user->bimba_unit);

            // Jika aplikasi kamu juga pakai no_cabang sebagai pengaman tambahan
            if (Schema::hasColumn($table, 'no_cabang') && !empty($user->no_cabang)) {
                $builder->where("$table.no_cabang", $user->no_cabang);
            }
        } else {
            // User tidak punya unit → blokir semua akses ke data ini
            // Berguna untuk keamanan jika ada user "rusak" atau sementara
            $builder->whereRaw('1 = 0');
        }
    }
}