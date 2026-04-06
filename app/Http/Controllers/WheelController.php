<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WheelWinner;
use App\Models\Student;
use App\Models\VoucherLama;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class WheelController extends Controller
{
    protected array $vouchers = [
        'Rp 50.000',
        'Rp 100.000',
        'Rp 150.000',
        'Rp 200.000',
        'Rp 250.000',
        'Rp 300.000',
        'Rp 350.000',
        'Rp 400.000',
        'Rp 450.000',
        'Rp 500.000',
        'Rp 550.000',
        'Rp 600.000',
        'Rp 650.000',
        'Rp 700.000',
        'Rp 750.000',
        'Rp 800.000',
        'Rp 850.000',
        'Rp 900.000',
        'Rp 950.000',
        'Rp 1.000.000',
        'Rp 1.050.000',
        'Rp 1.100.000',
        'Rp 1.150.000',
        'Rp 1.200.000'
    ];

    public function publicIndex(Request $request, $row_hash)
    {
        // Periksa signature
        if (! $request->hasValidSignature()) {
            Log::warning('Invalid signature in publicIndex', [
                'url' => $request->fullUrl(),
                'row_hash' => $row_hash
            ]);
            abort(403, 'Tanda tangan URL tidak valid atau kadaluarsa.');
        }

        // Cek row_hash valid
        $all = $this->fetchAvailableFromStudents();
        $found = collect($all)->first(fn($x) => ($x['row_hash'] ?? '') === $row_hash);
        if (! $found) {
            Log::warning('Humas not found in publicIndex', ['row_hash' => $row_hash]);
            abort(404, 'Humas tidak ditemukan atau sudah pernah menang.');
        }

        // Gunakan temporarySignedRoute untuk spin_post_url
        $spin_post_url = URL::temporarySignedRoute(
            'wheels.public.spin',
            now()->addHours(1),
            ['row_hash' => $row_hash]
        );

        return view('wheels.public', [
            'row_hash' => $row_hash,
            'referrer_name' => $found['referrer_name'] ?? $found['name'] ?? null,
            'brought_name' => $found['brought_name'] ?? null,
            'spin_post_url' => $spin_post_url,
        ]);
    }

    /**
     * Public spin — menerima POST dari halaman publik.
     * Periksa signature manual (hasValidSignature).
     */
    public function publicSpin(Request $request)
    {
        // Periksa signature dari query string
        if (! $request->hasValidSignature()) {
            Log::warning('Invalid signature in publicSpin', [
                'url' => $request->fullUrl(),
                'query' => $request->query(),
                'body' => $request->all()
            ]);
            return response()->json(['error' => 'Invalid signature. Akses hanya melalui link resmi.'], 403);
        }

        $row_hash = $request->query('row_hash');
        $v = Validator::make(['row_hash' => $row_hash], [
            'row_hash' => ['required', 'string'],
        ]);
        if ($v->fails()) {
            Log::warning('Invalid row_hash in publicSpin', ['row_hash' => $row_hash]);
            return response()->json(['error' => 'Invalid request: row_hash missing or invalid'], 422);
        }

        $available = $this->fetchAvailableFromStudents();
        $chosen = collect($available)->first(fn($x) => ($x['row_hash'] ?? '') === $row_hash);
        if (! $chosen) {
            Log::warning('Chosen not found in publicSpin', ['row_hash' => $row_hash]);
            return response()->json(['error' => 'Nama tidak tersedia untuk undian (mungkin sudah pernah menang).'], 422);
        }

        $voucherIndex = random_int(0, count($this->vouchers) - 1);
        $voucher = $this->vouchers[$voucherIndex];

        try {
            $winner = DB::transaction(function () use ($chosen, $voucher, $voucherIndex) {
                $studentId = $chosen['student_id'] ?? null;
                $rowHash = $chosen['row_hash'] ?? null;
                $referrer = $chosen['referrer_name'] ?? '';
                $brought = $chosen['brought_name'] ?? '';

                $displayName = $referrer;
                if ($brought) $displayName = strtoupper($referrer) . ' (' . $brought . ')';

                if (!empty($rowHash) && WheelWinner::where('row_hash', $rowHash)->exists()) {
                    throw new \RuntimeException('Item ini sudah dipakai sebagai pemenang (row_hash).');
                }

                if (WheelWinner::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($displayName))])->exists()) {
                    throw new \RuntimeException('Nama ini sudah pernah menang.');
                }

                $w = new WheelWinner();
                $w->name = $displayName;
                $w->voucher = $voucher;
                $w->voucher_index = $voucherIndex;
                $w->row_hash = $rowHash;
                $w->voucher_amount = $this->parseRpToInt($voucher);
                if ($studentId) $w->student_id = $studentId;
                $w->won_at = now();
                $w->save();

                return $w;
            });
        } catch (\Throwable $e) {
            Log::error('Public spin failure: ' . $e->getMessage(), ['chosen' => $chosen ?? null, 'exception' => $e]);
            return response()->json(['error' => 'Gagal menyimpan pemenang: ' . $e->getMessage()], 409);
        }

        // Setelah winner tersimpan, buat voucher di voucher_lama dan flash session (best-effort)
        $createdOk = false;
        try {
            $createdOk = $this->createVouchersAndFlash($winner);
            if (! $createdOk) {
                Log::warning('publicSpin: winner saved but voucher rows not created', [
                    'winner_id' => $winner->id ?? null,
                    'row_hash' => $winner->row_hash ?? null,
                    'voucher_amount' => $winner->voucher_amount ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            // createVouchersAndFlash seharusnya menangkap sendiri, tapi just in case
            Log::error('publicSpin: unexpected error while creating voucher rows: ' . $e->getMessage(), ['winner_id' => $winner->id ?? null]);
        }

        return response()->json([
            'name' => $winner->name,
            'voucher' => $winner->voucher,
            'voucher_index' => (int) $winner->voucher_index,
            'won_at' => optional($winner->won_at)->toDateTimeString(),
            'voucher_amount' => $winner->voucher_amount,
            'row_hash' => $winner->row_hash,
            'voucher_created' => (bool) $createdOk,
        ]);
    }

    public function index()
    {
        $available = $this->fetchAvailableFromStudents();
        return view('wheels.index', ['availableNames' => array_values($available)]);
    }

    public function names(Request $request)
    {
        $available = $this->fetchAvailableFromStudents();

        $rowHash = $request->query('row_hash');
        $studentId = $request->query('student_id');

        if ($rowHash) {
            $available = array_values(array_filter($available, function ($it) use ($rowHash) {
                return (isset($it['row_hash']) && $it['row_hash'] === $rowHash)
                    || (isset($it['row_hash']) && (string)$it['row_hash'] === (string)$rowHash);
            }));
        } elseif ($studentId) {
            $available = array_values(array_filter($available, function ($it) use ($studentId) {
                return isset($it['student_id']) && ((string)$it['student_id'] === (string)$studentId);
            }));
        }

        return response()->json(['data' => array_values($available)]);
    }

    /**
     * Lookup data referrer + info voucher terakhir
     */
    public function lookup(Request $request)
    {
        $row = $request->query('row_hash');
        $ref  = $request->query('referrer');

        Log::info('wheels.lookup called', ['row_hash' => $row, 'referrer' => $ref, 'ip' => $request->ip()]);

        $all = $this->fetchAvailableFromStudents() ?? [];
        $normalize = fn($s) => mb_strtolower(trim((string)($s ?? '')));

        $found = null;
        if ($row) {
            $found = collect($all)->first(fn($x) => (($x['row_hash'] ?? '') === $row));
        } elseif ($ref) {
            $needle = $normalize($ref);
            $found = collect($all)->first(fn($x) => $normalize($x['referrer_name'] ?? '') === $needle);
            if (! $found) {
                $found = collect($all)->first(fn($x) => mb_stripos($normalize($x['referrer_name'] ?? ''), $needle) !== false);
            }
        }

        if (! $found) {
            Log::warning('wheels.lookup not found', ['row_hash' => $row, 'referrer' => $ref]);
            return response()->json(['error' => 'Tidak ditemukan'], 404);
        }

        $studentData = null;
        if (!empty($found['student_id'])) {
            $student = Student::find($found['student_id']);
            if ($student) {
                $studentData = [
                    'student_id' => $student->id,
                    'nim'        => $student->nim ?? null,
                    'nama'       => $student->nama ?? ($student->name ?? null),
                    'orangtua'   => $student->orangtua ?? ($student->parent_name ?? null),
                    'telp_hp'    => $student->telp_hp ?? ($student->no_telp ?? $student->no_telp_hp ?? null),
                ];
            }
        }

        $lastVoucherAmount = null;
        if (!empty($found['row_hash'])) {
            $last = WheelWinner::where('row_hash', $found['row_hash'])->latest('won_at')->first();
            if ($last) {
                $lastVoucherAmount = $last->voucher_amount ?? $this->parseRpToInt($last->voucher ?? null);
            }
        }

        if ($lastVoucherAmount === null && !empty($found['referrer_name'])) {
            $needle = '%' . $normalize($found['referrer_name']) . '%';
            $last = WheelWinner::whereRaw('LOWER(name) LIKE ?', [$needle])->latest('won_at')->first();
            if ($last) {
                $lastVoucherAmount = $last->voucher_amount ?? $this->parseRpToInt($last->voucher ?? null);
            }
        }

        $voucher_count = null;
        $suggested_voucher_numbers = null;
        if (!empty($lastVoucherAmount) && is_numeric($lastVoucherAmount) && $lastVoucherAmount > 0) {
            $voucher_count = max(1, (int) ceil((float) $lastVoucherAmount / 50000));
            $suggested_voucher_numbers = $this->generateVoucherNumbers($voucher_count, 'WHEEL');
        }

        $response = array_merge($found, [
            'student' => $studentData,
            'last_voucher_amount' => $lastVoucherAmount,
            'voucher_count' => $voucher_count,
            'suggested_voucher_numbers' => $suggested_voucher_numbers,
        ]);

        return response()->json($response);
    }

    /**
     * Parse string mata uang seperti "Rp 100.000" atau "100000" menjadi integer (defensif).
     */
    protected function parseRpToInt($value): ?int
    {
        if ($value === null) return null;
        $digits = preg_replace('/[^\d]/u', '', (string) $value);
        if ($digits === '') return null;
        return (int) $digits;
    }

    /**
     * Generate nomor voucher sejumlah $count, prefix opsional.
     * Mengecek DB VoucherLama agar tidak menghasilkan duplikat.
     */
    protected function generateVoucherNumbers(int $count, string $prefix = null): array
    {
        $out = [];
        $prefix = $prefix ? strtoupper($prefix) . '-' . date('Ymd') : 'VCHR-' . date('Ymd');

        $attempts = 0;
        $maxAttempts = max(100, $count * 30);

        while (count($out) < $count && $attempts < $maxAttempts) {
            $attempts++;
            $rand = strtoupper(Str::random(6));
            $candidate = $prefix . '-' . $rand . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            if (in_array($candidate, $out, true)) continue;
            $exists = VoucherLama::where('voucher', $candidate)->exists();
            if ($exists) continue;

            $out[] = $candidate;
        }

        while (count($out) < $count) {
            $out[] = $prefix . '-' . uniqid();
        }

        return $out;
    }

    /**
     * Ambil data available dari tabel students
     */
    protected function fetchAvailableFromStudents(): array
{
    $humasCol = 'informasi_humas_nama';
    $studentNameCol = 'nama';

    $rows = DB::table('students')
        ->join('registrations', function($join) {
            $join->on('registrations.student_id', '=', 'students.id')
                 ->where('registrations.status', '=', 'accepted');
        })
        ->whereNotNull($humasCol)
        ->whereRaw("TRIM({$humasCol}) <> ''")
        ->select([
            'students.id as student_id',
            DB::raw("TRIM({$humasCol}) as humas_name_raw"),
            DB::raw("TRIM({$studentNameCol}) as student_name"),
            'registrations.bimba_unit as bimba_unit',   // ⬅️ tambahkan ini
            'registrations.no_cabang as no_cabang',     // ⬅️ dan ini
        ])
        ->orderBy('humas_name_raw')
        ->limit(5000)
        ->get();

    $usedHashes = WheelWinner::pluck('row_hash')->filter()->map(fn($v) => (string) $v)->toArray();
    $usedNamesLower = WheelWinner::pluck('name')
        ->filter()
        ->map(fn($n) => mb_strtolower(trim((string)$n)))
        ->toArray();

    $out = [];
    foreach ($rows as $r) {
        $referrerRaw = trim((string) $r->humas_name_raw);
        if ($referrerRaw === '') continue;

        $refLower = mb_strtolower($referrerRaw);
        $rowHash = md5('stu:' . $r->student_id);

        if (in_array($rowHash, $usedHashes, true)) continue;
        if (in_array($refLower, $usedNamesLower, true)) continue;

        $out[] = [
            'student_id'    => $r->student_id,
            'referrer_name' => $referrerRaw,
            'brought_name'  => trim((string)$r->student_name),
            'name'          => $referrerRaw,
            'row_hash'      => $rowHash,
            'is_new_student'=> false,
            'bimba_unit'    => $r->bimba_unit ?? null,   // ⬅️ simpan ke array $chosen
            'no_cabang'     => $r->no_cabang ?? null,    // ⬅️ simpan ke array $chosen
        ];
    }

    return $out;
}

    /**
     * Jalankan spin roda, simpan pemenang (admin)
     */
    public function spin(Request $request)
{
    $request->validate([
        'row_hash' => ['nullable', 'string'],
        'name' => ['nullable', 'string'],
    ]);

    $rowHashReq = $request->input('row_hash');
    $nameReq = trim((string) $request->input('name', ''));

    $available = $this->fetchAvailableFromStudents();
    if (empty($available)) {
        return response()->json(['error' => 'Tidak ada nama tersedia untuk undian.'], 422);
    }

    $chosen = null;
    if ($rowHashReq) {
        $chosen = collect($available)->first(fn($x) => ($x['row_hash'] ?? '') === $rowHashReq);
    }
    if (!$chosen && $nameReq !== '') {
        $chosen = collect($available)->first(function ($x) use ($nameReq) {
            $ref = $x['referrer_name'] ?? '';
            $brought = $x['brought_name'] ?? '';
            $disp = $brought ? $ref . '(' . $brought . ')' : $ref;
            return mb_strtolower(trim($disp)) === mb_strtolower(trim($nameReq))
                || mb_strtolower(trim($ref)) === mb_strtolower(trim($nameReq));
        });
    }
    if (!$chosen) {
        $chosen = collect($available)->random();
    }

    $voucherIndex = random_int(0, count($this->vouchers) - 1);
    $voucher = $this->vouchers[$voucherIndex];

    try {
        $winner = DB::transaction(function () use ($chosen, $voucher, $voucherIndex) {

            $studentId  = $chosen['student_id']   ?? null;
            $rowHash    = $chosen['row_hash']     ?? null;
            $referrer   = $chosen['referrer_name'] ?? '';
            $brought    = $chosen['brought_name']  ?? '';

            // ⬇️ ambil unit & cabang dari $chosen (hasil fetchAvailableFromStudents)
            $bimbaUnit  = $chosen['bimba_unit']   ?? null;
            $noCabang   = $chosen['no_cabang']    ?? null;

            $displayName = $referrer;
            if ($brought) $displayName = strtoupper($referrer) . '(' . $brought . ')';

            if (!empty($rowHash) && WheelWinner::where('row_hash', $rowHash)->exists()) {
                throw new \RuntimeException('Item ini sudah dipakai sebagai pemenang (row_hash).');
            }

            if (WheelWinner::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($displayName))])->exists()) {
                throw new \RuntimeException('Nama ini sudah pernah menang.');
            }

            $w = new WheelWinner();
            $w->name           = $displayName;
            $w->voucher        = $voucher;
            $w->voucher_index  = $voucherIndex;
            $w->row_hash       = $rowHash;
            $w->voucher_amount = $this->parseRpToInt($voucher);
            if ($studentId) {
                $w->student_id = $studentId;
            }

            // ⬇️ SET ke kolom baru
            $w->bimba_unit = $bimbaUnit;
            $w->no_cabang  = $noCabang;

            $w->won_at = now();
            $w->save();

            return $w;
        }, 5);
    } catch (\Throwable $e) {
        Log::warning('Spin failure: ' . $e->getMessage(), ['chosen' => $chosen ?? null]);
        return response()->json(['error' => 'Gagal menyimpan pemenang: ' . $e->getMessage()], Response::HTTP_CONFLICT);
    }

    // Buat voucher & flash
    $createdOk = false;
    try {
        $createdOk = $this->createVouchersAndFlash($winner);
        if (! $createdOk) {
            Log::warning('spin: winner saved but voucher rows not created', [
                'winner_id' => $winner->id ?? null,
                'row_hash' => $winner->row_hash ?? null,
            ]);
        }
    } catch (\Throwable $e) {
        Log::error('spin: unexpected error while creating voucher rows: ' . $e->getMessage(), ['winner_id' => $winner->id ?? null]);
    }

    return response()->json([
        'name' => $winner->name,
        'voucher' => $winner->voucher,
        'voucher_index' => (int) $winner->voucher_index,
        'id' => $winner->id,
        'student_id' => $winner->student_id ?? null,
        'row_hash' => $winner->row_hash ?? null,
        'won_at' => optional($winner->won_at)->toDateTimeString(),
        'referrer' => $chosen['referrer_name'] ?? null,
        'brought' => $chosen['brought_name'] ?? null,
        'voucher_amount' => $winner->voucher_amount,
        'voucher_count' => $winner->voucher_amount ? (int) max(1, round($winner->voucher_amount / 50000)) : 1,
        'voucher_created' => (bool) $createdOk,
        // optional kalau mau dikirim ke frontend juga:
        'bimba_unit' => $winner->bimba_unit,
        'no_cabang'  => $winner->no_cabang,
    ]);
}


    public function history(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        if ($perPage <= 0) $perPage = 25;

        $winners = WheelWinner::latest('won_at')->paginate($perPage);
        return response()->json($winners);
    }

    /**
     * Helper: buat voucher rows di voucher_lama dan flash hasil ke session.
     * Menggunakan DB::transaction + logging yang lebih lengkap.
     *
     * @param \App\Models\WheelWinner $winner
     * @return bool true jika berhasil membuat voucher rows, false jika gagal
     */
    /**
 * Helper: buat voucher rows di voucher_lama dan flash hasil ke session.
 * Mengembalikan true jika berhasil membuat voucher rows, false jika gagal.
 *
 * Perubahan penting:
 * - tanggal_penyerahan diset NULL setelah spin
 * - status diset 'belum_diserahkan'
 * - flash menggunakan key 'spinResult' dan field 'tanggal_spin'
 */
protected function createVouchersAndFlash(\App\Models\WheelWinner $winner): bool
{
    try {
        $nilaiPerVoucher = 50000;
        $voucherAmount = (int) ($winner->voucher_amount ?? $this->parseRpToInt($winner->voucher ?? null) ?? 0);

        if ($voucherAmount <= 0) {
            $voucherAmount = $nilaiPerVoucher;
        }

        $voucherCount = max(1, intdiv($voucherAmount, $nilaiPerVoucher));
        $prefix = 'SPIN-' . date('Ymd');
        $voucherNumbers = $this->generateVoucherNumbers($voucherCount, $prefix);

        Log::info('createVouchersAndFlash: mulai membuat voucher rows', [
            'winner_id' => $winner->id ?? null,
            'voucher_amount' => $voucherAmount,
            'voucher_count' => $voucherCount,
            'voucher_numbers_sample' => array_slice($voucherNumbers, 0, 3),
        ]);

        $createdRows = [];
        $nimHumas = null;
        $tanggalNow = now()->toDateString();

        // persiapkan data humas dari winner->name (ambil sebelum '(' jika ada)
        $rawName = (string) ($winner->name ?? '');
        $referrerName = trim(preg_replace('/\s*\(.*$/u', '', $rawName));
        if ($referrerName === '') $referrerName = $rawName;

        // cari data buku_induk (defensif)
        $bukuIndukRecord = null;
        try {
            $bukuIndukRecord = \App\Models\BukuInduk::whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower($referrerName)])->first();
            if (! $bukuIndukRecord) {
                $bukuIndukRecord = \App\Models\BukuInduk::where('nama', 'like', '%' . $referrerName . '%')->first();
            }
        } catch (\Throwable $ex) {
            Log::warning('createVouchersAndFlash: lookup BukuInduk gagal', ['err' => $ex->getMessage()]);
            $bukuIndukRecord = null;
        }

        // ambil student jika ada
        $student = null;
        if (!empty($winner->student_id)) {
            $student = \App\Models\Student::find($winner->student_id);
        }

        // Buat semua rows dalam satu transaksi
        DB::transaction(function () use (&$createdRows, &$nimHumas, $voucherNumbers, $nilaiPerVoucher, $bukuIndukRecord, $student, $referrerName, $winner, $tanggalNow) {
            foreach ($voucherNumbers as $vnum) {
                $nimForHumas = $bukuIndukRecord->nim ?? null;
                $orangtuaForHumas = $bukuIndukRecord->orangtua ?? null;
                $telpForHumas = $bukuIndukRecord->no_telp_hp ?? ($bukuIndukRecord->no_telp ?? null);

                $nimMuridBaru = $student->nim ?? null;
                $namaMuridBaru = $student->nama ?? ($student->name ?? null);
                $orangtuaMuridBaru = $student->orangtua ?? ($student->parent_name ?? null);
                $telpMuridBaru = $student->telp_hp ?? ($student->no_telp ?? $student->no_telp_hp ?? null);

                // IMPORTANT: jangan isi tanggal_penyerahan di sini (biarkan null)
                $row = \App\Models\VoucherLama::create([
                    'voucher' => $vnum,
                    'jumlah_voucher' => 1,
                    'nominal' => $nilaiPerVoucher,
                    'tanggal_penyerahan' => null,             // <-- tetap NULL setelah spin
                    'tanggal' => $tanggalNow,                 // tanggal spin / pembuatan voucher
                    'status' => 'belum_diserahkan',           // <-- awal: belum diserahkan
                    'nim' => $nimForHumas,
                    'nama_murid' => $referrerName,
                    'orangtua' => $orangtuaForHumas,
                    'telp_hp' => $telpForHumas,
                    'nim_murid_baru' => $nimMuridBaru,
                    'nama_murid_baru' => $namaMuridBaru,
                    'orangtua_murid_baru' => $orangtuaMuridBaru,
                    'telp_hp_murid_baru' => $telpMuridBaru,
                    'source' => 'spin',

                    // ⬇️ Tambahan: isi UNIT & NO CABANG dari pemenang spin
                    'bimba_unit'      => $winner->bimba_unit ?? null,   // kalau di DB kolomnya 'bimba_unit', ganti jadi 'bimba_unit' =>
                    'no_cabang' => $winner->no_cabang ?? null,
                ]);

                $createdRows[] = $row->toArray();
                if ($nimForHumas && ! $nimHumas) $nimHumas = $nimForHumas;
            }
        });

        // flash ke session supaya tampil di index (sama struktur dengan yang view harapkan: spinResult)
        \Session::flash('spinResult', [
            'count' => count($createdRows),
            'nominal' => $voucherAmount,
            'nominal_formatted' => number_format($voucherAmount, 0, ',', '.'),
            'rows' => collect($createdRows)->map(function ($r) use ($tanggalNow) {
                return [
                    'voucher' => $r['voucher'] ?? null,
                    'nominal' => $r['nominal'] ?? 50000,
                    'tanggal_spin' => $r['tanggal'] ?? $tanggalNow,
                    'tanggal_penyerahan' => null,                    // pastikan null
                    'status' => $r['status'] ?? 'belum_diserahkan',
                    'nim' => $r['nim'] ?? null,
                    'nama_murid' => $r['nama_murid'] ?? null,
                    'nim_murid_baru' => $r['nim_murid_baru'] ?? null,
                    'nama_murid_baru' => $r['nama_murid_baru'] ?? null,
                    'orangtua_murid_baru' => $r['orangtua_murid_baru'] ?? null,
                    'telp_hp_murid_baru' => $r['telp_hp_murid_baru'] ?? null,

                    // ⬇️ supaya kalau view baca dari session juga dapat UNIT & CABANG
                    'bimba_unit'      => $r['bimba_unit']      ?? null,
                    'no_cabang' => $r['no_cabang'] ?? null,
                ];
            })->toArray(),
            'vouchers' => collect($createdRows)->pluck('voucher')->toArray(),
            'nim_humas' => $nimHumas,
            'tanggal' => $tanggalNow,
        ]);

        Log::info('createVouchersAndFlash: selesai membuat voucher rows', [
            'winner_id' => $winner->id ?? null,
            'created_count' => count($createdRows),
        ]);

        return true;
    } catch (\Throwable $e) {
        // log error lengkap agar mudah didiagnosa
        Log::error('Error creating voucher rows after wheel winner: ' . $e->getMessage(), [
            'winner_id' => $winner->id ?? null,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return false;
    }
}


}
