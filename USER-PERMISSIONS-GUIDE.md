# Case Manager Pro - Kullanıcı Yetki Yönetimi Kılavuzu

## Genel Bakış

Case Manager Pro eklentisi, WordPress'in standart kullanıcı rol sistemini genişleterek vaka yönetimi için özel roller ve yetkiler sunar.

## Kullanıcı Rolleri

### 1. Case Submitter (Vaka Gönderici)
**Yetkiler:**
- `cmp_submit_case` - Yeni vaka gönderebilir
- `cmp_view_own_cases` - Sadece kendi vakalarını görebilir
- `cmp_download_files` - Dosya indirebilir

**Kullanım Alanı:** Normal kullanıcılar, müşteriler

### 2. Case Reviewer (Vaka İnceleyici)
**Yetkiler:**
- `cmp_view_all_cases` - Tüm vakaları görebilir
- `cmp_comment_cases` - Vakalara yorum ekleyebilir
- `cmp_download_files` - Dosya indirebilir
- `cmp_view_case_analytics` - Analytics sayfasını görebilir

**Kullanım Alanı:** Destek personeli, inceleyiciler

### 3. Case Manager (Vaka Yöneticisi)
**Yetkiler:**
- Tüm Case Reviewer yetkileri +
- `cmp_edit_all_cases` - Tüm vakaları düzenleyebilir
- `cmp_manage_settings` - Ayarları yönetebilir
- `cmp_delete_all_cases` - Vaka silebilir

**Kullanım Alanı:** Yöneticiler, süpervizörler

## Yetki Yönetimi Yöntemleri

### 1. WordPress Admin Paneli

#### Tek Kullanıcı Düzenleme:
1. **Kullanıcılar > Tüm Kullanıcılar** sayfasına gidin
2. Düzenlemek istediğiniz kullanıcıya tıklayın
3. **Rol** bölümünden Case Manager Pro rollerinden birini seçin
4. **Kullanıcıyı Güncelle** butonuna tıklayın

#### Case Manager Pro User Permissions Sayfası:
1. **Cases > User Permissions** sayfasına gidin
2. Kullanıcı listesinde **Edit Permissions** butonuna tıklayın
3. CMP rolü ve bireysel yetkileri ayarlayın
4. **Update Permissions** butonuna tıklayın

#### Toplu Rol Atama:
1. **Cases > User Permissions** sayfasında kullanıcıları seçin
2. **Bulk Assign Roles** butonuna tıklayın
3. Atamak istediğiniz rolü seçin
4. **Assign Role** butonuna tıklayın

### 2. Programatik Yöntemler

#### Kullanıcıya Rol Atama:
```php
// Kullanıcı ID 5'i Case Reviewer yap
$user = get_user_by('id', 5);
$user->add_role('case_reviewer');

// Veya helper fonksiyon kullanarak
cmp_assign_role_to_user(5, 'case_reviewer');
```

#### Bireysel Yetki Verme:
```php
// Kullanıcıya özel yetki ver
$user = get_user_by('id', 5);
$user->add_cap('cmp_view_all_cases');

// Veya helper fonksiyon kullanarak
cmp_grant_user_capability(5, 'cmp_view_all_cases');
```

#### Yetki Kontrolü:
```php
// Kullanıcının yetkisini kontrol et
if (current_user_can('cmp_view_all_cases')) {
    // Kullanıcı tüm vakaları görebilir
}

// Veya helper fonksiyon kullanarak
if (cmp_user_has_capability(5, 'cmp_view_all_cases')) {
    // Kullanıcı ID 5 tüm vakaları görebilir
}
```

### 3. Functions.php ile Otomatik Atama

```php
// Yeni kullanıcı kaydında otomatik rol atama
add_action('user_register', 'auto_assign_cmp_role');
function auto_assign_cmp_role($user_id) {
    $user = get_user_by('id', $user_id);
    $user->add_role('case_submitter');
}

// E-posta adresine göre rol atama
add_action('user_register', 'assign_role_by_email');
function assign_role_by_email($user_id) {
    $user = get_user_by('id', $user_id);
    $email = $user->user_email;
    
    if (strpos($email, '@company.com') !== false) {
        $user->add_role('case_manager');
    } elseif (strpos($email, '@support.com') !== false) {
        $user->add_role('case_reviewer');
    } else {
        $user->add_role('case_submitter');
    }
}
```

## Özel Yetki Listesi

| Yetki | Açıklama |
|-------|----------|
| `cmp_submit_case` | Yeni vaka gönderebilir |
| `cmp_view_own_cases` | Kendi vakalarını görebilir |
| `cmp_view_all_cases` | Tüm vakaları görebilir |
| `cmp_edit_all_cases` | Tüm vakaları düzenleyebilir |
| `cmp_comment_cases` | Vakalara yorum ekleyebilir |
| `cmp_manage_settings` | Eklenti ayarlarını yönetebilir |
| `cmp_view_case_analytics` | Analytics sayfasını görebilir |
| `cmp_download_files` | Dosya indirebilir |
| `cmp_delete_all_cases` | Vaka silebilir |

## Güvenlik Notları

1. **Administrator Yetkisi:** Sadece WordPress administrator'ları kullanıcı yetkilerini değiştirebilir
2. **Yetki Kontrolü:** Tüm işlemler için uygun yetki kontrolü yapılır
3. **Nonce Koruması:** AJAX işlemleri nonce ile korunur
4. **Sanitizasyon:** Tüm kullanıcı girdileri sanitize edilir

## Sorun Giderme

### Kullanıcı Rolleri Görünmüyor
1. Eklentiyi deaktive edip tekrar aktive edin
2. WordPress cache'ini temizleyin
3. Debug sayfasından rol durumunu kontrol edin

### Yetkiler Çalışmıyor
1. Kullanıcının doğru role sahip olduğunu kontrol edin
2. Bireysel yetkilerin doğru atandığını kontrol edin
3. WordPress capabilities cache'ini temizleyin

### Toplu İşlemler Çalışmıyor
1. JavaScript hatalarını kontrol edin
2. AJAX isteklerinin başarılı olduğunu kontrol edin
3. Nonce değerlerinin doğru olduğunu kontrol edin

## Helper Fonksiyonlar

`user-permissions-helper.php` dosyasını temanızın `functions.php` dosyasına dahil ederek aşağıdaki fonksiyonları kullanabilirsiniz:

- `cmp_grant_user_capability($user_id, $capability)`
- `cmp_remove_user_capability($user_id, $capability)`
- `cmp_user_has_capability($user_id, $capability)`
- `cmp_assign_role_to_user($user_id, $role)`
- `cmp_setup_reviewer_permissions($user_id)`

## Örnekler

### Örnek 1: Departmana Göre Rol Atama
```php
function assign_role_by_department($user_id) {
    $department = get_user_meta($user_id, 'department', true);
    
    switch ($department) {
        case 'support':
            cmp_assign_role_to_user($user_id, 'case_reviewer');
            break;
        case 'management':
            cmp_assign_role_to_user($user_id, 'case_manager');
            break;
        default:
            cmp_assign_role_to_user($user_id, 'case_submitter');
    }
}
```

### Örnek 2: Özel Yetki Kombinasyonu
```php
function setup_custom_permissions($user_id) {
    // Sadece kendi vakalarını görebilir ama yorum ekleyebilir
    cmp_grant_user_capability($user_id, 'cmp_view_own_cases');
    cmp_grant_user_capability($user_id, 'cmp_comment_cases');
    cmp_grant_user_capability($user_id, 'cmp_download_files');
}
```

### Örnek 3: Geçici Yetki Verme
```php
function grant_temporary_access($user_id, $days = 30) {
    // Geçici Case Reviewer yetkisi ver
    cmp_assign_role_to_user($user_id, 'case_reviewer');
    
    // Belirli bir tarihte yetkiyi kaldırmak için cron job oluştur
    wp_schedule_single_event(
        time() + ($days * DAY_IN_SECONDS),
        'remove_temporary_cmp_access',
        array($user_id)
    );
}

add_action('remove_temporary_cmp_access', 'remove_cmp_access');
function remove_cmp_access($user_id) {
    $user = get_user_by('id', $user_id);
    $user->remove_role('case_reviewer');
    $user->add_role('case_submitter');
}
``` 