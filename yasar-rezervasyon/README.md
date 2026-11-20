# Yaşar Üniversitesi Tesis Rezervasyon Sistemi

Öğrenci, idari ve akademik personel, antrenör ve mezunların üniversite tesislerini takvim üzerinden rezerve ettiği,
SKS'nin onayladığı web uygulaması. PHP 8.3 + Laravel 13 + MySQL 8. Mobil uyumlu, PWA, Türkçe/İngilizce.

## Dokümanlar

| | |
|---|---|
| [01 – Gereksinimler](docs/01-gereksinimler.md) | Kesinleşmiş iş kuralları |
| [02 – Veritabanı](docs/02-veritabani.md) | ER diyagramı ve tablolar |
| [03 – Ekran akışları](docs/03-ekran-akislari.md) | Roller, menüler, ekranlar |
| [04 – ISO 27001](docs/04-iso27001.md) | Kontrol eşlemesi ve üretim kontrol listesi |

## Hızlı başlangıç (geliştirme)

Gereksinimler: PHP 8.3+ (`pdo_sqlite`, `mbstring`, `intl`, `gd`, `zip`), Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

http://localhost:8000 — geliştirme modunda sahte LDAP kullanılır, tüm şifreler `test`:

| Kullanıcı | Rol |
|---|---|
| `ogrenci`, `ogrenci2`, `ogrenci3` | Öğrenci |
| `akademik` | Akademik personel |
| `idari` | İdari personel + Spor Salonu onaycısı |
| `antrenor` | Antrenör (2 takım) |
| `gorevli` | Salon görevlisi |
| `sksadmin` | SKS Admin |
| `mezun` | Mezun |

Zamanlanmış görevleri elle denemek için:

```bash
php artisan schedule:work
```

## Testler

```bash
php artisan test
```

İş kuralları (pota/salon çakışması, limitler, roller, tatil, kapatma, ekipman stoku), onay akışı, gelmeme yaptırımı,
sabit bloklar ve güvenlik (hız sınırı, başlıklar, KVKK, şifreleme, audit zinciri) testleri.

## Üretim kurulumu

1. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, MySQL (`DB_CONNECTION=mysql`), `LDAP_DRIVER=native` ve LDAP ayarları,
   e-posta (SMTP / Microsoft 365), `SESSION_SECURE_COOKIE=true`.
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan migrate --force` ve `php artisan db:seed --class=HolidaySeeder --force`
4. `php artisan config:cache route:cache view:cache`
5. Cron: `* * * * * cd /var/www/rezervasyon && php artisan schedule:run >> /dev/null 2>&1`
6. Kuyruk işçisi (e-postalar): `php artisan queue:work --tries=3` (supervisor/systemd)
7. İlk SKS Admin'i atamak için kişi bir kez giriş yaptıktan sonra:

```bash
php artisan tinker --execute="App\Models\User::where('username','KULLANICI')->first()->forceFill(['is_sks_admin'=>true])->save();"
```

Ayrıntılı güvenlik kontrol listesi: [docs/04-iso27001.md](docs/04-iso27001.md#3-üretim-kurulum-kontrol-listesi).

## Zamanlanmış görevler

| Komut | Sıklık | İş |
|---|---|---|
| `reservations:expire` | Her dakika | Başlangıca kadar onaylanmayan talepleri iptal eder |
| `reservations:mark-no-shows` | Her dakika | Check-in yapılmayanları "gelmedi" işaretler, yaptırımı değerlendirir |
| `reservations:remind-approvers` | 5 dk | Onaycılara hatırlatma |
| `reservations:remind-users` | 5 dk | Kullanıcılara 1 gün / 2 saat önce hatırlatma |
| `players:health-warnings` | 08:30 | Sağlık raporu uyarısı |
| `retention:purge` | 03:15 | 5 yılı dolan verileri anonimleştirir |
| `audit:verify` | 03:45 | İşlem kaydı zincirini doğrular |

## Klasör yapısı

```
app/Services/ReservationService.php   Rezervasyon kuralları (talep, onay, red, iptal, check-in, katılım)
app/Services/Availability.php         Çalışma saati, tatil, kapatma, birim ve ekipman uygunluğu
app/Services/BlockService.php         SKS sabit blokları ve kapatmalar
app/Services/SanctionService.php      Gelmeme / geç iptal yaptırımı
app/Services/Audit.php                Hash zincirli işlem kaydı
app/Services/Ldap/                    LDAP (native) ve geliştirme için sahte dizin
app/Services/ScheduledTasks.php       Zamanlanmış görevler
app/Http/Middleware/                  Güvenlik başlıkları, KVKK, rol, dil, aktif kullanıcı
resources/views/                      Blade ekranları (inline script/stil yok — CSP)
public/css/app.css, public/js/app.js  Arayüz
lang/en.json                          İngilizce çeviriler (kaynak dil Türkçe)
```
