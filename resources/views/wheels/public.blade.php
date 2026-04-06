@extends('layouts.plain')

@section('title', 'Roda Voucher biMBA — Publik')

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
<div class="container py-3">
    <h3 class="text-center mb-3">🎡 Roda Undian Voucher biMBA-AIUEO (Publik)</h3>

    <div class="d-flex flex-column gap-3 align-items-center">
        <div id="wheel-wrap" style="position: relative; display: inline-block; width: 100%; max-width: 520px; aspect-ratio: 1/1;">
            <canvas id="wheel" style="display: block; width: 100%; height: 100%; border-radius: 50%; box-shadow: 0 8px 30px rgba(15,23,42,0.08);"></canvas>

            <div id="stopLine" aria-hidden="true" style="
                position: absolute;
                left: 50%;
                top: calc(100% - 72px);
                transform: translateX(-50%);
                width: 6px;
                height: 48px;
                background: rgba(0,0,0,0.08);
                border-radius: 4px;
                z-index: 25;
                pointer-events: none;
                box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            "></div>

            <div id="pointer" style="
                position: absolute;
                left: 50%;
                top: calc(100% - 15px);
                transform: translateX(-50%);
                width: 0;
                height: 0;
                border-left: 18px solid transparent;
                border-right: 18px solid transparent;
                border-top: 0;
                border-bottom: 30px solid #e11d48;
                z-index: 30;
                filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));
            " aria-hidden="true"></div>
        </div>

        <div style="width: 100%; max-width: 520px;">
            <div class="card p-2 mb-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="mb-0">Pilih Nama (untuk undian)</h5>
                        <small class="text-muted">Centang 1 nama lalu klik <strong>Putar!</strong></small>
                    </div>
                    <div class="text-end">
                        <button id="refreshNames" class="btn btn-sm btn-outline-secondary">Refresh</button>
                    </div>
                </div>

                <div id="nameList" style="max-height: 300px; overflow: auto; padding-right: 6px;">
                    <div class="text-center text-muted py-4">
                        <span class="spinner"></span> Memuat daftar nama…
                    </div>
                </div>


                <small class="text-muted d-block mt-2">Nama yang sudah menang akan otomatis hilang dari daftar.</small>
            </div>

            <div class="card p-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h6 class="mb-0">Riwayat Terbaru</h6>
                        <small class="text-muted">Pemenang terakhir</small>
                    </div>
                    <div>
                        <button id="refreshHistory" class="btn btn-sm btn-outline-secondary">Refresh</button>
                    </div>
                </div>

                <div id="recentHistory" style="max-height: 200px; overflow: auto; padding-right: 6px;">
                    <div class="text-center text-muted py-3">
                        <span class="spinner"></span> Memuat riwayat…
                    </div>
                </div>

                <div class="mt-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted">Klik "Lihat Semua" untuk riwayat lengkap.</small>
                    <button id="openHistoryModal" class="btn btn-sm btn-outline-primary">Lihat Semua</button>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-3">
        <button id="spin" class="btn btn-primary btn-md me-2" disabled aria-label="Putar roda undian">Putar!</button>        
    </div>

    <div class="mt-3 text-center">
        <h4 id="winner" class="fw-bold text-success"></h4>
    </div>
</div>

<!-- Modal sederhana untuk riwayat -->
<div class="modal fade" id="winnersHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Riwayat Pemenang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="historyTableContainer">
                    <div class="text-center text-muted py-4">
                        <span class="spinner"></span> Memuat riwayat…
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <small id="historyMeta" class="text-muted"></small>
                </div>
                <div>
                    <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    /* ringkasan style dari versi index (sederhana) */
    .spinner{display:inline-block;width:20px;height:20px;border:3px solid rgba(0,0,0,0.1);border-top-color:#007bff;border-radius:50%;animation:spin 1s linear infinite;margin-right:8px;vertical-align:middle}
    @keyframes spin{to{transform:rotate(360deg)}}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
<script>
(function(){
    // server-provided signed POST URL (direkomendasikan)
    const SPIN_URL = {!! json_encode($spin_post_url ?? route('wheels.public.spin')) !!};
    // Names & history — kita panggil tanpa signed (route biasa)
    const NAMES_URL = '{{ route("wheels.names") }}' + '?' + new URLSearchParams({ row_hash: '{{ e($row_hash) }}' }).toString();
    const HISTORY_URL = '{{ route("wheels.history") }}' + '?' + new URLSearchParams({ row_hash: '{{ e($row_hash) }}' }).toString();

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const canvas = document.getElementById('wheel');
    const ctx = canvas && canvas.getContext ? canvas.getContext('2d') : null;
    const spinBtn = document.getElementById('spin');
    const resetBtn = document.getElementById('reset');
    const winnerEl = document.getElementById('winner');
    const stopLine = document.getElementById('stopLine');
    const nameListEl = document.getElementById('nameList');
    const refreshBtn = document.getElementById('refreshNames');
    const selectRandomBtn = document.getElementById('selectRandom');
    const clearAllUsedBtn = document.getElementById('clearAllUsed');
    const refreshHistoryBtn = document.getElementById('refreshHistory');
    const recentEl = document.getElementById('recentHistory');
    const openHistoryModalBtn = document.getElementById('openHistoryModal');
    const historyTableContainer = document.getElementById('historyTableContainer');
    const historyMeta = document.getElementById('historyMeta');
    const winnersHistoryModalEl = document.getElementById('winnersHistoryModal');

    let winnersHistoryModal;
    try{ if(winnersHistoryModalEl) winnersHistoryModal = new bootstrap.Modal(winnersHistoryModalEl); }catch(e){ winnersHistoryModal = null; }

    let size = Math.min(520, Math.max(300, Math.min(window.innerWidth * 0.95, 360)));
    if(canvas){ canvas.width = size; canvas.height = size; }
    const wrap = canvas ? canvas.parentElement : null;
    if(wrap){ wrap.style.width = `${size}px`; wrap.style.height = `${size}px`; }
    const r = size / 2;

    const vouchers = [
        'Rp 50.000','Rp 100.000','Rp 150.000','Rp 200.000','Rp 250.000','Rp 300.000','Rp 350.000','Rp 400.000',
        'Rp 450.000','Rp 500.000','Rp 550.000','Rp 600.000','Rp 650.000','Rp 700.000','Rp 750.000','Rp 800.000',
        'Rp 850.000','Rp 900.000','Rp 950.000','Rp 1.000.000','Rp 1.050.000','Rp 1.100.000','Rp 1.150.000','Rp 1.200.000'
    ];

    let rotation = 0, spinning = false;
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    const audioCtx = AudioContext ? new AudioContext() : null;
    function tickSound(vol = 0.06, freq = 1200, len = 0.01){ if(!audioCtx) return; const o = audioCtx.createOscillator(), g = audioCtx.createGain(); o.type='square'; o.frequency.value=freq; g.gain.value=vol; o.connect(g); g.connect(audioCtx.destination); o.start(); setTimeout(()=>o.stop(), len*1000); }

    function drawWheel(){ if(!ctx) return; ctx.clearRect(0,0,canvas.width,canvas.height); const arc = 2*Math.PI / vouchers.length; for(let i=0;i<vouchers.length;i++){ const angle = i*arc; ctx.beginPath(); ctx.moveTo(r,r); ctx.fillStyle = `hsl(${i*360/vouchers.length} 72% 70%)`; ctx.arc(r,r,r-2,angle, angle+arc); ctx.closePath(); ctx.fill(); ctx.save(); ctx.translate(r,r); ctx.rotate(angle + arc/2); ctx.fillStyle='#111'; ctx.textAlign='right'; const fontSize = Math.max(10, Math.floor(size/35)); ctx.font = `bold ${fontSize}px Inter, Arial`; ctx.fillText(vouchers[i], r-10, 4); ctx.restore(); } ctx.beginPath(); ctx.arc(r,r,r*0.18,0,2*Math.PI); ctx.fillStyle='#ffffffee'; ctx.fill(); ctx.lineWidth=2; ctx.strokeStyle='rgba(0,0,0,0.06)'; ctx.stroke(); }

    function easeOut(t){ return 1 - Math.pow(1 - t, 3); }
    function reset(){ rotation = 0; if(canvas) canvas.style.transform = 'rotate(0deg)'; winnerEl.innerHTML=''; spinning=false; disableSpin(); }
    function norm360(d){ return ((d%360)+360)%360; }
    function burstConfetti(){ if(window.confetti) confetti({ particleCount:80, startVelocity:30, spread:160, origin:{x:0.5,y:0.4} }); }

    let availableNames = [];

    async function loadNames(retryCount=0, maxRetries=3){
        if(retryCount>=maxRetries){ if(nameListEl) nameListEl.innerHTML = '<div class="text-danger py-3">Gagal memuat nama setelah beberapa percobaan.</div>'; return; }
        if(nameListEl) nameListEl.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner"></span> Memuat daftar nama…</div>';
        try{
            const res = await fetch(NAMES_URL + '&_=' + Date.now(), { cache:'no-store', credentials:'same-origin', headers:{ 'X-Requested-With':'XMLHttpRequest' } });
            if(!res.ok) throw new Error('HTTP '+res.status);
            const json = await res.json();
            availableNames = json.data || json || [];
            availableNames = availableNames.map(it=>({ referrer_name:(it.referrer_name||it.referer||it.ref||it.name||'').toString().trim(), brought_name:(it.brought_name||it.student_name||it.brought||it.child_name||'').toString().trim(), is_new_student:!!(it.is_new||it.is_new_student||it.brought_is_new), row_hash:it.row_hash||it.rowHash||it.row||'', student_id:it.student_id||it.studentId||'' }));
            renderNameList();
        }catch(err){ console.error('loadNames error', err); setTimeout(()=>loadNames(retryCount+1, maxRetries), 1000); }
    }

    function escapeHtml(s){ return (s===null||s===undefined)?'':(s+'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function renderNameList(){ if(!nameListEl) return; if(!availableNames||!availableNames.length){ nameListEl.innerHTML = '<div class="text-center text-muted py-4">Tidak ada nama tersedia.</div>'; disableSpin(); return; } nameListEl.innerHTML = ''; const list = [...availableNames].sort((a,b)=> (a.referrer_name||'').toString().toUpperCase().localeCompare((b.referrer_name||'').toString().toUpperCase())); for(const it of list){ const rowHash = it.row_hash||''; const studentId = it.student_id??''; const uniqueId = rowHash || (studentId ? 'stu:'+studentId : Math.random().toString(36).slice(2,9)); const ref = (it.referrer_name||'').toString().trim(); const brought = (it.brought_name||'').toString().trim(); const isNew = !!it.is_new_student; const id = 'n_'+uniqueId; const div = document.createElement('div'); div.className='form-check'; const left = document.createElement('div'); left.style.display='flex'; left.style.alignItems='center'; left.style.gap='10px'; const radio = document.createElement('input'); radio.className='form-check-input name-checkbox'; radio.type='radio'; radio.name='selected_name'; radio.id=id; if(rowHash) radio.dataset.row_hash = rowHash; if(studentId) radio.dataset.student_id = studentId; radio.dataset.ref = ref; radio.dataset.brought = brought; if(it.is_new_student) radio.dataset.is_new='1'; radio.value = rowHash || (studentId ? 'stu:'+studentId : ref); const label = document.createElement('label'); label.className='form-check-label'; label.setAttribute('for', id); const refLine = `<div class="referrer">${escapeHtml((ref||'').toString().toUpperCase())} (humas)</div>`; const broughtLine = brought ? `<div class="brought">${escapeHtml(brought)}${isNew ? ' (murid Baru)' : ''}</div>` : ''; label.innerHTML = refLine + broughtLine; left.appendChild(radio); left.appendChild(label); const rowWrapper = document.createElement('div'); rowWrapper.style.display='flex'; rowWrapper.style.justifyContent='space-between'; rowWrapper.style.alignItems='center'; rowWrapper.appendChild(left); div.appendChild(rowWrapper); nameListEl.appendChild(div); }
        nameListEl.removeEventListener('change', onNameChange); nameListEl.addEventListener('change', onNameChange); disableSpin(); }

    selectRandomBtn && selectRandomBtn.addEventListener('click', ()=>{ const radios = document.querySelectorAll('.name-checkbox'); if(!radios.length) return; radios.forEach(r=>r.checked=false); const idx = Math.floor(Math.random()*radios.length); radios[idx].checked = true; onNameChange(); });
    clearAllUsedBtn && clearAllUsedBtn.addEventListener('click', ()=>{ document.querySelectorAll('.form-check .form-check-input[disabled]').forEach(el=>{ const rowHash = el.dataset.row_hash; const studentId = el.dataset.student_id; el.closest('.form-check')?.remove(); availableNames = availableNames.filter(x=> (rowHash && (x.row_hash||'') !== rowHash) || (studentId && ((x.student_id||'')+'') !== (studentId+'')) || (!rowHash && !studentId) ); }); disableSpin(); });
    refreshBtn && refreshBtn.addEventListener('click', async ()=>{ await loadNames(); });

    function enableSpin(){ if(spinBtn){ spinBtn.removeAttribute('disabled'); spinBtn.classList.remove('disabled'); } }
    function disableSpin(){ if(spinBtn){ spinBtn.setAttribute('disabled','true'); spinBtn.classList.add('disabled'); } }
    function onNameChange(){ const any = document.querySelector('input[name="selected_name"]:checked') !== null; if(any) enableSpin(); else disableSpin(); }

    async function loadRecentHistory(limit=6){ if(recentEl) recentEl.innerHTML = '<div class="text-center text-muted py-3"><span class="spinner"></span> Memuat riwayat…</div>'; try{ const res = await fetch(HISTORY_URL + '&per_page=' + limit + '&_=' + Date.now(), { cache:'no-store', credentials:'same-origin', headers:{ 'X-Requested-With':'XMLHttpRequest' } }); if(!res.ok) throw new Error('HTTP '+res.status); const json = await res.json(); const rows = json.data || json || []; if(!rows||rows.length===0){ if(recentEl) recentEl.innerHTML = '<div class="text-center text-muted py-3">Belum ada pemenang.</div>'; return; } const list = document.createElement('div'); list.className='list-group list-group-flush'; rows.slice(0,limit).forEach(r=>{ const item = document.createElement('div'); item.className='list-group-item d-flex justify-content-between align-items-start'; const sub = r.new_student ? `<div class="text-muted small">Murid: ${escapeHtml(r.new_student.nama||r.new_student.name||'')}</div>` : ''; item.innerHTML = `<div><div class="fw-semibold">${escapeHtml(r.name)}</div>${sub}<small class="text-muted">${escapeHtml(r.won_at||r.created_at||'')}</small></div><div class="text-end"><div class="badge bg-success">${escapeHtml(r.voucher||'')}</div></div>`; list.appendChild(item); }); if(recentEl){ recentEl.innerHTML=''; recentEl.appendChild(list); } }catch(err){ console.error('loadRecentHistory err', err); if(recentEl) recentEl.innerHTML = '<div class="text-danger py-3">Gagal memuat riwayat.</div>'; } }

    let animationFrameId = null;
    async function spin(){ if(spinning || !CSRF){ if(!CSRF && winnerEl) winnerEl.textContent = 'Error: Token keamanan tidak ditemukan.'; return; } const checked = document.querySelector('input[name="selected_name"]:checked'); if(!checked){ if(winnerEl) winnerEl.textContent = 'Pilih satu nama dulu sebelum memutar.'; return; }

        spinning = true; if(winnerEl) winnerEl.innerHTML = '<span class="spinner"></span> Sedang memutar...'; if(audioCtx && audioCtx.state==='suspended') await audioCtx.resume().catch(()=>{});

        const payload = {};
        if(checked.dataset.row_hash) payload.row_hash = checked.dataset.row_hash;
        else if(checked.dataset.student_id) payload.student_id = checked.dataset.student_id;
        else payload.name = checked.dataset.ref || checked.value;

        let data;
        try{
            const res = await fetch(SPIN_URL, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });

            if(!res.ok){
                if(res.status===419){ if(winnerEl) winnerEl.textContent='Sesi kadaluarsa. Silakan muat ulang halaman.'; spinning=false; return; }
                const text = await res.text(); try{ const errJson = JSON.parse(text); if(winnerEl) winnerEl.textContent = errJson.error || errJson.message || ('Spin gagal (HTTP '+res.status+')'); }catch(e){ if(winnerEl) winnerEl.textContent = 'Spin gagal (HTTP '+res.status+')'; }
                spinning=false; return;
            }

            data = await res.json();
            if(!data || typeof data !== 'object') throw new Error('Invalid response format');
        }catch(err){ console.error('Spin error', err); if(winnerEl) winnerEl.textContent = 'Gagal memutar roda. Coba lagi.'; spinning=false; return; }

        const sectorAngle = 360 / vouchers.length;
        let targetSector = data.voucher_index ?? -1;
        if(targetSector === -1 && data.voucher){ const voucherLower = (data.voucher||'').toLowerCase().trim(); targetSector = vouchers.findIndex(v=>v.toLowerCase().trim()===voucherLower); }
        if(targetSector === -1) targetSector = Math.floor(Math.random()*vouchers.length);

        const sectorCenterDeg = targetSector * sectorAngle + (sectorAngle/2);
        const pointerAngle = 90;
        const finalNorm = ((pointerAngle - sectorCenterDeg) % 360 + 360) % 360;
        const targetOffset = ((-finalNorm) % 360 + 360) % 360;
        const spinAngle = (4*360) + (Math.floor(Math.random()*3)*360) + targetOffset;

        const duration = 4200 + Math.random()*1000;
        const start = performance.now();
        let lastTickSector = -1;

        function animate(now){ const t = Math.min((now-start)/duration,1); const eased = easeOut(t); rotation = spinAngle * eased; if(canvas) canvas.style.transform = `rotate(${-rotation}deg)`; const normalized = norm360(-rotation); const relative = norm360(pointerAngle - normalized); const currentSector = Math.floor(relative / sectorAngle) % vouchers.length; if(currentSector !== lastTickSector){ lastTickSector = currentSector; tickSound(0.04, 1200 - (currentSector % 6)*60, 0.01); } if(t<1){ animationFrameId = requestAnimationFrame(animate); } else { spinning=false; const selectedVoucher = vouchers[targetSector]; const checkedLabel = document.querySelector(`label[for="${checked.id}"]`); const nameDisplayHtml = checkedLabel ? escapeHtml(checkedLabel.textContent) : 'Nama Pemenang'; if(winnerEl) winnerEl.innerHTML = `🎉 <div style="font-size:1rem; line-height:1.4;">${nameDisplayHtml}</div><div style="margin-top:8px;">mendapatkan <strong>${escapeHtml(selectedVoucher)}</strong> 🎁</div>`; burstConfetti(); tickSound(0.14, 880, 0.06); if(stopLine){ stopLine.classList.remove('flash'); void stopLine.offsetWidth; stopLine.classList.add('flash'); }

                        const rowHash = data.row_hash || payload.row_hash;
                        const studentId = data.student_id || payload.student_id;
                        if(rowHash){ const el = document.querySelector(`input[name="selected_name"][data-row_hash="${rowHash}"]`); if(el) el.closest('.form-check')?.remove(); availableNames = availableNames.filter(x => (x.row_hash||'') !== rowHash); }
                        else if(studentId){ const el = document.querySelector(`input[name="selected_name"][data-student-id="${studentId}"]`); if(el) el.closest('.form-check')?.remove(); availableNames = availableNames.filter(x => ((x.student_id||'')+'') !== (studentId+'')); }
                        else { console.warn('No unique identifier provided, skipping removal'); }

                        disableSpin(); loadRecentHistory(6);

                        const finalNormNow = norm360(-rotation);
                        const relaNow = norm360(pointerAngle - finalNormNow);
                        const sectorNow = Math.floor(relaNow / sectorAngle) % vouchers.length;
                        console.log({ finalNormNow, relaNow, sectorNow, expected: targetSector });
                        if(sectorNow !== targetSector) console.error('Mismatch: visual landed on sector', sectorNow, 'but expected', targetSector);
                    }
                }

                animationFrameId = requestAnimationFrame(animate);
    }

    function debounce(fn, wait){ let timeout; return function(...args){ clearTimeout(timeout); timeout = setTimeout(()=>fn.apply(this,args), wait); } }

    // init
    drawWheel(); reset(); loadNames(); loadRecentHistory(6);

    // events
    spinBtn && spinBtn.addEventListener('click', spin);
    resetBtn && resetBtn.addEventListener('click', ()=>{ if(animationFrameId) cancelAnimationFrame(animationFrameId); if(stopLine) stopLine.classList.remove('flash'); reset(); });
    window.addEventListener('resize', debounce(()=>{ size = Math.min(520, Math.max(300, Math.min(window.innerWidth * 0.95, 360))); if(canvas){ canvas.width = size; canvas.height = size; } if(wrap){ wrap.style.width = `${size}px`; wrap.style.height = `${size}px`; } drawWheel(); }, 100));
    window.addEventListener('beforeunload', ()=>{ if(animationFrameId) cancelAnimationFrame(animationFrameId); });

    // history modal
    refreshHistoryBtn && refreshHistoryBtn.addEventListener('click', ()=>loadRecentHistory(6));
    openHistoryModalBtn && openHistoryModalBtn.addEventListener('click', ()=>{ if(winnersHistoryModal) winnersHistoryModal.show(); loadHistoryPage(1,12); });

    async function loadHistoryPage(page=1, perPage=12){ if(historyTableContainer) historyTableContainer.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner"></span> Memuat riwayat…</div>'; try{ const res = await fetch(HISTORY_URL + `&page=${page}&per_page=${perPage}&_=` + Date.now(), { cache:'no-store', credentials:'same-origin', headers:{ 'X-Requested-With':'XMLHttpRequest' } }); if(!res.ok) throw new Error('HTTP '+res.status); const json = await res.json(); const rows = json.data || []; if(!rows.length){ if(historyTableContainer) historyTableContainer.innerHTML = '<div class="text-center text-muted py-4">Belum ada pemenang.</div>'; historyMeta.textContent=''; return; } const table = document.createElement('table'); table.className='table table-sm table-hover'; table.innerHTML = `<thead><tr><th>#</th><th>Nama</th><th>New Murid</th><th>Voucher</th><th>Waktu</th></tr></thead>`; const tb = document.createElement('tbody'); rows.forEach((r, idx)=>{ const newStudentName = r.new_student ? (r.new_student.nama || r.new_student.name || '') : ''; const tr = document.createElement('tr'); tr.innerHTML = `<td>${((json.current_page-1)*json.per_page)+idx+1}</td><td>${escapeHtml(r.name)}</td><td>${escapeHtml(newStudentName)}</td><td>${escapeHtml(r.voucher||'')}</td><td>${escapeHtml(r.won_at||r.created_at||'')}</td>`; tb.appendChild(tr); }); table.appendChild(tb); if(historyTableContainer){ historyTableContainer.innerHTML=''; historyTableContainer.appendChild(table); } historyMeta.textContent = `Halaman ${json.current_page} dari ${json.last_page || 1} — Total ${json.total ?? rows.length}`; }catch(err){ console.error('loadHistoryPage err', err); if(historyTableContainer) historyTableContainer.innerHTML = '<div class="text-danger py-3">Gagal memuat riwayat.</div>'; if(historyMeta) historyMeta.textContent=''; } }

})();
</script>
@endpush
