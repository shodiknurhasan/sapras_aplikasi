<?php
class User {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function login($username, $password) {
        $username = trim($username);

        // Cek akun admin terlebih dahulu.
        $stmt = mysqli_prepare($this->conn,
            "SELECT username, nama, password, 'admin' AS role
             FROM admin
             WHERE username = ? AND aktif = 1
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($data && password_verify($password, $data['password'])) {
            return $data;
        }

        // Jika bukan admin, cek akun siswa.
        $stmt = mysqli_prepare($this->conn,
            "SELECT username, nama, password, 'siswa' AS role
             FROM siswa
             WHERE username = ? AND aktif = 1
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($data && password_verify($password, $data['password'])) {
            return $data;
        }

        return false;
    }
    public function register($nis, $nama, $kelas, $username, $password) {
        $nis = (int)$nis;
        $nama = trim($nama);
        $kelas = trim($kelas);
        $username = trim($username);

        if ($nis <= 0 || $nama === '' || $kelas === '' || $username === '' || $password === '') {
            return ['ok' => false, 'message' => 'Semua data wajib diisi.'];
        }

        $stmt = mysqli_prepare($this->conn,
            "SELECT nis, username FROM siswa WHERE nis = ? OR username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "is", $nis, $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            if ((int)$row['nis'] === $nis) {
                return ['ok' => false, 'message' => 'NIS sudah terdaftar.'];
            }
            return ['ok' => false, 'message' => 'Username sudah digunakan.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare($this->conn,
            "INSERT INTO siswa (nis, nama, kelas, username, password, aktif)
             VALUES (?, ?, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt, "issss", $nis, $nama, $kelas, $username, $hash);

        if (mysqli_stmt_execute($stmt)) {
            return ['ok' => true, 'message' => 'Registrasi berhasil.'];
        }

        return ['ok' => false, 'message' => 'Registrasi gagal: ' . mysqli_error($this->conn)];
    }

}
?>
