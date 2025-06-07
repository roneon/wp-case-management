<?php
/**
 * Database Upgrade Script for Case Manager Pro
 * Web tabanlı veritabanı upgrade aracı
 */

// Güvenlik kontrolü - sadece admin kullanıcılar erişebilir
if (!defined('ABSPATH')) {
    // WordPress'i yükle
    $wp_config_path = '';
    
    // wp-config.php dosyasını bul
    $possible_paths = array(
        './wp-config.php',
        '../wp-config.php',
        '../../wp-config.php',
        '../../../wp-config.php'
    );
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $wp_config_path = $path;
            break;
        }
    }
    
    if (empty($wp_config_path)) {
        die('WordPress wp-config.php dosyası bulunamadı!');
    }
    
    require_once($wp_config_path);
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    require_once(ABSPATH . 'wp-load.php');
}

// Admin kontrolü
if (!current_user_can('manage_options')) {
    wp_die('Bu sayfaya erişim yetkiniz yok. Lütfen admin olarak giriş yapın.');
}

// Plugin dosyalarını yükle
$plugin_path = dirname(__FILE__);

// WordPress plugin dizininde olup olmadığını kontrol et
$wp_plugins_dir = WP_PLUGIN_DIR;
$plugin_name = 'case-manager-pro'; // veya rnncase

// Olası plugin yolları
$possible_plugin_paths = array(
    $wp_plugins_dir . '/' . $plugin_name,
    $wp_plugins_dir . '/rnncase',
    $wp_plugins_dir . '/case-manager-pro',
    dirname(__FILE__), // Mevcut dizin
    ABSPATH . 'wp-content/plugins/' . $plugin_name,
    ABSPATH . 'wp-content/plugins/rnncase',
    ABSPATH . 'wp-content/plugins/case-manager-pro'
);

$found_plugin_path = null;
foreach ($possible_plugin_paths as $path) {
    if (file_exists($path . '/case-manager-pro.php')) {
        $found_plugin_path = $path;
        break;
    }
}

if ($found_plugin_path) {
    $plugin_path = $found_plugin_path;
} else {
    // Eğer plugin dizini bulunamazsa, mevcut dizini kullan
    $plugin_path = dirname(__FILE__);
}

// Ana plugin dosyasını yükle (CMP_VERSION sabiti için)
$main_plugin_file = $plugin_path . '/case-manager-pro.php';
if (file_exists($main_plugin_file)) {
    // Output buffering ile çıktıları bastır
    ob_start();
    try {
        require_once($main_plugin_file);
    } catch (Exception $e) {
        // Hataları yakala ama devam et
        error_log('Main plugin load error: ' . $e->getMessage());
    }
    ob_end_clean();
}

$database_file = $plugin_path . '/includes/class-cmp-database.php';

// Debug bilgisi
$debug_info = array(
    'wp_plugins_dir' => $wp_plugins_dir,
    'possible_paths' => $possible_plugin_paths,
    'found_plugin_path' => $found_plugin_path,
    'plugin_path' => $plugin_path,
    'main_plugin_file' => $main_plugin_file,
    'main_plugin_exists' => file_exists($main_plugin_file),
    'database_file' => $database_file,
    'file_exists' => file_exists($database_file),
    'current_dir' => getcwd(),
    'script_name' => $_SERVER['SCRIPT_NAME'],
    'cmp_version_defined' => defined('CMP_VERSION'),
    'class_exists' => class_exists('CMP_Database')
);

if (file_exists($database_file)) {
    require_once($database_file);
} else {
    // Alternatif yolları dene
    $alternative_paths = array(
        $plugin_path . '/includes/class-cmp-database.php',
        './includes/class-cmp-database.php',
        '../includes/class-cmp-database.php',
        dirname($_SERVER['SCRIPT_FILENAME']) . '/includes/class-cmp-database.php',
        WP_PLUGIN_DIR . '/case-manager-pro/includes/class-cmp-database.php',
        WP_PLUGIN_DIR . '/rnncase/includes/class-cmp-database.php'
    );
    
    $found = false;
    foreach ($alternative_paths as $alt_path) {
        if (file_exists($alt_path)) {
            require_once($alt_path);
            $found = true;
            $debug_info['found_database_path'] = $alt_path;
            break;
        }
    }
    
    if (!$found) {
        echo '<pre>';
        echo "Debug Bilgileri:\n";
        print_r($debug_info);
        echo "\nDenenen Alternatif Yollar:\n";
        print_r($alternative_paths);
        echo '</pre>';
        die('CMP Database class dosyası bulunamadı! Lütfen dosyanın doğru konumda olduğundan emin olun.');
    }
}

// CMP_VERSION sabitini kontrol et
if (!defined('CMP_VERSION')) {
    define('CMP_VERSION', '1.0.0');
}

// Class'ın yüklendiğini kontrol et
if (!class_exists('CMP_Database')) {
    echo '<pre>';
    echo "Debug Bilgileri:\n";
    print_r($debug_info);
    echo '</pre>';
    die('CMP_Database class\'ı yüklenemedi! Lütfen dosyaların doğru olduğundan emin olun.');
}

// AJAX işlemi kontrolü
if (isset($_POST['action']) && $_POST['action'] === 'run_upgrade') {
    // Nonce kontrolü
    if (!wp_verify_nonce($_POST['nonce'], 'cmp_upgrade_nonce')) {
        wp_die('Güvenlik kontrolü başarısız!');
    }
    
    try {
        // Database instance'ını al
        $db = CMP_Database::get_instance();
        
        // Upgrade işlemini çalıştır
        CMP_Database::upgrade_tables();
        
        // Tablo yapısını kontrol et
        global $wpdb;
        $table_files = $wpdb->prefix . 'cmp_files';
        
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_files}");
        
        $result = array(
            'success' => true,
            'message' => 'Upgrade işlemi başarıyla tamamlandı!',
            'columns' => $columns
        );
        
        wp_send_json($result);
        
    } catch (Exception $e) {
        wp_send_json(array(
            'success' => false,
            'message' => 'HATA: ' . $e->getMessage()
        ));
    }
}

// Mevcut tablo durumunu kontrol et
global $wpdb;
$table_files = $wpdb->prefix . 'cmp_files';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_files}'") == $table_files;

if ($table_exists) {
    $current_columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_files}");
} else {
    $current_columns = array();
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Manager Pro - Database Upgrade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .upgrade-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        .btn-upgrade {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-upgrade:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        .column-item {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 5px;
        }
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="upgrade-container">
        <div class="card">
            <div class="card-header position-relative">
                <h2 class="mb-0">
                    <i class="fas fa-database me-3"></i>
                    Case Manager Pro - Database Upgrade
                </h2>
                <p class="mb-0 mt-2 opacity-75">Veritabanı tablolarını güncelleyin</p>
                
                <?php if ($table_exists): ?>
                    <span class="badge bg-success status-badge">
                        <i class="fas fa-check me-1"></i>Tablo Mevcut
                    </span>
                <?php else: ?>
                    <span class="badge bg-warning status-badge">
                        <i class="fas fa-exclamation-triangle me-1"></i>Tablo Bulunamadı
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="card-body">
                <!-- Sistem Bilgileri -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-info-circle text-info me-2"></i>Sistem Bilgileri
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><strong>WordPress:</strong> <?php echo get_bloginfo('version'); ?></li>
                                    <li><strong>PHP:</strong> <?php echo PHP_VERSION; ?></li>
                                    <li><strong>MySQL:</strong> <?php echo $wpdb->db_version(); ?></li>
                                    <li><strong>Tablo Öneki:</strong> <?php echo $wpdb->prefix; ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-user text-primary me-2"></i>Kullanıcı Bilgileri
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><strong>Kullanıcı:</strong> <?php echo wp_get_current_user()->display_name; ?></li>
                                    <li><strong>Email:</strong> <?php echo wp_get_current_user()->user_email; ?></li>
                                    <li><strong>Rol:</strong> <?php echo implode(', ', wp_get_current_user()->roles); ?></li>
                                    <li><strong>Yetki:</strong> <span class="badge bg-success">Admin</span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Mevcut Tablo Yapısı -->
                <?php if ($table_exists && !empty($current_columns)): ?>
                    <div class="mb-4">
                        <h5>
                            <i class="fas fa-table text-primary me-2"></i>
                            Mevcut Tablo Yapısı (<?php echo $table_files; ?>)
                        </h5>
                        <div class="row">
                            <?php foreach ($current_columns as $column): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="column-item">
                                        <strong><?php echo $column->Field; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $column->Type; ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Upgrade Butonu -->
                <div class="text-center">
                    <button id="upgradeBtn" class="btn btn-upgrade btn-lg text-white">
                        <i class="fas fa-rocket me-2"></i>
                        Veritabanını Güncelle
                    </button>
                    <p class="text-muted mt-3 mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Bu işlem eksik kolonları ekleyecek ve mevcut verileri koruyacaktır.
                    </p>
                </div>
                
                <!-- Sonuç Alanı -->
                <div id="resultArea" class="mt-4" style="display: none;"></div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Yükleniyor...</span>
            </div>
            <h5>Veritabanı Güncelleniyor...</h5>
            <p class="text-muted mb-0">Lütfen bekleyin, işlem tamamlanıyor.</p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('upgradeBtn').addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            const loadingOverlay = document.getElementById('loadingOverlay');
            const resultArea = document.getElementById('resultArea');
            
            // Loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Güncelleniyor...';
            loadingOverlay.style.display = 'flex';
            
            // AJAX isteği
            const formData = new FormData();
            formData.append('action', 'run_upgrade');
            formData.append('nonce', '<?php echo wp_create_nonce('cmp_upgrade_nonce'); ?>');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loadingOverlay.style.display = 'none';
                
                if (data.success) {
                    resultArea.innerHTML = `
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle"></i> Upgrade Completed Successfully!</h5>
                            <p>Database tables have been upgraded to the latest version.</p>
                            
                            <h6>Upgraded Components:</h6>
                            <ul>
                                <li><i class="fas fa-table text-primary"></i> cmp_files table structure updated</li>
                                <li><i class="fas fa-plus text-success"></i> Added: original_filename column</li>
                                <li><i class="fas fa-plus text-success"></i> Added: stored_filename column</li>
                                <li><i class="fas fa-plus text-success"></i> Added: file_url column</li>
                                <li><i class="fas fa-plus text-success"></i> Added: mime_type column</li>
                                <li><i class="fas fa-plus text-success"></i> Added: attachment_id column</li>
                                <li><i class="fas fa-plus text-success"></i> Added: uploaded_by column</li>
                                <li><i class="fas fa-plus text-success"></i> Added: is_temporary column</li>
                                <li><i class="fas fa-index text-info"></i> Database indexes optimized</li>
                            </ul>
                        </div>
                        
                        <div class="mt-4">
                            <h6><i class="fas fa-database"></i> Database Updated Successfully!</h6>
                            <p class="text-success">All required columns have been added to the cmp_files table.</p>
                        </div>
                    `;
                    
                    btn.innerHTML = '<i class="fas fa-check me-2"></i>Tamamlandı!';
                    btn.classList.remove('btn-upgrade');
                    btn.classList.add('btn-success');
                    
                    // 3 saniye sonra sayfayı yenile
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                    
                } else {
                    resultArea.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Hata!</strong> ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                    
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
                
                resultArea.style.display = 'block';
            })
            .catch(error => {
                loadingOverlay.style.display = 'none';
                console.error('Error:', error);
                
                resultArea.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Bağlantı Hatası!</strong> Lütfen tekrar deneyin.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                
                resultArea.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    </script>
</body>
</html> 