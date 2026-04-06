<?php

namespace App\Imports;

use App\Models\Penerimaan;
use App\Models\BukuInduk;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PenerimaanImport implements ToModel, WithHeadingRow
{
    private function parseInt($value): int
    {
        if ($value === null || $value === '') return 0;
        $value = trim((string) $value);
        $value = str_replace(['Rp', 'Rp.', ' ', '.', ',', 'Rp ', 'rp'], '', $value);
        return is_numeric($value) ? (int)$value : 0;
    }

    private function parseDate($value): ?string
    {
        if (empty($value)) return null;

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Throwable $e) {
                Log::warning('Gagal parse tanggal Excel', ['value' => $value]);
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            Log::warning('Gagal parse tanggal string', ['value' => $value]);
            return null;
        }
    }

    private function getValue($row, array $possibleHeaders): ?string
    {
        $rowLowerKeys = array_map('strtolower', array_keys($row));

        foreach ($possibleHeaders as $header) {
            $headerLower = strtolower(trim($header));
            $keyIndex = array_search($headerLower, $rowLowerKeys);
            if ($keyIndex !== false) {
                $actualKey = array_keys($row)[$keyIndex];
                $value = trim((string) ($row[$actualKey] ?? ''));
                return $value !== '' ? $value : null;
            }
        }
        return null;
    }

    /**
     * Generate nomor kwitansi dengan format sama seperti controller store
     */
    private function generateKwitansi(string $nim, Carbon $tanggal): string
    {
        $nimLast3     = str_pad(substr($nim, -3), 3, '0', STR_PAD_LEFT);
        $tahun2       = str_pad($tanggal->year % 100, 2, '0', STR_PAD_LEFT);
        $bulan2       = str_pad($tanggal->month, 2, '0', STR_PAD_LEFT);
        $tanggal2     = str_pad($tanggal->day, 2, '0', STR_PAD_LEFT);

        $base         = "KW{$nimLast3}{$tahun2}{$bulan2}{$tanggal2}";

        // Cari urutan terakhir untuk murid + tanggal ini
        $last = Penerimaan::where('nim', $nim)
            ->whereDate('tanggal', $tanggal->format('Y-m-d'))
            ->where('kwitansi', 'like', $base . '%')
            ->orderByRaw("CAST(SUBSTRING(kwitansi, -2) AS UNSIGNED) DESC")
            ->first();

        $nextIndex = 1;
        if ($last && preg_match('/(\d{2})$/', $last->kwitansi, $matches)) {
            $nextIndex = (int)$matches[1] + 1;
        }

        return $base . str_pad($nextIndex, 2, '0', STR_PAD_LEFT);
    }

    public function model(array $row)
    {
        try {
            $nim = trim((string) ($this->getValue($row, ['nim', 'NIM']) ?? ''));
            if (empty($nim)) {
                Log::warning('IMPORT SKIP - NIM kosong');
                return null;
            }

            $tanggalRaw = $this->getValue($row, ['tanggal', 'Tanggal']) ?? null;
            $tanggal    = $this->parseDate($tanggalRaw);

            if (!$tanggal) {
                Log::warning('IMPORT SKIP - Tanggal tidak valid/kosong', ['nim' => $nim]);
                return null;
            }

            $tanggalCarbon = Carbon::parse($tanggal);

            $buku = BukuInduk::where('nim', $nim)->first();

            // Ambil kwitansi dari Excel
            $kwitansiExcel = $this->getValue($row, ['kwitansi', 'Kwitansi']) ?? '';

            // Jika kosong → generate
            if (empty($kwitansiExcel)) {
                $kwitansi = $this->generateKwitansi($nim, $tanggalCarbon);
                Log::info('IMPORT - Generate kwitansi baru', [
                    'nim'      => $nim,
                    'tanggal'  => $tanggal,
                    'kwitansi' => $kwitansi,
                ]);
            } else {
                $kwitansi = trim($kwitansiExcel);
                Log::info('IMPORT - Menggunakan kwitansi dari Excel', [
                    'nim'      => $nim,
                    'kwitansi' => $kwitansi,
                ]);
            }

            // Cek duplikat kwitansi (penting!)
            if (Penerimaan::where('kwitansi', $kwitansi)->exists()) {
                Log::warning('IMPORT SKIP - Kwitansi sudah ada di database', [
                    'nim'      => $nim,
                    'kwitansi' => $kwitansi,
                ]);
                return null;
            }

            // --------------------------------------------------
            // Sisanya sama seperti kode asli Anda
            // --------------------------------------------------

            $via        = strtolower(trim($this->getValue($row, ['via', 'Via']) ?? 'cash'));
            $bulan      = trim($this->getValue($row, ['bulan', 'Bulan']) ?? '');
            $tahun      = (int) ($this->getValue($row, ['tahun', 'Tahun']) ?? $tanggalCarbon->year);

            $nama_murid = trim($this->getValue($row, ['nama_murid', 'Nama Murid']) ?? ($buku?->nama ?? ''));
            $kelas      = trim($this->getValue($row, ['kelas', 'Kelas']) ?? ($buku?->kelas ?? ''));
            $gol        = trim($this->getValue($row, ['gol', 'Gol']) ?? ($buku?->gol ?? ''));
            $kd         = trim($this->getValue($row, ['kd', 'KD']) ?? ($buku?->kd ?? ''));
            $status     = trim($this->getValue($row, ['status', 'Status']) ?? ($buku?->status ?? 'aktif'));
            $guru       = trim($this->getValue($row, ['guru', 'Guru']) ?? ($buku?->guru ?? ''));

            $bimba_unit = trim($this->getValue($row, ['bimba_unit', 'biMBA Unit', 'unit']) ?? ($buku?->bimba_unit ?? ''));
            $no_cabang  = trim($this->getValue($row, ['no_cabang', 'No Cabang']) ?? ($buku?->no_cabang ?? ''));

            $daftar      = $this->parseInt($this->getValue($row, ['daftar', 'Daftar']) ?? 0);
            $voucher     = $this->parseInt($this->getValue($row, ['voucher', 'Voucher']) ?? 0);
            $spp         = $this->parseInt($this->getValue($row, ['spp', 'SPP', 'nilai_spp']) ?? 0);
            $kaos        = $this->parseInt($this->getValue($row, ['kaos', 'Kaos Pendek']) ?? 0);
            $kaosPanjang = $this->parseInt($this->getValue($row, ['kaos_lengan_panjang', 'Kaos Panjang']) ?? 0);
            $kpk         = $this->parseInt($this->getValue($row, ['kpk', 'KPK']) ?? 0);
            $tas         = $this->parseInt($this->getValue($row, ['tas', 'TAS']) ?? 0);
            $rbas        = $this->parseInt($this->getValue($row, ['RBAS', 'rbas']) ?? 0);
            $bcabs01     = $this->parseInt($this->getValue($row, ['BCABS01', 'bcabs01']) ?? 0);
            $bcabs02     = $this->parseInt($this->getValue($row, ['BCABS02', 'bcabs02']) ?? 0);
            $sertifikat  = $this->parseInt($this->getValue($row, ['sertifikat', 'Sertifikat']) ?? 0);
            $stpb        = $this->parseInt($this->getValue($row, ['stpb', 'STPB']) ?? 0);
            $event       = $this->parseInt($this->getValue($row, ['event', 'Event']) ?? 0);
            $lain_lain   = $this->parseInt($this->getValue($row, ['lain_lain', 'Lain-lain']) ?? 0);

            $ukuranPendek   = trim($this->getValue($row, ['ukuran_kaos_pendek', 'Ukuran Kaos Pendek']) ?? '');
            $ukuranPanjang  = trim($this->getValue($row, ['ukuran_kaos_panjang', 'Ukuran Kaos Panjang']) ?? '');

            $tglKaosPendek  = $this->parseDate($this->getValue($row, ['tanggal_penyerahan_kaos_pendek']) ?? null);
            $tglKaosPanjang = $this->parseDate($this->getValue($row, ['tanggal_penyerahan_kaos_panjang']) ?? null);
            // ... (tanggal penyerahan lainnya sama seperti kode asli)

            $kaosPendekDetails  = $this->getValue($row, ['kaos_pendek_details']) ?? null;
            $kaosPanjangDetails = $this->getValue($row, ['kaos_panjang_details']) ?? null;

            $total_excel = $this->parseInt($this->getValue($row, ['total', 'Total']) ?? 0);
            $calc_total  = $daftar + $voucher + $spp + $kaos + $kaosPanjang + $kpk + $tas + $rbas + $bcabs01 + $bcabs02 + $sertifikat + $stpb + $event + $lain_lain;
            $total       = $total_excel > 0 ? $total_excel : $calc_total;

            $data = [
                'kwitansi'                    => $kwitansi,
                'via'                         => $via,
                'tanggal'                     => $tanggal,
                'bulan'                       => $bulan,
                'tahun'                       => $tahun,
                'nim'                         => $nim,
                'nama_murid'                  => $nama_murid,
                'kelas'                       => $kelas,
                'gol'                         => $gol,
                'kd'                          => $kd,
                'status'                      => $status,
                'guru'                        => $guru,
                'bimba_unit'                  => $bimba_unit,
                'no_cabang'                   => $no_cabang,

                'daftar'                      => $daftar,
                'voucher'                     => $voucher,
                'spp'                         => $spp,
                'kaos'                        => $kaos,
                'kaos_lengan_panjang'         => $kaosPanjang,
                'ukuran_kaos_pendek'          => $ukuranPendek,
                'ukuran_kaos_panjang'         => $ukuranPanjang,
                'tanggal_penyerahan_kaos_pendek'  => $tglKaosPendek,
                'tanggal_penyerahan_kaos_panjang' => $tglKaosPanjang,
                'kpk'                         => $kpk,
                // ... (tambahkan field tanggal_penyerahan_* lainnya sesuai kode asli)

                'tas'                         => $tas,
                'RBAS'                        => $rbas,
                'BCABS01'                     => $bcabs01,
                'BCABS02'                     => $bcabs02,
                'sertifikat'                  => $sertifikat,
                'stpb'                        => $stpb,
                'event'                       => $event,
                'lain_lain'                   => $lain_lain,

                'total'                       => $total,

                'kaos_pendek_details'         => $kaosPendekDetails,
                'kaos_panjang_details'        => $kaosPanjangDetails,

                'bukti_transfer_path'         => null,
            ];

            Log::info('IMPORT - Siap simpan data', [
                'nim'      => $nim,
                'kwitansi' => $kwitansi,
                'total'    => $total,
            ]);

            return new Penerimaan($data);

        } catch (\Throwable $e) {
            Log::error('PenerimaanImport GAGAL', [
                'row'   => $row,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }
}