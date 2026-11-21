# v1.0 Kod İncelemesi — Yanıt ve Düzeltmeler (v1.1)

Harici teknik incelemedeki her bulgu kodla karşılaştırıldı. Geçerli olanlar düzeltildi ve her düzeltme için otomatik test
yazıldı (`tests/Feature/ReviewFixesTest.php`). Eş zamanlılık bulguları **gerçek MariaDB 10.11** üzerinde, aynı anda çalışan
20–30 süreçle ölçüldü (`tests/Concurrency/stress.php`).

## Özet

| # | Bulgu | Değerlendirme | Durum |
|---|---|---|---|
| 1 | Başka takım adına rezervasyon | **Geçerli.** Personel (antrenör olmayan) herhangi bir takımı seçebiliyordu. | ✅ Düzeltildi |
| 2 | Sabit QR gerçek katılımı kanıtlamıyor | **Geçerli.** | ✅ Dinamik QR eklendi |
| 3 | Limitler transaction dışında | **Geçerli, ölçüldü:** 20 eş zamanlı istekten **2'si** geçti (günlük sınır 1). | ✅ Düzeltildi, 0 ihlal |
| 4 | Sabit blok mevcut blokları iptal edebiliyor | **Geçerli.** Ayrıca blok oluşturma kilitsizdi. | ✅ Düzeltildi |
| 5 | Görevli tüm tesislerde işlem yapabiliyor | **Geçerli.** | ✅ Tesis–görevli ataması |
| 6 | Katılımcı ayrılma tekrar işlenebiliyor | **Kısmen geçerli.** Art arda ikinci istek zaten 403 alıyordu; ancak eş zamanlı istekler ve iptal edilmiş rezervasyon kontrolü eksikti. | ✅ Düzeltildi |
| 7 | Ekipman durum makinesi eksik | **Geçerli.** Teslim edilmeden iade alınabiliyordu. | ✅ Düzeltildi |
| 8 | Katılımcı eklemede onay yok | **Tasarım kararı.** Gereksinimde "katılımcıya e-posta gider, isterse listeden çıkar" olarak kararlaştırıldı. | ➖ Değişmedi (isteğe bağlı geliştirme) |
| 9 | KVKK silmenin tüm kaynaklarda doğrulanması | **Kısmen geçerli.** Başarısız e-posta kuyruğu ve ekipman notları kapsam dışındaydı. | ✅ Genişletildi |
| — | **İncelemede olmayan ek bulgu:** Onay / red / iptal / otomatik sonlandırma aynı anda gelirse birbirinin üzerine yazabiliyordu. | Kod incelemesinde tespit edildi. | ✅ Düzeltildi |
| — | **İncelemede olmayan ek bulgu:** Hiç giriş yapmamış birini katılımcı eklemek **500 hatası** veriyordu. | Yük testinde tespit edildi. | ✅ Düzeltildi |

## Ayrıntılar

### 1. Takım rezervasyonu yetkisi
- Takım adına artık **yalnızca o takımın antrenörü veya SKS Admin** rezervasyon yapabilir (`User::canBookForTeam`).
- Takımı olmayan kullanıcılara takım aktiviteleri formda hiç gösterilmez.
- ⚠️ **Davranış değişikliği:** Daha önce "takım rezervasyonu antrenör ve personel" kararı vardı. Antrenör olmayan personel artık
  takım basketbolu seçemez; tam salonu **voleybol** aktivitesiyle yine alabilir. Bir personelin takım adına rezervasyon yapması
  gerekiyorsa SKS Admin onu o takımın antrenörü olarak atar.

### 2. QR ile giriş
- Yeni varsayılan: **dinamik QR**. Tesis girişindeki tablet, görevli oturumuyla `/kiosk/{tesis}` sayfasını açar. Kod **30 saniyede
  bir** değişir ve HMAC-SHA256 ile imzalıdır (`app/Services/CheckinToken.php`). Okutma + onay adımı için en fazla ~90 saniyelik
  tolerans vardır; önceden çekilmiş fotoğraf veya kaydedilmiş adres çalışmaz.
- Tesis ayarından **sabit (basılı) QR** moduna geçilebilir (tablet koyulamayan yerler için). Dinamik moddaki bir tesiste sabit bağlantı reddedilir.
- **Sınırlama:** Dinamik QR, tesisteki biri tarafından **canlı** olarak çekilip anında gönderilen bir görüntüyü tamamen engellemez.
  Bu nedenle görevli işaretlemesi korunmuştur. Daha yüksek güvence için konum doğrulaması (kampüs Wi-Fi IP aralığı) ileride eklenebilir.

### 3. Eş zamanlı limit aşımı
- Limit kontrolü artık transaction içinde, ilgili **kullanıcı satırları kilitlendikten sonra** yeniden yapılır.
- Kilit sırası her istekte aynıdır (kullanıcılar → tesis birimleri → ekipman) ve kilitlenme (deadlock) önlenir.
- Ekipman stoku da kilit altında kontrol edilir (ekipman birden fazla tesiste ortak kullanılabildiği için).

**Ölçüm (MariaDB 10.11, aynı anda başlayan süreçler):**

| Senaryo | Düzeltme öncesi | Düzeltme sonrası (5 tur) |
|---|---|---|
| Aynı öğrenci, aynı gün, 20–30 farklı saat (sınır: günde 1) | 20 istekten **2 başarılı** ❌ | 30 istekten **1 başarılı** ✅, 0 deadlock |
| 30 kişi aynı saat, 2 pota | — | Her turda **2 başarılı** ✅ |
| Aynı talebe 30 eş zamanlı onay / red / sahip iptali | — | Onay ve red **hiçbir turda birlikte geçmedi**; birim kilidi her zaman son durumla tutarlı ✅ |

### 4. Sabit bloklar
- Yeni blok, **mevcut bir sabit blokla çakışıyorsa oluşturulmaz**; önizlemede kırmızı uyarı çıkar ve onay düğmesi gösterilmez.
- Blok ve kapatma oluşturma tesis birimlerini kilitler, çakışmaları kilit altında yeniden hesaplar.

### 5. Görevli kapsamı
- Yeni `facility_attendants` tablosu. Görevli yalnızca **atandığı tesislerin** günlük listesini, katılım ve ekipman işlemlerini ve
  giriş tabletini görür. SKS Admin tüm tesislerde işlem yapabilir. Atama: *Tesisler → Düzenle → Salon görevlileri*.
- Rolü kaldırılan kişinin açık oturumu bir sonraki istekte yetkisini kaybeder (test edildi).

### 6. Katılımcı ayrılma
- Rezervasyon satırı kilitlenir; iptal edilmiş, süresi dolmuş veya başlamış rezervasyondan ayrılınamaz.
- Katılımcı sayısı azaltılmaz, **aktif katılımcılardan yeniden hesaplanır**.

### 7. Ekipman durum makinesi
`bekliyor → teslim edildi → iade edildi (sağlam / hasarlı / kayıp)`. Teslim yalnızca onaylı rezervasyonda ve rezervasyon günü yapılabilir;
çift teslim, teslimsiz iade ve çift iade reddedilir.

### 9. KVKK
- Başarısız e-posta işleri (alıcı adı ve e-postası içerir) **30 gün** sonra otomatik silinir (`queue:prune-failed`).
- 5 yıllık anonimleştirme artık ekipman notlarını ve teslim eden/alan bilgisini de temizler.
- İşlem kaydı (audit log) bilinçli olarak silinmez; hassas alanlar zaten maskelenir. Saklama süresi kurumun BGYS politikasıyla belirlenmelidir.

### Diğer iyileştirmeler
- **Durum geçişleri:** Onay, red, sahip iptali, SKS iptali, otomatik sonlandırma ve katılım işaretleme artık rezervasyon satırını
  kilitleyip güncel halini okur. Örneğin zamanlayıcı eski listeyle çalışırken talep onaylanırsa, onaylı talep iptal edilmez.
- **Çalışma saati değişikliği:** Yeni saatlerin dışında kalan ileri tarihli rezervasyonlar otomatik iptal edilmez; yöneticiye kodlarıyla listelenir.
- **LDAP kesintisi:** Kullanıcıya açık bir mesaj gösterilir, oturum açılmaz (test edildi).

## İncelemenin "canlıya almadan önce" test listesi

| Test | Durum |
|---|---|
| Takım rezervasyonları için yetki testleri | ✅ Otomatik test |
| Aynı saatte 20–50 eş zamanlı rezervasyon | ✅ MariaDB'de 30 süreç (`tests/Concurrency/stress.php`) |
| Günlük ve haftalık limit aşma | ✅ Otomatik test + eş zamanlı ölçüm |
| QR bağlantısının farklı konumdan kullanılması | ✅ Eski bağlantı / sahte imza / sabit bağlantı testleri |
| Görevlinin başka tesis rezervasyonuna erişimi | ✅ Otomatik test |
| Sabit blok ve mevcut rezervasyon çakışması | ✅ Otomatik test |
| Ekipman teslim/iade durumları | ✅ Otomatik test |
| KVKK veri silme ve log saklama | ✅ Otomatik test (oyuncu verisi, 5 yıl anonimleştirme) |
| LDAP bağlantı kesintisi | ✅ Otomatik test (sahte kesinti). **Gerçek LDAP ile Bilgi İşlem ortamında tekrarlanmalı.** |
| Mobil, erişilebilirlik ve yoğunluk | ⏳ Gerçek kullanıcılarla pilot kullanım önerilir |

**Testler:** 53 otomatik test, 194 doğrulama — hepsi başarılı. Not: İnceleme ortamında testler çalıştırılamamıştı; testler SQLite ile
çalışır (`php artisan test`), eş zamanlılık testi MySQL/MariaDB gerektirir.
