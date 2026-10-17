# TODO — Production Readiness Improvements

Temuan dari audit `ttpryg/inventory-engine` (branch `develop`, commit `bf3db40`).
Diurutkan berdasarkan prioritas: P0 = bloker production, P1 = serius, P2 = penting.

---

## P0 — Bloker (wajib sebelum production)

### [x] 1. Tambahkan transaksi DB + row locking (anti overselling)
- [x] `InventoryService`: bungkus operasi stock-mutating dalam transaksi
      (`reserveStock`, `commitReservation`, `releaseReservation`,
      `adjustStock`, `transferStock`, `initializeStock`)
- [x] `PdoInventoryStockRepository`: tambah `findByIdForUpdate()` /
      `findByLocationAndProductForUpdate()` (`SELECT ... FOR UPDATE`,
      otomatis di-skip untuk SQLite; implementasi memory = find biasa)
- [ ] Pendekatan alternatif: optimistic locking (kolom `version` pada
      `inventory_stocks`) — `UPDATE ... WHERE version = :old`
      — tidak dipakai; pessimistic locking (`SELECT ... FOR UPDATE`) dipilih
- [x] Pastikan seluruh write (stock + reservation + movement) dalam satu
      transaksi; rollback bila salah satu gagal
- [x] Catatan: in-memory driver tidak butuh locking, tapi API harus tetap
      sama agar service tidak branch logic
      (via `TransactionManagerInterface` + `NullTransactionManager`)

### [x] 2. Proses expiry reservation
- [x] Tambah method `expireReservation(string $reservationId)` di
      `InventoryService` (set status `EXPIRED`, lepaskan reserved qty,
      catat movement `release`, dispatch event `StockExpiredEvent`)
- [x] Tambah `processExpiredReservations(): int` yang memanggil
      `StockReservationRepositoryInterface::findExpiredActiveReservations()`
      (dipanggil scheduler/cron milik konsumen)
- [x] `commitReservation()`: tolak reservation yang sudah lewat `expires_at`
      (cek `$reservation->isExpired()`) — jangan hanya cek status ACTIVE

### [x] 3. Buat operasi multi-langkah atomik
- [x] `transferStock()`: dua kali `save()` (source + target) harus dalam satu
      transaksi — saat ini gagal di tengah = stok terpotong tanpa masuk target
- [x] `reserveStock()`: stock save + reservation save + movement record dalam
      satu transaksi
- [x] `initializeStock()`: stock save + movement record dalam satu transaksi
- [x] Evaluasi `PdoInventoryStockRepository::save()` (find-then-insert/update)
      → race pada insert ID sama; pertimbangkan `INSERT ... ON DUPLICATE KEY
      UPDATE` (MySQL) / upsert yang sesuai driver
      (selesai: `ON CONFLICT(id) DO UPDATE` untuk SQLite,
      `ON DUPLICATE KEY UPDATE` untuk MySQL/MariaDB)
- [x] Bug terkait yang ikut diperbaiki: `PdoStockReservationRepository::save()`
      & `PdoInventoryLocationRepository::save()` mengirim param `created_at`
      berlebih pada jalur UPDATE → `PDOException: column index out of range`
      (jalur UPDATE tidak pernah teruji sebelumnya)

---

## P1 — Serius

### [ ] 4. Perbaiki schema agar portable (MySQL/MariaDB)
- [ ] `database/schema.sql`: `CREATE INDEX IF NOT EXISTS` hanya valid di
      SQLite — **gagal di MySQL/MariaDB** (padahal didukung & diclaim)
- [ ] Pisahkan file schema per driver: `schema.sqlite.sql`,
      `schema.mysql.sql` (MySQL 8 punya `CREATE INDEX IF NOT EXISTS`,
      MariaDB tidak — cek versi target)
- [ ] Tambah service MySQL/MariaDB di `docker-compose.yml` untuk test
      integrasi nyata (saat ini test integrasi hanya SQLite in-memory)
- [ ] Tambah index untuk `stock_reservations (status, expires_at)` &
      `(reference_type, reference_id)` dan `stock_movements
      (inventory_stock_id, created_at)` — kueri expiry & audit akan lambat
      tanpa index di volume besar

### [ ] 5. Validasi bisnis di entity
- [ ] `InventoryStock::adjust()`: tolak hasil `quantityOnHand < 0`
      (throw exception)
- [ ] `InventoryStock::reserve()`: validasi `$quantity > 0`
- [ ] `InventoryStock::commit()` / `release()`: validasi `$quantity > 0` dan
      jangan melebihi `quantityReserved` (saat ini `max(0, …)` menutupi
      inkonsistensi diam-diam — pertimbangkan throw instead)
- [ ] `InventoryService::reserveStock()`: validasi `$quantity > 0` sebelum
      cek ketersediaan
- [ ] Review apakah `getQuantityAvailable()` masih perlu `max(0, …)` setelah
      guard di atas ada

### [ ] 6. Exception hierarchy
- [ ] Buat `src/Exceptions/` dengan base `InventoryException extends
      RuntimeException` lalu:
  - [ ] `InsufficientStockException` (bawa requested/available)
  - [ ] `StockNotFoundException`
  - [ ] `ReservationNotFoundException`
  - [ ] `ReservationNotActiveException` / `ReservationExpiredException`
  - [ ] `InvalidQuantityException`
- [ ] Ganti semua `throw new RuntimeException(...)` di `InventoryService`
      dengan exception spesifik agar konsumen bisa catch presisi

### [ ] 7. Ganti `uniqid()` dengan UUID
- [ ] `'mov-'.uniqid()` & `'stk-'.uniqid()` di `InventoryService` (8 tempat)
      → kolisi pada concurrency tinggi
- [ ] Gunakan UUIDv4: `bin2hex(random_bytes(16))` atau tambah `ramsey/uuid`
      sebagai dependency runtime
- [ ] Pertimbangkan: biarkan konsumen supply ID (seperti param `id` lain) atau
      auto-generate bila null — konsisten dengan `createLocation`/
      `initializeStock` yang mewajibkan ID

---

## P2 — Penting

### [ ] 8. Perluas test suite
- [ ] Unit test `releaseReservation()` (happy path + non-active = silent no-op)
- [ ] Unit test `adjustStock()` (positif/negatif/batas stok 0)
- [ ] Unit test expiry: `processExpiredReservations()` + commit ditolak saat
      expired
- [ ] Unit test `LowStockAlertEvent` terdispatch saat available <= threshold
- [ ] Unit test jalur error: stock tidak ada, reservasi tidak ada,
      quantity <= 0, insufficient stock
- [ ] Unit test `transferStock()` ke lokasi tujuan yang belum ada stoknya
      (auto-initialize) + kegagalan di tengah (rollback)
- [ ] Integration test PDO driver: `PdoStockReservationRepository` &
      `PdoStockMovementRepository` (saat ini baru Location & Stock yang
      teruji)
- [ ] Test konkurensi (opsional tapi disarankan): dua proses reserve bersamaan
      tidak boleh oversell
- [ ] Aktifkan coverage di CI (`--coverage-clover` / codecov) — saat ini
      `coverage: none`

### [ ] 9. Static analysis & CI checks
- [ ] Tambah `phpstan/phpstan` (target level tertinggi yang lolos) ke
      `require-dev`
- [ ] Tambah job CI: `vendor/bin/phpstan analyse`
- [ ] Tambah job CI: `composer fix:check` (Pint `--test` + Rector `--dry-run`)
      — script sudah ada di composer.json tapi tidak dijalankan di CI
- [ ] Tambah script composer `analyse` untuk lokal

### [ ] 10. Perbaiki dokumentasi
- [ ] README contoh `transferStock()`: named argument salah —
      `sourceLocationType:` / `sourceLocationId:` / `targetLocationType:` /
      `targetLocationId:` → signature aktual: `fromLocationType:` /
      `fromLocationId:` / `toLocationType:` / `toLocationId:` (contoh saat ini
      error `Unknown named parameter`)
- [ ] README: dokumentasikan alur expiry + contoh scheduler
      (`processExpiredReservations`)
- [ ] README: klaim "preventing overselling across concurrent checkout
      sessions" hanya boleh tetap setelah P0 #1 selesai (saat ini belum benar)
- [ ] Tambah CHANGELOG.md (ikut Keep a Changelog) sebelum rilis berikutnya

---

## Reference

- Test lokal via Docker:
  `docker run --rm -v "$PWD:/var/www/html" -w /var/www/html inventory-engine-php:8.1 vendor/bin/phpunit`
- Status saat audit: `OK (4 tests, 17 assertions)` — lolos, tapi cakupan sempit
- Schema: `database/schema.sql`
- Kontrak repo (termasuk `findExpiredActiveReservations()` yang belum
  terpakai): `src/Contracts/StockReservationRepositoryInterface.php`
