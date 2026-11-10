const GRATIS_ONGKIR_MIN = 150000;
const TARIF_ONGKIR = { reguler: 15000, express: 30000 };

let keranjang = bacaStorage("rr_keranjang", {});

function itemKeranjang() {
    return Object.keys(keranjang)
        .map((id) => ({ produk: cariProduk(Number(id)), jumlah: keranjang[id] }))
        .filter((item) => item.produk);
}

const hitungSubtotal = () => itemKeranjang().reduce((total, { produk, jumlah }) => total + produk.harga * jumlah, 0);
const adaBukuFisik = () => itemKeranjang().some(({ produk }) => produk.tipe !== "ebook");
const hitungOngkir = (tarif) => (adaBukuFisik() && hitungSubtotal() < GRATIS_ONGKIR_MIN ? tarif : 0);
const batasJumlah = (produk) => (produk.tipe === "ebook" ? 99 : produk.stok);

// buang produk di keranjang yang sudah tidak ada di katalog
function bersihkanKeranjang() {
    Object.keys(keranjang).forEach((id) => {
        if (!cariProduk(Number(id))) delete keranjang[id];
    });
}

function tambahKeKeranjang(id, jumlah = 1) {
    const produk = cariProduk(id);
    if (!produk) return false;

    const jumlahBaru = (keranjang[id] || 0) + jumlah;
    if (jumlahBaru > batasJumlah(produk)) {
        tampilkanToast(`Stok ${produk.judul} tidak cukup (maks. ${batasJumlah(produk)})`);
        return false;
    }
    keranjang[id] = jumlahBaru;
    perbaruiKeranjang();
    tampilkanToast(produk.judul + " ditambahkan ke keranjang");
    return true;
}

function ubahJumlah(id, selisih) {
    const produk = cariProduk(id);
    const jumlahBaru = (keranjang[id] || 0) + selisih;

    if (jumlahBaru <= 0) {
        delete keranjang[id];
    } else if (jumlahBaru > batasJumlah(produk)) {
        tampilkanToast("Stok tidak cukup");
        return;
    } else {
        keranjang[id] = jumlahBaru;
    }
    perbaruiKeranjang();
}

function kosongkanKeranjang() {
    keranjang = {};
    perbaruiKeranjang();
}

function perbaruiKeranjang() {
    simpanStorage("rr_keranjang", keranjang);
    const jumlahBarang = itemKeranjang().reduce((n, item) => n + item.jumlah, 0);
    $("tombol-keranjang").textContent = `Keranjang (${jumlahBarang})`;

    if (location.hash === "#keranjang") tampilkanHalamanKeranjang();
}

function tampilkanHalamanKeranjang() {
    const daftar = itemKeranjang();
    const subtotal = hitungSubtotal();

    $("info-keranjang").textContent = daftar.length ? daftar.length + " jenis produk" : "";

    $("isi-keranjang").innerHTML = daftar.length
        ? daftar.map(({ produk, jumlah }) => `
            <div class="d-flex align-items-center gap-3 py-3 border-bottom">
                <a href="#produk/${produk.id}" style="width: 48px">${cover(produk, false)}</a>
                <div class="flex-grow-1">
                    <a class="fw-semibold text-reset text-decoration-none" href="#produk/${produk.id}">${escapeHtml(produk.judul)}</a>
                    <small class="d-block text-secondary">${escapeHtml(produk.penulis)}</small>
                    ${rupiah(produk.harga)}
                </div>
                ${kontrolJumlah(jumlah, `data-kurang="${produk.id}"`, `data-tambah="${produk.id}"`)}
                <button class="btn btn-outline-dark btn-sm" data-hapus="${produk.id}">Hapus</button>
            </div>`).join("")
        : pesanKosong("Keranjang masih kosong.", "Belanja sekarang");

    $("ringkasan-keranjang").innerHTML = `
        <h5>Ringkasan</h5>
        ${baris("Subtotal", rupiah(subtotal))}
        ${baris("Ongkir", subtotal >= GRATIS_ONGKIR_MIN ? "Gratis" : "Dihitung di checkout")}
        ${baris("Total", rupiah(subtotal), "fw-bold border-top pt-2")}
        ${daftar.length ? '<a class="btn btn-dark w-100" href="#checkout">Lanjut ke Checkout</a>' : ""}`;
}

$("isi-keranjang").addEventListener("click", (e) => {
    const { tambah, kurang, hapus } = e.target.closest("button")?.dataset || {};
    if (tambah) ubahJumlah(Number(tambah), 1);
    if (kurang) ubahJumlah(Number(kurang), -1);
    if (hapus) {
        delete keranjang[hapus];
        perbaruiKeranjang();
    }
});
