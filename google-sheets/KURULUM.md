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

✅ **Kontrol:** E-Tabloda **Görevler** ve **Birimler** adlı iki sayfa var. **Birimler** sayfasındaki
"Örnek Birim" satırlarını kendi birimlerinizle değiştirin.

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

## 5. Sayfayı bağlayın (1 dk)

1. https://hsynarslan.github.io/hsynarslan/ adresini açın.
2. **Google E-Tablolar'ı bağla** düğmesine (ya da sağ üstteki **Ayarlar** düğmesine) tıklayın.
3. **Web uygulaması adresi** alanına 4. adımda kopyaladığınız adresi yapıştırın.
4. **Bağlantı anahtarı** alanına 3. adımdaki anahtarı yapıştırın.
5. **Kaydet ve bağlan** düğmesine basın.

✅ **Kontrol:** Sağ üstte **"Google E-Tablolar'a bağlı"** yazıyor. **Yeni görev** ile bir iş ekleyin;
birkaç saniye içinde E-Tablonun **Görevler** sayfasında görünür.

Bağlantı bilgileri yalnızca o tarayıcıda saklanır. Telefonda ya da başka bir bilgisayarda kullanmak için orada da
5. adımı bir kez tekrarlayın.

---

## Sık karşılaşılan sorunlar

| Mesaj | Çözüm |
|---|---|
| **Bağlantı anahtarı hatalı** | Anahtarı **İş Takip → 2. Bağlantı anahtarını göster** menüsünden yeniden kopyalayın. Başında ya da sonunda boşluk kalmadığından emin olun. |
| **Kurulum yapılmamış** | 3. adımı yapın ve **1. Kurulumu yap** menüsünü izin verdikten sonra bir kez daha çalıştırın. |
| **Google'a ulaşılamadı** / **beklenmeyen yanıt** | 4. adımda **Erişimi olan kullanıcılar: Herkes** seçildiğinden ve adresin `/exec` ile bittiğinden emin olun. Kurum hesabında bu seçenek yoksa kişisel Gmail hesabıyla kurun. |
| **"Görevler" sayfası bulunamadı** | Sayfa adını değiştirdiyseniz geri **Görevler** yapın ya da **1. Kurulumu yap** menüsünü yeniden çalıştırın. |

## Kodu güncellemek gerekirse

Kod.gs'in yeni bir sürümünü yapıştırdıktan sonra değişikliğin yayına girmesi için:
**Dağıt → Dağıtımları yönet** (Manage deployments) → kalem simgesi → **Sürüm: Yeni sürüm** → **Dağıt**.
Web uygulaması adresi değişmez.

## Güvenlik

- Tablonuz Google Drive'ınızda kalır, siz paylaşmadıkça kimse açamaz.
- Web sayfası tabloya yalnızca adres **ve** bağlantı anahtarı birlikte verildiğinde erişebilir.
- Anahtarın başkasının eline geçtiğinden şüphelenirseniz **İş Takip → Bağlantı anahtarını yenile** menüsünü
  kullanın, ardından sayfadaki Ayarlar'a yeni anahtarı girin.
