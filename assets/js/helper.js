const NOMOR_WA = "6281234567890";

const $ = (id) => document.getElementById(id);
const rupiah = (angka) => "Rp" + Number(angka).toLocaleString("id-ID");
const linkWhatsApp = (pesan = "") => "https://wa.me/" + NOMOR_WA + (pesan ? "?text=" + encodeURIComponent(pesan) : "");

function escapeHtml(teks) {
    const pengganti = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
    return String(teks).replace(/[&<>"']/g, (huruf) => pengganti[huruf]);
}

function bacaStorage(kunci, bawaan) {
    try {
        return JSON.parse(localStorage.getItem(kunci)) || bawaan;
    } catch {
        return bawaan;
    }
}

function simpanStorage(kunci, nilai) {
    try {
        localStorage.setItem(kunci, JSON.stringify(nilai));
    } catch {}
}

function tampilkanToast(pesan) {
    const el = $("toast");
    el.querySelector(".toast-body").textContent = pesan;
    bootstrap.Toast.getOrCreateInstance(el, { delay: 2200 }).show();
}

// potongan HTML yang dipakai di beberapa halaman
function cover(produk, teks = true) {
    const gaya = produk.gambar ? `background: url('${escapeHtml(produk.gambar)}') center/cover` : "";
    return `
        <div class="ratio" style="--bs-aspect-ratio: 133%">
            <div class="bg-secondary-subtle text-secondary small text-center rounded d-flex align-items-center justify-content-center" style="${gaya}">
                ${produk.gambar || !teks ? "" : "Belum ada cover"}
            </div>
        </div>`;
}

function baris(label, nilai, kelas = "") {
    return `<div class="d-flex justify-content-between gap-2 mb-2 ${kelas}"><span>${label}</span><span>${nilai}</span></div>`;
}

function isian(label, id, atribut = "") {
    return `
        <div class="mb-3">
            <label class="form-label" for="${id}">${label}</label>
            <input class="form-control" id="${id}" ${atribut}>
        </div>`;
}

function kontrolJumlah(jumlah, kurang, tambah) {
    return `
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-secondary btn-sm" ${kurang}>−</button>
            <span class="jumlah text-center" style="min-width: 1.5rem">${jumlah}</span>
            <button class="btn btn-outline-secondary btn-sm" ${tambah}>+</button>
        </div>`;
}

function pesanKosong(teks, tombol) {
    return `<div class="text-center text-secondary py-4">${teks}<br><a class="btn btn-dark mt-3" href="#beranda">${tombol}</a></div>`;
}
