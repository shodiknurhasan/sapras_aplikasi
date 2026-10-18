<?php
class Pengaduan {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Ambil semua pengaduan, bisa difilter.
     * $f: status, tgl_dari, tgl_sampai, bulan (YYYY-MM), siswa (nama/NIS), kategori (id)
     */
    public function semua($f = []) {
        $where = [];

        if (!empty($f['status'])) {
            $st = mysqli_real_escape_string($this->conn, $f['status']);
            $where[] = "p.status = '$st'";
        }
        if (!empty($f['bulan']) && preg_match('/^\d{4}-\d{2}$/', $f['bulan'])) {
            $b = mysqli_real_escape_string($this->conn, $f['bulan']);
            $where[] = "DATE_FORMAT(p.created_at, '%Y-%m') = '$b'";
        }
        if (!empty($f['tgl_dari']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['tgl_dari'])) {
            $d = mysqli_real_escape_string($this->conn, $f['tgl_dari']);
            $where[] = "DATE(p.created_at) >= '$d'";
        }
        if (!empty($f['tgl_sampai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['tgl_sampai'])) {
            $d = mysqli_real_escape_string($this->conn, $f['tgl_sampai']);
            $where[] = "DATE(p.created_at) <= '$d'";
        }
        if (!empty($f['kategori'])) {
            $where[] = "p.id_kategori = " . (int)$f['kategori'];
        }
        if (!empty($f['siswa'])) {
            $kw = mysqli_real_escape_string($this->conn, trim($f['siswa']));
            $where[] = "(s.nama LIKE '%$kw%' OR CAST(s.nis AS CHAR) LIKE '%$kw%')";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT p.*, s.nama, s.kelas, k.nama_kategori
                FROM pengaduan p
                JOIN siswa s ON s.nis=p.nis
                JOIN kategori k ON k.id_kategori=p.id_kategori
                $whereSql
                ORDER BY p.created_at DESC";
        return mysqli_query($this->conn, $sql);
    }

    public function siswa($nis) {
        $nis = (int)$nis;
        $sql = "SELECT p.*, k.nama_kategori
                FROM pengaduan p
                JOIN kategori k ON k.id_kategori=p.id_kategori
                WHERE p.nis=$nis
                ORDER BY p.created_at DESC";
        return mysqli_query($this->conn, $sql);
    }

    public function get($id) {
        $id = (int)$id;
        $sql = "SELECT p.*, s.nama, s.kelas, s.username, k.nama_kategori
                FROM pengaduan p
                JOIN siswa s ON s.nis=p.nis
                JOIN kategori k ON k.id_kategori=p.id_kategori
                WHERE p.id_pengaduan=$id";
        $q = mysqli_query($this->conn, $sql);
        return mysqli_fetch_assoc($q);
    }

    public function tambah($nis, $id_kategori, $lokasi, $keterangan, $foto = null) {
        $nis = (int)$nis;
        $id_kategori = (int)$id_kategori;
        $lokasi = mysqli_real_escape_string($this->conn, trim($lokasi));
        $keterangan = mysqli_real_escape_string($this->conn, trim($keterangan));
        $fotoSql = $foto ? "'" . mysqli_real_escape_string($this->conn, $foto) . "'" : "NULL";

        return mysqli_query($this->conn,
            "INSERT INTO pengaduan (nis,id_kategori,lokasi,keterangan,foto,status)
             VALUES ($nis,$id_kategori,'$lokasi','$keterangan',$fotoSql,'Menunggu')");
    }

    public function updateStatus($id, $status, $feedback, $catatan, $username) {
        $id = (int)$id;
        $status = mysqli_real_escape_string($this->conn, $status);
        $feedback = mysqli_real_escape_string($this->conn, trim($feedback));
        $catatan = mysqli_real_escape_string($this->conn, trim($catatan));
        $username = mysqli_real_escape_string($this->conn, $username);

        mysqli_begin_transaction($this->conn);

        $ok1 = mysqli_query($this->conn,
            "UPDATE pengaduan SET status='$status' WHERE id_pengaduan=$id");

        $ok2 = mysqli_query($this->conn,
            "INSERT INTO penanganan (id_pengaduan,username,status,feedback,catatan)
             VALUES ($id,'$username','$status','$feedback','$catatan')");

        if ($ok1 && $ok2) {
            mysqli_commit($this->conn);
            return true;
        }

        mysqli_rollback($this->conn);
        return false;
    }

    public function penanganan($id) {
        $id = (int)$id;
        return mysqli_query($this->conn,
            "SELECT p.*, a.nama AS nama_admin
             FROM penanganan p
             LEFT JOIN admin a ON a.username=p.username
             WHERE p.id_pengaduan=$id
             ORDER BY p.created_at DESC");
    }

    public function hapusSiswa($id, $nis) {
        $id  = (int)$id;
        $nis = (int)$nis;

        // Ambil data pengaduan milik siswa ini
        $q = mysqli_query($this->conn,
            "SELECT foto, status FROM pengaduan WHERE id_pengaduan=$id AND nis=$nis");
        $row = mysqli_fetch_assoc($q);

        // Tidak ditemukan atau bukan milik siswa ini
        if (!$row) return ['ok' => false, 'message' => 'Pengaduan tidak ditemukan.'];

        // Hanya boleh hapus kalau Menunggu atau Ditolak
        if (!in_array($row['status'], ['Menunggu', 'Ditolak'])) {
            return ['ok' => false, 'message' => 'Pengaduan yang sedang diproses atau sudah selesai tidak dapat dihapus.'];
        }

        mysqli_begin_transaction($this->conn);

        // Hapus data penanganan terkait (jaga-jaga ada FK)
        $ok1 = mysqli_query($this->conn,
            "DELETE FROM penanganan WHERE id_pengaduan=$id");

        // Hapus pengaduan
        $ok2 = mysqli_query($this->conn,
            "DELETE FROM pengaduan WHERE id_pengaduan=$id AND nis=$nis");

        if ($ok1 && $ok2 && mysqli_affected_rows($this->conn) > 0) {
            mysqli_commit($this->conn);

            // Hapus file foto jika ada
            if (!empty($row['foto'])) {
                $path = __DIR__ . '/../assets/uploads/' . $row['foto'];
                if (file_exists($path)) @unlink($path);
            }

            return ['ok' => true, 'message' => 'Pengaduan berhasil dihapus.'];
        }

        mysqli_rollback($this->conn);
        return ['ok' => false, 'message' => 'Gagal menghapus pengaduan.'];
    }

    /**
     * Histori aktivitas pengaduan (kronologis, terbaru di atas).
     * Menggabungkan: (1) pengaduan dibuat siswa, (2) setiap perubahan status oleh admin.
     * $f: status, tgl_dari, tgl_sampai, kategori (id), q (nama siswa / NIS / ID pengaduan)
     */
    private function historiBase($f = []) {
        $where = [];

        if (!empty($f['status'])) {
            $st = mysqli_real_escape_string($this->conn, $f['status']);
            $where[] = "x.status = '$st'";
        }
        if (!empty($f['tgl_dari']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['tgl_dari'])) {
            $d = mysqli_real_escape_string($this->conn, $f['tgl_dari']);
            $where[] = "DATE(x.created_at) >= '$d'";
        }
        if (!empty($f['tgl_sampai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['tgl_sampai'])) {
            $d = mysqli_real_escape_string($this->conn, $f['tgl_sampai']);
            $where[] = "DATE(x.created_at) <= '$d'";
        }
        if (!empty($f['kategori'])) {
            $where[] = "p.id_kategori = " . (int)$f['kategori'];
        }
        if (!empty($f['q'])) {
            $kw = mysqli_real_escape_string($this->conn, trim($f['q']));
            $idNum = (int)ltrim($kw, '#0');
            $cond = "s.nama LIKE '%$kw%' OR CAST(s.nis AS CHAR) LIKE '%$kw%'";
            if ($idNum > 0) $cond .= " OR x.id_pengaduan = $idNum";
            $where[] = "($cond)";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return "FROM (
                    SELECT id_pengaduan, 'Menunggu' AS status, '' AS feedback, '' AS catatan,
                           NULL AS username, created_at, 'dibuat' AS jenis
                    FROM pengaduan
                    UNION ALL
                    SELECT id_pengaduan, status, feedback, catatan,
                           username, created_at, 'penanganan' AS jenis
                    FROM penanganan
                ) x
                JOIN pengaduan p ON p.id_pengaduan = x.id_pengaduan
                JOIN siswa s     ON s.nis = p.nis
                JOIN kategori k  ON k.id_kategori = p.id_kategori
                LEFT JOIN admin a ON a.username = x.username
                $whereSql";
    }

    public function histori($f = [], $limit = 20, $offset = 0) {
        $limit  = max(1, (int)$limit);
        $offset = max(0, (int)$offset);
        $sql = "SELECT x.*, s.nama, s.kelas, k.nama_kategori, p.lokasi,
                       a.nama AS nama_admin
                " . $this->historiBase($f) . "
                ORDER BY x.created_at DESC, x.id_pengaduan DESC
                LIMIT $limit OFFSET $offset";
        return mysqli_query($this->conn, $sql);
    }

    public function historiCount($f = []) {
        $sql = "SELECT COUNT(*) AS total " . $this->historiBase($f);
        $q = mysqli_query($this->conn, $sql);
        $r = $q ? mysqli_fetch_assoc($q) : null;
        return (int)($r['total'] ?? 0);
    }

    public function statistik() {
        $result = [
            'total' => 0,
            'menunggu' => 0,
            'diproses' => 0,
            'selesai' => 0
        ];

        $q = mysqli_query($this->conn,
            "SELECT status, COUNT(*) total FROM pengaduan GROUP BY status");
        while ($r = mysqli_fetch_assoc($q)) {
            $result['total'] += (int)$r['total'];
            if ($r['status'] === 'Menunggu') $result['menunggu'] = (int)$r['total'];
            if ($r['status'] === 'Diproses') $result['diproses'] = (int)$r['total'];
            if ($r['status'] === 'Selesai') $result['selesai'] = (int)$r['total'];
        }
        return $result;
    }
}
?>
