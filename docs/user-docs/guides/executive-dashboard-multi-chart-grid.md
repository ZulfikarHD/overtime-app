# Panduan Pengguna: Grid Grafik Analitik Operasional Dashboard (E09-04)

Panduan ini ditujukan bagi **Kepala Departemen (Manager)**, **Supervisor**, dan **Team Leader (Foreman)** dalam membaca dan memanfaatkan 5 grafik analitik operasional pada halaman utama Dashboard (`/dashboard`) untuk briefing harian (Asakai / Morning Standup) dan evaluasi bulanan.

---

## 1. Lokasi & Akses Halaman

Grafik analitik operasional berada di bagian bawah halaman Dashboard (`/dashboard`), tepat di bawah grafik perbandingan antar seksi (_Section Burn Comparison_).

- **Akses Cepat**: Menu Utama Sidebar -> **Dashboard** (`/dashboard`).
- **Filter Periode**: Gunakan pemilih bulan/tanggal di bilah atas untuk meninjau bulan berjalan atau riwayat bulan-bulan sebelumnya.
- **Filter Lingkup**:
    - **Admin**: Dapat memilih semua departemen atau seksi tertentu.
    - **Manager**: Secara otomatis menampilkan data seksi-seksi di bawah departemen yang dipimpin.
    - **Team Leader**: Secara otomatis membatasi data pada seksi yang dipimpinnya.

---

## 2. Mengenal 5 Grafik Analitik

### A. Peringkat Lembur Karyawan (Top 10)

Grafik batang horizontal yang mengurutkan 10 karyawan dengan akumulasi jam lembur disetujui tertinggi pada bulan yang dipilih.

- **Fungsi Utama**: Mendeteksi dini risiko kelelahan (_operator fatigue_) dan ketimpangan alokasi pekerjaan lembur.
- **Garis Batas Soft (Soft Limit)**: Menampilkan batas aman kebijakan bulanan pabrik (standar: 80 jam/bulan atau sesuai kebijakan departemen).
- **Indikator Warna Bar**:
    - 🟢 **Hijau (Safe)**: Jam lembur masih dalam batas aman kebijakan.
    - 🟡 **Kuning/Amber (Warning)**: Jam lembur telah melampaui batas soft bulanan.
    - 🔴 **Merah (Danger)**: Jam lembur telah melampaui 115% dari batas soft bulanan.

---

### B. Distribusi Kategori Lembur (Category Donut)

Grafik lingkaran (_donut_) yang memperlihatkan pembagian total jam lembur ke dalam 4 kategori pekerjaan:

1. **Produksi (Production)** (Biru): Lembur untuk mengejar target volume unit harian.
2. **TPM / Maintenance** (Hijau Emerald): Perawatan preventif, perbaikan mesin breakdown, atau ganti mold/dies.
3. **CapEx Project** (Ungu): Proyek investasi modal, modifikasi line baru, atau instalasi mesin baru.
4. **Lain-lain (Others)** (Abu-abu): Aktivitas 5S, audit internal, atau pelatihan wajib.

> **Tips Interaksi Ergonomis**: Pada perangkat layar sentuh atau tablet lantai pabrik, klik tombol kategori di bawah grafik lingkaran untuk menyaring data atau menyorot porsi kategori terkait.

---

### C. Tren Jam Kerja 12 Bulan (12-Month Trend)

Grafik garis ganda yang melacak riwayat akumulasi lembur selama 12 bulan terakhir:

- **Garis Biru (HKN)**: Total jam lembur pada Hari Kerja Normal.
- **Garis Kuning/Amber (HLR)**: Total jam lembur pada Hari Libur Resmi atau akhir pekan.

**Tujuan Analisis**: Membantu manajemen mengevaluasi apakah ketergantungan pada lembur akhir pekan (HLR) menurun atau meningkat dari musim ke musim.

---

### D. Tren Indeks Kontribusi Harian (Daily Index Trend)

Grafik garis harian yang menampilkan intensitas lembur harian terhadap target kecepatan (_daily pacing target_).

- **Garis Biru Solid**: Indeks realisasi harian (%). Jika pada hari tertentu lembur melebihi target pacing harian, titik grafik akan berubah menjadi merah.
- **Garis Merah Putus-putus**: Batas kecepatan standar kebijakan (100% pacing).
- **Badge Rata-rata**: Menunjukkan rata-rata indeks kontribusi harian dari hari ke-1 hingga hari ini.

---

### E. Distribusi Hari: HKN vs HLR Mingguan (Day Type Breakdown)

Grafik batang terkelompok (_grouped bar_) yang membandingkan jam lembur hari kerja biasa (HKN) dan hari libur resmi (HLR) untuk setiap pekan (Pekan 1 sampai Pekan 5):

- **Pekan 1**: Tanggal 1–7.
- **Pekan 2**: Tanggal 8–14.
- **Pekan 3**: Tanggal 15–21.
- **Pekan 4**: Tanggal 22–28.
- **Pekan 5**: Tanggal 29 hingga akhir bulan.
- **Badge Rasio HLR**: Memperlihatkan persentase total jam lembur yang dihabiskan pada hari libur resmi.

---

## 3. Langkah-Langkah Tindakan dalam Morning Standup

1. **Cek Peringkat Top 10**:
    - Jika ada nama karyawan dengan bar kuning atau merah, periksa apakah karyawan tersebut dijadwalkan lembur lagi minggu ini. Alihkan jadwal lembur berikutnya ke rekan kerja lain di seksi yang sama.
2. **Evaluasi Donut Kategori**:
    - Jika porsi **TPM / Maintenance** membengkak secara tidak wajar, koordinasikan dengan tim _Maintenance Engineering_ untuk memeriksa anomali mesin atau _downtime_.
    - Jika ada porsi **CapEx Project**, pastikan kode proyek terhubung dengan benar untuk kebutuhan akuntansi aset.
3. **Pantau Lonjakan Harian**:
    - Amati grafik _Daily Index Trend_. Lonjakan tajam di atas 100% menandakan adanya lonjakan kerja mendadak (_unplanned overtime_) yang perlu diverifikasi penyebab dasarnya.

---

## 4. Pertanyaan Umum (FAQ)

- **Q: Mengapa grafik menampilkan pesan "Belum ada data"?**
    - **A**: Pesan tersebut muncul jika belum ada pengajuan lembur (SPKL) yang berstatus **APPROVED** pada bulan atau seksi yang Anda pilih. Begitu SPKL disetujui, grafik akan otomatis terisi.
- **Q: Apakah operator biasa dapat melihat grafik ini?**
    - **A**: Tidak. Operator biasa hanya dapat mengakses _Self-Service Dashboard_ (`/my/dashboard`) yang hanya menampilkan riwayat lembur pribadi mereka demi menjaga privasi dan ketenangan kerja.
