# İş Takip: Kendi kopyanızı kurun

Bu rehber, İş Takip'i **kendi Google hesabınızla ve kendi verilerinizle** kullanmak isteyenler içindir.
Sonunda kendinize ait bir adresiniz olur, örneğin `https://kullaniciadiniz.github.io/is-takip/`. Bu adres
her cihazda yalnızca **sizin şifrenizi** sorar. Verileriniz sizin Google Drive'ınızda, e-postalar sizin Gmail
hesabınızdan gider.

**Toplam süre:** yaklaşık 25 dakika. Bir kez yapılır.

| Bölüm | Ne yapılıyor | Süre |
|---|---|---|
| [0. Hazırlık](#0-hazırlık) | Hesapları kontrol etme | 2 dk |
| [1. Kodun kopyasını alın](#1-kodun-kopyasını-alın-github) | GitHub'da kopya (fork) ve ad değiştirme | 3 dk |
| [2. Google E-Tablonuzu kurun](#2-google-e-tablonuzu-kurun) | E-Tablo, köprü kodu, şifre, yayınlama | 10 dk |
| [3. Sayfayı E-Tablonuza bağlayın](#3-sayfayı-e-tablonuza-bağlayın) | Adresi ayar dosyasına yazma | 3 dk |
| [4. Sayfayı yayınlayın](#4-sayfayı-yayınlayın-github-pages) | GitHub Pages'i açma | 3 dk |
| [5. İlk giriş ve ayarlar](#5-ilk-giriş-ve-ayarlar) | Giriş, birimler, e-posta | 5 dk |

> Ekranlarınız İngilizceyse menü adları parantez içinde verilmiştir. Her bölümün sonundaki ✅ **Kontrol** satırını
> görüyorsanız bir sonraki bölüme geçin.

---

## 0. Hazırlık

İki ücretsiz hesap gerekir:

- **Google hesabı:** Kişisel bir **Gmail** hesabı önerilir. Kurum (okul/şirket) Google hesaplarında yönetici bazı
  izinleri kapatmış olabilir; o durumda 2.4. adımda "Herkes" seçeneği görünmez.
- **GitHub hesabı:** Yoksa https://github.com/signup adresinden açın. Kullanıcı adınız sayfanızın adresinde
  görünecek (`kullaniciadiniz.github.io`).

Bu adımları **bilgisayardan** yapın. Google E-Tablolar'ın telefon uygulamasında gereken menüler yoktur.

✅ **Kontrol:** Tarayıcıda hem Google'a hem GitHub'a giriş yapmış durumdasınız.

---

## 1. Kodun kopyasını alın (GitHub)

### 1.1. Kopyalayın (Fork)
1. https://github.com/hsynarslan/hsynarslan adresini açın.
2. Sağ üstteki **Fork** düğmesine tıklayın.
3. Açılan sayfada **Repository name** alanına `is-takip` yazın. Bu ad sayfanızın adresinin son kısmı olur.
4. **Create fork** düğmesine basın.

Birkaç saniye sonra `github.com/kullaniciadiniz/is-takip` adresinde kendi kopyanız açılır.

> **Fork ekranı ad sormadıysa:** Kopyanızda **Settings** (Ayarlar) sekmesini açın. En üstteki
> **Repository name** alanına `is-takip` yazıp **Rename** deyin.

> Kopyada `spor-rezervasyon` ve `yasar-rezervasyon` gibi başka klasörler de görürsünüz. Bunlar ayrı projelerdir,
> İş Takip'i etkilemez; dokunmanıza gerek yok.

✅ **Kontrol:** Tarayıcınızın adres çubuğunda `github.com/kullaniciadiniz/is-takip` yazıyor.

---

## 2. Google E-Tablonuzu kurun

Bu bölüm, ayrıntılı [Google E-Tablolar kurulum rehberinin](google-sheets/KURULUM.md) 1–4. adımlarının kısa halidir.
Bir adımda takılırsanız oradaki açıklamalara ve **Sık karşılaşılan sorunlar** tablosuna bakın.

### 2.1. E-Tabloyu açın
1. https://sheets.google.com → **Boş e-tablo** (Blank spreadsheet).
2. Sol üstte "Adsız e-tablo" yazısına tıklayıp adını **İş Takip** yapın.

### 2.2. Köprü kodunu ekleyin
1. E-Tabloda **Uzantılar → Apps Script** (Extensions → Apps Script). Yeni bir sekme açılır.
2. Sol üstte proje adını **İş Takip** yapın.
3. Kod alanındaki her şeyi silin (**Ctrl+A**, ardından **Delete**).
4. Başka bir sekmede **kendi kopyanızdaki** `google-sheets/Kod.gs` dosyasını açın:
   `https://github.com/kullaniciadiniz/is-takip/blob/main/google-sheets/Kod.gs`
   Sağ üstteki **kopyala simgesine** ("Copy raw file") tıklayın.
5. Apps Script'e yapıştırın ve **💾 Kaydet** deyin.

### 2.3. Kurulumu çalıştırın ve web şifrenizi belirleyin
1. E-Tablo sekmesine dönüp sayfayı yenileyin (**F5**). Üstte **İş Takip** menüsü belirir.
2. **İş Takip → 1. Kurulumu yap.**
3. İzin ekranında sırasıyla şunları seçin: **Devam** → Google hesabınız → **Gelişmiş** (Advanced) →
   **İş Takip (güvenli değil) sayfasına git** → **İzin ver** (Allow).
   "Google bu uygulamayı doğrulamadı" uyarısı normaldir; kodu siz eklediğiniz için çıkar.
4. **İş Takip → 1. Kurulumu yap** seçeneğine **bir kez daha** tıklayın. "Kurulum tamamlandı" penceresi açılır.
5. **İş Takip → 3. Web şifresi belirle.** Sayfaya girerken kullanacağınız şifreyi iki kez yazın
   (en az 8 karakter) ve **Kaydet** deyin.

### 2.4. Web uygulaması olarak yayınlayın
1. Apps Script sekmesinde sağ üstteki **Dağıt → Yeni dağıtım** (Deploy → New deployment).
2. Dişli simgesinden **Web uygulaması** (Web app) seçin.
3. **Şu kullanıcı olarak yürüt:** **Ben** (Me). **Erişimi olan kullanıcılar:** **Herkes** (Anyone).
4. **Dağıt** deyin. İzin isterse 2.3. adımdaki gibi onaylayın.
5. **Web uygulaması URL'sini** kopyalayın. `https://script.google.com/macros/s/…/exec` biçimindedir.
   Bir sonraki bölümde kullanacaksınız; bir yere not edin.

✅ **Kontrol:** E-Tabloda **Görevler**, **Birimler** ve **AltGörevler** sayfaları var ve elinizde `…/exec` ile biten bir adres var.

---

## 3. Sayfayı E-Tablonuza bağlayın

Kopyanızdaki ayar dosyasında hâlâ orijinal sahibin adresi yazıyor. Onu kendi adresinizle değiştireceksiniz.

1. GitHub'da kendi kopyanızda `js/config.js` dosyasını açın:
   `https://github.com/kullaniciadiniz/is-takip/blob/main/js/config.js`
2. Sağ üstteki **kalem simgesine** (Edit this file) tıklayın.
3. `sheetsUrl:` ile başlayan satırı bulun. Tırnak içindeki adresi silip **2.4. adımda kopyaladığınız adresi**
   yapıştırın. Satır şöyle görünmeli:
   ```js
   sheetsUrl: "https://script.google.com/macros/s/SIZIN-ADRESINIZ/exec",
   ```
   Tırnakları ve satır sonundaki virgülü silmeyin. Dosyadaki diğer satırlara dokunmayın.
4. Sağ üstteki yeşil **Commit changes…** düğmesine, açılan pencerede yine **Commit changes** düğmesine basın.

> Bu adresin herkese açık bir dosyada durması sorun değildir: Adres tek başına hiçbir veriye erişim sağlamaz,
> her istekte web şifreniz gerekir. 15 dakikada 10 hatalı denemeden sonra şifreyle giriş 15 dakika kapanır.

✅ **Kontrol:** `js/config.js` dosyasında `sheetsUrl` satırında kendi adresiniz yazıyor.

---

## 4. Sayfayı yayınlayın (GitHub Pages)

1. Kopyanızda **Settings → Pages** (sol menüde, "Code and automation" altında).
2. **Source:** **Deploy from a branch**.
3. **Branch:** **main**, klasör **/ (root)**. Ardından **Save**.
4. 1–2 dakika bekleyip sayfayı yenileyin. Üstte **"Your site is live at https://kullaniciadiniz.github.io/is-takip/"**
   yazısı belirir.

> **Site açılmıyorsa:**
> - Kopyanızda **Actions** sekmesini açın. *"Workflows aren't being run on this forked repository"* yazıyorsa yeşil
>   **I understand my workflows, go ahead and enable them** düğmesine basın.
> - Ardından 3. bölümü tekrarlayıp `js/config.js`'te küçük bir değişiklik kaydedin (ör. bir boşluk ekleyin).
>   Yeni kayıt, yayını yeniden başlatır.
> - Birkaç dakika bekleyin. İlk yayın 10 dakikayı bulabilir.

✅ **Kontrol:** `https://kullaniciadiniz.github.io/is-takip/` açılıyor ve **"İş Takip kilitli"** ekranı görünüyor.

---

## 5. İlk giriş ve ayarlar

### 5.1. Giriş
1. Sayfanızı açın, **2.3. adımda belirlediğiniz web şifresini** yazıp **Giriş yap** deyin.
2. İlk girişte adınız sorulur. Değişiklik geçmişi ve yorumlar bu adla kaydedilir.

> "Şifre hatalı" derse: 3. bölümdeki adresin doğru yapıştırıldığından ve 2.3. adımda **3. Web şifresi belirle**
> menüsünü çalıştırdığınızdan emin olun. Hâlâ orijinal sahibin şifre ekranını görüyorsanız `config.js`'teki adres
> değişmemiştir.

### 5.2. Birimleriniz
1. **Birim performansı** panelinde **Birimleri yönet** düğmesine basın.
2. Örnek birimleri silin ya da düzenleyin, kendi birimlerinizi ekleyin.
3. Birim sorumlularına e-posta gidecekse **E-posta** alanını doldurun.

### 5.3. E-posta (isteğe bağlı)
1. E-Tabloda bir kez **İş Takip → Günlük e-postayı şimdi gönder (deneme)** menüsünü çalıştırıp izin verin.
   E-posta ve Google Takvim izinleri bu adımda istenir. Size bir deneme e-postası gelir.
2. Sayfada üstteki **E-posta** düğmesine basın ve şunları doldurun:
   - **Alıcı e-posta adresi:** Günlük özetin gideceği adres (ör. iş e-postanız).
   - **Gönderen adı:** Alıcıların göreceği ad (ör. adınız soyadınız).
   - **Yanıtların geleceği adres:** "Yanıtla" denince yanıtın gideceği adres.
   - İsterseniz **Son gün hatırlatması** ve **takvim daveti** seçenekleri.
3. **Aç ve kaydet** deyin.

E-postalar Gmail hesabınızdan, yazdığınız adla gider; yanıtlar yazdığınız adrese gelir.

### 5.4. Telefona ekleyin (isteğe bağlı)
Sayfanızı telefonda açın. Tarayıcı menüsünden **Ana ekrana ekle** (Add to Home Screen) deyin. İş Takip bir
uygulama gibi açılır.

✅ **Kontrol:** Yeni bir görev ekleyin. Birkaç saniye içinde E-Tablonuzun **Görevler** sayfasında görünür. 🎉

---

## Güncellemeler

Orijinal projeye yeni özellikler eklendikçe kopyanızı güncelleyebilirsiniz.

1. **Sayfayı güncelleyin:** GitHub'da kopyanızın ana sayfasında **Sync fork → Update branch** deyin.
   - **"Discard commits"** seçeneğini **seçmeyin**. O seçenek kendi adres ayarınızı siler.
   - GitHub bir çakışma bildirirse ya da güncellemeden sonra orijinal sahibin şifre ekranı gelirse
     3. bölümü tekrarlayın.
2. **Google tarafını güncelleyin:** Sayfada **"Google tarafındaki kodu güncelleyin"** uyarısı çıkarsa:
   - Apps Script'te eski kodu silip kopyanızdaki güncel `google-sheets/Kod.gs` dosyasını yapıştırın ve **Kaydet** deyin.
   - **Dağıt → Dağıtımları yönet → kalem simgesi → Sürüm: Yeni sürüm → Dağıt.**
     ("Yeni dağıtım" değil; o, adresi değiştirir.)
   - E-Tabloyu yenileyip **İş Takip → 1. Kurulumu yap** menüsünü bir kez çalıştırın.

Verileriniz ve şifreniz güncellemelerden etkilenmez.

---

## Sık sorulanlar

**Verilerimi kim görebilir?**
Yalnızca siz. Veriler Google Drive'ınızdaki E-Tabloda durur. Sayfa, verilere yalnızca web şifrenizle erişir.
GitHub'daki kopyanızda veri yoktur, yalnızca kod vardır.

**Şifremi unuttum.**
E-Tabloda **İş Takip → 3. Web şifresi belirle** menüsünden yenisini belirleyin.

**Ekibimle birlikte kullanabilir miyiz?**
Evet. Sayfa adresinizi ve web şifrenizi paylaşın. Herkes aynı verileri görür; kimin neyi değiştirdiği, girişte
yazdığı adla geçmişe kaydedilir. E-postalar sizin hesabınızdan gider.

**Ücretli mi?**
Hayır. Google E-Tablolar, Apps Script ve GitHub Pages bu kullanım için ücretsizdir. Gmail üzerinden günde
yaklaşık 100 e-posta gönderilebilir. Kalan hak, sayfadaki **E-posta** penceresinde görünür.

**Office 365 / Outlook kullanıyorum.**
Veriler yine Google E-Tabloda durur. Günlük e-postaları ve takvim davetlerini iş adresinize yönlendirebilirsiniz;
davetler Outlook takviminize eklenebilir. Ayrıntılar: [Google E-Tablolar kurulum rehberi](google-sheets/KURULUM.md).
