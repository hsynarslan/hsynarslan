# İş Takip

Birimlere atanan işlerin takip edildiği web sayfası. **Veritabanı Office 365'teki bir Excel dosyasıdır**;
sayfa Microsoft Graph API ile bu dosyayı doğrudan okur ve yazar. Sunucu gerekmez, statik bir sayfadır
(GitHub Pages, SharePoint, IIS vb. her yerde barındırılabilir).

```
Tarayıcı (index.html)  ──MSAL ile giriş──►  Microsoft Entra ID
        │
        └──Graph API──►  OneDrive / SharePoint  ──►  IsTakip.xlsx
                                                     ├─ Gorevler  (tablo)
                                                     ├─ Birimler  (tablo)
                                                     └─ Özet      (formüller)
```

> Ayrıca: [`spor-rezervasyon/`](spor-rezervasyon/) — spor tesisi rezervasyon sistemi için tıklanabilir arayüz prototipi.

## Özellikler

- Üst bantta günün özeti ve tamamlanma halkası
- Özet kartları: toplam, tamamlanan, açık ve geciken iş sayısı (tıklayınca listeyi süzer)
- Birim performansı: her birim için renkli simge, yüzde ve durum çubuğu (tıklayınca o birime göre süzer)
- Durum dağılımı grafiği ve "Yaklaşan terminler" listesi (gecikenler ve önümüzdeki 7 gün)
- **Liste** ve **Pano** görünümü; panoda kartları sürükleyerek durum değiştirme
- Görev listesi: arama, birim/durum filtresi, "sadece gecikenler", sütuna göre sıralama
- **Tek tıkla "bitti" işaretleme** → Excel'de `TamamlandıMı = TRUE`, `Durum = Tamamlandı`, `TamamlanmaTarihi = bugün`
- Yeni görev ekleme ve düzenleme (görev numarası otomatik: `G-0001`, `G-0002`…)
- Termini geçmiş işler kırmızı işaretlenir, kaç gün geciktiği yazılır
- Mobil uyumlu, açık ve koyu tema

## Hızlı deneme (demo modu)

`js/config.js` içindeki `clientId` boşsa sayfa **demo modunda** açılır, örnek verilerle çalışır ve
verileri tarayıcıda tutar:

```bash
python3 -m http.server 8000
# http://localhost:8000
```

## Excel dosyası

Hazır şablon: [`sablon/IsTakip.xlsx`](sablon/IsTakip.xlsx). Bunu OneDrive'a veya SharePoint'e yükleyin.

**`Gorevler` tablosu** (Excel'de *Tablo* olarak tanımlı olmalı, tablo adı `Gorevler`):

| Sütun | İçerik |
|---|---|
| GörevNo | `G-0001` gibi benzersiz numara (web sayfası otomatik verir) |
| AtamaTarihi | Tarih |
| Birim | `Birimler` tablosundaki bir birim adı |
| GörevTanımı | Metin |
| Öncelik | Yüksek / Orta / Düşük |
| TerminTarihi | Tarih |
| Durum | Atandı / Devam Ediyor / Tamamlandı / İptal |
| TamamlandıMı | TRUE / FALSE (Excel'de *Ekle > Onay Kutusu* ile onay kutusuna çevrilebilir) |
| TamamlanmaTarihi | Tarih |
| Not | Metin |

**`Birimler` tablosu:** `BirimKodu`, `BirimAdı`, `Sorumlu`, `E-posta`

Kurallar:
- Sütun sırası önemli değildir, eşleştirme başlık adına göre yapılır.
- Tabloya kendi sütunlarınızı (ör. formül sütunları) ekleyebilirsiniz; web sayfası bunlara dokunmaz.
- Satır silmek yerine `Durum = İptal` yapın, böylece geçmiş kaybolmaz.
- Excel'de satırları sıralamak veya filtrelemek sorun değildir; güncelleme GörevNo ile yapılır.

Şablonu yeniden üretmek için: `pip install openpyxl && python tools/sablon_olustur.py`

## Office 365'e bağlama

### 1. Uygulama kaydı (bir kez yapılır)

1. [Azure Portal](https://portal.azure.com) → **Microsoft Entra ID** → **Uygulama kayıtları** → **Yeni kayıt**
2. Ad: `İş Takip`. Desteklenen hesap türleri: *Yalnızca bu kuruluş dizinindeki hesaplar*
3. **Yeniden yönlendirme URI'si** → platform olarak **Tek sayfalı uygulama (SPA)** seçin, adres olarak
   sayfanın yayınlanacağı adresi yazın. Örnekler:
   `https://hsynarslan.github.io/hsynarslan/`, yerel deneme için ayrıca `http://localhost:8000/`
4. **API izinleri** → Microsoft Graph → *Temsilci izinleri*: `User.Read`, `Files.ReadWrite.All`
   (kuruluşunuz kullanıcı onayını kapattıysa BT yöneticisinin "yönetici onayı vermesi" gerekir)
5. **Genel bakış** sayfasından *Uygulama (istemci) kimliği* ve *Dizin (kiracı) kimliği* değerlerini kopyalayın

### 2. `js/config.js` dosyasını doldurun

```js
clientId: "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
tenantId: "yyyyyyyy-yyyy-yyyy-yyyy-yyyyyyyyyyyy",
shareUrl: "https://kurum.sharepoint.com/:x:/g/personal/.../EAbc...?e=XyZ",
```

`shareUrl`, Excel'de **Paylaş → Bağlantıyı kopyala** ile alınan linktir. Dosya kendi OneDrive'ınızdaysa
link yerine `filePath: "Belgeler/IsTakip.xlsx"` de yazabilirsiniz.

### 3. Yayınlayın

Örneğin GitHub Pages: repo **Settings → Pages → Branch** bölümünden ilgili dalı seçin. Yayın adresi,
1. adımdaki yeniden yönlendirme URI'si ile birebir aynı olmalıdır.

## Yetki ve güvenlik

- Kullanıcılar sayfaya kendi Microsoft 365 hesaplarıyla girer; sayfa Excel'e **giriş yapan kişinin yetkisiyle**
  erişir. Dosyayı kiminle paylaştıysanız yalnızca o kişiler verileri görebilir ve değiştirebilir.
- Uygulamada gizli anahtar (client secret) yoktur; `config.js` içindeki kimlikler gizli bilgi sayılmaz.

## Dosyalar

```
index.html              Sayfa
css/styles.css          Görünüm
js/config.js            Ayarlar (clientId, Excel linki, tablo adları, seçenekler)
js/stores.js            Veri katmanı: ExcelStore (Graph API) ve DemoStore
js/app.js               Arayüz mantığı
sablon/IsTakip.xlsx     Excel şablonu
tools/sablon_olustur.py Şablonu üreten betik
```

## Sonraki adımlar için fikirler

- **Power Automate:** Termine 2 gün kala veya süre geçince ilgili birimin sorumlusuna e-posta
  (e-posta adresleri `Birimler` tablosundan alınır)
- Birim sorumlularının yalnızca kendi işlerini görmesi (giriş yapan kişinin e-postasını `Birimler` ile eşleştirerek)
- Aynı anda çok kişi yazacaksa Excel yerine SharePoint Listesi'ne geçiş (veri katmanı `stores.js` içinde ayrı durduğu için arayüz değişmez)
