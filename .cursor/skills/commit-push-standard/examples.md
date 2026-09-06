# Commit Message Examples

Contoh commit message menggunakan standar format: **cerita/deskripsi singkat + bullet points perubahan & file terdampak**.

---

## Contoh: Commit Deskriptif dengan Body

### Fitur UI components (multi-file)

```
feat(ui): tambah DataTable, EmptyState, CurrencyDisplay, dan FormField components

Dibutuhkan kumpulan reusable components untuk standardisasi tampilan dan 
interaksi halaman CRUD pada sprint berikutnya. Tanpa standar ini, setiap 
developer berpotensi membuat implementasi sendiri yang inkonsisten. Komponen 
ini juga dioptimalkan agar responsif dan ringan di perangkat mobile.

Perubahan:
- Tambah komponen tabel utama dengan pagination dan sorting di `DataTable.vue`
- Tambah filter bar pencarian dan dropdown status di `DataTableFilter.vue`
- Tambah kontrol navigasi halaman dan limit baris di `DataTablePagination.vue`
- Definisikan TypeScript interfaces kolom dan schema data di `types.ts`
- Tambah komponen state kosong dengan aksi CTA di `EmptyState.vue`
- Tambah formatter tanggal dan mata uang di `DateDisplay.vue` dan `CurrencyDisplay.vue`
- Buat input wrapper dengan pesan validasi error di `FormField.vue`
- Buat composable format Rupiah dan deteksi offline di `useCurrency.ts` dan `useOnlineStatus.ts`

Refs: US-S0.6, US-S0.7
```

### Layout dan navigation

```
feat(layout): tambah sidebar navigation dan role-based layout untuk admin, guru, dan ortu

Setiap role pengguna membutuhkan navigasi yang berbeda sesuai hak aksesnya. 
Layout dirancang responsif dengan sidebar collapsible pada desktop dan bottom 
nav pada mobile (khusus ortu) untuk memastikan pengalaman penggunaan optimal 
di setiap perangkat.

Perubahan:
- Buat template responsif dasar dengan slot sidebar dan topbar di `AppLayout.vue`
- Implementasi navigasi khusus administrator di `AdminLayout.vue`
- Implementasi navigasi jadwal dan penilaian guru di `TeacherLayout.vue`
- Implementasi menu ringkas orang tua di `ParentLayout.vue`
- Buat sidebar collapsible dengan indikator menu aktif di `Sidebar.vue`
- Buat navigasi bawah mobile khusus peran orang tua di `MobileBottomNav.vue`

Tests: Manual test navigasi di setiap role, responsive behavior di viewport 360px dan 1280px
Refs: US-S0.4
```

### CI/CD pipeline

```
feat(ci): tambah GitHub Actions CI/CD pipeline dengan tenant isolation scan

Aplikasi multi-tenant membutuhkan jaminan data isolation agar tidak bocor 
antar tenant sebelum rilis ke production. Deployment manual sebelumnya lambat 
dan rentan human error, sehingga dibutuhkan pipeline otomatis untuk lint, 
test, verifikasi isolasi data, dan deployment.

Perubahan:
- Buat automated pipeline lint, type-check, dan test di `.github/workflows/ci.yml`
- Buat job deployment otomatis ke staging dan production di `.github/workflows/deploy.yml`
- Tambah script deteksi kebocoran tenant id di `scripts/check-tenant-isolation.sh`
- Konfigurasi multi-stage image build di `Dockerfile`

Tests: Pipeline diuji di feature branch (semua step passing), tenant isolation scan 0 violation
Refs: US-S0.5
```

### Fix bug

```
fix(auth): perbaiki redirect loop saat session expired di halaman dashboard

Ketika session pengguna kedaluwarsa saat berada di dashboard, request API 
mengembalikan error 401 tetapi frontend tidak menangani redirect dengan benar, 
sehingga pengguna stuck dalam refresh loop tanpa informasi jelas. Masalah ini 
dilaporkan oleh beberapa pengguna dalam seminggu terakhir.

Perubahan:
- Tangani error 401 dan trigger event logout di `resources/js/lib/axios.ts`
- Tambah guard pengecekan session aktif beserta flash message di `resources/js/router/middleware.ts`
- Simpan intended URL ke storage lokal untuk auto-redirect pasca login di `resources/js/stores/auth.ts`

Tests: Feature test simulasi expired session dengan assertRedirect, manual test session timeout 1 menit
```

### Refactor

```
refactor(payment): ekstrak payment logic dari controller ke PaymentService

PaymentController sebelumnya memiliki lebih dari 400 baris kode yang mencampur 
penanganan HTTP request dengan logika transaksi pembayaran. Logika ini perlu 
diekstrak agar dapat digunakan kembali pada fitur booking dan membership, serta 
mempermudah unit testing secara terisolasi.

Perubahan:
- Ekstrak proses pembayaran, verifikasi status, dan refund ke `PaymentService.php`
- Buat abstraksi gateway pembayaran di `PaymentGatewayInterface.php`
- Sederhanakan controller menjadi delegator request/response di `PaymentController.php`

Tests: Semua existing test passing tanpa modifikasi, tambah 12 unit test cases di PaymentServiceTest.php
```

### Database migration

```
feat(akademik): tambah tabel mata pelajaran dan jadwal kelas

Guru dan admin membutuhkan pengelolaan jadwal mengajar dan pembagian mata 
pelajaran untuk tahun ajaran baru. Struktur relasi database ini menjadi 
fondasi utama sebelum fitur absensi dan penginputan nilai dapat dibangun.

Perubahan:
- Buat migrasi tabel `subjects`, `class_schedules`, dan pivot `subject_teacher` di `database/migrations/2026_08_14_000001_create_academic_tables.php`
- Buat Eloquent model relasi dan factory di `Subject.php` dan `ClassSchedule.php`
- Tambah data master mata pelajaran kurikulum merdeka di `SubjectSeeder.php`
- Definisikan enum kategori mata pelajaran di `SubjectCategory.php`

Breaking: Perlu jalankan migrasi database via `php artisan migrate`
Tests: Feature test CRUD subjects, unit test validasi conflict jadwal kelas
Refs: US-S1.3
```

### Bug fix yang butuh migration baru (tetap `fix`, bukan `feat`)

```
fix(pos): perbaiki stok tidak berkurang saat order redemption poin

Order yang dibayar menggunakan redemption poin loyalty sebelumnya tidak 
mengurangi stok fisik produk karena kolom redemption belum tercatat di tabel 
orders. Akibatnya pengecekan payment method selalu fallback ke default dan 
melewati event pengurangan stok.

Perubahan:
- Tambah kolom `redemption_id` dan `redemption_amount` via `database/migrations/2026_08_14_000002_add_redemption_fields_to_orders_table.php`
- Daftarkan field redemption pada fillable dan casting di `Order.php`
- Perbaiki logika observer agar memicu pemotongan stok pada transaksi poin di `OrderObserver.php`

Tests: Feature test transaksi order dengan redemption poin, assert kuantitas stok produk berkurang
```

### Redesign UI murni, tanpa fitur baru (`style`, bukan `feat`)

```
style(produk): redesign card produk jadi lebih compact dan konsisten dengan brand

Card produk saat ini memiliki whitespace berlebih di mobile sehingga hanya 
memuat 2 item per scroll. Tampilan diperbarui agar lebih padat dan menyelaraskan 
palet warna dengan standar desain baru tanpa mengubah data maupun logika bisnis.

Perubahan:
- Sesuaikan padding, typography, dan badge stok di `ProductCard.vue`
- Rapikan alignment thumbnail gambar dan harga diskon di `ProductThumbnail.vue`
```

### Restrukturisasi komponen tanpa ubah behavior (`refactor`, bukan `feat`)

```
refactor(checkout): pecah CheckoutPage jadi CheckoutSummary dan CheckoutForm

Komponen CheckoutPage sebelumnya melebihi 500 baris karena menggabungkan 
input form pelanggan, opsi pengiriman, dan ringkasan total biaya. Pemisahan ini 
mempermudah pemeliharaan dan memungkinkan komponen ringkasan dipakai ulang.

Perubahan:
- Ubah `CheckoutPage.vue` menjadi layout wrapper yang ramping
- Ekstrak komponen kalkulasi biaya dan rincian item ke `CheckoutSummary.vue`
- Ekstrak input data penerima dan metode pembayaran ke `CheckoutForm.vue`

Tests: Existing feature test checkout tetap passing tanpa modifikasi
```

### Optimasi performa (`perf`)

```
perf(dashboard): eager load relasi order items untuk hilangkan N+1 query

Halaman dashboard sebelumnya mengeksekusi 200+ query terpisah saat memuat 100 
order karena relasi item diakses di loop tanpa eager loading. Hal ini menyebabkan 
waktu pemuatan mencapai 4-5 detik di jam operasional sibuk.

Perubahan:
- Tambahkan eager loading `items.product` pada query order di `DashboardController.php`
- Optimasi pemanggilan relasi pada serialisasi data di `OrderResource.php`

Tests: Query count via DB::listen berkurang dari 200+ query menjadi 3 query
```

### Update dependency (`chore`)

```
chore(deps): update laravel/framework ke v13.2 dan vue ke v3.5.x

Pembaruan framework diperlukan untuk mengatasi security advisory terkait 
validasi unggah file pada Laravel, serta memperbaiki bug reactivity pada 
Vue 3.5 yang berpengaruh ke composable mata uang.

Perubahan:
- Bump dependensi framework di `composer.json` dan lockfile di `composer.lock`
- Update library Vue dan dependensi frontend di `package.json` dan `pnpm-lock.yaml`

Tests: `composer ci:check` passing, manual smoke test alur checkout
```

### Dokumentasi (`docs`)

```
docs(adr): tambah ADR-030 alasan pnpm sebagai package manager eksklusif

Pencatatan keputusan arsitektur diperlukan setelah beberapa kontributor tanpa 
sengaja menggunakan npm yang menghasilkan lockfile ganda dan inkonsistensi 
resolusi paket dependensi di CI/CD.

Perubahan:
- Tambah dokumen keputusan arsitektur di `docs/dev-docs/decisions/030-pnpm-exclusive-package-manager.md`
- Daftarkan ADR baru ke indeks dokumentasi di `docs/dev-docs/decisions/README.md`
```

### Revert commit (`revert`)

```
revert: batalkan "feat(promo): tambah diskon otomatis per kategori"

Perhitungan diskon kategori ganda mengalami kesalahan logika yang menyebabkan 
beberapa produk mendapatkan potongan harga dua kali pada transaksi pagi ini. 
Fitur dibatalkan sementara agar transaksi kasir tidak terdampak hingga perbaikan tuntas.

Perubahan:
- Hapus penerapan diskon multi-kategori di `DiscountService.php`
- Kembalikan formula kalkulasi diskon semula di `CartService.php`

Refs: Revert dari commit a1b2c3d
```

---

## Contoh: Commit Trivial (Tanpa Body)

Hanya untuk perubahan yang BENAR-BENAR trivial:

```
fix(ui): perbaiki typo "pembayran" menjadi "pembayaran"
```

```
style(lint): format ulang file sesuai pint rules
```

```
chore(deps): bump yarn.lock setelah update
```

---

## Anti-Pattern (JANGAN Lakukan)

```
# Terlalu singkat — tidak ada konteks apa yang ditambah
feat(ui): tambah components

# Generic — update apa? kenapa?
update code

# WIP tanpa informasi
wip

# Menjelaskan APA yang sudah jelas dari diff, bukan MENGAPA
feat(user): tambah kolom phone_number di tabel users

# Summary ambigu — base apa? layout apa?
feat(layout): tambah base layout

# Body cuma repeat summary dan pakai daftar flat tanpa penjelasan per file
feat(auth): tambah login page

Tambah halaman login untuk user bisa masuk ke aplikasi.

Modified: LoginPage.vue, LoginController.php
(^ Body cuma repeat summary, tidak ada konteks masalah, dan daftar file flat)

# Body yang BAIK seharusnya:
feat(auth): tambah login page

User sekarang harus login dulu sebelum akses dashboard karena ada data 
sensitif siswa. Implementasi login dengan session-based auth dan remember 
me feature untuk UX yang lebih baik di perangkat bersama.

Perubahan:
- Buat form input email, password, dan remember me di `LoginPage.vue`
- Buat controller penanganan autentikasi dan rate limiting di `LoginController.php`
- Pasang middleware proteksi rute dashboard di `AuthMiddleware.php`

Tests: Feature test login flow, test remember me cookie

# Default ke "feat" padahal ini bug fix (fix tetap "fix" walau butuh file baru)
feat(order): tambah kolom redemption_id untuk perbaiki bug stok

# Seharusnya:
fix(order): perbaiki stok tidak berkurang saat order pakai redemption poin

# Default ke "feat" padahal ini cuma redesign visual tanpa fitur baru
feat(ui): redesign halaman produk

# Seharusnya (tergantung ada perubahan struktur komponen atau tidak):
style(produk): redesign card produk jadi lebih compact
# atau
refactor(produk): restrukturisasi ProductPage jadi komponen lebih kecil
```

---

## PR Description Contoh

```markdown
## Ringkasan
- Tambah reusable DataTable component dengan pagination dan responsive card view
- Tambah shared components: EmptyState, CurrencyDisplay, DateDisplay
- Tambah FormField component untuk standarisasi form layout

## Mengapa
Sprint berikutnya akan banyak halaman CRUD (siswa, guru, pembayaran).
Tanpa standar components, setiap halaman akan punya implementasi berbeda
yang menyulitkan maintenance. Components ini juga sudah dioptimasi untuk
budget Android device (Redmi 9-class) yang jadi target utama user parent.

## Perubahan Utama
- `DataTable.vue`: support server-side pagination, search filter, mobile card layout
- `useCurrency.ts`: format Rupiah dengan useCurrency composable
- `EmptyState.vue`: consistent empty state dengan icon, title, description, CTA
- `FormField.vue`: wrapper untuk label + input + error + hint

## Testing
- [x] Unit test useCurrency formatting
- [x] Manual test responsive DataTable di 360px viewport
- [ ] Integration test dengan real API data

## Catatan untuk Reviewer
- DataTable pagination menggunakan Inertia preserveState untuk UX
- CurrencyDisplay intentionally tidak pakai Intl.NumberFormat karena
  inconsistent di Android WebView lama
```
