let pengguna = null;
let tujuanSetelahLogin = "";

const MENU_ADMIN = { dashboard: "Dashboard", buku: "Kelola Buku", kategori: "Kategori", pesanan: "Status Pesanan", pesan: "Pesan" };

async function muatPengguna() {
    try {
        pengguna = (await Api.sayaSiapa()).pengguna;
    } catch {
        pengguna = null;
    }
    tampilkanAkun();
}

function tampilkanAkun() {
    const admin = pengguna?.role === "admin";

    $("bar-admin").classList.toggle("d-none", !admin);
    $("bar-admin").innerHTML = admin ? `
        <div class="container d-flex flex-wrap gap-2 gap-md-4 py-2">
            <b class="text-white">Menu Admin</b>
            ${Object.entries(MENU_ADMIN).map(([kode, label]) =>
                `<a class="text-white-50 text-decoration-none" href="admin/?hal=${kode}">${label}</a>`).join("")}
        </div>` : "";

    if (!pengguna) {
        $("area-akun").innerHTML = `
            <a class="text-dark text-decoration-none" href="#login">Masuk</a>
            <a class="btn btn-dark btn-sm" href="#daftar">Daftar</a>`;
        return;
    }

    $("area-akun").innerHTML = `
        <span>Halo, ${escapeHtml(pengguna.nama.split(" ")[0])}</span>
        <a class="text-dark text-decoration-none" href="#pesanan">Pesanan Saya</a>
        ${admin ? '<a class="text-dark text-decoration-none" href="admin/">Admin</a>' : ""}
        <button class="btn btn-link btn-sm p-0 text-dark" id="tombol-keluar">Keluar</button>`;
}

function setelahBerhasilMasuk(data) {
    pengguna = data.pengguna;
    tampilkanAkun();

    if (pengguna.role === "admin") {
        location.href = "admin/";
        return;
    }
    location.hash = tujuanSetelahLogin || "beranda";
    tujuanSetelahLogin = "";
}

function pasangEventAkun() {
    $("area-akun").addEventListener("click", async (e) => {
        if (e.target.id !== "tombol-keluar") return;
        await Api.keluar().catch(() => {});
        pengguna = null;
        tampilkanAkun();
        location.hash = "beranda";
    });

    $("form-login").addEventListener("submit", async (e) => {
        e.preventDefault();
        $("login-galat").textContent = "";
        try {
            const data = await Api.masuk({
                email: $("login-email").value.trim(),
                password: $("login-sandi").value,
            });
            e.target.reset();
            setelahBerhasilMasuk(data);
        } catch (err) {
            $("login-galat").textContent = err.message;
        }
    });

    $("form-daftar").addEventListener("submit", async (e) => {
        e.preventDefault();
        const galat = $("daftar-galat");
        const sandi = $("daftar-sandi").value;
        galat.textContent = "";

        if (sandi.length < 6) {
            galat.textContent = "Kata sandi minimal 6 karakter.";
            return;
        }
        if (sandi !== $("daftar-sandi2").value) {
            galat.textContent = "Konfirmasi kata sandi tidak sama.";
            return;
        }

        try {
            const data = await Api.daftar({
                nama: $("daftar-nama").value.trim(),
                email: $("daftar-email").value.trim(),
                password: sandi,
            });
            e.target.reset();
            setelahBerhasilMasuk(data);
        } catch (err) {
            galat.textContent = err.message;
        }
    });
}
