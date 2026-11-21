/* Satır içi script kullanılmaz (CSP: script-src 'self'). Davranışlar data-* öznitelikleriyle bağlanır. */
(function () {
  "use strict";
  // Onay isteyen formlar: <form data-confirm="Emin misiniz?">
  document.addEventListener("submit", function (e) {
    var msg = e.target.getAttribute("data-confirm");
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
  // Değişince gönderilen alanlar: <select data-autosubmit>
  document.addEventListener("change", function (e) {
    if (e.target.hasAttribute("data-autosubmit") && e.target.form) e.target.form.submit();
  });
  // Grup kapalıysa katılımcı listesi seçeneğini pasifleştir
  var group = document.querySelector("[data-group-toggle]");
  var list = document.querySelector("[data-list-toggle]");
  function sync() { if (group && list) { list.disabled = !group.checked; if (!group.checked) list.checked = false; } }
  if (group) { group.addEventListener("change", sync); sync(); }
  // Bitiş saati başlangıçtan önce olamaz
  var start = document.querySelector("[data-start]");
  var end = document.querySelector("[data-end]");
  function syncEnd() {
    if (!start || !end) return;
    Array.prototype.forEach.call(end.options, function (o) { o.disabled = !!start.value && o.value <= start.value && o.value !== "00:00"; });
    if (end.selectedOptions[0] && end.selectedOptions[0].disabled) {
      var first = Array.prototype.find.call(end.options, function (o) { return !o.disabled; });
      if (first) end.value = first.value;
    }
  }
  if (start) { start.addEventListener("change", syncEnd); syncEnd(); }
  // Mobil kenar menü
  document.addEventListener("click", function (e) {
    if (e.target.closest("[data-sidebar-open]")) document.body.classList.add("nav-open");
    if (e.target.closest("[data-sidebar-close]")) document.body.classList.remove("nav-open");
  });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape") document.body.classList.remove("nav-open"); });
  // Giriş tableti: QR görselini düzenli yenile, saati göster
  var qr = document.querySelector("img[data-refresh]");
  if (qr) {
    var base = qr.getAttribute("src");
    setInterval(function () { qr.src = base + "?t=" + Date.now(); }, Math.max(5000, parseInt(qr.getAttribute("data-refresh"), 10) / 2));
  }
  var clock = document.querySelector("[data-clock]");
  if (clock) {
    var tick = function () { clock.textContent = new Date().toLocaleTimeString(document.documentElement.lang || "tr", { hour: "2-digit", minute: "2-digit" }); };
    tick(); setInterval(tick, 10000);
  }
  // Yazdır düğmesi
  document.addEventListener("click", function (e) {
    if (e.target.closest("[data-print]")) { e.preventDefault(); window.print(); }
  });
  // PWA
  if ("serviceWorker" in navigator && location.protocol === "https:") {
    navigator.serviceWorker.register("/sw.js").catch(function () {});
  }
})();
