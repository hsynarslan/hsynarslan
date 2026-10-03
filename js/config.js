// İş Takip — yapılandırma
// clientId boş bırakılırsa uygulama DEMO modunda çalışır (veriler tarayıcıda tutulur).
window.APP_CONFIG = {
  // Azure Portal > Microsoft Entra ID > Uygulama kayıtları > "Uygulama (istemci) kimliği"
  clientId: "",

  // Kiracı (tenant): Azure'daki "Dizin (kiracı) kimliği" ya da kurumun onmicrosoft.com alan adı
  tenantId: "stuyasaredu.onmicrosoft.com",

  // Boş bırakılırsa sayfanın kendi adresi kullanılır. Azure'daki "Yeniden yönlendirme URI" ile aynı olmalı.
  redirectUri: "",

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
