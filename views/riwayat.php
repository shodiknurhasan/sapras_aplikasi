<?php
session_start();

include '../config/koneksi.php';
include '../config/auth.php';
include '../config/icons.php';
include '../config/layout.php';

requireSiswa();

include '../models/Pengaduan.php';


// =====================================================
// AMBIL DATA AKUN SISWA
// =====================================================

$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

$username_safe = mysqli_real_escape_string($conn, $username);

$q = mysqli_query(
    $conn,
    "SELECT nis, nama FROM siswa WHERE username='$username_safe' LIMIT 1"
);

$akun = mysqli_fetch_assoc($q);


// Jika akun tidak ditemukan
if (!$akun) {
    die('Data akun siswa tidak ditemukan.');
}

$nis = (int) $akun['nis'];


// =====================================================
// OBJECT PENGADUAN
// =====================================================

$m = new Pengaduan($conn);


// =====================================================
// HANDLE HAPUS
// =====================================================

$msg_hapus = '';
$err_hapus = false;

if (isset($_GET['hapus'])) {

    $id_hapus = (int) $_GET['hapus'];

    $result = $m->hapusSiswa($id_hapus, $nis);

    if (is_array($result)) {

        $msg_hapus = isset($result['message'])
            ? $result['message']
            : '';

        $err_hapus = isset($result['ok'])
            ? !$result['ok']
            : true;

    } else {

        $msg_hapus = 'Pengaduan berhasil diproses.';
        $err_hapus = false;

    }
}


// =====================================================
// AMBIL DATA PENGADUAN SISWA
// =====================================================

$rs = $m->siswa($nis);

$rows = array();

if ($rs) {

    while ($r = mysqli_fetch_assoc($rs)) {
        $rows[] = $r;
    }

}


// =====================================================
// FILTER STATUS
// =====================================================

$filter = isset($_GET['status'])
    ? $_GET['status']
    : '';


// Jangan gunakan fn() => karena PHP versi lama
if ($filter !== '') {

    $filtered_rows = array();

    foreach ($rows as $r) {

        if (
            isset($r['status']) &&
            $r['status'] === $filter
        ) {
            $filtered_rows[] = $r;
        }

    }

    $rows = $filtered_rows;
}

?>

<!doctype html>

<html lang="id">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Riwayat Pengaduan — SIGAP SARPRAS
    </title>

    <link
        rel="stylesheet"
        href="../assets/style.css?v=5"
    >

</head>


<body>


<div class="app">


    <!-- SIDEBAR -->

    <?= sidebar('history') ?>


    <main class="main">


        <!-- ================================================= -->
        <!-- HEADER -->
        <!-- ================================================= -->

        <div class="top">

            <div>

                <h2>
                    Riwayat Pengaduan
                </h2>

                <p>

                    Semua laporan yang pernah Anda ajukan ·

                    <?= count($rows) ?>

                    pengaduan

                    <?php if ($filter !== ''): ?>

                        · filter:

                        <?= htmlspecialchars($filter) ?>

                    <?php endif; ?>

                </p>

            </div>


            <div class="top-actions">


                <div class="bell">

                    <?= icon('bell', '17') ?>

                </div>


                <div class="avatar">

                    <?= strtoupper(
                        substr(
                            isset($akun['nama'])
                                ? $akun['nama']
                                : 'S',
                            0,
                            1
                        )
                    ) ?>

                </div>


            </div>

        </div>



        <!-- ================================================= -->
        <!-- PESAN HAPUS -->
        <!-- ================================================= -->

        <?php if ($msg_hapus !== ''): ?>

            <div
                class="alert <?= $err_hapus ? 'error' : 'success' ?>"
            >

                <?= htmlspecialchars($msg_hapus) ?>

            </div>

        <?php endif; ?>



        <?php if (isset($_GET['deleted'])): ?>

            <div class="alert success">

                Pengaduan berhasil dihapus.

            </div>

        <?php endif; ?>



        <!-- ================================================= -->
        <!-- PANEL -->
        <!-- ================================================= -->

        <div class="panel table-panel">


            <!-- TOOLBAR -->

            <div class="toolbar">


                <strong class="section-title">

                    Daftar Pengaduan Saya

                </strong>


                <div class="toolbar-actions">


                    <!-- SEMUA -->

                    <a
                        class="btn <?= $filter === '' ? 'primary' : '' ?>"
                        href="riwayat.php"
                    >

                        Semua

                    </a>


                    <!-- MENUNGGU -->

                    <a
                        class="btn <?= $filter === 'Menunggu' ? 'primary' : '' ?>"
                        href="riwayat.php?status=Menunggu"
                    >

                        Menunggu

                    </a>


                    <!-- DIPROSES -->

                    <a
                        class="btn <?= $filter === 'Diproses' ? 'primary' : '' ?>"
                        href="riwayat.php?status=Diproses"
                    >

                        Diproses

                    </a>


                    <!-- SELESAI -->

                    <a
                        class="btn <?= $filter === 'Selesai' ? 'primary' : '' ?>"
                        href="riwayat.php?status=Selesai"
                    >

                        Selesai

                    </a>


                    <!-- BUAT PENGADUAN -->

                    <a
                        href="form_pengaduan.php"
                        class="btn primary"
                    >

                        <span
                            style="
                                display:flex;
                                align-items:center;
                                gap:6px;
                            "
                        >

                            <?= icon('plus', '13') ?>

                            Buat Baru

                        </span>

                    </a>


                </div>

            </div>



            <!-- ================================================= -->
            <!-- TABLE -->
            <!-- ================================================= -->

            <div class="table-wrap">


                <table>


                    <thead>

                        <tr>

                            <th>
                                #ID
                            </th>

                            <th>
                                Kategori
                            </th>

                            <th>
                                Lokasi
                            </th>

                            <th>
                                Tanggal
                            </th>

                            <th>
                                Status
                            </th>

                            <th style="text-align:center;">
                                Aksi
                            </th>

                        </tr>

                    </thead>



                    <tbody>


                    <!-- TIDAK ADA DATA -->

                    <?php if (empty($rows)): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="empty-state"
                            >

                                <?php if ($filter !== ''): ?>

                                    Tidak ada pengaduan dengan status
                                    "<?= htmlspecialchars($filter) ?>".

                                <?php else: ?>

                                    Belum ada pengaduan.

                                <?php endif; ?>

                            </td>

                        </tr>


                    <?php else: ?>


                        <!-- DATA -->

                        <?php foreach ($rows as $r): ?>


                            <?php

                            $status = isset($r['status'])
                                ? $r['status']
                                : '';

                            $id_pengaduan = isset($r['id_pengaduan'])
                                ? (int) $r['id_pengaduan']
                                : 0;

                            $kategori = isset($r['nama_kategori'])
                                ? $r['nama_kategori']
                                : '-';

                            $lokasi = isset($r['lokasi'])
                                ? $r['lokasi']
                                : '-';

                            $tanggal = isset($r['created_at'])
                                ? $r['created_at']
                                : '';

                            ?>


                            <?php

                            // Pengaduan hanya bisa dihapus
                            // jika status Menunggu atau Ditolak

                            $bisa_hapus =
                                ($status === 'Menunggu' ||
                                 $status === 'Ditolak');

                            ?>


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
                                        $id_pengaduan,
                                        4,
                                        '0',
                                        STR_PAD_LEFT
                                    ) ?>

                                </td>



                                <!-- KATEGORI -->

                                <td>

                                    <div
                                        style="
                                            font-weight:600;
                                            color:var(--text);
                                        "
                                    >

                                        <?= htmlspecialchars($kategori) ?>

                                    </div>

                                </td>



                                <!-- LOKASI -->

                                <td
                                    style="
                                        font-size:12px;
                                        color:var(--muted);
                                    "
                                >

                                    <?= htmlspecialchars($lokasi) ?>

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

                                    if ($tanggal !== '') {

                                        echo date(
                                            'd M Y',
                                            strtotime($tanggal)
                                        );

                                    } else {

                                        echo '-';

                                    }

                                    ?>

                                </td>



                                <!-- STATUS -->

                                <td>


                                    <?php

                                    $status_class = '';

                                    if ($status === 'Selesai') {

                                        $status_class = 'selesai';

                                    } elseif ($status === 'Diproses') {

                                        $status_class = 'proses';

                                    } elseif ($status === 'Ditolak') {

                                        $status_class = 'ditolak';

                                    }

                                    ?>


                                    <span
                                        class="status <?= $status_class ?>"
                                    >

                                        <?= htmlspecialchars($status) ?>

                                    </span>


                                </td>



                                <!-- AKSI -->

                                <td>


                                    <div
                                        class="action-row"
                                        style="justify-content:center;"
                                    >


                                        <!-- LIHAT -->

                                        <a
                                            class="action-btn"
                                            href="detail_pengaduan.php?id=<?= $id_pengaduan ?>"
                                        >

                                            <span
                                                style="
                                                    display:flex;
                                                    align-items:center;
                                                    gap:4px;
                                                "
                                            >

                                                <?= icon('eye', '13') ?>

                                                Lihat

                                            </span>

                                        </a>



                                        <?php if ($bisa_hapus): ?>


                                            <!-- HAPUS -->

                                            <a
                                                class="action-btn danger"
                                                href="riwayat.php?hapus=<?= $id_pengaduan ?><?= $filter !== '' ? '&status=' . urlencode($filter) : '' ?>"
                                                onclick="return confirm('Hapus pengaduan ini?\n\nPengaduan yang dihapus tidak dapat dikembalikan.')"
                                            >

                                                <span
                                                    style="
                                                        display:flex;
                                                        align-items:center;
                                                        gap:4px;
                                                    "
                                                >

                                                    <?= icon('trash', '13') ?>

                                                    Hapus

                                                </span>

                                            </a>


                                        <?php else: ?>


                                            <span
                                                style="
                                                    font-size:11px;
                                                    color:var(--muted-light);
                                                    padding:6px 4px;
                                                "
                                            >

                                                Tidak dapat dihapus

                                            </span>


                                        <?php endif; ?>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                    </tbody>

                </table>


            </div>


        </div>



        <!-- ================================================= -->
        <!-- INFORMASI -->
        <!-- ================================================= -->

        <div
            style="
                margin-top:12px;
                font-size:12px;
                color:var(--muted);
                display:flex;
                align-items:flex-start;
                gap:7px;
                line-height:1.5;
            "
        >

            <?= icon('info', '14') ?>


            <span>

                Pengaduan berstatus

                <strong style="color:var(--amber-dark);">
                    Menunggu
                </strong>

                atau

                <strong style="color:#9f1239;">
                    Ditolak
                </strong>

                dapat dihapus.

                Pengaduan yang sedang

                <strong>
                    Diproses
                </strong>

                atau sudah

                <strong>
                    Selesai
                </strong>

                tidak dapat dihapus.

            </span>


        </div>


    </main>

</div>


</body>

</html>