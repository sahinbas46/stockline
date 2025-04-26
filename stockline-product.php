<?php
/* Template Name: Stockline Product Detail */
get_header();
global $wpdb;

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$product_id) { echo '<p>Ürün bulunamadı.</p>'; get_footer(); exit; }

$table = $wpdb->prefix.'stockline_products';
$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $product_id), ARRAY_A);

if (!$product) { echo '<p>Ürün bulunamadı.</p>'; get_footer(); exit; }
?>
<div class="stockline-product-detail" style="max-width:700px;margin:40px auto;padding:20px;background:#fff;border-radius:12px;box-shadow:0 2px 12px #0002;">
    <img src="<?= esc_url($product['img_url']) ?>" alt="<?= esc_attr($product['title']) ?>" style="width:100%;max-width:500px;display:block;margin:0 auto 24px auto;border-radius:8px;">
    <h1 style="text-align:center;"><?= esc_html($product['title']) ?></h1>
    <?php if ($product['desc_text']): ?>
        <div style="margin-bottom:18px;text-align:center;"><?= esc_html($product['desc_text']) ?></div>
    <?php endif; ?>
    <?php if ($product['tags']): ?>
        <div style="text-align:center;margin-bottom:18px;color:#666;">
            <?php foreach (explode(',', $product['tags']) as $tag): ?>
                <span style="display:inline-block;padding:2px 10px;border-radius:6px;background:#efefef;margin-right:8px;margin-bottom:4px;"><?= esc_html(trim($tag)) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($product['dropbox_original_url']): ?>
        <div style="text-align:center;margin-top:18px;">
            <a href="<?= esc_url($product['dropbox_original_url']) ?>" class="button" style="padding:12px 28px;font-size:1.1em;border-radius:6px;background:#222;color:#fff;text-decoration:none;display:inline-block;" download>
                Orijinal Görseli İndir
            </a>
        </div>
    <?php endif; ?>
</div>
<?php
get_footer();
?>