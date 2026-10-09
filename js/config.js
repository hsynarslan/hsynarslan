// İş Takip — yapılandırma
// clientId boş bırakılırsa uygulama DEMO modunda çalışır (veriler tarayıcıda tutulur).
window.APP_CONFIG = {
  // Azure Portal > Microsoft Entra ID > Uygulama kayıtları > "Uygulama (istemci) kimliği"
  clientId: "",

  // Kiracı (tenant): Azure'daki "Dizin (kiracı) kimliği" ya da kurumun onmicrosoft.com alan adı
  tenantId: "stuyasaredu.onmicrosoft.com",

  // Boş bırakılırsa sayfanın kendi adresi kullanılır. Azure'daki "Yeniden yönlendirme URI" ile aynı olmalı.
  redirectUri: "",

  // Google E-Tablolar web uygulaması adresi (…/exec). Doldurulursa sayfa her cihazda yalnızca
  // E-Tablodaki "İş Takip > 3. Web şifresi belirle" menüsüyle belirlenen şifreyi sorar.
  // Adres tek başına veriye erişim sağlamaz; her istek şifre ya da bağlantı anahtarı gerektirir.
  sheetsUrl: "https://script.google.com/macros/s/AKfycbxT3ddcEtcvtX9lns8H5EBtLNF4c-P8iqcOGwSgMutnNFDKAG2g5usMpMdGY0yROhEbDQ/exec",

  // Excel dosyasının paylaşım linki (OneDrive / SharePoint > Paylaş > Bağlantıyı kopyala).
  // Boş bırakılabilir: sayfadaki "Ayarlar" penceresinden girilen link tarayıcıda saklanır
  // ve buradaki değerin yerine geçer. Herkese açık bir repoda linki buraya yazmayın.
  shareUrl: "",

  // Paylaşım linki yerine kendi OneDrive'ınızdaki yol da verilebilir, ör. "Belgeler/IsTakip.xlsx"
  filePath: "",

  // Excel içindeki tablo adları (Tablo Tasarımı > Tablo Adı)
  tasksTable: "Gorevler",
  unitsTable: "Birimler",

  // Seçenek listeleri
  statuses: ["Atandı", "Devam Ediyor", "Tamamlandı", "İptal"],
  priorities: ["Yüksek", "Orta", "Düşük"],

  // Görev numarası öneki
  idPrefix: "G-"
};
