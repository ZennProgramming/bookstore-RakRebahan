async function panggilApi(url, opsi = {}) {
    const respons = await fetch(url, { headers: { "Content-Type": "application/json" }, ...opsi });
    const data = await respons.json().catch(() => null);

    if (!respons.ok) {
        const galat = new Error(data?.pesan || "Server tidak merespons. Pastikan Apache & MySQL sudah menyala.");
        galat.status = respons.status;
        throw galat;
    }
    return data;
}

const kirim = (data) => ({ method: "POST", body: JSON.stringify(data) });

const Api = {
    produk:         ()     => panggilApi("api/produk.php"),
    kategori:       ()     => panggilApi("api/kategori.php"),
    sayaSiapa:      ()     => panggilApi("api/auth.php?aksi=saya"),
    daftar:         (data) => panggilApi("api/auth.php?aksi=daftar", kirim(data)),
    masuk:          (data) => panggilApi("api/auth.php?aksi=masuk", kirim(data)),
    keluar:         ()     => panggilApi("api/auth.php?aksi=keluar", kirim({})),
    buatPesanan:    (data) => panggilApi("api/pesanan.php", kirim(data)),
    riwayatPesanan: ()     => panggilApi("api/pesanan.php"),
    kirimPesan:     (data) => panggilApi("api/pesan.php", kirim(data)),
};
