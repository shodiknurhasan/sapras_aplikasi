<?php
session_start();
include '../config/koneksi.php';
include '../config/auth.php';
include '../config/icons.php';
include '../config/layout.php';
include '../config/timeline.php';
requireAdmin();
include '../models/Pengaduan.php';
include '../models/Kategori.php';

$m = new Pengaduan($conn);

$f = [
    'status'     => trim($_GET['status']     ?? ''),
    'tgl_dari'   => trim($_GET['tgl_dari']   ?? ''),
    'tgl_sampai' => trim($_GET['tgl_sampai'] ?? ''),
    'kategori'   => trim($_GET['kategori']   ?? ''),
    'q'          => trim($_GET['q']          ?? ''),
];
$adaFilter = false;
foreach ($f as $v) { if ($v !== '') { $adaFilter = true; break; } }

// Pagination
$perPage = 20;
$total   = $m->historiCount($f);
$pages   = max(1, (int)ceil($total / $perPage));
$page    = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset  = ($page - 1) * $perPage;

$rows = [];
$q = $m->histori($f, $perPage, $offset);
if ($q) while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;

// Dropdown kategori
$kat = new Kategori($conn);
$listKategori = [];
$qk = $kat->tampil();
if ($qk) while ($k = mysqli_fetch_assoc($qk)) $listKategori[] = $k;

function urlHistori($page) {
    $params = $_GET;
    $params['page'] = $page;
    return 'histori_pengaduan.php?' . http_build_query($params);
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Histori Pengaduan — SIGAP SARPRAS</title>
    <link rel="stylesheet" href="../assets/style.css?v=5">
</head>
<body>
<div class="app">
    <?= sidebar('histori') ?>
    <main class="main">
        <div class="top">
            <div>
                <h2>Histori Pengaduan</h2>
                <p>
                    Jejak seluruh aktivitas pengaduan · <?= $total ?> aktivitas<?= $adaFilter ? ' · hasil filter' : '' ?>
                </p>
            </div>
            <div class="top-actions">
                <div class="bell"><?= icon('bell', '17') ?></div>
                <div class="avatar"><?= strtoupper(substr($_SESSION['nama'] ?? 'A', 0, 1)) ?></div>
            </div>
        </div>

        <div class="panel table-panel">
            <div class="toolbar">
                <strong class="section-title">Log Aktivitas</strong>
            </div>

            <form method="get" class="filter-form">
                <div class="field">
                    <label>Dari Tanggal</label>
                    <input type="date" name="tgl_dari" value="<?= htmlspecialchars($f['tgl_dari']) ?>">
                </div>
                <div class="field">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="tgl_sampai" value="<?= htmlspecialchars($f['tgl_sampai']) ?>">
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <option value="">Semua status</option>
                        <?php foreach (['Menunggu','Diverifikasi','Diproses','Selesai','Ditolak'] as $s): ?>
                        <option <?= $f['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Kategori</label>
                    <select name="kategori">
                        <option value="">Semua kategori</option>
                        <?php foreach ($listKategori as $k): ?>
                        <option value="<?= (int)$k['id_kategori'] ?>" <?= (string)$f['kategori'] === (string)$k['id_kategori'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['nama_kategori']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Cari (nama / NIS / ID)</label>
                    <input type="text" name="q" placeholder="Cari..." value="<?= htmlspecialchars($f['q']) ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn primary">Terapkan</button>
                    <?php if ($adaFilter): ?><a href="histori_pengaduan.php" class="btn">Reset</a><?php endif; ?>
                </div>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pengaduan</th>
                            <th>Aktivitas</th>
                            <th>Oleh</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                        <tr><td colspan="5" class="empty-state">
                            <?= $adaFilter ? 'Tidak ada aktivitas yang sesuai dengan filter.' : 'Belum ada histori pengaduan.' ?>
                        </td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $h) echo historiRow($h); ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total > 0): ?>
            <div class="pager">
                <span>Menampilkan <?= $offset + 1 ?>–<?= $offset + count($rows) ?> dari <?= $total ?> aktivitas</span>
                <?php if ($pages > 1): ?>
                <div class="pager-links">
                    <?php if ($page > 1): ?><a class="btn" href="<?= htmlspecialchars(urlHistori($page - 1)) ?>">‹ Sebelumnya</a><?php endif; ?>
                    <span class="btn primary" style="pointer-events:none;">Hal. <?= $page ?> / <?= $pages ?></span>
                    <?php if ($page < $pages): ?><a class="btn" href="<?= htmlspecialchars(urlHistori($page + 1)) ?>">Berikutnya ›</a><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
