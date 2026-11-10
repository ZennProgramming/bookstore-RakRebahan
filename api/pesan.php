<?php
require_once __DIR__ . '/_umum.php';
wajibMetode('POST');

$terakhir = $_SESSION['pesan_terakhir'] ?? 0;
if (time() - $terakhir < 20) {
    gagal('Tunggu sebentar sebelum mengirim pesan lagi.', 429);
}

$input = ambilInput();
$nama  = teks($input['nama'] ?? '', 100);
$isi   = teks($input['pesan'] ?? '', 2000);

if ($nama === '' || mb_strlen($isi) < 5) {
    gagal('Isi nama dan pesan (minimal 5 karakter).');
}

db()->prepare('INSERT INTO messages (name, message) VALUES (?, ?)')->execute([$nama, $isi]);
$_SESSION['pesan_terakhir'] = time();

kirimJson(['ok' => true], 201);
