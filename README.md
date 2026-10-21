# NFC Kartvizit

PHP + MySQL tabanlı NFC dijital kartvizit ve **öğrenci dijital portfolyosu** uygulaması.

## Özellikler
- Dijital kartvizit (`/u/kullaniciadi`): iletişim, sosyal medya, vCard, QR, "Bilgini Bırak" formu
- Öğrenci portfolyosu (`/p/kullaniciadi`): ortaokul/lise öğrencileri için okul, sınıf, hakkında, hedef, yetenekler ve başarı / proje / sertifika / etkinlik zaman çizelgesi
- Kalıcı NFC linki (`/t/KOD`): panelden seçime göre kartvizite **veya** portfolyoya yönlendirir; etikete yeniden yazmak gerekmez
- Analitik, kişi (lead) yönetimi, admin paneli, Google ile giriş

## Kurulum
1. `database.sql` dosyasını içe aktarın (temiz kurulum).
   Mevcut kurulumu güncelliyorsanız sırasıyla `migration_v2.sql`, `migration_v3.sql`, `migration_v4.sql` dosyalarını çalıştırın.
2. `config.php` içinde veritabanı bilgilerini ve `SITE_URL` değerini ayarlayın.
3. Site bir alt klasörde çalışıyorsa `.htaccess` içindeki `RewriteBase` değerini ona göre ayarlayın (ör. `/nfc/`).
4. `uploads/` klasörüne yazma izni verin.
