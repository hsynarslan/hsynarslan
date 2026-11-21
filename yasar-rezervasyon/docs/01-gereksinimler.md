# Yaşar Üniversitesi Tesis Rezervasyon Sistemi — Gereksinim Dokümanı

| | |
|---|---|
| **Sürüm** | 1.1 (kod incelemesi düzeltmeleri, bkz. [05-inceleme-yaniti.md](05-inceleme-yaniti.md)) |
| **Tarih** | 20.11.2026 |
| **Durum** | Kesinleşti (açık konular bölüm 14'te) |
| **Kapsam** | Spor salonu, dans salonu, çalışma/toplantı odaları ve benzeri tesislerin rezervasyonu |
| **Bilgi güvenliği** | ISO/IEC 27001:2022 Ek A kontrolleri gözetilerek tasarlanmıştır (bkz. [04-iso27001.md](04-iso27001.md)) |

---

## 1. Amaç

Öğrencilerin, idari ve akademik personelin, antrenörlerin ve mezunların üniversite tesislerini takvim üzerinden
görüp rezervasyon talebi oluşturabilmesi; taleplerin SKS tarafından onaylanması; katılımın, ekipmanın ve kuralların
tek bir sistemde takip edilmesi.

## 2. Roller

### 2.1 LDAP'tan otomatik gelen temel roller

| Rol | Kod | LDAP kaynağı |
|---|---|---|
| Öğrenci | `student` | Öğrenci OU'su |
| İdari Personel | `admin_staff` | İdari personel OU'su |
| Akademik Personel | `academic` | Akademik personel OU'su |
| Mezun | `alumni` | Mezunlar için açılacak ayrı OU |

Kullanıcının DN'i hiçbir tanımlı OU'ya uymuyorsa giriş reddedilir.
"Personel" ifadesi bu dokümanda **İdari + Akademik** personeli kapsar.

### 2.2 Sistem içinde atanan roller (SKS Admin atar)

| Rol | Yetki |
|---|---|
| **Antrenör** | Takım ve tam salon rezervasyonu yapar, kendi takımlarının oyuncularını yönetir. Günlük/haftalık/süre limitlerinden ve gelmeme yaptırımından muaftır. |
| **Tesis Onaycısı** | Kendisine atanmış tesislerin taleplerini görür, onaylar/reddeder; o tesislerin raporlarını görür. Bir tesise birden fazla onaycı atanabilir. |
| **SKS Admin** | Bütün tesislerin taleplerini onaylar; tesis, kural, onaycı, takım, ekipman, kapatma, tatil, sabit blok, ayar, KVKK metni ve kullanıcı yönetimi; bütün raporlar ve işlem kaydı. |
| **Salon Görevlisi** | **Atandığı tesislerin** günlük listesini görür; "geldi / gelmedi" işaretler; ekipman teslim eder ve teslim alır; giriş tabletini açar. Onay yetkisi yoktur. |

Bir kişide birden fazla rol bulunabilir (ör. akademik personel + tesis onaycısı).

## 3. Giriş ve oturum

- Giriş **üniversite LDAP** hesabıyla yapılır; sistemde şifre tutulmaz.
- LDAP bağlantısı **LDAPS veya StartTLS** ile şifrelenir.
- İlk girişte kullanıcı kaydı LDAP bilgileriyle (ad, e-posta, öğrenci/personel no, bölüm) otomatik oluşturulur; her girişte güncellenir.
- Hatalı giriş denemeleri kullanıcı adı + IP bazında sınırlanır (5 deneme / dakika).
- Oturum 30 dakika işlem yapılmazsa sona erer; çerezler `Secure`, `HttpOnly`, `SameSite=Lax`.
- İlk girişte **KVKK aydınlatma metni** onayı alınmadan sistem kullanılamaz; metin güncellenirse tekrar onay istenir.

## 4. Tesis modeli

```
Tesis (ör. Spor Salonu)
 ├── Alt birimler: Pota A, Pota B
 └── Aktivite türleri:
      ├── Voleybol             → 2 birim (tüm salon)    — Antrenör, Personel
      ├── Basketbol (Bireysel) → 1 birim (tek pota)     — herkes
      └── Basketbol (Takım)    → 2 birim (iki pota)     — Antrenör, Personel, takım seçimi zorunlu
```

Tek alanlı odalar (çalışma odası, dans salonu) tek alt birimli tesis olarak tanımlanır.
Çakışma kontrolü **alt birim** düzeyinde yapılır.

### 4.1 Tesis ayarları (SKS)

| Ayar | Açıklama |
|---|---|
| Ad (TR/EN), açıklama (TR/EN) | İki dilde |
| Slot süresi | **30** veya **60** dakika |
| Kapasite | En fazla kişi sayısı |
| Grup rezervasyonuna açık mı | Kapalıysa yalnızca bireysel rezervasyon |
| Katılımcı listesi zorunlu mu | Zorunluysa katılımcılar LDAP'tan seçilir; değilse sadece kişi sayısı girilir |
| İptal süresi (saat) | Bu süreden sonra yapılan iptal **geç iptal** sayılır |
| Kullanabilecek roller | Öğrenci, İdari, Akademik, Antrenör, Mezun |
| Çalışma saatleri | Haftanın her günü için açılış–kapanış ya da "kapalı" |
| Onaycılar | Bir veya birden fazla kullanıcı |
| Kurallar | Rol bazında limit değerleri (bkz. bölüm 5) |
| QR kodu | Tesis girişine asılacak check-in kodu; yeniden üretilebilir |

### 4.2 Aktivite türü ayarları

Ad (TR/EN), gereken birim sayısı, takım aktivitesi mi, kullanabilecek roller.

## 5. Rezervasyon kuralları

### 5.1 Varsayılan değerler

| Kural | Varsayılan | Antrenör |
|---|---|---|
| Tek rezervasyonun en uzun süresi | 2 saat | Muaf |
| Tesis başına günlük rezervasyon | 1 | Muaf |
| Tesis başına haftalık rezervasyon | 3 | Muaf |
| En ileri tarih | 14 gün | Rol kuralıyla değiştirilebilir |
| En geç talep zamanı | Başlangıçtan 2 saat önce | Rol kuralıyla değiştirilebilir |

Değerler önce **tesis + rol** kuralına, yoksa **tesis varsayılanına**, yoksa **sistem varsayılanına** bakılarak belirlenir.

### 5.2 Talep sırasında yapılan kontroller (sırasıyla)

1. Kullanıcının aktif yaptırımı var mı (antrenör muaf)
2. Tesis aktif mi, kullanıcının rolü tesise ve aktiviteye izinli mi
3. Takım aktivitesinde takım seçildi mi; takım adına **yalnızca o takımın antrenörü veya SKS Admin** rezervasyon yapabilir
4. Başlangıç ve süre slot sınırlarına uyuyor mu
5. Tesisin o günkü çalışma saatleri içinde mi
6. Resmi tatil veya kapatma ile çakışıyor mu
7. En geç talep zamanı ve en ileri tarih
8. En uzun süre
9. Kapasite; grup kapalıysa kişi sayısı 1
10. Katılımcı listesi zorunluysa katılımcılar girilmiş mi; katılımcılar tesise izinli rollerde mi
11. Günlük ve haftalık limit — **rezervasyonu yapan ve her katılımcı için** (katılımcı olmak da limitten düşer). Kesin kontrol, ilgili kullanıcı kayıtları kilitlenerek yapılır; eş zamanlı isteklerle aşılamaz
12. Yeterli sayıda boş alt birim var mı (beklemede ve onaylı rezervasyonlar slotu kilitler)
13. İstenen ekipman o saat aralığında stokta var mı

### 5.3 Çakışma kuralı

- **İlk talep eden alır.** Beklemedeki talep slotu kilitler; başka kimse o birime talep oluşturamaz.
- Roller arasında öncelik yoktur. Tek istisna SKS'dir: sabit blok ve kapatmalar mevcut rezervasyonları gerekçeyle iptal edebilir.

## 6. Rezervasyon yaşam döngüsü

```
             ┌──────────── kullanıcı geri çeker ───────────┐
             │                                              ▼
[Talep] → BEKLEMEDE ──onay──► ONAYLANDI ──başlangıç+15dk──► katılım: GELDİ / GELMEDİ
             │   │                 │
             │   └──red (gerekçe)──┴──► REDDEDİLDİ
             │                     └──iptal (kullanıcı/SKS)──► İPTAL (süre geçtiyse "geç iptal")
             └── başlangıç saatine kadar onaylanmadı ──► SÜRESİ DOLDU (otomatik iptal)
```

- **Otomatik onay yoktur.**
- Talep oluşunca tesisin onaycılarına ve SKS Admin'lere anında e-posta gider.
- Talep **4 saat** içinde ya da başlangıca **2 saat** kala (hangisi önce gelirse) hâlâ bekliyorsa **hatırlatma** e-postası gider.
- Başlangıç saatine kadar onaylanmayan talep **süresi doldu** durumuna geçer; kullanıcıya e-posta gider.
- Beklemedeki talep her zaman geri çekilebilir.

## 7. Sabit bloklar ve kapatmalar

- **Sabit (tekrarlayan) bloklar**: Yalnızca SKS girer. Mevcut bir sabit blokla çakışan yeni blok oluşturulamaz (önce eskisi kaldırılır). Tesis, birimler (veya aktivite), takım (isteğe bağlı), başlık, haftanın günleri,
  saat aralığı, başlangıç–bitiş tarihi. Onay gerektirmez. Çakışan rezervasyonlar önizlemede listelenir; SKS bunları gerekçeyle iptal eder,
  kullanıcılara e-posta gider. Tatil ve kapatma günleri atlanır.
- **Tek seferlik kapatma**: Bakım, turnuva, etkinlik. Tesis (veya tüm tesisler), başlangıç–bitiş, açıklama. Çakışan rezervasyonlar aynı şekilde iptal edilebilir.
- **Resmi tatiller**: Tüm tesisler kapalı. Liste her yıl hazır gelir, SKS Admin düzenler (yarım gün desteklenir: "13:00'ten itibaren kapalı").

## 8. Takımlar ve oyuncular

- Takımlar önceden tanımlanır (ad, branş, antrenör).
- Oyuncular **yalnızca öğrencidir**; LDAP'tan öğrenci numarası ile aranarak eklenir.
- Saklanan bilgiler: ad, soyad, öğrenci no, e-posta, bölüm (LDAP'tan); forma no, mevki, doğum tarihi, telefon, lisans no,
  sağlık raporu tarihi ve geçerlilik bitişi, acil durum kişisi ve telefonu, fotoğraf, notlar.
- **Hassas alanlar veritabanında şifreli** tutulur; yalnızca SKS Admin ve takımın antrenörü görebilir; her görüntüleme loglanır.
- Sağlık raporu bitişine **30 gün** kala ve süresi dolduğunda antrenöre e-posta gider.
- Oyuncu takımdan çıkarıldığında **sağlık ve kişisel iletişim bilgileri silinir**; oyuncu "eski oyuncu" olarak takım geçmişinde kalır
  (ad, öğrenci no, forma no, mevki, tarihler).

## 9. Katılım ve yaptırım

- **QR check-in**: Varsayılan olarak tesis girişindeki bir tablet **30 saniyede bir değişen, imzalı** bir QR kod gösterir
  (önceden çekilmiş fotoğraf çalışmaz). Tablet koyulamayan tesisler için SKS "sabit (basılı) QR" modunu seçebilir.
  Kullanıcı telefon kamerasıyla okutur, oturum açıkken rezervasyonuna giriş yapılır. Pencere: başlangıçtan 10 dk önce – 15 dk sonra.
- **Görevli işaretlemesi**: Salon görevlisi "geldi / gelmedi" işaretler ve otomatik kararı düzeltebilir.
- İlk 15 dakikada check-in yapılmayan onaylı rezervasyon **gelmedi** olarak işaretlenir.
- **Yaptırım**: 30 gün içinde **3** gelmeme veya geç iptal → **14 gün** rezervasyon yasağı, e-posta bildirimi.
  Sayılar ayarlanabilir; SKS Admin yasağı elle kaldırabilir. Antrenörler muaftır.

## 10. Ekipman

- SKS ekipman tanımlar: ad (TR/EN), toplam stok, kullanılabileceği tesisler.
- Rezervasyon formunda ekipman ve adet seçilir; o saat aralığındaki kalan stok kontrol edilir.
- Ekipman talebi rezervasyonla birlikte onaylanır.
- Görevli teslim ve iade anını, iade durumunu (sağlam / hasarlı / kayıp) işaretler. Hasarlı/kayıp iadeler raporda görünür.
- Durum sırası zorunludur: teslim yalnızca onaylı rezervasyonda ve rezervasyon günü; iade yalnızca teslimden sonra ve bir kez.

## 11. Bildirimler (yalnızca e-posta, kullanıcının dilinde)

| Olay | Alıcı |
|---|---|
| Yeni talep | Tesis onaycıları + SKS Admin |
| Onay bekleyen talep hatırlatması | Tesis onaycıları + SKS Admin |
| Onaylandı / Reddedildi (gerekçe) / SKS tarafından iptal / Süresi doldu | Rezervasyon sahibi |
| Katılımcı olarak eklendiniz | Katılımcı |
| Hatırlatma: 1 gün önce ve 2 saat önce (QR hatırlatmasıyla) | Sahip + katılımcılar |
| Yaptırım uygulandı | Kullanıcı |
| Sağlık raporu süresi doluyor/doldu | Antrenör |

## 12. Raporlar (Excel ve PDF dışa aktarma; tarih aralığı ve tesis filtresi)

1. Doluluk (tesis bazında %) ve gün × saat ısı haritası
2. Kullanıcı istatistikleri (rol bazında, en çok rezervasyon yapanlar)
3. Gelmeme ve iptal (oranlar, geç iptaller, yaptırımlılar)
4. Onay performansı (ortalama onay süresi, süresi dolan talepler, onaycı bazında işlem)
5. Takım kullanımı (takım bazında saat)
6. Ekipman (hasarlı/kayıp iadeler)

Tesis onaycıları yalnızca kendi tesislerini görür.

## 13. Genel

- **Dil**: Türkçe (varsayılan) ve İngilizce; kullanıcı seçer, tercih saklanır.
- **Platform**: Mobil uyumlu web uygulaması + PWA (ana ekrana eklenebilir). Mağaza uygulaması yok.
- **Teknoloji**: PHP 8.3+ (Laravel 13), MySQL 8, üniversite sunucusu.
- **Veri saklama**: Rezervasyonlar 5 yıl saklanır, sonra anonimleştirilir.
- **İşlem kaydı**: Bütün işlemler değiştirilemez (hash zincirli) işlem kaydına yazılır; yalnızca SKS Admin görür, kimse silemez.
- **Ücret** yoktur. Dış kullanıcı olarak yalnızca mezunlar (LDAP mezun OU'su) vardır.

## 14. Açık konular

| # | Konu | Sorumlu |
|---|---|---|
| 1 | E-posta gönderimi: SMTP mi, Microsoft 365 mi; gönderen adres (ör. `rezervasyon@yasar.edu.tr`) | Bilgi İşlem |
| 2 | LDAP sunucu adresi, servis hesabı, OU adları (öğrenci, idari, akademik, mezun), öznitelik adları | Bilgi İşlem |
| 3 | Sunucudaki PHP (8.3+) ve MySQL (8.0+) sürümleri, `php-ldap` eklentisi, HTTPS sertifikası | Bilgi İşlem |
| 4 | KVKK aydınlatma metni (TR/EN) | Hukuk Birimi |
| 5 | Resmi tatil listesinin (özellikle dini bayramlar) her yıl kontrolü | SKS Admin |

## 15. Kabul edilen varsayımlar

- Antrenörlerin LDAP hesabı vardır.
- Bir tesise birden fazla onaycı atanabilir; talebi ilk karara bağlayan işlemi tamamlar.
- Ekipman için ayrı yaptırım yoktur, yalnızca kayıt ve rapor tutulur.
- Grup rezervasyonunda katılım: sahip veya katılımcılardan biri check-in yaparsa rezervasyon "geldi" sayılır; gelmeme yaptırımı rezervasyon sahibine uygulanır.
