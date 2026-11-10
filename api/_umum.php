<?php
require_once __DIR__ . '/../config/koneksi.php';

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

set_exception_handler(function (Throwable $e) {
    error_log((string) $e);
    $pesan = 'Terjadi kesalahan di server. Coba lagi nanti.';
    if (MODE_DEBUG) {
        $pesan .= ' [' . $e->getMessage() . ']';
    }
    kirimJson(['pesan' => $pesan], 500);
});

function kirimJson($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function gagal(string $pesan, int $status = 400): void
{
    kirimJson(['pesan' => $pesan], $status);
}

function wajibMetode(string $metode): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $metode) {
        gagal('Metode request tidak diizinkan.', 405);
    }
}

function ambilInput(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function teks($nilai, int $maks = 255): string
{
    if (!is_string($nilai)) {
        return '';
    }
    return mb_substr(trim($nilai), 0, $maks);
}

function penggunaLogin(): ?array
{
    return $_SESSION['pengguna'] ?? null;
}

function wajibLogin(): array
{
    $pengguna = penggunaLogin();
    if ($pengguna === null) {
        gagal('Silakan masuk terlebih dahulu.', 401);
    }
    return $pengguna;
}
