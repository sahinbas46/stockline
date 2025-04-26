<?php
/*
Plugin Name: Stockline Dropbox Galeri & Koleksiyon
Description: Dropbox klasörü ile entegre, koleksiyon tabanlı, Google Vision destekli SEO uyumlu galeri eklentisi.
Version: 2.1
Author: sahinbas46
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define('STOCKLINE_PATH', plugin_dir_path(__FILE__));

// Görsel mime türleri
add_filter('upload_mimes', function($mimes){
    $mimes['png']  = 'image/png';
    $mimes['jpg']  = 'image/jpeg';
    $mimes['jpeg'] = 'image/jpeg';
    $mimes['gif']  = 'image/gif';
    $mimes['webp'] = 'image/webp';
    return $mimes;
});

// Veritabanı tabloları (dropbox_original_url yeni alan!)
register_activation_hook(__FILE__, 'stockline_activate');
function stockline_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stockline_products (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(191),
            desc_text TEXT,
            tags VARCHAR(191),
            img_url TEXT,
            dropbox_path TEXT,
            dropbox_original_url TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;
    ");
    $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stockline_collections (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            slug VARCHAR(191) NOT NULL UNIQUE
        ) $charset_collate;
    ");
    $wpdb->query("
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stockline_collection_items (
            collection_id BIGINT NOT NULL,
            product_id BIGINT NOT NULL,
            PRIMARY KEY(collection_id, product_id)
        ) $charset_collate;
    ");
    // Tabloya yeni alan ekle (kuruluysa migration)
    $cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}stockline_products LIKE 'dropbox_original_url'");
    if (!$cols) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}stockline_products ADD dropbox_original_url TEXT");
    }
}

// Admin menüler
add_action('admin_menu', 'stockline_admin_menu');
function stockline_admin_menu() {
    add_menu_page(
        'Stockline',
        'Stockline',
        'manage_options',
        'stockline',
        'stockline_dashboard_page',
        'dashicons-images-alt2'
    );
    add_submenu_page(
        'stockline',
        'Ürünlerim',
        'Ürünlerim',
        'manage_options',
        'stockline-products',
        'stockline_products_page'
    );
    add_submenu_page(
        'stockline',
        'Ürün Ekle',
        'Ürün Ekle',
        'manage_options',
        'stockline-product-add',
        'stockline_product_add_page'
    );
    add_submenu_page(
        'stockline',
        'Koleksiyonlar',
        'Koleksiyonlar',
        'manage_options',
        'stockline-collections',
        'stockline_collections_page'
    );
    add_submenu_page(
        'stockline',
        'Ayarlar',
        'Ayarlar',
        'manage_options',
        'stockline-settings',
        'stockline_settings_page'
    );
    // Düzenle sayfası menüde görünmesin, ama route olarak açılsın
    add_submenu_page(
        'stockline',
        'Ürün Düzenle',
        '',
        'manage_options',
        'stockline-product-edit',
        'stockline_product_edit_page'
    );
}
function stockline_dashboard_page() { echo '<h1>Stockline Dashboard</h1>'; }
function stockline_products_page() { require_once STOCKLINE_PATH . 'admin/products-list.php'; }
function stockline_product_add_page() { require_once STOCKLINE_PATH . 'admin/product-add.php'; }
function stockline_collections_page() { require_once STOCKLINE_PATH . 'admin/collections.php'; }
function stockline_settings_page() { require_once STOCKLINE_PATH . 'admin/settings.php'; }
function stockline_product_edit_page() { require_once STOCKLINE_PATH . 'admin/product-edit.php'; }

// Güvenli indirme fonksiyonu (cURL ile)
function stockline_download_image($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; StocklineBot/1.0)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $data = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http == 200 && $data !== false) {
        return $data;
    }
    return false;
}

// Ürünleri Dropbox'tan içeri aktaran AJAX handler
add_action('wp_ajax_stockline_dropbox_import', 'stockline_dropbox_import_callback');
function stockline_dropbox_import_callback() {
    if (!current_user_can('manage_options')) die('Yetkisiz.');

    $link = trim($_POST['dropbox_folder_link']);
    if (!$link) die(json_encode(['success'=>false,'msg'=>'Dropbox klasör linki boş!']));

    $access_token = get_option('stockline_dropbox_access_token', '');
    if (!$access_token) die(json_encode(['success'=>false,'msg'=>'Dropbox Access Token girilmemiş!']));

    if (!preg_match('~/scl/fo/([a-zA-Z0-9]+)/~', $link)) {
        die(json_encode(['success'=>false, 'msg'=>'Link formatı hatalı!']));
    }

    $api_url = "https://api.dropboxapi.com/2/sharing/get_shared_link_metadata";
    $payload = [ "url" => $link ];
    $headers = [
        "Authorization: Bearer $access_token",
        "Content-Type: application/json"
    ];
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $resp = curl_exec($ch);
    curl_close($ch);
    $meta = json_decode($resp, true);

    if (isset($meta['.tag']) && $meta['.tag'] === 'folder') {
        $dropbox_path = ""; // kök için boş string!
    } elseif (isset($meta['path_lower'])) {
        $dropbox_path = $meta['path_lower'];
    } else {
        $debug_url = admin_url('admin-ajax.php?action=stockline_download_debug');
        die(json_encode([
            'success'=>false,
            'msg'=>'Klasör bulunamadı! (<a href="'.$debug_url.'" target="_blank">Debug dosyasını indir</a>)'
        ]));
    }

    $api_url = "https://api.dropboxapi.com/2/files/list_folder";
    $payload = [
        "path" => $dropbox_path,
        "recursive" => false,
        "include_media_info" => true,
        "shared_link" => [
            "url" => $link
        ]
    ];
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $resp = curl_exec($ch);
    curl_close($ch);

    $debug_file = plugin_dir_path(__FILE__).'dropbox_debug.txt';
    file_put_contents($debug_file, $resp);

    $list = json_decode($resp, true);

    if (!isset($list['entries'])) {
        $debug_url = admin_url('admin-ajax.php?action=stockline_download_debug');
        die(json_encode([
            'success'=>false,
            'msg'=>'Klasör içeriği alınamadı! (<a href="'.$debug_url.'" target="_blank">Debug dosyasını indir</a>)'
        ]));
    }

    $debug_links_file = plugin_dir_path(__FILE__).'dropbox_debug_links.txt';
    file_put_contents($debug_links_file, "");

    $added = 0;
    global $wpdb;
    foreach($list['entries'] as $file) {
        if ($file['.tag'] !== 'file') continue;
        $name = $file['name'];
        $dropbox_file_path = $file['path_lower'];
        if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $name)) continue;

        // Orijinal yüksek çözünürlüklü bağlantıyı al (get_temporary_link)
        $api_url = "https://api.dropboxapi.com/2/files/get_temporary_link";
        $payload = ["path" => $dropbox_file_path];
        $headers = [
            "Authorization: Bearer $access_token",
            "Content-Type: application/json"
        ];
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $tmp_resp = curl_exec($ch);
        curl_close($ch);
        $tmp_data = json_decode($tmp_resp, true);

        if (isset($tmp_data['link'])) {
            $img_url = $tmp_data['link'];
            $dropbox_original_url = $img_url;
            file_put_contents($debug_links_file, "[$name][get_temporary_link]\n$img_url\n\n", FILE_APPEND);
        } else {
            file_put_contents($debug_links_file, "[$name][get_temporary_link error]\n$tmp_resp\n", FILE_APPEND);
            continue;
        }

        // Yerel kütüphane için dosyayı indir & doğrula
        $safe_name = strtolower(preg_replace('/[^a-zA-Z0-9\-_\.]/', '-', $name));
        $safe_ext = pathinfo($safe_name, PATHINFO_EXTENSION);
        if (!$safe_ext) $safe_ext = "png";
        $img_data = stockline_download_image($img_url);

        if ($img_data === false || strlen($img_data) < 1000) {
            file_put_contents($debug_links_file, "[$name][indirilen dosya çok küçük veya boş]\n", FILE_APPEND);
            continue;
        }
        $finfo = finfo_open();
        $mime = finfo_buffer($finfo, $img_data, FILEINFO_MIME_TYPE);
        finfo_close($finfo);

        if (strpos($mime, 'image/') !== 0) {
            file_put_contents($debug_links_file, "[$name][indirilen içerik görsel değil: $mime]\n", FILE_APPEND);
            continue;
        }

        $upload = wp_upload_bits($safe_name, null, $img_data);

        if ($upload['error']) {
            file_put_contents($debug_links_file, "[$name][wp_upload_bits error]\n".print_r($upload['error'], true)."\n", FILE_APPEND);
            continue;
        }

        add_filter('wp_check_filetype_and_ext', function($data, $file, $filename, $mimes) use ($safe_ext) {
            $data['ext'] = $safe_ext;
            $data['type'] = "image/$safe_ext";
            $data['proper_filename'] = $filename;
            return $data;
        }, 10, 4);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $file_array = array(
            'name'     => $safe_name,
            'tmp_name' => $upload['file']
        );

        $attach_id = media_handle_sideload( $file_array, 0 );

        remove_all_filters('wp_check_filetype_and_ext');

        if ( is_wp_error($attach_id) ) {
            file_put_contents($debug_links_file, "[$name][media_handle_sideload error]\n".print_r($attach_id, true)."\n", FILE_APPEND);
            @unlink($upload['file']);
            continue;
        }

        $img_url = wp_get_attachment_url( $attach_id );

        $wpdb->insert($wpdb->prefix.'stockline_products', [
            'title' => $name,
            'desc_text' => '',
            'tags' => '',
            'img_url' => $img_url,
            'dropbox_path' => $dropbox_file_path,
            'dropbox_original_url' => $dropbox_original_url
        ]);
        $added++;
    }
    $debug_url = admin_url('admin-ajax.php?action=stockline_download_debug');
    $debug_links_url = admin_url('admin-ajax.php?action=stockline_download_debug_links');
    die(json_encode([
        'success'=>true,
        'msg'=>"$added görsel eklendi! (<a href=\"$debug_url\" target=\"_blank\">Klasör debug</a> / <a href=\"$debug_links_url\" target=\"_blank\">Link debug</a>)"
    ]));
}

// Ürün silme handler (GET ile)
add_action('admin_init', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'stockline-products' && isset($_GET['delete'])) {
        global $wpdb;
        $id = intval($_GET['delete']);
        $wpdb->delete($wpdb->prefix.'stockline_products', ['id'=>$id]);
        wp_redirect(admin_url('admin.php?page=stockline-products&msg=silindi'));
        exit;
    }
});

// Ürün düzenleme handler (POST)
add_action('admin_post_stockline_product_edit', function() {
    if (!current_user_can('manage_options')) wp_die('Yetkisiz');
    global $wpdb;
    $id = intval($_POST['id']);
    $title = sanitize_text_field($_POST['title']);
    $desc_text = sanitize_textarea_field($_POST['desc_text']);
    $tags = sanitize_text_field($_POST['tags']);
    $wpdb->update($wpdb->prefix.'stockline_products', [
        'title' => $title,
        'desc_text' => $desc_text,
        'tags' => $tags
    ], ['id'=>$id]);
    wp_redirect(admin_url('admin.php?page=stockline-products&msg=duzenlendi'));
    exit;
});

// DEBUG dosyası indirme
add_action('wp_ajax_stockline_download_debug', function() {
    $file = plugin_dir_path(__FILE__).'dropbox_debug.txt';
    if (!file_exists($file)) {
        wp_die('Debug dosyası bulunamadı.');
    }
    header('Content-Description: File Transfer');
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename=dropbox_debug.txt');
    readfile($file);
    exit;
});
add_action('wp_ajax_stockline_download_debug_links', function() {
    $file = plugin_dir_path(__FILE__).'dropbox_debug_links.txt';
    if (!file_exists($file)) {
        wp_die('Debug dosyası bulunamadı.');
    }
    header('Content-Description: File Transfer');
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename=dropbox_debug_links.txt');
    readfile($file);
    exit;
});

// Frontend Shortcode
require_once STOCKLINE_PATH . 'includes/gallery-shortcode.php';