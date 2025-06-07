# Case Manager Pro - Aktivasyon Sorun Giderme

## Aktivasyon Hatası Alıyorsanız

### 1. Test Eklentisini Deneyin
Önce `case-manager-pro-test.php` dosyasını aktive edin. Bu basit test eklentisi aktivasyon sorunlarını tespit etmemize yardımcı olur.

### 2. Yaygın Çözümler

#### Memory Limit Sorunu
WordPress hosting'inizin PHP memory limit'ini artırın:
- cPanel > PHP Seçenekleri > memory_limit: 256M veya daha yüksek
- wp-config.php dosyasına ekleyin: `ini_set('memory_limit', '256M');`

#### PHP Versiyonu
- PHP 7.4 veya daha yüksek versiyon gereklidir
- Hosting panelinizden PHP versiyonunu kontrol edin

#### Dosya İzinleri
- Plugin klasörü izinleri: 755
- Plugin dosyaları izinleri: 644

#### WordPress Gereksinimleri
- WordPress 5.0 veya daha yüksek
- MySQL 5.6 veya daha yüksek

### 3. Hata Loglarını Kontrol Edin

WordPress hata loglarını kontrol edin:
- cPanel > Hata Logları
- wp-content/debug.log dosyası
- Hosting sağlayıcınızın hata logları

### 4. Adım Adım Aktivasyon

1. Önce test eklentisini aktive edin
2. Test başarılıysa ana eklentiyi aktive edin
3. Hata alırsanız WordPress admin panelinde hata mesajını kontrol edin

### 5. Destek

Sorun devam ederse:
- WordPress admin panelindeki hata mesajını kaydedin
- Hosting sağlayıcınızın hata loglarını kontrol edin
- PHP ve WordPress versiyonlarınızı not edin

## Başarılı Aktivasyon Sonrası

Eklenti başarıyla aktive olduktan sonra:
1. **Ayarlar** > **Case Manager Pro** sayfasına gidin
2. Bulut depolama sağlayıcınızı seçin ve yapılandırın
3. Dosya ayarlarını yapılandırın
4. Test bağlantısı yapın

## Özellikler

- Amazon S3, Google Drive, Dropbox desteği
- Otomatik dosya temizleme
- Kullanıcı rolleri ve yetkileri
- E-posta bildirimleri
- Gelişmiş analytics
- Frontend dashboard
- Çoklu dil desteği

# Case Manager Pro - WordPress Plugin

## 📋 Proje Açıklaması

Case Manager Pro, WordPress için geliştirilmiş profesyonel bir vaka yönetim sistemidir. Kullanıcıların vaka yüklemesi, yetkililerin değerlendirmesi ve bulut tabanlı dosya depolama özelliklerini içerir.

## 🎯 Ana Özellikler

### ✅ Kullanıcı Yönetimi
- **Vaka Yükleyiciler**: Sadece kendi vakalarını görebilir
- **Vaka Değerlendiriciler**: Tüm vakaları görüntüleyebilir, yorum yapabilir
- **Vaka Yöneticileri**: Tam sistem kontrolü

### ✅ Dosya Yönetimi
- 2GB'a kadar dosya desteği
- Otomatik dosya silme (ayarlanabilir süre)
- Dosya türü kısıtlamaları
- Güvenli dosya indirme

### ✅ Bulut Depolama Entegrasyonu
- **Amazon S3**: Profesyonel çözüm
- **Google Drive**: Kolay kullanım
- **Dropbox**: Kullanıcı dostu arayüz

### ✅ Güvenlik Özellikleri
- Yetki tabanlı erişim kontrolü
- Güvenli dosya yükleme
- Aktivite günlüğü
- IP ve kullanıcı ajanı takibi

### ✅ Bildirim Sistemi
- E-posta bildirimleri
- Vaka durumu güncellemeleri
- Sonuç bildirimleri

## 🛠️ Kurulum

### Gereksinimler
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+
- SSL sertifikası (önerilir)

### Kurulum Adımları

1. **Eklenti Dosyalarını Yükleyin**
   ```bash
   # WordPress plugins dizinine kopyalayın
   wp-content/plugins/case-manager-pro/
   ```

2. **Eklentiyi Aktifleştirin**
   - WordPress admin panelinde Eklentiler > Yüklü Eklentiler
   - "Case Manager Pro" eklentisini aktifleştirin

3. **Veritabanı Tablolarını Oluşturun**
   - Eklenti aktifleştirildiğinde otomatik olarak oluşturulur

4. **Kullanıcı Rollerini Ayarlayın**
   - Kullanıcılar > Tüm Kullanıcılar
   - Kullanıcılara uygun rolleri atayın

## ⚙️ Yapılandırma

### Ayarlar Sayfası
`WordPress Admin > Ayarlar > Case Manager Pro`

### Genel Ayarlar
- **E-posta Bildirimleri**: Aktif/Pasif
- **Dashboard Sayfası**: Ana sayfa seçimi

### Bulut Depolama Ayarları

#### Amazon S3
```
Access Key ID: YOUR_ACCESS_KEY
Secret Access Key: YOUR_SECRET_KEY
Bucket Name: your-bucket-name
Region: us-east-1
```

#### Google Drive
```
Client ID: YOUR_CLIENT_ID
Client Secret: YOUR_CLIENT_SECRET
Folder ID: YOUR_FOLDER_ID (opsiyonel)
```

#### Dropbox
```
App Key: YOUR_APP_KEY
App Secret: YOUR_APP_SECRET
Access Token: YOUR_ACCESS_TOKEN
```

### Dosya Yönetimi Ayarları
- **Dosya Saklama Süresi**: 1-365 gün
- **Maksimum Dosya Boyutu**: 1-5120 MB
- **İzin Verilen Dosya Türleri**: pdf,doc,docx,jpg,png,zip

## 👥 Kullanıcı Rolleri

### Case Submitter (Vaka Yükleyici)
- Vaka oluşturma
- Kendi vakalarını görüntüleme
- Dosya yükleme
- Sonuçları görüntüleme

### Case Reviewer (Vaka Değerlendirici)
- Tüm vakaları görüntüleme
- Yorum yapma
- Dosya indirme
- Sonuç belirleme

### Case Manager (Vaka Yöneticisi)
- Tam sistem erişimi
- Kullanıcı yönetimi
- Ayarlar yönetimi
- Raporlama

## 🔧 Shortcode Kullanımı

### Vaka Yükleme Formu
```php
[cmp_submit_case]
```

### Vaka Listesi
```php
[cmp_case_list]
```

### Kullanıcı Dashboard'u
```php
[cmp_dashboard]
```

### Vaka Detayları
```php
[cmp_case_details id="123"]
```

## 🌐 Çoklu Dil Desteği

### Desteklenen Diller
- İngilizce (varsayılan)
- Türkçe (eklenti ile birlikte)

### Yeni Dil Ekleme
1. `languages/case-manager-pro.pot` dosyasını kopyalayın
2. Poedit ile çevirin
3. `languages/` klasörüne kaydedin

## 📊 Veritabanı Yapısı

### Tablolar
- `wp_cmp_cases`: Vaka bilgileri
- `wp_cmp_case_files`: Dosya bilgileri
- `wp_cmp_case_comments`: Yorumlar
- `wp_cmp_notifications`: Bildirimler
- `wp_cmp_activity_log`: Aktivite günlüğü

## 🔒 Güvenlik

### Dosya Güvenliği
- Dosya türü kontrolü
- Boyut sınırlaması
- Güvenli yükleme yolları
- Virüs tarama (opsiyonel)

### Erişim Kontrolü
- Capability tabanlı yetkilendirme
- Nonce doğrulama
- CSRF koruması
- SQL injection koruması

## 🚀 Performans

### Optimizasyonlar
- Veritabanı indeksleri
- Dosya önbellekleme
- AJAX yüklemeleri
- Lazy loading

### Öneriler
- CDN kullanımı
- Önbellek eklentileri
- Veritabanı optimizasyonu
- Düzenli temizlik

## 🐛 Hata Ayıklama

### Debug Modu
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Log Dosyaları
- WordPress: `wp-content/debug.log`
- Plugin: `wp-content/uploads/cmp-logs/`

## 📞 Destek

### Dokümantasyon
- [Kullanıcı Kılavuzu](docs/user-guide.md)
- [Geliştirici API](docs/developer-api.md)
- [SSS](docs/faq.md)

### İletişim
- E-posta: support@example.com
- GitHub Issues: [Sorun Bildirin](https://github.com/username/case-manager-pro/issues)

## 📝 Lisans

Bu eklenti GPL v2 veya üzeri lisansı altında dağıtılmaktadır.

## 🔄 Güncellemeler

### Sürüm 1.0.0
- İlk sürüm
- Temel vaka yönetimi
- Bulut depolama entegrasyonu
- Kullanıcı rolleri

### Gelecek Sürümler
- [ ] Gelişmiş raporlama
- [ ] API entegrasyonları
- [ ] Mobil uygulama
- [ ] Workflow yönetimi

## 🤝 Katkıda Bulunma

1. Fork yapın
2. Feature branch oluşturun
3. Değişikliklerinizi commit edin
4. Pull request gönderin

## ⚠️ Önemli Notlar

- Büyük dosya yüklemeleri için sunucu ayarlarını kontrol edin
- Bulut depolama maliyetlerini takip edin
- Düzenli yedekleme yapın
- Güvenlik güncellemelerini takip edin

---

**Case Manager Pro** - Profesyonel vaka yönetimi için güçlü WordPress eklentisi. 