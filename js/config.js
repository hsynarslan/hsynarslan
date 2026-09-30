// İş Takip — yapılandırma
// clientId boş bırakılırsa uygulama DEMO modunda çalışır (veriler tarayıcıda tutulur).
window.APP_CONFIG = {
  // Azure Portal > Microsoft Entra ID > Uygulama kayıtları > "Uygulama (istemci) kimliği"
  clientId: "",

  // Kiracı (tenant) kimliği ya da "organizations" (iş/okul hesapları için)
  tenantId: "organizations",

  // Boş bırakılırsa sayfanın kendi adresi kullanılır. Azure'daki "Yeniden yönlendirme URI" ile aynı olmalı.
  redirectUri: "",

  // Excel dosyasının paylaşım linki (OneDrive / SharePoint > Paylaş > Bağlantıyı kopyala)
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
