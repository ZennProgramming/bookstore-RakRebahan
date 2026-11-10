<?php
require_once __DIR__ . '/_umum.php';

function mulaiSesi(array $baris): array
{
    session_regenerate_id(true);
    $_SESSION['pengguna'] = [
        'id'    => (int) $baris['id'],
        'nama'  => $baris['name'],
        'email' => $baris['email'],
        'role'  => $baris['role'],
    ];
    return $_SESSION['pengguna'];
}

$aksi = $_GET['aksi'] ?? '';

switch ($aksi) {
    case 'saya':
        wajibMetode('GET');
        kirimJson(['pengguna' => penggunaLogin()]);
        break;

    case 'daftar':
        wajibMetode('POST');
        $input    = ambilInput();
        $nama     = teks($input['nama'] ?? '', 100);
        $email    = strtolower(teks($input['email'] ?? '', 150));
        $password = (string) ($input['password'] ?? '');

        if (mb_strlen($nama) < 2) {
            gagal('Nama minimal 2 karakter.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            gagal('Format email tidak valid.');
        }
        if (strlen($password) < 6) {
            gagal('Kata sandi minimal 6 karakter.');
        }

        $cek = db()->prepare('SELECT id FROM users WHERE email = ?');
        $cek->execute([$email]);
        if ($cek->fetch()) {
            gagal('Email sudah terdaftar.', 409);
        }

        $simpan = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        $simpan->execute([$nama, $email, password_hash($password, PASSWORD_DEFAULT)]);

        $pengguna = mulaiSesi([
            'id'    => db()->lastInsertId(),
            'name'  => $nama,
            'email' => $email,
            'role'  => 'customer',
        ]);
        kirimJson(['pengguna' => $pengguna], 201);
        break;

    case 'masuk':
        wajibMetode('POST');
        $input    = ambilInput();
        $email    = strtolower(teks($input['email'] ?? '', 150));
        $password = (string) ($input['password'] ?? '');

        $stmt = db()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            gagal('Email atau kata sandi salah.', 401);
        }

        kirimJson(['pengguna' => mulaiSesi($user)]);
        break;

    case 'keluar':
        wajibMetode('POST');
        $_SESSION = [];
        session_destroy();
        kirimJson(['ok' => true]);
        break;

    default:
        gagal('Aksi tidak dikenal.', 404);
}
