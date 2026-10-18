<?php
session_start();

include '../config/koneksi.php';
include '../config/auth.php';
include '../config/icons.php';
include '../config/layout.php';

requireAdmin();

include '../models/Pengaduan.php';
include '../models/Kategori.php';

$m = new Pengaduan($conn);

/* Ambil nilai filter dari URL */
$filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$f = [
    'status'     => $filter,
    'tgl_dari'   => isset($_GET['tgl_dari'])   ? trim($_GET['tgl_dari'])   : '',
    'tgl_sampai' => isset($_GET['tgl_sampai']) ? trim($_GET['tgl_sampai']) : '',
    'bulan'      => isset($_GET['bulan'])      ? trim($_GET['bulan'])      : '',
    'siswa'      => isset($_GET['siswa'])      ? trim($_GET['siswa'])      : '',
    'kategori'   => isset($_GET['kategori'])   ? trim($_GET['kategori'])   : '',
];

$q = $m->semua($f);

$rows = [];
if ($q) {
    while ($r = mysqli_fetch_assoc($q)) {
        $rows[] = $r;
    }
}

/* Daftar kategori untuk dropdown */
$kat = new Kategori($conn);
$listKategori = [];
$qk = $kat->tampil();
if ($qk) {
    while ($k = mysqli_fetch_assoc($qk)) {
        $listKategori[] = $k;
    }
}

/* Ada filter aktif selain status? */
$adaFilter = false;
foreach ($f as $v) {
    if ($v !== '') { $adaFilter = true; break; }
}

/* URL tombol status: pertahankan filter lain */
function urlStatus($status) {
    $params = $_GET;
    unset($params['status']);
    if ($status !== '') {
        $params['status'] = $status;
    }
    $qs = http_build_query($params);
    return 'semua_pengaduan.php' . ($qs ? '?' . $qs : '');
}
?>

<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Semua Pengaduan — SIGAP SARPRAS</title>

    <link rel="stylesheet" href="../assets/style.css?v=5">
</head>

<body>

<div class="app">

    <?= sidebar('complaints') ?>

    <main class="main">

        <!-- HEADER -->
        <div class="top">

            <div>
                <h2>Semua Pengaduan</h2>

                <p>
                    Daftar seluruh pengaduan sarana prasarana ·
                    <?= count($rows) ?> laporan

                    <?php if ($adaFilter): ?>
                        · hasil filter
                    <?php endif; ?>
                </p>
            </div>

            <div class="top-actions">

                <div class="bell">
                    <?= icon('bell', '17') ?>
                </div>

                <div class="avatar">
                    <?= strtoupper(substr(isset($_SESSION['nama']) ? $_SESSION['nama'] : 'A', 0, 1)) ?>
                </div>

            </div>

        </div>


        <!-- PANEL -->
        <div class="panel table-panel">

            <!-- TOOLBAR -->
            <div class="toolbar">

                <strong class="section-title">
                    Daftar Pengaduan
                </strong>

                <div class="toolbar-actions">
                    <?php
                    $opsiStatus = [
                        ''             => 'Semua',
                        'Menunggu'     => 'Menunggu',
                        'Diverifikasi' => 'Diverifikasi',
                        'Diproses'     => 'Diproses',
                        'Selesai'      => 'Selesai',
                        'Ditolak'      => 'Ditolak',
                    ];
                    foreach ($opsiStatus as $val => $label): ?>
                        <a class="btn <?= $filter === $val ? 'primary' : '' ?>"
                           href="<?= htmlspecialchars(urlStatus($val)) ?>">
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </div>

            </div>


            <!-- FILTER -->
            <form method="get" class="filter-form">
                <?php if ($filter !== ''): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
                <?php endif; ?>

                <div class="field">
                    <label>Dari Tanggal</label>
                    <input type="date" name="tgl_dari" value="<?= htmlspecialchars($f['tgl_dari']) ?>">
                </div>

                <div class="field">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="tgl_sampai" value="<?= htmlspecialchars($f['tgl_sampai']) ?>">
                </div>

                <div class="field">
                    <label>Bulan</label>
                    <input type="month" name="bulan" value="<?= htmlspecialchars($f['bulan']) ?>">
                </div>

                <div class="field">
                    <label>Siswa (nama / NIS)</label>
                    <input type="text" name="siswa" placeholder="Cari siswa..." value="<?= htmlspecialchars($f['siswa']) ?>">
                </div>

                <div class="field">
                    <label>Kategori</label>
                    <select name="kategori">
                        <option value="">Semua kategori</option>
                        <?php foreach ($listKategori as $k): ?>
                            <option value="<?= (int)$k['id_kategori'] ?>"
                                <?= (string)$f['kategori'] === (string)$k['id_kategori'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn primary">Terapkan</button>
                    <?php if ($adaFilter): ?>
                        <a href="semua_pengaduan.php" class="btn">Reset</a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- TABLE -->
            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>
                            <th>#ID</th>
                            <th>Pelapor</th>
                            <th>Kategori</th>
                            <th>Lokasi</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($rows)): ?>

                        <tr>

                            <td colspan="7" class="empty-state">

                                <?php if ($adaFilter): ?>

                                    Tidak ada pengaduan yang sesuai dengan filter.

                                <?php else: ?>

                                    Belum ada pengaduan.

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach ($rows as $r): ?>

                        <tr>

                            <!-- ID -->
                            <td
                                style="
                                    font-weight:600;
                                    color:var(--muted);
                                    font-size:12px;
                                "
                            >
                                #<?= str_pad(
                                    isset($r['id_pengaduan']) ? $r['id_pengaduan'] : 0,
                                    4,
                                    '0',
                                    STR_PAD_LEFT
                                ) ?>
                            </td>


                            <!-- PELAPOR -->
                            <td>

                                <div
                                    style="
                                        font-weight:600;
                                        color:var(--text);
                                    "
                                >
                                    <?= htmlspecialchars(
                                        isset($r['nama']) ? $r['nama'] : '-'
                                    ) ?>
                                </div>

                                <div
                                    style="
                                        font-size:11px;
                                        color:var(--muted);
                                        margin-top:2px;
                                    "
                                >
                                    <?= htmlspecialchars(
                                        isset($r['kelas']) ? $r['kelas'] : '-'
                                    ) ?>
                                </div>

                            </td>


                            <!-- KATEGORI -->
                            <td>
                                <?= htmlspecialchars(
                                    isset($r['nama_kategori'])
                                        ? $r['nama_kategori']
                                        : '-'
                                ) ?>
                            </td>


                            <!-- LOKASI -->
                            <td
                                style="
                                    font-size:12px;
                                    color:var(--muted);
                                "
                            >
                                <?= htmlspecialchars(
                                    isset($r['lokasi'])
                                        ? $r['lokasi']
                                        : '-'
                                ) ?>
                            </td>


                            <!-- TANGGAL -->
                            <td
                                style="
                                    font-size:12px;
                                    color:var(--muted);
                                    white-space:nowrap;
                                "
                            >

                                <?php
                                if (
                                    isset($r['created_at']) &&
                                    !empty($r['created_at'])
                                ) {
                                    echo date(
                                        'd M Y',
                                        strtotime($r['created_at'])
                                    );
                                } else {
                                    echo '-';
                                }
                                ?>

                            </td>


                            <!-- STATUS -->
                            <td>

                                <?php
                                $status = isset($r['status'])
                                    ? $r['status']
                                    : '';

                                $statusClass = '';

                                if ($status === 'Selesai') {
                                    $statusClass = 'selesai';
                                } elseif ($status === 'Diproses') {
                                    $statusClass = 'proses';
                                } elseif ($status === 'Ditolak') {
                                    $statusClass = 'ditolak';
                                }
                                ?>

                                <span class="status <?= $statusClass ?>">

                                    <?= htmlspecialchars($status) ?>

                                </span>

                            </td>


                            <!-- AKSI -->
                            <td>

                                <a
                                    class="action-btn"
                                    href="tanggapan.php?id=<?= isset($r['id_pengaduan']) ? $r['id_pengaduan'] : 0 ?>"
                                >

                                    <span
                                        style="
                                            display:flex;
                                            align-items:center;
                                            gap:4px;
                                        "
                                    >

                                        <?= icon('eye', '13') ?>

                                        <?php
                                        if ($status === 'Menunggu') {
                                            echo 'Tanggapi';
                                        } else {
                                            echo 'Detail';
                                        }
                                        ?>

                                    </span>

                                </a>

                            </td>

                        </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>

</body>
</html>