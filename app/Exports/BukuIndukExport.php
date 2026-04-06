<?php

namespace App\Exports;

use App\Models\BukuInduk;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Contracts\Database\Query\Builder;

class BukuIndukExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query(): Builder
{
    $query = BukuInduk::query()->with('jadwal');  // ← TAMBAHKAN INI

    // Filter sesuai halaman index
    if (!empty($this->filters['murid'])) {
        $query->where('nim', $this->filters['murid']);
    }
    if (!empty($this->filters['unit'])) {
        $query->where('bimba_unit', $this->filters['unit']);
    }
    if (!empty($this->filters['status'])) {
        $query->where('status', $this->filters['status']);
    }

    return $query->orderBy('nim', 'asc');
}

    /**
     * Header harus sesuai dengan yang di-support import (case-sensitive & variatif)
     */
    public function headings(): array
    {
        return [
            'nim',
            'nama',
            'unit',               // ← pakai 'unit' agar cocok dengan import
            'no cabang',          // ← pakai 'no cabang' agar cocok
            'kelas',
            'gol',
            'kd',
            'spp',                // angka murni tanpa Rp
            'tgl_masuk',
            'status',
            'tmpt_lahir',
            'tgl_lahir',
            'usia',
            'lama_bljr',
            'tahap',
            'tgl_keluar',
            'kategori_keluar',
            'alasan',
            'guru',               // ← penting, cocok dengan import
            'orangtua',
            'no_telp_hp',
            'alamat_murid',
            'petugas_trial',
            'note',
            'periode',
            'tgl_mulai',
            'tgl_akhir',
            'tgl_bayar',
            'tgl_selesai',
            'level',
            'jenis_kbm',
            'kode_jadwal',
            'hari_jam',
            'no_cab_merge',
            'no_pembayaran_murid',
            'note_garansi',
            'alert',
            'alert2',
            'asal_modul',
            'keterangan_optional',
            'status_pindah',
            'tanggal_pindah',
            'ke_bimba_intervio',
            'keterangan',
        ];
    }

    /**
     * Mapping data sesuai header di atas
     * - SPP jadi angka murni (import bisa baca)
     * - Unit & cabang dipisah (bukan digabung)
     */
    public function map($item): array
    {
        // Bersihkan SPP jadi angka murni
        $sppClean = $item->spp ? (int) str_replace(['.', 'Rp', ' '], '', $item->spp) : null;

        return [
            $item->nim ?? '',
            $item->nama ?? '',
            $item->bimba_unit ?? '',           // langsung bimba_unit
            $item->no_cabang ?? '',            // langsung no_cabang
            $item->kelas ?? '',
            $item->gol ?? '',
            $item->kd ?? '',
            $sppClean,                         // angka saja
            $item->tgl_masuk ?? '',
            $item->status ?? '',
            $item->tmpt_lahir ?? '',
            $item->tgl_lahir ?? '',
            $item->usia ?? '',
            $item->lama_bljr ?? '',
            $item->tahap ?? '',
            $item->tgl_keluar ?? '',
            $item->kategori_keluar ?? '',
            $item->alasan ?? '',
            $item->guru ?? '',                 // penting untuk profile
            $item->orangtua ?? '',
            $item->no_telp_hp ?? '',
            $item->alamat_murid ?? '',
            $item->petugas_trial ?? '',
            $item->note ?? '',
            $item->periode ?? '',
            $item->tgl_mulai ?? '',
            $item->tgl_akhir ?? '',
            $item->tgl_bayar ?? '',
            $item->tgl_selesai ?? '',
            $item->level ?? '',
            $item->jenis_kbm ?? '',
            $item->kode_jadwal ?? '',
            $item->hari_jam_export ?? '',   // ← pakai accessor baru
            $item->hari_jam ?? '',
            $item->no_cab_merge ?? '',
            $item->no_pembayaran_murid ?? '',
            $item->note_garansi ?? '',
            $item->alert ?? '',
            $item->alert2 ?? '',
            $item->asal_modul ?? '',
            $item->keterangan_optional ?? '',
            $item->status_pindah ?? '',
            $item->tanggal_pindah ?? '',
            $item->ke_bimba_intervio ?? '',
            $item->keterangan ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
{
    return [
        // Header style (tetap aman)
        1 => [
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['argb' => 'FF000000'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFD9EAD3'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ],

        // Format tanggal - benar pakai array & 'formatCode'
        'J' => [
            'numberFormat' => [
                'formatCode' => 'dd-mm-yyyy',
            ],
        ],
        'L' => [
            'numberFormat' => [
                'formatCode' => 'dd-mm-yyyy',
            ],
        ],
        'P' => [
            'numberFormat' => [
                'formatCode' => 'dd-mm-yyyy',
            ],
        ],
        'U' => [
            'numberFormat' => [
                'formatCode' => 'dd-mm-yyyy',
            ],
        ],
        'AA' => [
            'numberFormat' => [
                'formatCode' => 'dd-mm-yyyy',
            ],
        ],
        'AB' => [
            'numberFormat' => [
                'formatCode' => 'dd-mm-yyyy',
            ],
        ],
        'AC' => [
            'numberFormat' => [
                'formatCode' => 'dd-mm-yyyy',
            ],
        ],
        'AK' => [
            'numberFormat' => [
                'formatCode' => 'dd-mm-yyyy',
            ],
        ],
    ];
}
}