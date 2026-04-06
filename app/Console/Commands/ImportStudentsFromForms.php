<?php

namespace App\Console\Commands;

use App\Models\BukuInduk;
use App\Models\Student;
use App\Models\MuridTrial;
use App\Models\Unit;
use App\Services\GoogleFormService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ImportStudentsFromForms extends Command
{
    protected $signature = 'forms:import-students
        {sheet? : Nama sheet (opsional, default: Registrasi)}
        {--dd : Dump & die untuk debug (hanya 1 baris pertama)}
        {--stage=map : Tahap debug [map|create|update|stored]}';

    protected $description = 'Import Google Form → Student + Trial + Buku Induk OTOMATIS';

   public function handle(GoogleFormService $forms): int
{
    $sheetName = $this->argument('sheet') ?: 'Registrasi';
    $rows = collect($forms->getResponses($sheetName));

    if ($rows->isEmpty()) {
        $this->info("Sheet '{$sheetName}' kosong.");
        return self::SUCCESS;
    }

    $imported = $updated = $skipped = 0;

    foreach ($rows as $index => $row) {
        if ($this->option('dd') && $index > 0) break;

        try {
            $payload = $this->mapRow($row);

            if (empty($payload['nama'])) {
                $skipped++;
                continue;
            }

            // DETEKSI MUTASI (kata mutasi, pindah, transfer, dll)
            $sourceFromForm = $payload['sumber_pendaftaran'] ?? '';
            $isMutasi = preg_match('/\b(mutasi|pindah|pindahan|transfer|pindah cabang)\b/i', $sourceFromForm);

            // Jika mutasi → source = direct + buat NIM
            // Jika bukan → source = trial + NIM = null
            $payload['source'] = $isMutasi ? 'direct' : 'trial';
            $payload['nim']    = $isMutasi ? $this->nextNim($payload['bimba_unit'] ?? null, $payload['no_cabang'] ?? null) : null;

            $key = $this->buildUpsertKey($payload);

            DB::transaction(function () use (
                &$imported, &$updated, &$skipped, $payload, $key, $isMutasi
            ) {
                $student = Student::where($key)->lockForUpdate()->first();

                // Resolve no_cabang
                if (!empty($payload['bimba_unit']) && empty($payload['no_cabang'])) {
                    $payload['no_cabang'] = $this->resolveNoCabangFromBimbaUnit($payload['bimba_unit']);
                }

                if (!$student) {
                    $student = Student::create($payload);
                    $imported++;

                    if ($isMutasi) {
                        $this->info("BARU (Mutasi → NIM dibuat: {$student->nim}): {$student->nama}");
                        // TIDAK MASUK BUKU INDUK → tunggu registrasi di-approve
                    } else {
                        $this->info("BARU (Trial Baru): {$student->nama}");
                    }
                } else {
                    $student->fill($payload);

                    if ($isMutasi) {
                        $student->source = 'direct';
                        if (empty($student->nim)) {
                            $student->nim = $this->nextNim($student->bimba_unit, $student->no_cabang);
                        }
                    } else {
                        $student->source = 'trial';
                        $student->nim = null;
                        $student->promoted_at = null;
                    }

                    if ($student->isDirty()) {
                        $student->save();
                        $updated++;

                        if ($isMutasi) {
                            $this->info("UPDATE → Mutasi (NIM: {$student->nim}): {$student->nama}");
                            // TIDAK MASUK BUKU INDUK
                        } else {
                            $this->info("UPDATE → Trial Baru: {$student->nama}");
                        }
                    } else {
                        $skipped++;
                    }
                }

                // Hanya buat trial relation jika BUKAN mutasi
                if (!$isMutasi) {
                    $this->ensureTrialRelation($student); // status 'baru'
                }
            });

        } catch (\Throwable $e) {
            $skipped++;
            Log::error('IMPORT STUDENT ERROR', [
                'row'   => $index + 2,
                'nama'  => $payload['nama'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            $this->error("Error baris " . ($index + 2) . ": " . $e->getMessage());
        }
    }

    $this->newLine();
    $this->info("SELESAI IMPORT DARI GOOGLE FORM");
    $this->table(['Status', 'Jumlah'], [
        ['Baru/Updated' => $imported + $updated],
        ['Skip'         => $skipped],
        ['Total Baris'  => $rows->count()],
    ]);

    return self::SUCCESS;
}

    // Khusus untuk murid RESMI (bukan trial)
        

    // ====================================================================
    // MAPPING & NORMALISASI
    // ====================================================================
    protected function mapRow(array $row): array
    {
        // Alias header umum (kolom milik murid)
        $alias = [
            'timestamp' => 'form_timestamp',
            'email address' => 'email',
            'sumber pendaftaran' => 'sumber_pendaftaran',

            'nama lengkap murid' => 'nama',
            'tanggal lahir' => 'tgl_lahir',
            'alamat' => 'alamat',
            'tempat lahir' => 'tempat_lahir',
            'jenis kelamin' => 'jenis_kelamin',
            'agama' => 'agama_murid',

            'kode pos' => 'kode_pos',
            'no rumah' => 'no_rumah',
            'rt' => 'rt',
            'rw' => 'rw',
            'kelurahan' => 'kelurahan',
            'kecamatan' => 'kecamatan',
            'kodya kab' => 'kodya_kab',
            'kodya / kab' => 'kodya_kab',
            'provinsi' => 'provinsi',

            'tanggal masuk sekolah' => 'tanggal_masuk',
            'tanggal daftar' => 'tanggal_masuk',
            'tgl masuk' => 'tanggal_masuk',

            'biaya pendaftaran' => 'biaya_pendaftaran',
            'uang pendaftaran' => 'biaya_pendaftaran',
            'biaya pendaftaran siswa' => 'biaya_pendaftaran',

            'spp bulanan' => 'spp_bulanan',
            'spp' => 'spp_bulanan',

            'informasi bimba aiueo didapat dari' => 'informasi_bimba',
            'informasi bimba' => 'informasi_bimba',
            'sumber informasi bimba' => 'informasi_bimba',
            'informasi bimba didapat dari' => 'informasi_bimba',

            'sebutkan nama murid yang memberi tahu tentang bimba' => 'informasi_humas_nama',
            'sebutkan nama murid yang memberi tahu tentang bimba:' => 'informasi_humas_nama',

            'hari' => 'hari',
            'jam' => 'jam',
            'kelas' => 'kelas',
            'nomor telepon' => 'no_telp',
            'nomor telepon kontak' => 'no_telp',
            'guru wali' => 'guru_wali',

            // Foto KK
            'upload foto kk' => 'foto_kk',
            'upload kk' => 'foto_kk',
            'kartu keluarga' => 'foto_kk',
            'foto kartu keluarga' => 'foto_kk',
            'upload kartu keluarga' => 'foto_kk',
            'kk' => 'foto_kk',
            'file kk' => 'foto_kk',
            'upload file kk' => 'foto_kk',
            'dokumen kk' => 'foto_kk',
            'link foto kk' => 'foto_kk',

            // mapping untuk biMBA unit
            'bimba unit' => 'bimba_unit',
            'bimba-aiueo unit' => 'bimba_unit',
            'bimba-aiueo' => 'bimba_unit',
            'bi mba unit' => 'bimba_unit',
            'bi mba-aiueo' => 'bimba_unit',
            'bi mba' => 'bimba_unit',
            'unit bimba' => 'bimba_unit',
            'unit bimba aiueo' => 'bimba_unit',

            // noise kolom dummy
            'column 7' => null,
        ];

        $parseDate = fn($v) => $this->tryParseDate((string) $v)?->format('Y-m-d');
        $parseDateTime = fn($v) => $this->tryParseDateTime((string) $v);

        $parseMoney = function ($v) {
            if ($v === null || $v === '') {
                return null;
            }
            $raw = preg_replace('/[^0-9,\.]/', '', (string) $v);
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
            return is_numeric($raw) ? (float) $raw : null;
        };

        $normStr = function ($v) {
            if ($v === null) {
                return null;
            }
            $vv = trim((string) $v);
            return ($vv === '' || $vv === '-' || strtolower($vv) === 'null') ? null : $vv;
        };

        $normHari = function ($v) use ($normStr) {
            $v = $normStr($v);
            if (!$v)
                return null;
            if (str_contains($v, ',')) {
                $v = trim(strtok($v, ','));
            }
            $map = [
                'seini' => 'Senin',
                'senin' => 'Senin',
                'selasa' => 'Selasa',
                'rabu' => 'Rabu',
                'kamis' => 'Kamis',
                'jumat' => 'Jumat',
                "jum'at" => 'Jumat',
                'sabtu' => 'Sabtu',
                'minggu' => 'Minggu',
            ];
            $x = strtolower($v);
            return $map[$x] ?? ucwords($x);
        };

        $normJam = function ($v) use ($normStr) {
            $v = $normStr($v);
            if (!$v)
                return null;
            if (str_contains($v, ',')) {
                $parts = explode(',', $v);
                $v = trim($parts[1] ?? $v);
            }
            $v = preg_replace('/^jam\s*/i', '', $v);
            $v = str_replace('.', ':', $v);
            return $v;
        };

        $result = [];
        $unmapped = [];

        foreach ($row as $rawHeader => $val) {
            $val = is_array($val) ? ($val[0] ?? null) : $val;

            ['base' => $base, 'role' => $role] = $this->parseHeader((string) $rawHeader);
            $baseNorm = $this->normHeader($base);

            // AYAH
            if ($role === 'ayah') {
                if ($baseNorm === 'nama') {
                    $result['nama_ayah'] = $normStr($val);
                    continue;
                }
                if ($baseNorm === 'agama') {
                    $result['agama_ayah'] = $normStr($val);
                    continue;
                }
                if ($baseNorm === 'pekerjaan') {
                    $result['pekerjaan_ayah'] = $normStr($val);
                    continue;
                }
                if ($baseNorm === 'alamat kantor') {
                    $result['alamat_kantor_ayah'] = $normStr($val);
                    continue;
                }
                if ($baseNorm === 'telepon kantor') {
                    $result['telepon_kantor_ayah'] = $normStr($val);
                    continue;
                }
                if (in_array($baseNorm, ['hp wa', 'hp', 'hpwa'], true)) {
                    $result['hp_ayah'] = $normStr($val);
                    continue;
                }
            }

            // IBU
            if ($role === 'ibu') {
                if ($baseNorm === 'nama') {
                    $result['nama_ibu'] = $normStr($val);
                    continue;
                }
                if ($baseNorm === 'agama') {
                    $result['agama_ibu'] = $normStr($val);
                    continue;
                }
                if ($baseNorm === 'pekerjaan') {
                    $result['pekerjaan_ibu'] = $normStr($val);
                    continue;
                }
                if ($baseNorm === 'alamat kantor') {
                    $result['alamat_kantor_ibu'] = $normStr($val);
                    continue;
                }
                if ($baseNorm === 'telepon kantor') {
                    $result['telepon_kantor_ibu'] = $normStr($val);
                    continue;
                }
                if (in_array($baseNorm, ['hp wa', 'hp', 'hpwa'], true)) {
                    $result['hp_ibu'] = $normStr($val);
                    continue;
                }
            }

            // General alias
            if (array_key_exists($baseNorm, $alias) && $alias[$baseNorm]) {
                $result[$alias[$baseNorm]] = $normStr($val);
                continue;
            }

            // Fallback informasi_bimba
            if (
                str_contains($baseNorm, 'informasi') &&
                (str_contains($baseNorm, 'bimba') || str_contains($baseNorm, 'aiueo')) &&
                (str_contains($baseNorm, 'didapat') || str_contains($baseNorm, 'sumber'))
            ) {
                $result['informasi_bimba'] = $normStr($val);
                continue;
            }

            // Jadwal / hari / jam
            if (str_contains($baseNorm, 'jadwal') && (str_contains($baseNorm, 'spp') || str_contains($baseNorm, 'belajar'))) {
                $result['hari'] = $result['hari'] ?? $normHari($val);
                $result['jam'] = $result['jam'] ?? $normJam($val);
                continue;
            }

            if (str_starts_with($baseNorm, 'hari') || str_contains($baseNorm, 'hari belajar')) {
                $result['hari'] = $normHari($val);
                continue;
            }

            if (
                str_starts_with($baseNorm, 'jam') ||
                str_contains($baseNorm, 'jam belajar') ||
                str_contains($baseNorm, 'jam les') ||
                str_contains($baseNorm, 'jam kb')
            ) {
                $result['jam'] = $normJam($val);
                continue;
            }

            // Biaya & SPP
            if (str_contains($baseNorm, 'biaya') && str_contains($baseNorm, 'pendaftaran')) {
                $result['biaya_pendaftaran'] = $normStr($val);
                continue;
            }

            if (str_contains($baseNorm, 'spp') && str_contains($baseNorm, 'bulan')) {
                $result['spp_bulanan'] = $normStr($val);
                continue;
            }

            $unmapped[] = [
                'raw' => (string) $rawHeader,
                'base' => $baseNorm,
                'role' => $role,
            ];
        }

        // Validations & turunan
        $result['informasi_bimba'] = $result['informasi_bimba'] ?? null;
        $result['informasi_humas_nama'] = $result['informasi_humas_nama'] ?? null;

        if (!empty($result['informasi_humas_nama']) && !empty($result['informasi_bimba'])) {
            $src = strtolower($result['informasi_bimba']);
            if (!str_contains($src, 'humas')) {
                $result['informasi_humas_nama'] = null;
            }
        }

        if (isset($result['form_timestamp'])) {
            $result['form_timestamp'] = $parseDateTime($result['form_timestamp']);
        }

        if (!empty($result['tgl_lahir'])) {
            $result['tgl_lahir'] = $parseDate($result['tgl_lahir']);
            $result['usia'] = $result['tgl_lahir'] ? Carbon::parse($result['tgl_lahir'])->age : null;
        }

        foreach (['kode_pos', 'no_rumah', 'rt', 'rw', 'kelurahan', 'kecamatan', 'kodya_kab', 'provinsi'] as $f) {
            $result[$f] = $result[$f] ?? null;
        }

        if (!empty($result['tanggal_masuk'])) {
            $result['tanggal_masuk'] = $parseDate($result['tanggal_masuk']);
        }

        if (isset($result['biaya_pendaftaran'])) {
            $result['biaya_pendaftaran'] = $parseMoney($result['biaya_pendaftaran']);
        }

        if (isset($result['spp_bulanan'])) {
            $result['spp_bulanan'] = $parseMoney($result['spp_bulanan']);
        }

        if (!empty($result['hari'])) {
            $result['hari'] = $normHari($result['hari']);
        }

        if (!empty($result['jam'])) {
            $result['jam'] = $normJam($result['jam']);
        }

        if (empty($result['orangtua'])) {
            $join = trim(implode(' & ', array_filter([
                $result['nama_ayah'] ?? null,
                $result['nama_ibu'] ?? null,
            ])));
            if ($join !== '') {
                $result['orangtua'] = $join;
            }
        }

        if (!empty($unmapped)) {
            Log::debug('IMPORT: Unmapped headers', $unmapped);
        }

        // Isi no_cabang otomatis jika ada bimba_unit
        if (!empty($result['bimba_unit']) && empty($result['no_cabang'] ?? null)) {
            try {
                $resolved = $this->resolveNoCabangFromBimbaUnit($result['bimba_unit']);
                if ($resolved) {
                    $result['no_cabang'] = $resolved;
                } else {
                    Log::info('IMPORT: belum ada mapping unit->no_cabang', [
                        'bimba_unit' => $result['bimba_unit'],
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('IMPORT: gagal resolve no_cabang', [
                    'bimba_unit' => $result['bimba_unit'],
                    'err' => $e->getMessage(),
                ]);
            }
        }

        Log::debug('IMPORT: mapped essentials', [
            'nama' => $result['nama'] ?? null,
            'informasi_bimba' => $result['informasi_bimba'] ?? null,
            'informasi_humas_nama' => $result['informasi_humas_nama'] ?? null,
            'hari' => $result['hari'] ?? null,
            'jam' => $result['jam'] ?? null,
            'bimba_unit' => $result['bimba_unit'] ?? null,
            'no_cabang' => $result['no_cabang'] ?? null,
        ]);

        return $result;
    }

    /**
     * Try to normalize a unit name for matching.
     */
    protected function normalizeUnitName(?string $s): ?string
    {
        if (!$s) {
            return null;
        }
        $s = trim(mb_strtolower((string) $s));
        // hapus kata noise umum
        $s = preg_replace('/\b(unit|bi?mba|aiueo|cabang)\b/u', ' ', $s);
        // buang punctuation
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    /**
     * Resolve no_cabang dari bimba_unit (versi dengan aturan khusus).
     */
    protected function resolveNoCabangFromBimbaUnit(?string $bimbaUnit): ?string
    {
        if (empty($bimbaUnit)) {
            return null;
        }

        $raw = trim((string) $bimbaUnit);
        $lower = mb_strtolower($raw);

        // ATURAN KHUSUS – DIPAKSA BENAR SELAMANYA
        if (str_contains($lower, 'griya') && str_contains($lower, 'pesona') && str_contains($lower, 'madani')) {
            return '05141'; // Griya Pesona Madani
        }
        if (str_contains($lower, 'sapta taruna iv') || str_contains($lower, 'sapta taruna 4')) {
            return '01045';
        }
        if (str_contains($lower, 'pondok indah')) {
            return '05141';
        }
        if (str_contains($lower, 'kebayoran')) {
            return '05142';
        }

        // Coba ambil kode angka langsung (misal "05141 Griya Pesona Madani")
        if (preg_match('/\b(05[0-9]{3,5})\b/', $raw, $m)) {
            return $m[1];
        }

        // Fallback: cek di tabel units dengan normalize
        $norm = $this->normalizeUnitName($raw);
        $unit = Unit::whereRaw('LOWER(bimba_unit) LIKE ?', ["%{$norm}%"])
            ->orWhereRaw('LOWER(bimba_unit) LIKE ?', ["%{$raw}%"])
            ->first(['no_cabang']);

        if (!empty($unit?->no_cabang)) {
            return $unit->no_cabang;
        }

        return null;
    }

    protected function normalizeSourceFromForm(?string $sumber): string
{
    if (!$sumber) return 'trial';

    $x = strtolower(trim($sumber));

    // Hanya Google Form yang dipaksa trial
    if (str_contains($x, 'trial') || str_contains($x, 'daftar trial')) {
        return 'trial';
    }

    // Kalau di form tertulis "mutasi" atau "pindahan", boleh langsung
    if (str_contains($x, 'mutasi') || str_contains($x, 'pindahan') || str_contains($x, 'pindah cabang')) {
        return 'direct'; // atau 'mutasi' kalau mau bedain
    }

    // Selain itu → tetap trial dulu (kebanyakan registrasi biasa)
    return 'trial';
}

    protected function buildUpsertKey(array $p): array
    {
        if (!empty($p['nim'])) {
            return ['nim' => $p['nim']];
        }

        if (!empty($p['email']) && !empty($p['nama'])) {
            return ['email' => $p['email'], 'nama' => $p['nama']];
        }

        if (!empty($p['nama']) && !empty($p['tgl_lahir'])) {
            return ['nama' => $p['nama'], 'tgl_lahir' => $p['tgl_lahir']];
        }

        return ['nama' => $p['nama']];
    }

    // ====================================================================
    // NIM PER-CABANG
    // ====================================================================
    protected function nextNim(?string $bimbaUnit = null, ?string $noCabang = null): string
    {
        // 1. Tentukan prefix kodeUnit (5 digit)
        if (!empty($noCabang)) {
            $kodeUnit = preg_replace('/\D/', '', $noCabang);
            if (strlen($kodeUnit) < 5) {
                $kodeUnit = str_pad($kodeUnit, 5, '0', STR_PAD_LEFT);
            }
        } elseif (!empty($bimbaUnit)) {
            $kodeUnit = $this->resolveNoCabangFromBimbaUnit($bimbaUnit) ?? '01045';
        } else {
            $kodeUnit = '01045';
        }

        // 2. Cari NIM terakhir dengan prefix kodeUnit di buku_induk & students
        $lastNimBukuInduk = BukuInduk::whereRaw('LEFT(nim, 5) = ?', [$kodeUnit])
            ->whereRaw('LENGTH(nim) = 9')
            ->lockForUpdate()
            ->orderByRaw('CAST(SUBSTRING(nim, 6, 4) AS UNSIGNED) DESC')
            ->value('nim');

        $lastNimStudent = Student::whereRaw('LEFT(nim, 5) = ?', [$kodeUnit])
            ->whereRaw('LENGTH(nim) = 9')
            ->lockForUpdate()
            ->orderByRaw('CAST(SUBSTRING(nim, 6, 4) AS UNSIGNED) DESC')
            ->value('nim');

        $lastSeq = 0;

        if ($lastNimBukuInduk) {
            $lastSeq = max($lastSeq, (int) substr($lastNimBukuInduk, 5));
        }
        if ($lastNimStudent) {
            $lastSeq = max($lastSeq, (int) substr($lastNimStudent, 5));
        }

        $nextSeq = $lastSeq + 1;

        // 3. Bentuk NIM: [kodeUnit][urut 4 digit]
        $nim = $kodeUnit . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        // 4. Jaga-jaga kalau sudah terpakai
        while (Student::where('nim', $nim)->lockForUpdate()->exists()) {
            $nextSeq++;
            $nim = $kodeUnit . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
        }

        return $nim;
    }

    protected function normHeader(string $h): string
    {
        $h = mb_strtolower($h, 'UTF-8');
        $h = trim($h);
        $h = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}\x{2060}]/u', '', $h);
        $h = str_replace("\xC2\xA0", ' ', $h);
        $h = preg_replace('/[.,\/\\\\\-\_\:\;\|\(\)\[\]\{\}]+/u', ' ', $h);
        $h = preg_replace('/\s+/u', ' ', $h);
        $h = preg_replace('/\bkanto\b/u', 'kantor', $h);
        return trim($h);
    }

    protected function parseHeader(string $raw): array
    {
        $low = mb_strtolower($raw, 'UTF-8');
        $role = null;

        if (preg_match('/\bayah\b/u', $low)) {
            $role = 'ayah';
        }
        if (preg_match('/\bibu\b/u', $low)) {
            $role = 'ibu';
        }

        $base = preg_replace('/\s*\((ayah|ibu)\)\s*/iu', ' ', $raw);
        $base = preg_replace('/\b(ayah|ibu)\b/iu', ' ', $base);
        $base = $this->normHeader($base);
        $base = preg_replace('/\bno\s+/u', '', $base);
        $base = preg_replace('/\bhp\s*\/\s*wa\b/u', 'hp wa', $base);

        return ['base' => trim($base), 'role' => $role];
    }

    protected function tryParseDate(?string $val): ?Carbon
    {
        if (!$val) {
            return null;
        }
        try {
            return Carbon::parse($val);
        } catch (\Throwable $e) {
        }

        $formats = ['d/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y', 'm-d-Y', 'Y/m/d', 'Y-m-d'];
        foreach ($formats as $f) {
            try {
                return Carbon::createFromFormat($f, $val);
            } catch (\Throwable $e) {
            }
        }
        return null;
    }

    protected function tryParseDateTime(?string $val): ?Carbon
    {
        if (!$val) {
            return null;
        }
        try {
            return Carbon::parse($val);
        } catch (\Throwable $e) {
        }

        $formats = [
            'd/m/Y H:i:s',
            'd-m-Y H:i:s',
            'm/d/Y H:i:s',
            'm-d-Y H:i:s',
            'Y-m-d H:i:s',
            'd/m/Y H:i',
            'd-m-Y H:i',
            'm/d/Y H:i',
            'm-d-Y H:i',
            'Y-m-d H:i',
            'd/m/Y H.i:s',
            'd-m-Y H.i:s',
            'm/d/Y H.i:s',
            'm-d-Y H.i:s',
            'Y-m-d H.i:s',
            'd/m/Y H.i',
            'd-m-Y H.i',
            'm/d/Y H.i',
            'm-d/Y H.i',
            'Y-m-d H.i',
        ];

        foreach ($formats as $f) {
            try {
                return Carbon::createFromFormat($f, $val);
            } catch (\Throwable $e) {
            }
        }

        return null;
    }

    protected function assertStudentColumns(): void
    {
        $need = ['biaya_pendaftaran', 'spp_bulanan', 'informasi_bimba', 'hari', 'jam'];
        foreach ($need as $col) {
            if (!Schema::hasColumn('students', $col)) {
                Log::warning("IMPORT: Kolom '{$col}' TIDAK ADA di tabel students. Nilai akan diabaikan oleh Eloquent.");
            }
        }
    }

    protected function ensureTrialRelation(Student $student, string $status = 'Baru'): void
{
    // HANYA untuk yang benar-benar trial
    if ($student->source !== 'trial') {
        return; // Mutasi / direct → tidak perlu MuridTrial
    }

    $info = $student->informasi_bimba
        ?? $student->informasi_humas_nama
        ?? $student->informasi
        ?? $student->info
        ?? 'Dari Google Form';

    // Jika SUDAH punya relasi trial → sync data penting saja
    if ($student->murid_trial_id) {
        $trial = $student->muridTrial;

        $updates = [];
        if (empty($trial->bimba_unit) && !empty($student->bimba_unit)) {
            $updates['bimba_unit'] = $student->bimba_unit;
        }
        if (empty($trial->no_cabang) && !empty($student->no_cabang)) {
            $updates['no_cabang'] = $student->no_cabang;
        }
        if (empty($trial->info) && !empty($info)) {
            $updates['info'] = $info;
        }
        if (empty($trial->hari) && !empty($student->hari)) {
            $updates['hari'] = $student->hari;
        }
        if (empty($trial->jam) && !empty($student->jam)) {
            $updates['jam'] = $student->jam;
        }

        if (!empty($updates)) {
            $trial->update($updates);
        }

        return;
    }

    // BUAT BARU → STATUS LANGSUNG 'baru' (bukan aktif!)
    try {
        $trial = MuridTrial::create([
            'nama'          => $student->nama,
            'status_trial'  => $status, // ← DI SINI: 'baru'
            'kelas'         => $student->kelas ?? 'Reguler',
            'tgl_lahir'     => $student->tgl_lahir,
            'usia'          => $student->usia,
            'orangtua'      => $student->orangtua,
            'no_telp'       => $student->no_telp,
            'alamat'        => $student->alamat,
            'guru_trial'    => $student->guru_wali,
            'tgl_mulai'     => $student->tanggal_masuk ?? now(),
            'hari'          => $student->hari,
            'jam'           => $student->jam,
            'info'          => $info,
            'bimba_unit'    => $student->bimba_unit,
            'no_cabang'     => $student->no_cabang,
            'petugas_trial' => $student->petugas_trial ?? null,
        ]);

        $student->murid_trial_id = $trial->id;
        $student->saveQuietly();

        Log::info('IMPORT: MuridTrial BARU dibuat dari Google Form → status: baru', [
            'trial_id'   => $trial->id,
            'student_id' => $student->id,
            'nama'       => $trial->nama,
        ]);

    } catch (\Throwable $e) {
        Log::error('IMPORT: Gagal buat MuridTrial', [
            'student_id' => $student->id,
            'nama'       => $student->nama,
            'error'      => $e->getMessage(),
        ]);
        report($e);
    }
}

    protected function essentials(array $payload, ?Student $student = null): array
    {
        return [
            'nama' => $payload['nama'] ?? ($student->nama ?? null),
            'biaya_pendaftaran' => $payload['biaya_pendaftaran'] ?? ($student->biaya_pendaftaran ?? null),
            'spp_bulanan' => $payload['spp_bulanan'] ?? ($student->spp_bulanan ?? null),
            'informasi_bimba' => $payload['informasi_bimba'] ?? ($student->informasi_bimba ?? null),
            'hari' => $payload['hari'] ?? ($student->hari ?? null),
            'jam' => $payload['jam'] ?? ($student->jam ?? null),
            'source' => $payload['source'] ?? ($student->source ?? null),
            'murid_trial_id' => $student->murid_trial_id ?? null,
        ];
    }
}
