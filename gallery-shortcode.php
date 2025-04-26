<?php
// [stockline_all_products_metro] - Kırpmadan, metro masonry grid + hover info ve detay linki
add_shortcode('stockline_all_products_metro', function() {
    global $wpdb;
    $product_table = $wpdb->prefix.'stockline_products';
    $products = $wpdb->get_results("SELECT * FROM $product_table ORDER BY created_at DESC", ARRAY_A);

    ob_start();
    ?>
    <div class="stockline-metro-masonry">
    <?php foreach ($products as $pr): 
        $detail_url = site_url('/stockline-product?id=' . $pr['id']);
        $download_url = $pr['dropbox_original_url'] ? esc_url($pr['dropbox_original_url']) : '';
    ?>
        <div class="metro-masonry-item">
            <a href="<?= esc_url($detail_url) ?>" class="metro-link" title="<?= esc_attr($pr['title']) ?>">
                <img src="<?= esc_url($pr['img_url']) ?>" alt="<?= esc_attr($pr['title']) ?>">
                <div class="metro-overlay">
                    <div class="metro-title"><?= esc_html($pr['title']) ?></div>
                    <?php if ($download_url): ?>
                        <a class="metro-download" href="<?= $download_url ?>" target="_blank" title="Orijinali indir">
                            <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                                <path d="M12 16a1 1 0 0 1-1-1V5a1 1 0 1 1 2 0v10a1 1 0 0 1-1 1z"></path>
                                <path d="M7 13a1 1 0 0 1 1.707-.707l2.293 2.293V5a1 1 0 1 1 2 0v9.586l2.293-2.293A1 1 0 0 1 17 13l-4 4-4-4z"></path>
                                <path d="M5 19a1 1 0 0 1 0-2h14a1 1 0 1 1 0 2H5z"></path>
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
});

// Koleksiyon için de metro dizilimli kısa kod (isteğe bağlı)
add_shortcode('stockline_collection_metro', function($atts) {
    global $wpdb;
    $atts = shortcode_atts(['id'=>0], $atts, 'stockline_collection_metro');
    $coll_id = intval($atts['id']);
    if (!$coll_id) return '';
    $items_table = $wpdb->prefix.'stockline_collection_items';
    $product_table = $wpdb->prefix.'stockline_products';

    $ids = $wpdb->get_col($wpdb->prepare("SELECT product_id FROM $items_table WHERE collection_id=%d", $coll_id));
    if (!$ids) return '<div>Koleksiyon boş</div>';

    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $products = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $product_table WHERE id IN ($placeholders)", $ids
    ), ARRAY_A);

    ob_start();
    ?>
    <div class="stockline-metro-masonry">
    <?php foreach ($products as $pr): 
        $detail_url = site_url('/stockline-product?id=' . $pr['id']);
        $download_url = $pr['dropbox_original_url'] ? esc_url($pr['dropbox_original_url']) : '';
    ?>
        <div class="metro-masonry-item">
            <a href="<?= esc_url($detail_url) ?>" class="metro-link" title="<?= esc_attr($pr['title']) ?>">
                <img src="<?= esc_url($pr['img_url']) ?>" alt="<?= esc_attr($pr['title']) ?>">
                <div class="metro-overlay">
                    <div class="metro-title"><?= esc_html($pr['title']) ?></div>
                    <?php if ($download_url): ?>
                        <a class="metro-download" href="<?= $download_url ?>" target="_blank" title="Orijinali indir">
                            <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                                <path d="M12 16a1 1 0 0 1-1-1V5a1 1 0 1 1 2 0v10a1 1 0 0 1-1 1z"></path>
                                <path d="M7 13a1 1 0 0 1 1.707-.707l2.293 2.293V5a1 1 0 1 1 2 0v9.586l2.293-2.293A1 1 0 0 1 17 13l-4 4-4-4z"></path>
                                <path d="M5 19a1 1 0 0 1 0-2h14a1 1 0 1 1 0 2H5z"></path>
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
});

// Masonry & Overlay CSS
add_action('wp_head', function() {
?>
<style>
.stockline-metro-masonry {
    column-count: 4;
    column-gap: 18px;
    width: 100%;
    max-width: 1350px;
    margin: 0 auto 40px auto;
}
@media (max-width: 1100px) {
    .stockline-metro-masonry { column-count: 3; }
}
@media (max-width: 700px) {
    .stockline-metro-masonry { column-count: 2; }
}
@media (max-width: 480px) {
    .stockline-metro-masonry { column-count: 1; }
}
.metro-masonry-item {
    break-inside: avoid;
    margin-bottom: 18px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px #0001;
    padding: 0;
    overflow: hidden;
    position: relative;
}
.metro-link {
    display: block;
    position: relative;
    width: 100%;
    height: 100%;
}
.metro-masonry-item img {
    width: 100%;
    max-width: 100%;
    height: auto;
    display: block;
    border-radius: 0;
    background: #f6f6f6;
    object-fit: contain;
    max-height: 420px;
    transition: filter 0.2s;
}
.metro-link:hover img { filter: brightness(0.92) blur(1px); }
.metro-overlay {
    position: absolute;
    left: 0; right: 0; bottom: 0;
    height: 60px;
    background: linear-gradient(0deg, rgba(20,20,20,0.90) 65%, rgba(20,20,20,0.2) 100%);
    color: #fff;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    padding: 6px 12px 8px 12px;
    box-sizing: border-box;
    pointer-events: none;
}
.metro-title {
    font-size: 1.02em;
    font-weight: 600;
    text-shadow: 0 2px 8px #0008;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 75%;
    pointer-events: all;
}
.metro-download {
    pointer-events: all;
    display: flex;
    align-items: center;
    opacity: 0.85;
    text-decoration: none;
    margin-left: 12px;
    transition: opacity 0.2s;
}
.metro-download:hover { opacity: 1; }
.metro-download svg { display: block; }
</style>
<?php
});