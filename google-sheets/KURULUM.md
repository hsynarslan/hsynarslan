# İş Takip: Google E-Tablolar kurulumu

Bu rehber, İş Takip sayfasını kendi Google E-Tablonuza bağlamak içindir. Toplam süre yaklaşık **10 dakika**,
yalnızca bir kez yapılır. Ekranınız İngilizceyse menü adları parantez içinde verilmiştir.

> **Hangi Google hesabı?** Kişisel bir Gmail hesabı önerilir. Kurum (okul/şirket) Google hesaplarında
> yönetici, dışarıdan erişime izin vermeyebilir; o durumda 3. adımda "Herkes" seçeneği görünmez.

---

## 1. E-Tabloyu oluşturun (1 dk)

1. https://sheets.google.com adresini açın.
2. **Boş e-tablo** (Blank spreadsheet) seçin.
3. Sol üstte "Adsız e-tablo" yazısına tıklayıp adını **İş Takip** yapın.

## 2. Köprü kodunu ekleyin (3 dk)

1. E-Tablonun üst menüsünden **Uzantılar → Apps Script** (Extensions → Apps Script) yolunu izleyin. Yeni bir sekme açılır.
2. Sol üstteki "Adsız proje" yazısına tıklayıp adını **İş Takip** yapın.
3. Kod alanında `function myFunction() { … }` yazısı var. **Hepsini silin** (Ctrl+A, ardından Delete).
4. Başka bir sekmede şu adresi açın:
   https://github.com/hsynarslan/hsynarslan/blob/main/google-sheets/Kod.gs
   Sağ üstteki **kopyala simgesine** ("Copy raw file") tıklayın.
5. Apps Script sekmesine dönün, kod alanına yapıştırın (Ctrl+V).
6. Üstteki **disket simgesine** (Kaydet) tıklayın.

## 3. Kurulumu çalıştırın (2 dk)

1. **E-Tablo sekmesine** dönün ve sayfayı yenileyin (F5).
2. Birkaç saniye sonra üst menüde **İş Takip** adlı yeni bir menü belirir.
3. **İş Takip → 1. Kurulumu yap** seçeneğine tıklayın.
4. **"Yetkilendirme gerekli"** penceresi çıkar. Sırasıyla:
   - **Devam** (Continue) deyin ve Google hesabınızı seçin.
   - **"Google bu uygulamayı doğrulamadı"** uyarısı çıkarsa bu normaldir; kodu siz eklediğiniz için Google tanımıyor.
     Sol alttaki **Gelişmiş** (Advanced) bağlantısına, ardından **İş Takip (güvenli değil) sayfasına git** bağlantısına tıklayın.
   - **İzin ver** (Allow) deyin.
5. **İş Takip → 1. Kurulumu yap** seçeneğine **bir kez daha** tıklayın. İlk tıklama yalnızca izin almak içindi.
6. "Kurulum tamamlandı" penceresi açılır ve içinde **bağlantı anahtarınız** yazar. Bu pencereyi kapatabilirsiniz;
   anahtarı istediğiniz zaman **İş Takip → 2. Bağlantı anahtarını göster** menüsünden tekrar görebilirsiniz.

✅ **Kontrol:** E-Tabloda **Görevler**, **Birimler** ve **AltGörevler** sayfaları var. Birimleri E-Tablodan ya da
web sayfasındaki **Birimleri yönet** düğmesinden kendi birimlerinizle değiştirin.

## 4. Web uygulaması olarak yayınlayın (3 dk)

1. **Apps Script** sekmesine dönün.
2. Sağ üstteki mavi **Dağıt** (Deploy) düğmesine, ardından **Yeni dağıtım** (New deployment) seçeneğine tıklayın.
3. Sol üstteki **dişli simgesine** ("Tür seçin") tıklayıp **Web uygulaması** (Web app) seçin.
4. Ayarları şöyle yapın:
   - **Açıklama:** `İş Takip`
   - **Şu kullanıcı olarak yürüt** (Execute as): **Ben** (Me)
   - **Erişimi olan kullanıcılar** (Who has access): **Herkes** (Anyone)
5. **Dağıt** (Deploy) deyin. Yeniden izin isterse 3. adımdaki gibi onaylayın.
6. Çıkan pencerede **Web uygulaması URL'si** yazar. Adres `https://script.google.com/macros/s/…/exec` biçimindedir.
   **Kopyala** deyin.

> "Herkes" ayarı, sayfanın sizden Google girişi istemeden tabloya ulaşabilmesi içindir. Tabloya erişim için adresin
> yanında **bağlantı anahtarı** da gerekir. Anahtarı ve adresi kimseyle paylaşmayın.

## 5. Sayfayı bağlayın ve şifre belirleyin (1 dk)

1. https://hsynarslan.github.io/hsynarslan/ adresini açın. **"İş Takip'i bağlayın"** ekranı gelir.
2. **Web uygulaması adresi** alanına 4. adımda kopyaladığınız adresi yapıştırın.
3. **Bağlantı anahtarı** alanına 3. adımdaki anahtarı yapıştırın.
4. **Yeni şifre** ve **Şifre (tekrar)** alanlarına bu cihazda kullanacağınız bir şifre yazın (en az 6 karakter).
5. **Bağlan ve kilitle** düğmesine basın.

✅ **Kontrol:** Sağ üstte **"Google E-Tablolar'a bağlı"** yazıyor. **Yeni görev** ile bir iş ekleyin;
birkaç saniye içinde E-Tablonun **Görevler** sayfasında görünür.

### Şifre nasıl çalışır?

- Adres ve anahtar bu tarayıcıda **şifrenizle şifrelenmiş** olarak saklanır; şifrenin kendisi hiçbir yerde tutulmaz.
- Sayfa her yeni açılışta şifre sorar. Sekmeyi kapatınca, **Kilitle** düğmesine basınca ya da **30 dakika** işlem
  yapılmayınca kendiliğinden kilitlenir.
- Şifreyi değiştirmek için: **Ayarlar** → mevcut şifre + yeni şifre → **Kaydet ve bağlan**.
- **Şifremi unuttum** derseniz yalnızca bu cihazdaki kayıt silinir; E-Tablodaki verileriniz durur. Adres ve anahtarla
  (E-Tabloda **İş Takip → 2. Bağlantı anahtarını göster**) yeniden bağlanıp yeni şifre belirlersiniz.
- Telefonda ya da başka bir bilgisayarda kullanmak için orada da 5. adımı bir kez yapın. Her cihazın şifresi ayrıdır.

---

## Her cihazda yalnızca şifre (önerilen)

Adres ve anahtarı her cihazda ayrı ayrı girmek yerine, şifreyi Google tarafında bir kez belirleyebilirsiniz.
Bundan sonra sayfa **hangi cihazdan açılırsa açılsın yalnızca şifrenizi sorar.**

1. Kod.gs'in güncel sürümünü yükleyin (aşağıdaki **Kodu güncellemek gerekirse** bölümü).
2. E-Tabloda **İş Takip → 3. Web şifresi belirle** menüsünü açın, şifrenizi iki kez yazıp **Kaydet** deyin
   (en az 8 karakter).
3. Web uygulaması adresinin (`…/exec`) sayfanın ayarlarına (`js/config.js` → `sheetsUrl`) yazılması gerekir.
   Bunu bir kez yapmak yeterlidir.

Güvenlik:
- Şifre Google'da düz metin olarak değil, tuzlanmış SHA-256 özeti olarak saklanır; E-Tabloyu açan biri bile göremez.
- 15 dakika içinde 10 hatalı denemeden sonra şifreyle giriş 15 dakika kapanır.
- Şifreyi değiştirmek ya da unuttuğunuzda yenisini belirlemek için 2. adımı tekrarlayın.
- Adres herkese açık olsa bile tek başına işe yaramaz; her istekte şifre ya da bağlantı anahtarı gerekir.

## Sık karşılaşılan sorunlar

| Mesaj | Çözüm |
|---|---|
| **Bağlantı anahtarı hatalı** | Anahtarı **İş Takip → 2. Bağlantı anahtarını göster** menüsünden yeniden kopyalayın. Başında ya da sonunda boşluk kalmadığından emin olun. |
| **Kurulum yapılmamış** | 3. adımı yapın ve **1. Kurulumu yap** menüsünü izin verdikten sonra bir kez daha çalıştırın. |
| **Google'a ulaşılamadı** / **beklenmeyen yanıt** | 4. adımda **Erişimi olan kullanıcılar: Herkes** seçildiğinden ve adresin `/exec` ile bittiğinden emin olun. Kurum hesabında bu seçenek yoksa kişisel Gmail hesabıyla kurun. |
| **"Görevler" sayfası bulunamadı** | Sayfa adını değiştirdiyseniz geri **Görevler** yapın ya da **1. Kurulumu yap** menüsünü yeniden çalıştırın. |

## Kodu güncellemek gerekirse

Sayfada **"Google tarafındaki kodu güncelleyin"** uyarısı görüyorsanız E-Tablodaki köprü kodunun yeni sürümü
gerekiyor. Örneğin birim yönetimi ve alt paketler 2. sürümle geldi. Verileriniz silinmez; yaklaşık 3 dakika sürer.

1. E-Tablonuzu açın ve **Uzantılar → Apps Script** yolunu izleyin.
2. Kod alanındaki her şeyi silin (Ctrl+A, ardından Delete).
3. https://github.com/hsynarslan/hsynarslan/blob/main/google-sheets/Kod.gs adresinden kodu yeniden kopyalayıp
   yapıştırın ve **💾 Kaydet** deyin.
4. **Dağıt → Dağıtımları yönet** (Manage deployments) yolunu izleyin.
5. Listedeki dağıtımın sağındaki **kalem simgesine** (Düzenle) tıklayın.
6. **Sürüm** (Version) açılır listesinden **Yeni sürüm** (New version) seçin ve **Dağıt** (Deploy) deyin.
   Yeniden izin isterse 3. adımdaki gibi onaylayın.
7. E-Tablo sekmesine dönüp sayfayı yenileyin ve **İş Takip → 1. Kurulumu yap** menüsünü bir kez çalıştırın.
   Bu adım yeni sayfaları (ör. **AltGörevler**) biçimlendirir; mevcut görevlerinize ve anahtarınıza dokunmaz.
8. İş Takip sayfasını yenileyin. Uyarı kaybolur.

> **"Yeni dağıtım" yapmayın.** Yeni dağıtım farklı bir adres üretir; o zaman sayfadaki Ayarlar'a yeni adresi
> girmeniz gerekir. "Dağıtımları yönet → Yeni sürüm" yolu adresi aynı bırakır.

## Güvenlik

- Tablonuz Google Drive'ınızda kalır, siz paylaşmadıkça kimse açamaz.
- Web sayfası tabloya yalnızca adres **ve** bağlantı anahtarı birlikte verildiğinde erişebilir.
- Anahtarın başkasının eline geçtiğinden şüphelenirseniz **İş Takip → Bağlantı anahtarını yenile** menüsünü
  kullanın, ardından sayfadaki Ayarlar'a yeni anahtarı girin.
