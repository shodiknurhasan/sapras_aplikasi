<?php
/**
 * Helper tampilan timeline penanganan pengaduan.
 * - timelinePenanganan()  : timeline tahapan untuk SISWA (detail_pengaduan.php)
 * - riwayatPenanganan()   : riwayat kronologis untuk ADMIN (tanggapan.php)
 */

function tglIndo($datetime, $withTime = false) {
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($datetime);
    $out = date('d', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
    return $withTime ? $out . ' · ' . date('H:i', $t) : $out;
}

/**
 * @param array $d        data pengaduan (hasil Pengaduan::get)
 * @param array $riwayat  baris penanganan, urut terbaru -> terlama
 */
function timelinePenanganan(array $d, array $riwayat): string {
    // Ambil catatan TERBARU untuk tiap status
    $latest = [];
    foreach ($riwayat as $h) {
        if (!isset($latest[$h['status']])) $latest[$h['status']] = $h;
    }

    $rank = ['Menunggu' => 0, 'Diverifikasi' => 1, 'Diproses' => 2, 'Selesai' => 3];
    $ditolak = ($d['status'] === 'Ditolak');
    $r = $rank[$d['status']] ?? 0;

    $steps = [
        ['key' => 'Menunggu',     'label' => 'Pengaduan Dibuat', 'icon' => 'send',   'pending' => ''],
        ['key' => 'Diverifikasi', 'label' => 'Diverifikasi',    'icon' => 'shield', 'pending' => 'Menunggu verifikasi petugas.'],
        ['key' => 'Diproses',     'label' => 'Diproses',        'icon' => 'tool',   'pending' => 'Belum mulai diproses.'],
        ['key' => 'Selesai',      'label' => 'Selesai',         'icon' => 'check',  'pending' => 'Penanganan belum selesai.'],
    ];

    // Tentukan state tiap tahap
    $items = [];
    foreach ($steps as $i => $s) {
        $e = $latest[$s['key']] ?? null;

        if ($ditolak) {
            // Pengaduan ditolak: tampilkan hanya tahap yang pernah dilalui
            if ($i > 0 && !$e) continue;
            $state = 'done';
        } elseif ($i === 0 || $i <= $r) {
            $state = ($i === 2 && $r === 2) ? 'current' : 'done';
        } else {
            $state = 'pending';
        }

        $items[] = [
            'state'  => $state,
            'label'  => $s['label'],
            'icon'   => $s['icon'],
            'entry'  => $e,
            'pending'=> $s['pending'],
            'first'  => ($i === 0),
        ];
    }

    if ($ditolak) {
        $items[] = [
            'state'  => 'rejected',
            'label'  => 'Ditolak',
            'icon'   => 'x',
            'entry'  => $latest['Ditolak'] ?? null,
            'pending'=> '',
            'first'  => false,
        ];
    }

    // Persentase progres
    $done = 0; $cur = 0;
    foreach ($items as $it) {
        if ($it['state'] === 'done') $done++;
        if ($it['state'] === 'current') $cur++;
    }
    $pct = $ditolak ? 100 : (int)round(($done + $cur * 0.5) / 4 * 100);

    $chip = ['done' => 'Selesai', 'current' => 'Sedang berlangsung', 'pending' => 'Menunggu', 'rejected' => 'Ditolak'];

    ob_start(); ?>
    <div class="tl-progress <?= $ditolak ? 'is-rejected' : '' ?>">
        <div class="tl-progress-head">
            <span><?= $ditolak ? 'Pengaduan tidak dilanjutkan' : 'Progres Penanganan' ?></span>
            <strong><?= $ditolak ? 'Ditolak' : $pct . '%' ?></strong>
        </div>
        <div class="tl-bar"><span style="width:<?= $pct ?>%"></span></div>
    </div>

    <ol class="tl">
        <?php foreach ($items as $n => $it):
            $next    = $items[$n + 1]['state'] ?? null;
            $linkOn  = $next !== null && $next !== 'pending';
            $e       = $it['entry'];
        ?>
        <li class="tl-step <?= $it['state'] ?> <?= $linkOn ? 'link-on' : '' ?>">
            <span class="tl-dot"><?= icon($it['icon'], '14') ?></span>
            <div class="tl-card">
                <div class="tl-head">
                    <h5><?= htmlspecialchars($it['label']) ?></h5>
                    <span class="tl-chip"><?= $chip[$it['state']] ?></span>
                </div>

                <?php if ($it['first']): ?>
                    <div class="tl-time"><?= icon('calendar', '12') ?> <?= tglIndo($d['created_at'], true) ?></div>
                    <p class="tl-text">Laporan berhasil dikirim dan menunggu ditinjau petugas.</p>

                <?php elseif ($e): ?>
                    <div class="tl-time">
                        <?= icon('calendar', '12') ?> <?= tglIndo($e['created_at'], true) ?>
                        <?php if (!empty($e['nama_admin'])): ?>
                            <span class="tl-sep">•</span> <?= icon('user', '12') ?> <?= htmlspecialchars($e['nama_admin']) ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($e['feedback'])): ?>
                        <div class="tl-note">
                            <small>Umpan balik petugas</small>
                            <?= nl2br(htmlspecialchars($e['feedback'])) ?>
                        </div>
                    <?php endif; ?>

                <?php elseif ($it['state'] === 'done'): ?>
                    <p class="tl-text">Tahap ini sudah dilalui.</p>

                <?php else: ?>
                    <p class="tl-text tl-muted"><?= htmlspecialchars($it['pending']) ?></p>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php
    return ob_get_clean();
}

/**
 * Riwayat kronologis (terbaru di atas) untuk admin. Menampilkan catatan internal.
 * @param array $riwayat baris penanganan, urut terbaru -> terlama
 */
function riwayatPenanganan(array $riwayat): string {
    $map = [
        'Menunggu'     => ['pending',  'send'],
        'Diverifikasi' => ['verified', 'shield'],
        'Diproses'     => ['current',  'tool'],
        'Selesai'      => ['done',     'check'],
        'Ditolak'      => ['rejected', 'x'],
    ];

    ob_start();
    if (!$riwayat): ?>
        <div class="tl-empty">
            <div><?= icon('list', '32') ?></div>
            <div>Belum ada riwayat penanganan.</div>
        </div>
    <?php else: ?>
        <ol class="tl">
            <?php foreach ($riwayat as $n => $h):
                list($cls, $ic) = $map[$h['status']] ?? ['pending', 'clock'];
                $linkOn = ($n < count($riwayat) - 1);
            ?>
            <li class="tl-step <?= $cls ?> <?= $linkOn ? 'link-on' : '' ?>">
                <span class="tl-dot"><?= icon($ic, '14') ?></span>
                <div class="tl-card">
                    <div class="tl-head">
                        <h5><?= htmlspecialchars($h['status']) ?></h5>
                        <?php if ($n === 0): ?><span class="tl-chip">Terbaru</span><?php endif; ?>
                    </div>
                    <div class="tl-time">
                        <?= icon('calendar', '12') ?> <?= tglIndo($h['created_at'], true) ?>
                        <?php if (!empty($h['nama_admin'])): ?>
                            <span class="tl-sep">•</span> <?= icon('user', '12') ?> <?= htmlspecialchars($h['nama_admin']) ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($h['feedback'])): ?>
                        <div class="tl-note">
                            <small>Umpan balik untuk siswa</small>
                            <?= nl2br(htmlspecialchars($h['feedback'])) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($h['catatan'])): ?>
                        <div class="tl-note internal">
                            <small>Catatan internal</small>
                            <?= nl2br(htmlspecialchars($h['catatan'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ol>
    <?php endif;
    return ob_get_clean();
}

/**
 * Satu baris histori pengaduan (dipakai dashboard admin & histori_pengaduan.php).
 * @param array $h baris hasil Pengaduan::histori()
 */
function historiRow(array $h): string {
    $cls = ['Selesai' => 'selesai', 'Diproses' => 'proses', 'Ditolak' => 'ditolak', 'Menunggu' => 'menunggu'];
    $ico = ['Menunggu' => 'send', 'Diverifikasi' => 'shield', 'Diproses' => 'tool', 'Selesai' => 'check', 'Ditolak' => 'x'];
    $st  = $h['status'];
    $dibuat = ($h['jenis'] === 'dibuat');
    $oleh = $dibuat
        ? $h['nama'] . ' (siswa)'
        : (!empty($h['nama_admin']) ? $h['nama_admin'] : ($h['username'] ?? '-'));

    ob_start(); ?>
    <tr>
        <td style="white-space:nowrap;font-size:12px;color:var(--muted);">
            <?= tglIndo($h['created_at']) ?>
            <div style="margin-top:2px;"><?= date('H:i', strtotime($h['created_at'])) ?></div>
        </td>
        <td>
            <div style="font-weight:600;">#<?= str_pad($h['id_pengaduan'], 4, '0', STR_PAD_LEFT) ?> · <?= htmlspecialchars($h['nama_kategori']) ?></div>
            <div style="font-size:11px;color:var(--muted);margin-top:2px;">
                <?= htmlspecialchars($h['nama']) ?> · <?= htmlspecialchars($h['kelas']) ?> · <?= htmlspecialchars($h['lokasi']) ?>
            </div>
        </td>
        <td>
            <div class="hist-event">
                <span class="hist-dot <?= $cls[$st] ?? '' ?>"><?= icon($ico[$st] ?? 'clock', '14') ?></span>
                <div>
                    <div style="font-weight:600;font-size:13px;">
                        <?= $dibuat ? 'Pengaduan dibuat' : 'Status diubah → ' . htmlspecialchars($st) ?>
                    </div>
                    <?php if (!empty($h['feedback'])): ?>
                        <div class="hist-note"><em>Feedback:</em> <?= htmlspecialchars($h['feedback']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($h['catatan'])): ?>
                        <div class="hist-note"><em>Catatan internal:</em> <?= htmlspecialchars($h['catatan']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </td>
        <td style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($oleh) ?></td>
        <td>
            <a class="action-btn" href="tanggapan.php?id=<?= (int)$h['id_pengaduan'] ?>">
                <span style="display:flex;align-items:center;gap:4px;"><?= icon('eye', '13') ?> Detail</span>
            </a>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}
