<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Penerimaan;
use App\Models\PettyCash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class RekapController extends Controller
{
    public function petty(Request $request)
    {
        $perPage = max((int) $request->input('per_page', 50), 50);

        $start = $request->input('start_date');
        $end   = $request->input('end_date');
        $unit  = $request->input('unit');

        /*
        |--------------------------------------------------------------------------
        | UNIT LIST
        |--------------------------------------------------------------------------
        */
        $unitList = \App\Models\Unit::withoutGlobalScopes()
            ->orderBy('biMBA_unit')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | QUERY BUILDER
        |--------------------------------------------------------------------------
        */
        $penerimaanQuery = Penerimaan::query();

        if ($start) {
            $penerimaanQuery->whereDate('tanggal', '>=', Carbon::parse($start));
        }
        if ($end) {
            $penerimaanQuery->whereDate('tanggal', '<=', Carbon::parse($end));
        }
        if ($unit) {
            $penerimaanQuery->where('bimba_unit', $unit);
        }

        /*
        |--------------------------------------------------------------------------
        | DATA UNTUK VIEW
        |--------------------------------------------------------------------------
        */
        $penerimaan = (clone $penerimaanQuery)
            ->orderByDesc('tanggal')
            ->paginate($perPage)
            ->withQueryString();

        $allPenerimaan = (clone $penerimaanQuery)->get();

        /*
        |--------------------------------------------------------------------------
        | PETTY CASH
        |--------------------------------------------------------------------------
        */
        $pettyCashQuery = PettyCash::query();

        if ($start) $pettyCashQuery->whereDate('tanggal', '>=', Carbon::parse($start));
        if ($end)   $pettyCashQuery->whereDate('tanggal', '<=', Carbon::parse($end));
        if ($unit)  $pettyCashQuery->where('bimba_unit', $unit);

        $pettycash = $pettyCashQuery->orderBy('tanggal', 'asc')->get();

        /*
        |--------------------------------------------------------------------------
        | toNumber - VERSI TERBAIK SAAT INI
        |--------------------------------------------------------------------------
        */
        $toNumber = function ($value) {
            if (blank($value) || $value === '0' || $value === 0) {
                return 0;
            }

            $value = (string) $value;

            // Bersihkan simbol
            $value = str_replace(['Rp', 'rp', 'IDR', 'Rp.', ' ', 'Rp ', ','], '', $value);

            // Hapus titik ribuan (1.234.567 → 1234567)
            $value = preg_replace('/\.(?=\d{3})/', '', $value);

            // Ambil angka
            if (preg_match('/[\d.]+/', $value, $matches)) {
                $clean = $matches[0];
                if (is_numeric($clean)) {
                    return (float) $clean;
                }
            }

            return 0;
        };

        /*
        |--------------------------------------------------------------------------
        | SALDO AWAL & PETTY CASH
        |--------------------------------------------------------------------------
        */
        $saldoAwalQuery = PettyCash::where('kategori', 'Saldo Awal');
        if ($unit) $saldoAwalQuery->where('bimba_unit', $unit);
        
        $saldoAwalRecord = $saldoAwalQuery->first();
        $saldoAwal = $saldoAwalRecord ? $toNumber($saldoAwalRecord->debit) : 0;

        $pcOperasionalQuery = (clone $pettyCashQuery)->where('kategori', '!=', 'Saldo Awal');

        $totalDebit = $pcOperasionalQuery->get()->sum(fn($x) => $toNumber($x->debit));
        $totalKredit = $pcOperasionalQuery->get()->sum(fn($x) => $toNumber($x->kredit));

        $saldoAkhir = $saldoAwal + $totalDebit - $totalKredit;

        $byKategori = $pcOperasionalQuery
            ->get()
            ->groupBy('kategori')
            ->map(fn($group) => $group->sum(fn($item) => $toNumber($item->kredit)))
            ->filter(fn($value, $key) => 
                $value > 0 && 
                !str_contains(strtolower($key), 'petty cash') && 
                !str_contains($key, '500')
            );

        /*
        |--------------------------------------------------------------------------
        | REKAP PENERIMAAN
        |--------------------------------------------------------------------------
        */
        $itemColumns = [
            'Daftar'      => 'daftar',
            'Voucher'     => 'voucher',
            'Kaos'        => 'kaos',
            'KPK'         => 'kpk',
            'Sertifikat'  => 'sertifikat',
            'STPB'        => 'stpb',
            'Tas'         => 'tas',
            'BCABS'       => 'bcabs',
            'RBAS'        => 'rbas',
            'Event'       => 'event',
            'Lain-lain'   => 'lain_lain',
        ];

        $masterItems = [
            ['kode' => '4-00001', 'label' => 'Daftar'],
            ['kode' => '4-00002', 'label' => 'Voucher'],
            ['kode' => '4-00003', 'label' => 'SPP (Cash/Transfer)'],
            ['kode' => '', 'label' => 'Cash'],
            ['kode' => '', 'label' => 'Transfer'],
            ['kode' => '', 'label' => 'EDC'],
            ['kode' => '', 'label' => 'VA'],
            ['kode' => '4-00004', 'label' => 'Kaos'],
            ['kode' => '4-00005', 'label' => 'KPK'],
            ['kode' => '4-00006', 'label' => 'Sertifikat'],
            ['kode' => '4-00007', 'label' => 'STPB'],
            ['kode' => '4-00008', 'label' => 'Tas'],
            ['kode' => '4-00009', 'label' => 'BCABS'],
            ['kode' => '', 'label' => 'RBAS'],
            ['kode' => '4-00010', 'label' => 'Event'],
            ['kode' => '4-00011', 'label' => 'Lain-lain'],
        ];

        $normVia = function ($v) {
            $v = strtoupper(trim((string) $v));
            return match ($v) {
                'TRANSFER', 'TF', 'BANK TRANSFER' => 'TRANSFER',
                'CASH', 'TUNAI'                   => 'CASH',
                'EDC', 'DEBIT'                    => 'EDC',
                'VA', 'VIRTUAL ACCOUNT'           => 'VA',
                default => 'LAINNYA',
            };
        };

        $methodTotals = ['CASH' => 0, 'TRANSFER' => 0, 'EDC' => 0, 'VA' => 0];
        $map = [];

        // Debug
        $debugDaftar = [];
        $debugVoucher = [];

        foreach ($allPenerimaan as $row) {
            $via = $normVia(data_get($row, 'via') ?: data_get($row, 'metode_bayar'));
            $isVA = ($via === 'VA');

            // SPP
            $sppVal = $toNumber(data_get($row, 'spp'));
            if ($sppVal > 0 && isset($methodTotals[$via])) {
                $methodTotals[$via] += $sppVal;
            }

            // Item Lainnya
            foreach ($itemColumns as $label => $col) {
                if ($label === 'SPP') continue;

                $val = $toNumber(data_get($row, $col));

                // Debug Daftar
                if ($label === 'Daftar' && $val > 0) {
                    $debugDaftar[] = [
                        'id' => $row->id ?? 'unknown',
                        'tanggal' => $row->tanggal ?? '-',
                        'raw' => data_get($row, $col),
                        'converted' => $val,
                    ];
                }

                // Debug Voucher
                if ($label === 'Voucher' && $val > 0) {
                    $debugVoucher[] = [
                        'id' => $row->id ?? 'unknown',
                        'tanggal' => $row->tanggal ?? '-',
                        'raw' => data_get($row, $col),
                        'converted' => $val,
                    ];
                }

                if ($val > 100_000_000_000) {
                    Log::warning("Nilai mencurigakan", [
                        'kolom' => $col,
                        'raw' => data_get($row, $col),
                        'processed' => $val
                    ]);
                    continue;
                }

                if ($val <= 0) continue;

                $map[$label] ??= ['va' => 0, 'non_va' => 0];

                if ($isVA) {
                    $map[$label]['va'] += $val;
                } else {
                    $map[$label]['non_va'] += $val;
                }
            }
        }

        // SPP khusus
        $map['SPP (Cash/Transfer)'] = [
            'va' => $methodTotals['VA'],
            'non_va' => $methodTotals['CASH'] + $methodTotals['TRANSFER'] + $methodTotals['EDC'],
        ];

        $totalVA = collect($map)->sum('va');
        $totalNonVA = collect($map)->sum('non_va');

        /*
        |--------------------------------------------------------------------------
        | FINAL REKAP
        |--------------------------------------------------------------------------
        */
        $rekapAiueo = [];

        foreach ($masterItems as $m) {
            $label = $m['label'];
            $kode  = $m['kode'];

            if (in_array($label, ['Cash', 'Transfer', 'EDC', 'VA'])) {
                $key = strtoupper($label);
                $rekapAiueo[] = [
                    'kode'   => $kode,
                    'type'   => $label,
                    'va'     => 0,
                    'non_va' => $methodTotals[$key] ?? 0,
                ];
                continue;
            }

            $rekapAiueo[] = [
                'kode'   => $kode,
                'type'   => $label,
                'va'     => $map[$label]['va'] ?? 0,
                'non_va' => $map[$label]['non_va'] ?? 0,
            ];
        }

        return view('rekap.petty.index', compact(
            'penerimaan',
            'pettycash',
            'rekapAiueo',
            'totalVA',
            'totalNonVA',
            'saldoAwal',
            'totalKredit',
            'saldoAkhir',
            'byKategori',
            'unitList',
            'unit',
            'start',
            'end',
            'debugDaftar',
            'debugVoucher'
        ));
    }
}