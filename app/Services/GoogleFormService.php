<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use Illuminate\Support\Collection;

class GoogleFormService
{
    protected Client $client;
    protected Sheets $service;
    protected string $spreadsheetId;

    public function __construct()
    {
        $this->spreadsheetId = (string) env('GOOGLE_SHEET_ID');

        $this->client = new Client();
        $this->client->setAuthConfig(storage_path('app/google/laravelsheetsproject-4d56608b1c64.json'));
        $this->client->addScope(Sheets::SPREADSHEETS);

        $this->service = new Sheets($this->client);
    }

    /**
     * Ambil semua responses dari Google Form (Sheet) sebagai koleksi array asosiatif.
     * - Menentukan kolom terakhir berdasarkan header baris 1 (dinamis, tidak mentok di Z).
     * - Mem-pad setiap baris sampai jumlah header agar trailing kosong tidak hilang.
     */
    public function getResponses(?string $sheetName = null): Collection
    {
        // Ambil metadata untuk daftar sheet
        $spreadsheet = $this->service->spreadsheets->get($this->spreadsheetId);
        $sheets = collect($spreadsheet->getSheets())->map(fn($s) => $s->getProperties()->getTitle());

        // Validasi nama sheet
        if ($sheetName && !$sheets->contains($sheetName)) {
            throw new \RuntimeException("Sheet '{$sheetName}' tidak ditemukan.");
        }

        // Default: sheet pertama
        $sheetName = $sheetName ?? 'Form Responses 1';

        // Jika ada spasi, Google API butuh sheet name diberi kutip
        if (str_contains($sheetName, ' ')) {
            $sheetName = "'{$sheetName}'";
        }

        // 1) Ambil HEADER baris pertama saja (dinamis)
        $headerResp = $this->service->spreadsheets_values->get(
            $this->spreadsheetId,
            "{$sheetName}!1:1"
        );

        $headersRaw = $headerResp->getValues()[0] ?? [];
        $headers = array_map(static fn($h) => is_string($h) ? trim($h) : $h, $headersRaw);
        $headerCount = count($headers);

        if ($headerCount === 0) {
            // Tidak ada header -> tidak ada data
            return collect();
        }

        // 2) Hitung huruf kolom terakhir dari jumlah header (A, B, ..., Z, AA, AB, ...)
        $lastCol = $this->columnIndexToLetter($headerCount);

        // 3) Ambil seluruh data termasuk header (A1:LASTCOL)
        $resp = $this->service->spreadsheets_values->get(
            $this->spreadsheetId,
            "{$sheetName}!A1:{$lastCol}"
        );

        $values = $resp->getValues() ?? [];
        if (empty($values) || count($values) < 2) {
            // Cuma header doang, atau kosong
            return collect();
        }

        // Buang baris header; sisanya data
        $rows = array_slice($values, 1);

        // 4) Bentuk associative rows dengan PAD sesuai jumlah header
        $data = array_map(function (array $row) use ($headers, $headerCount) {
            if (count($row) < $headerCount) {
                $row = array_pad($row, $headerCount, null);
            } elseif (count($row) > $headerCount) {
                $row = array_slice($row, 0, $headerCount);
            }

            $assoc = array_combine($headers, $row);

            // Rapikan string kosong -> null
            foreach ($assoc as $k => $v) {
                if (is_string($v)) {
                    $vv = trim($v);
                    $assoc[$k] = ($vv === '') ? null : $vv;
                }
            }

            return $assoc;
        }, $rows);

        return collect($data);
    }

    /**
     * Converter indeks kolom (1-based) -> huruf kolom (A, B, ..., Z, AA, AB, ...).
     */
    protected function columnIndexToLetter(int $index): string
    {
        // Proteksi
        if ($index < 1) {
            return 'A';
        }

        $letter = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = (int) floor(($index - $mod) / 26);
        }
        return $letter;
    }
}
