# Panduan Pengguna: Pelampiran Dokumen SPKL Fleksibel (Post-Shift Attachment)

## Ringkasan Fitur

Dalam operasional pabrik manufaktur otomotif, pengajuan lembur seringkali harus dikirim segera setelah shift selesai agar jam kerja dan estimasi biaya lembur dapat langsung tercatat. Namun, formulir fisik **Surat Perintah Kerja Lembur (SPKL)** seringkali masih dalam proses penandatanganan basah oleh supervisor atau manajer.

Sesuai **Kebijakan Fleksibel (Business Rule BR-05)**:

- Pengajuan lembur **TIDAK diblokir** meskipun dokumen fisik SPKL belum diunggah saat akhir shift.
- Team Leader diberikan tenggat waktu (grace period 2 hari kerja) untuk melampirkan foto atau scan dokumen SPKL fisik setelah shift berakhir.
- Dokumen SPKL disimpan di penyimpanan aman (_private storage_) dan hanya dapat diakses oleh pihak yang berwenang.

---

## 1. Peran Pengguna & Akses

| Peran               | Tindakan yang Dapat Dilakukan                                                                                                                 |
| :------------------ | :-------------------------------------------------------------------------------------------------------------------------------------------- |
| **Team Leader**     | Melampirkan foto/scan SPKL untuk pengajuan di seksinya, memasukkan nomor dokumen fisik, mengganti file jika ada revisi, dan mengunduh berkas. |
| **Section Manager** | Memeriksa berkas SPKL di departemennya, mengunduh berkas, dan memverifikasi dokumen (_Verifikasi SPKL_).                                      |
| **Administrator**   | Melampirkan, mengunduh, dan memverifikasi dokumen SPKL di seluruh area pabrik (_plant-wide_).                                                 |

---

## 2. Cara Melampirkan Dokumen SPKL

Terdapat tiga cara untuk membuka panel pengunggahan SPKL:

### Cara A: Langsung Setelah Mengirim Pengajuan Lembur

1. Setelah Anda menekan tombol **"Kirim Pengajuan Lembur"**, kartu konfirmasi sukses (_Post-Submission Card_) akan muncul di bagian atas formulir.
2. Pada banner SPKL berwarna kuning atau tombol aksi utama, klik tombol merah **"Lampirkan SPKL Sekarang"**.
3. Panel samping (_slide-in drawer_) **"Lampirkan Dokumen SPKL"** akan terbuka dari sisi kanan layar.

### Cara B: Dari Tabel Riwayat Pengajuan Lembur

1. Di bilah navigasi samping, klik menu **"Overtime Entry"** lalu pilih tab **"Riwayat Pengajuan"** (atau buka URL `/overtime/submissions`).
2. Cari baris pengajuan lembur yang bersangkutan.
3. Pada kolom **Aksi**, klik tombol **"Lampirkan SPKL"** (atau tombol berikon klip kertas).
4. Panel samping pengunggahan SPKL akan muncul.

### Cara C: Dari Modal Rincian Pengajuan (Detail Modal)

1. Buka rincian pengajuan dengan mengklik tombol **"Detail"** pada tabel riwayat.
2. Pada banner status SPKL di bagian atas, klik tombol **"Lampirkan SPKL"** atau **"Ganti Berkas"**.
3. Dialog detail akan otomatis ditutup dan panel samping pengunggahan SPKL akan terbuka (menghindari tumpukan popup ganda).

---

## 3. Langkah Pengunggahan pada Panel SPKL

1. **Periksa Informasi Pengajuan**:
    - Pastikan kode pengajuan (misal `OT-20260908-TRIM-001`), seksi, tanggal, dan total jam lembur sudah sesuai.
2. **Perhatikan Indikator Batas Waktu**:
    - Jika masih dalam batas waktu, indikator berwarna kuning akan menampilkan: _Batas Pengunggahan: DD/MM/YYYY (X hari tersisa)_.
    - Jika sudah melewati batas toleransi, indikator merah akan menampilkan peringatan: _⚠️ SPKL Terlambat_.
3. **Pilih Berkas Scan atau Foto**:
    - Tarik file ke kotak abu-abu bertuliskan _"Tarik file ke sini, atau klik untuk memilih file"_, atau klik kotak tersebut untuk membuka penjelajah file komputer atau kamera smartphone/tablet Anda.
    - **Format yang didukung**: Dokumen PDF (`.pdf`) atau gambar foto (`.png`, `.jpg`, `.jpeg`).
    - **Batas ukuran file**: Maksimal **3 MB**.
4. **Masukkan Nomor Dokumen Fisik SPKL (Opsional jika mengunggah file)**:
    - Ketikkan nomor registrasi fisik yang tertera pada lembar kertas SPKL, contoh: `SPKL/PROD/2026/IX/089`.
    - _Catatan:_ Jika scanner atau kamera Anda sedang mengalami kendala teknis, Anda tetap dapat memasukkan nomor dokumen fisik ini saja untuk mencatat kepatuhan awal.
5. **Simpan Pengunggahan**:
    - Klik tombol merah **"Unggah & Simpan SPKL"**.
    - Sistem akan mengunggah berkas secara aman ke penyimpanan privat terenkripsi.
    - Status SPKL akan otomatis berubah dari `Belum Dilampirkan` menjadi **`Terlampir` (Attached)**.

---

## 4. Cara Mengunduh Berkas SPKL

1. Buka menu **"Riwayat Pengajuan"** (`/overtime/submissions`).
2. Klik tombol **"Detail"** pada baris pengajuan lembur yang memiliki dokumen terlampir.
3. Pada banner status SPKL berwarna hijau, klik tombol **"Unduh SPKL"**.
4. Berkas SPKL akan diunduh secara aman menggunakan tautan bertanda tangan digital (_temporary signed URL_) yang berlaku selama 15 menit.

---

## 5. Cara Memverifikasi Dokumen SPKL (Khusus Manajer)

1. Masuk ke sistem menggunakan akun **Manajer** atau **Administrator**.
2. Buka tabel **Riwayat Pengajuan** dan pilih pengajuan lembur yang berstatus SPKL **`Terlampir`**.
3. Klik tombol **"Detail"** untuk membuka modal rincian.
4. Klik tombol **"Unduh SPKL"** terlebih dahulu untuk memeriksa kelengkapan tanda tangan basah supervisor pada foto/scan.
5. Jika dokumen telah sesuai, klik tombol hijau **"Verifikasi SPKL"**.
6. Status dokumen akan langsung diperbarui menjadi **`✅ SPKL: Terverifikasi oleh Manajer`**.

---

## 6. Pertanyaan Umum & Pemecahan Masalah (FAQ)

### T: Mengapa file foto saya ditolak dengan pesan error "Ukuran file maksimal 3 MB"?

**J:** Kamera smartphone modern sering menghasilkan foto berukuran 5–10 MB. Gunakan fitur kompresi foto bawaan galeri ponsel atau ubah resolusi kamera ke standar dokumen sebelum mengambil foto formulir SPKL.

### T: Bagaimana jika ada revisi pada formulir SPKL yang sudah saya lampirkan?

**J:** Anda dapat mengganti berkas kapan saja selama dokumen belum diverifikasi. Cukup buka kembali panel SPKL melalui tombol **"Ganti Berkas"**, lalu unggah berkas yang baru. Sistem akan secara otomatis menghapus berkas lama dari penyimpanan untuk menjaga kapasitas server.

### T: Apa yang terjadi jika SPKL berstatus "Terlambat"?

**J:** Pengajuan lembur tetap diproses dan jam lembur tetap tercatat, namun status badge pada riwayat pengajuan akan bertanda merah _⚠️ SPKL Terlambat_. Notifikasi kepatuhan akan dilaporkan kepada Manajer Departemen untuk ditindaklanjuti.
