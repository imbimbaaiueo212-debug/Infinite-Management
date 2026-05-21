<?php

namespace App\Imports;

use App\Models\Penerimaan;
use App\Models\BukuInduk;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PenerimaanImport implements ToModel, WithHeadingRow
{
    private function parseInt($value): int
    {
        if ($value === null || $value === '') return 0;
        $value = trim((string) $value);
        $value = str_replace(['Rp', 'Rp.', ' ', '.', ',', 'Rp ', 'rp', 'IDR'], '', $value);
        return is_numeric($value) ? (int)$value : 0;
    }

    private function parseDate($value): ?string
    {
        if (empty($value)) return null;

        // Excel Serial Number
        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Throwable $e) {
                Log::warning('Gagal parse tanggal Excel serial', ['value' => $value]);
            }
        }

        // String date
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

    private function generateKwitansi(string $nim, Carbon $tanggal): string
    {
        $nimLast3 = str_pad(substr($nim, -3), 3, '0', STR_PAD_LEFT);
        $tahun2   = str_pad($tanggal->year % 100, 2, '0', STR_PAD_LEFT);
        $bulan2   = str_pad($tanggal->month, 2, '0', STR_PAD_LEFT);
        $tanggal2 = str_pad($tanggal->day, 2, '0', STR_PAD_LEFT);

        $base = "KW{$nimLast3}{$tahun2}{$bulan2}{$tanggal2}";

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
            $nim = trim((string) ($this->getValue($row, ['nim', 'NIM', 'no_induk']) ?? ''));
            if (empty($nim)) {
                return null;
            }

            $tanggalRaw = $this->getValue($row, ['tanggal', 'Tanggal', 'tgl', 'date']);
            $tanggal    = $this->parseDate($tanggalRaw);

            if (!$tanggal) {
                Log::warning('IMPORT SKIP - Tanggal tidak valid', [
                    'nim' => $nim, 
                    'raw' => $tanggalRaw
                ]);
                return null;
            }

            $tanggalCarbon = Carbon::parse($tanggal);

            // === LOG UNTUK DEBUG TAHUN 2023 ===
            Log::info("IMPORT PROCESS", [
                'nim'   => $nim,
                'tahun' => $tanggalCarbon->year,
                'tanggal' => $tanggal
            ]);

            $buku = BukuInduk::where('nim', $nim)->first();

            // Kwitansi
            $kwitansiExcel = trim($this->getValue($row, ['kwitansi', 'Kwitansi', 'no_kwitansi']) ?? '');

            if (empty($kwitansiExcel)) {
                $kwitansi = $this->generateKwitansi($nim, $tanggalCarbon);
            } else {
                $kwitansi = $kwitansiExcel;
            }

            // Cek duplikat
            if (Penerimaan::where('kwitansi', $kwitansi)->exists()) {
                Log::info('IMPORT SKIP - Kwitansi sudah ada', ['kwitansi' => $kwitansi]);
                return null;
            }

            // Data Lainnya
            $via   = strtolower(trim($this->getValue($row, ['via', 'Via', 'pembayaran']) ?? 'cash'));
            $bulan = trim($this->getValue($row, ['bulan', 'Bulan']) ?? $tanggalCarbon->translatedFormat('F'));
            $tahun = (int) ($this->getValue($row, ['tahun', 'Tahun']) ?? $tanggalCarbon->year);

            $nama_murid = trim($this->getValue($row, ['nama_murid', 'Nama Murid', 'nama']) ?? ($buku?->nama ?? ''));
            $kelas      = trim($this->getValue($row, ['kelas', 'Kelas']) ?? ($buku?->kelas ?? ''));
            $gol        = trim($this->getValue($row, ['gol', 'Gol']) ?? ($buku?->gol ?? ''));
            $kd         = trim($this->getValue($row, ['kd', 'KD']) ?? ($buku?->kd ?? ''));
            $status     = strtolower(trim($this->getValue($row, ['status', 'Status']) ?? ($buku?->status ?? 'aktif')));

            if (!in_array($status, ['aktif', 'keluar'])) {
                $status = 'aktif';
            }

            // Parsing Nominal
            $daftar   = $this->parseInt($this->getValue($row, ['daftar', 'Daftar', 'pendaftaran']));
            $voucher  = $this->parseInt($this->getValue($row, ['voucher', 'Voucher']));
            $spp      = $this->parseInt($this->getValue($row, ['spp', 'SPP']));
            $kaos     = $this->parseInt($this->getValue($row, ['kaos', 'Kaos']));
            $kaosPanjang = $this->parseInt($this->getValue($row, ['kaos_lengan_panjang', 'Kaos Panjang']));
            $kpk      = $this->parseInt($this->getValue($row, ['kpk', 'KPK']));
            $tas      = $this->parseInt($this->getValue($row, ['tas', 'TAS']));
            $rbas     = $this->parseInt($this->getValue($row, ['rbas', 'RBAS']));
            $bcabs01  = $this->parseInt($this->getValue($row, ['bcabs01', 'BCABS01']));
            $bcabs02  = $this->parseInt($this->getValue($row, ['bcabs02', 'BCABS02']));
            $sertifikat = $this->parseInt($this->getValue($row, ['sertifikat', 'Sertifikat']));
            $stpb     = $this->parseInt($this->getValue($row, ['stpb', 'STPB']));
            $event    = $this->parseInt($this->getValue($row, ['event', 'Event']));
            $lain_lain = $this->parseInt($this->getValue($row, ['lain_lain', 'Lain-lain']));

            $total_excel = $this->parseInt($this->getValue($row, ['total', 'Total', 'jumlah']));
            $total       = $total_excel > 0 ? $total_excel : 
                           ($daftar + $voucher + $spp + $kaos + $kaosPanjang + $kpk + $tas + $rbas + $bcabs01 + $bcabs02 + $sertifikat + $stpb + $event + $lain_lain);

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
                'guru'                        => trim($this->getValue($row, ['guru', 'Guru']) ?? ($buku?->guru ?? '')),
                'bimba_unit'                  => trim($this->getValue($row, ['bimba_unit', 'unit', 'biMBA_unit']) ?? ($buku?->bimba_unit ?? '')),
                'no_cabang'                   => trim($this->getValue($row, ['no_cabang', 'cabang']) ?? ($buku?->no_cabang ?? '')),

                'daftar'                      => $daftar,
                'voucher'                     => $voucher,
                'spp'                         => $spp,
                'kaos'                        => $kaos,
                'kaos_lengan_panjang'         => $kaosPanjang,
                'kpk'                         => $kpk,
                'tas'                         => $tas,
                'RBAS'                        => $rbas,
                'BCABS01'                     => $bcabs01,
                'BCABS02'                     => $bcabs02,
                'sertifikat'                  => $sertifikat,
                'stpb'                        => $stpb,
                'event'                       => $event,
                'lain_lain'                   => $lain_lain,
                'total'                       => $total,
            ];

            Penerimaan::updateOrCreate(
                ['kwitansi' => $kwitansi],
                $data
            );

            Log::info("✅ IMPORT BERHASIL", [
                'tahun' => $tahun,
                'nim'   => $nim,
                'kwitansi' => $kwitansi,
                'total' => $total
            ]);

            return null;

        } catch (\Throwable $e) {
            Log::error('PenerimaanImport ERROR', [
                'error' => $e->getMessage(),
                'row'   => $row
            ]);
            return null;
        }
    }
}