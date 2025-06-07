<?php
/**
 * Case Manager Pro - User Permissions Helper
 * Bu dosyayı functions.php'ye ekleyebilir veya ayrı bir eklenti olarak kullanabilirsiniz
 */

// Kullanıcıya özel yetki verme
function cmp_grant_user_capability($user_id, $capability) {
    $user = get_user_by('id', $user_id);
    if ($user) {
        $user->add_cap($capability);
        return true;
    }
    return false;
}

// Kullanıcıdan yetki alma
function cmp_remove_user_capability($user_id, $capability) {
    $user = get_user_by('id', $user_id);
    if ($user) {
        $user->remove_cap($capability);
        return true;
    }
    return false;
}

// Kullanıcının yetkilerini kontrol etme
function cmp_user_has_capability($user_id, $capability) {
    $user = get_user_by('id', $user_id);
    if ($user) {
        return $user->has_cap($capability);
    }
    return false;
}

// Kullanıcıya Case Manager Pro rolü atama
function cmp_assign_role_to_user($user_id, $role) {
    $user = get_user_by('id', $user_id);
    if ($user) {
        $user->set_role($role);
        return true;
    }
    return false;
}

// Toplu yetki verme (örnek kullanım)
function cmp_setup_reviewer_permissions($user_id) {
    $capabilities = array(
        'cmp_view_all_cases',
        'cmp_comment_cases',
        'cmp_download_files',
        'cmp_view_case_analytics'
    );
    
    foreach ($capabilities as $cap) {
        cmp_grant_user_capability($user_id, $cap);
    }
}

// Kullanım örnekleri:

// Kullanıcı ID 5'e vaka görme yetkisi ver
// cmp_grant_user_capability(5, 'cmp_view_all_cases');

// Kullanıcı ID 10'u Case Reviewer yap
// cmp_assign_role_to_user(10, 'case_reviewer');

// Kullanıcı ID 15'i tam yetkili Case Manager yap
// cmp_assign_role_to_user(15, 'case_manager');

// Kullanıcının yetkisini kontrol et
// if (cmp_user_has_capability(5, 'cmp_view_all_cases')) {
//     echo "Kullanıcı tüm vakaları görebilir";
// }

/**
 * Admin panelinde kullanıcı listesine yetki bilgisi ekleme
 */
add_filter('manage_users_columns', 'cmp_add_user_columns');
function cmp_add_user_columns($columns) {
    $columns['cmp_permissions'] = 'CMP Permissions';
    return $columns;
}

add_action('manage_users_custom_column', 'cmp_show_user_permissions', 10, 3);
function cmp_show_user_permissions($value, $column_name, $user_id) {
    if ($column_name == 'cmp_permissions') {
        $user = get_user_by('id', $user_id);
        $cmp_caps = array();
        
        $all_caps = array(
            'cmp_submit_case' => 'Submit',
            'cmp_view_own_cases' => 'View Own',
            'cmp_view_all_cases' => 'View All',
            'cmp_edit_all_cases' => 'Edit All',
            'cmp_comment_cases' => 'Comment',
            'cmp_manage_settings' => 'Settings',
            'cmp_view_case_analytics' => 'Analytics'
        );
        
        foreach ($all_caps as $cap => $label) {
            if ($user->has_cap($cap)) {
                $cmp_caps[] = $label;
            }
        }
        
        return !empty($cmp_caps) ? implode(', ', $cmp_caps) : 'None';
    }
    return $value;
}

/**
 * Kullanıcı profil sayfasında CMP yetkileri gösterme
 */
add_action('show_user_profile', 'cmp_show_user_profile_permissions');
add_action('edit_user_profile', 'cmp_show_user_profile_permissions');

function cmp_show_user_profile_permissions($user) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    ?>
    <h3>Case Manager Pro Permissions</h3>
    <table class="form-table">
        <tr>
            <th><label>Current CMP Role</label></th>
            <td>
                <?php
                $roles = $user->roles;
                $cmp_roles = array_intersect($roles, array('case_submitter', 'case_reviewer', 'case_manager'));
                echo !empty($cmp_roles) ? implode(', ', $cmp_roles) : 'None';
                ?>
            </td>
        </tr>
        <tr>
            <th><label>CMP Capabilities</label></th>
            <td>
                <?php
                $cmp_caps = array(
                    'cmp_submit_case' => 'Submit Cases',
                    'cmp_view_own_cases' => 'View Own Cases',
                    'cmp_view_all_cases' => 'View All Cases',
                    'cmp_edit_all_cases' => 'Edit All Cases',
                    'cmp_comment_cases' => 'Comment on Cases',
                    'cmp_manage_settings' => 'Manage Settings',
                    'cmp_view_case_analytics' => 'View Analytics',
                    'cmp_download_files' => 'Download Files',
                    'cmp_delete_all_cases' => 'Delete Cases'
                );
                
                foreach ($cmp_caps as $cap => $label) {
                    $checked = $user->has_cap($cap) ? 'checked' : '';
                    echo "<label><input type='checkbox' name='cmp_caps[]' value='$cap' $checked> $label</label><br>";
                }
                ?>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Kullanıcı profil güncellemesini işleme
 */
add_action('personal_options_update', 'cmp_save_user_profile_permissions');
add_action('edit_user_profile_update', 'cmp_save_user_profile_permissions');

function cmp_save_user_profile_permissions($user_id) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return;
    }
    
    // Tüm CMP yetkilerini kaldır
    $all_cmp_caps = array(
        'cmp_submit_case',
        'cmp_view_own_cases', 
        'cmp_view_all_cases',
        'cmp_edit_all_cases',
        'cmp_comment_cases',
        'cmp_manage_settings',
        'cmp_view_case_analytics',
        'cmp_download_files',
        'cmp_delete_all_cases'
    );
    
    foreach ($all_cmp_caps as $cap) {
        $user->remove_cap($cap);
    }
    
    // Seçilen yetkileri ekle
    if (isset($_POST['cmp_caps']) && is_array($_POST['cmp_caps'])) {
        foreach ($_POST['cmp_caps'] as $cap) {
            if (in_array($cap, $all_cmp_caps)) {
                $user->add_cap($cap);
            }
        }
    }
}
?> 