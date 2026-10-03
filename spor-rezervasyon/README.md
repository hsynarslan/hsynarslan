# Spor Rezervasyon — tıklanabilir prototip

Üniversite spor tesisleri rezervasyon sisteminin arayüz prototipi. Tasarım brief'indeki 8 ekranın tamamını,
tanımlanan tasarım diliyle (renkler, 400/500 tipografi, 8px/12px köşe, 0.5px kenarlık, Tabler outline ikonlar)
çalışır halde gösterir. Sunucu gerekmez; veriler tarayıcıda (`localStorage`) tutulur.

```bash
python3 -m http.server 8000
# http://localhost:8000/spor-rezervasyon/
```

## Deneme hesapları

Herhangi bir şifreyle:

| Kullanıcı adı | Rol | Görebildiği menüler |
|---|---|---|
| `ogrenci` | Kullanıcı (ilk girişte feragatname ister) | Yeni · Rezervasyonlarım |
| `sks` | SKS onaylayıcı | + Onaylar · İstatistik |
| `admin` | Admin | + Yönetim |

Örnek verilere dönmek için tarayıcı konsolunda `localStorage.clear()` çalıştırıp sayfayı yenileyin.

## Ekranlar

1. **Giriş** — ortalanmış kart, hatalı girişte kırmızı uyarı şeridi
2. **Ana sayfa** — "Hoş geldiniz, {ad}" ve iki büyük kutu; feragatname yoksa otomatik yönlendirme
3. **Feragatname** — kaydırılabilir metin, onay kutusu işaretlenmeden buton pasif
4. **Yeni rezervasyon** — alan, tarih/başlangıç/bitiş, takım, öğrenci numarası chip girişi, sarı ön rezervasyon uyarısı,
   gönderimde yeşil başarı / kırmızı hata
5. **Rezervasyonlarım** — durum rozetli kartlar, reddedilenlerde sebep, ön rezervasyon ve onaylılarda iptal
6. **Onaylar** — adet rozeti, Bireysel/Takım rozeti, feragatname durumu, Onayla / Reddet (sebep + Vazgeç)
7. **İstatistik** — toplam rezervasyon ve kişi kartları, alan bazında tablo ve katılım oranı
8. **Yönetim** — Odalar, Açık saatler, Kapalı günler sekmeleri

Durum makinesi: `PENDING → APPROVED → COMPLETED`, ya da `REJECTED` / `CANCELLED`.

Talep gönderilirken yapılan kontroller: geçmiş tarih, bitiş > başlangıç, en fazla 3 saat, kapalı günler, alanın o
günkü açık saatleri, kapasite; münhasır alanlarda çakışan talep, paylaşımlı alanlarda o saatteki toplam kişi sayısı.

## Dosyalar

```
index.html       Sayfa iskeleti
css/styles.css   Tasarım dili (renk değişkenleri :root içinde)
js/app.js        Ekranlar, yönlendirme (#hash), örnek veri ve kurallar
```

Gerçek sisteme geçerken `js/app.js` içindeki giriş (SSO) ve `load`/`save` fonksiyonları sunucu API'si ile
değiştirilir; ekranlar aynı kalır.
