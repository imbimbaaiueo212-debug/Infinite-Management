<?php

namespace App\Imports;

use App\Models\BukuInduk;
use App\Models\BukuIndukHistory;
use App\Models\HargaSaptataruna;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\Log;

class BukuIndukImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            /* =====================================================
             * VALIDASI WAJIB
             * ===================================================== */
            $nim = trim((string) ($row['nim'] ?? ''));
            if ($nim === '') {
                continue;
            }

            /* =====================================================
             * NORMALISASI DASAR
             * ===================================================== */
            $bimbaUnit = $this->getHeaderValue($row, [
                'unit','bimba_unit','bimba unit','nama unit','cabang','nama_cabang'
            ]);

            $kelas = $this->getHeaderValue($row, [
                'kelas','kelas_belajar','kelas belajar','tingkat','level_kelas'
            ]);

            $guru = $this->getHeaderValue($row, [
                'guru','nama guru','wali_kelas','pengajar'
            ]);

            /* =====================================================
             * KONVERSI TANGGAL
             * ===================================================== */
            $tglLahir   = $this->convertExcelDate($row['tgl_lahir'] ?? null);
            $tglMasuk   = $this->convertExcelDate($row['tgl_masuk'] ?? null);
            $tglKeluar  = $this->convertExcelDate($row['tgl_keluar'] ?? null);
            $tglMulai   = $this->convertExcelDate($row['tgl_mulai'] ?? null);
            $tglAkhir   = $this->convertExcelDate($row['tgl_akhir'] ?? null);
            $tglBayar   = $this->convertExcelDate($row['tgl_bayar'] ?? null);
            $tglSelesai = $this->convertExcelDate($row['tgl_selesai'] ?? null);
            $tglPindah  = $this->convertExcelDate($row['tanggal_pindah'] ?? null);

            $usia = $tglLahir ? $tglLahir->age : null;
            $lama_bljr = $tglMasuk
                ? $tglMasuk->diff(Carbon::today())->format('%y tahun %m bulan')
                : null;

            /* =====================================================
             * OTOMATISASI STATUS (INI BAGIAN BARU / DIPERBAIKI)
             * ===================================================== */
            $statusInput = trim((string) ($row['status'] ?? ''));

            $status = $statusInput;

            // Hanya otomatisasi jika status di Excel kosong
            if (empty($statusInput)) {
                $today = Carbon::today();

                if ($tglKeluar) {
                    if ($tglKeluar->lessThanOrEqualTo($today)) {
                        $status = 'Keluar';
                    } else {
                        // tgl_keluar di masa depan → masih aktif
                        $status = 'Aktif';
                    }
                } elseif ($tglMasuk) {
                    if ($tglMasuk->lessThanOrEqualTo($today)) {
                        // sudah masuk
                        $status = 'Aktif';
                    } else {
                        // masuk di masa depan atau hari ini
                        $status = 'Baru';
                    }
                } else {
                    // tidak ada tgl_masuk & tgl_keluar → default aktif
                    $status = 'Aktif';
                }

                // Optional: bisa tambah log untuk debug
                // Log::info("Status otomatis untuk NIM {$nim}: {$status} (masuk: {$tglMasuk?->toDateString()}, keluar: {$tglKeluar?->toDateString()})");
            }

            /* =====================================================
             * LOGIKA SPP OTOMATIS
             * ===================================================== */
            $spp = null;
            $spp_source = 'manual';

            if (isset($row['spp']) && is_numeric($row['spp'])) {
                $spp = (float) $row['spp'];
                if ($spp > 0 && $spp < 1000) {
                    $spp *= 1000;
                }
                $spp_source = 'excel';
            }

            if ($spp === null) {
                $gol = strtoupper(trim($row['gol'] ?? ''));
                $kd  = strtoupper(trim($row['kd'] ?? ''));

                if ($gol && in_array($kd, ['A','B','C','D','E','F'])) {
                    $harga = HargaSaptataruna::whereRaw(
                        'UPPER(TRIM(kode)) = ?',
                        [$gol]
                    )->first();

                    if ($harga && !is_null($harga->{strtolower($kd)})) {
                        $spp = (float) $harga->{strtolower($kd)};
                        $spp_source = 'auto_harga_saptataruna';
                    }
                }
            }

            /* =====================================================
             * SINKRON UNIT ↔ CABANG
             * ===================================================== */
            [$noCabang, $bimbaUnit] =
                $this->syncCabangUnitFromDB($row, $bimbaUnit, $nim);

            /* =====================================================
             * DATA FINAL (FULL FIELD)
             * ===================================================== */
            $data = [
                // ===== IDENTITAS =====
                'nim'        => $nim,
                'nama'       => trim($row['nama'] ?? ''),
                'bimba_unit' => $bimbaUnit,
                'no_cabang'  => $noCabang,
                'kelas'      => $kelas,
                'guru'       => $guru,

                // ===== AKADEMIK & KEUANGAN =====
                'gol'        => strtoupper(trim($row['gol'] ?? '')),
                'kd'         => strtoupper(trim($row['kd'] ?? '')),
                'spp'        => $spp,

                // ===== STATUS & TANGGAL =====
                'tgl_masuk'      => $tglMasuk?->format('Y-m-d'),
                'tgl_keluar'     => $tglKeluar?->format('Y-m-d'),
                'tanggal_pindah' => $tglPindah?->format('Y-m-d'),
                'status'         => $status,   // ← menggunakan status yang sudah diolah

                // ===== BIODATA =====
                'tmpt_lahir' => trim($row['tmpt_lahir'] ?? ''),
                'tgl_lahir'  => $tglLahir?->format('Y-m-d'),
                'usia'       => $usia,
                'lama_bljr'  => $lama_bljr,

                // ===== KONTAK =====
                'orangtua'     => trim($row['orangtua'] ?? ''),
                'no_telp_hp'   => trim($row['no_telp_hp'] ?? ''),
                'alamat_murid' => trim($row['alamat_murid'] ?? ''),

                // ===== JADWAL & KBM =====
                'tahap'       => trim($row['tahap'] ?? ''),
                'level'       => trim($row['level'] ?? ''),
                'jenis_kbm'   => trim($row['jenis_kbm'] ?? ''),
                'kode_jadwal' => trim($row['kode_jadwal'] ?? ''),
                'hari_jam'    => trim($row['hari_jam'] ?? ''),
                'periode'     => trim($row['periode'] ?? ''),

                // ===== ADMINISTRATIF =====
                'petugas_trial'       => trim($row['petugas_trial'] ?? ''),
                'no_cab_merge'        => trim($row['no_cab_merge'] ?? ''),
                'no_pembayaran_murid' => trim($row['no_pembayaran_murid'] ?? ''),
                'asal_modul'          => trim($row['asal_modul'] ?? ''),
                'ke_bimba_intervio'   => trim($row['ke_bimba_intervio'] ?? ''),

                // ===== STATUS LANJUTAN =====
                'kategori_keluar' => trim($row['kategori_keluar'] ?? ''),
                'alasan'          => trim($row['alasan'] ?? ''),
                'status_pindah'   => trim($row['status_pindah'] ?? ''),

                // ===== CATATAN =====
                'note'                => trim($row['note'] ?? ''),
                'note_garansi'        => trim($row['note_garansi'] ?? ''),
                'keterangan_optional' => trim($row['keterangan_optional'] ?? ''),
                'alert'               => trim($row['alert'] ?? ''),
                'alert2'              => trim($row['alert2'] ?? ''),
                'keterangan'          => trim($row['keterangan'] ?? ''),
            ];

            /* =====================================================
             * SIMPAN / UPDATE
             * ===================================================== */
            $bukuInduk = BukuInduk::where('nim', $nim)->first();

            if ($bukuInduk) {
                $bukuInduk->update($data);

                BukuIndukHistory::create([
                    'buku_induk_id' => $bukuInduk->id,
                    'action' => 'update_import',
                    'user'   => auth()->user()?->name ?? 'import_system',
                    'note'   => "Updated via import | Status: {$status} | SPP: {$spp_source} | Jadwal: ".($row['kode_jadwal'] ?? '-'),
                ]);
            } else {
                $bukuInduk = BukuInduk::create($data);

                BukuIndukHistory::create([
                    'buku_induk_id' => $bukuInduk->id,
                    'action' => 'import_create',
                    'user'   => auth()->user()?->name ?? 'import_system',
                    'note'   => "Created via import | Status: {$status} | SPP: {$spp_source} | Jadwal: ".($row['kode_jadwal'] ?? '-'),
                ]);
            }
        }
    }
    /* =====================================================
     * SINKRON UNIT ↔ CABANG
     * ===================================================== */
    private function syncCabangUnitFromDB(Collection $row, ?string $bimbaUnit, string $nim): array
    {
        $noCabang = $this->getHeaderValue($row, [
            'no cabang','no_cabang','nocabang','no_cab','cabang_id'
        ]);

        $unit = $bimbaUnit;

        if ($noCabang && !$unit) {
            $found = Unit::withoutGlobalScopes()
                ->where('no_cabang', $noCabang)->first();
            if ($found) $unit = $found->biMBA_unit;
        }

        if ($unit && !$noCabang) {
            $found = Unit::withoutGlobalScopes()
                ->whereRaw('UPPER(biMBA_unit) = ?', [strtoupper($unit)])
                ->first();
            if ($found) $noCabang = $found->no_cabang;
        }

        if ($unit && $noCabang) {
            $valid = Unit::withoutGlobalScopes()
                ->where('no_cabang', $noCabang)
                ->whereRaw('UPPER(biMBA_unit) = ?', [strtoupper($unit)])
                ->exists();

            if (!$valid) {
                Log::warning("IMPORT MISMATCH | NIM {$nim} | Unit {$unit} | Cabang {$noCabang}");
            }
        }

        return [$noCabang, $unit];
    }

    /* =====================================================
     * HELPER
     * ===================================================== */
    private function getHeaderValue(Collection $row, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            $keyNorm = strtolower(str_replace([' ','_','-'], '', $key));
            foreach ($row as $header => $value) {
                $headerNorm = strtolower(str_replace([' ','_','-'], '', $header));
                if ($headerNorm === $keyNorm) {
                    $val = trim((string) $value);
                    return $val !== '' ? $val : null;
                }
            }
        }
        return null;
    }

    private function convertExcelDate($value): ?Carbon
    {
        if (!$value) return null;

        if (is_numeric($value)) {
            try {
                return Carbon::instance(Date::excelToDateTimeObject($value));
            } catch (\Exception $e) {
                Log::warning("Gagal konversi tanggal Excel: {$value}");
                return null;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            Log::warning("Gagal parse tanggal: {$value}");
            return null;
        }
    }
}
