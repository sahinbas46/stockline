<?php
// Bu dosyayı WordPress köküne veya temana atıp 1 defa çalıştırabilirsin.
$files = [
    'stockline.php' => <<<'EOC'
<?php
// (Buraya yukarıda verdiğim GÜNCEL stockline.php kodunun tamamını yapıştır)
EOC
,
    'admin/product-add.php' => <<<'EOC'
<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap">
    <!-- (Buraya product-add.php içeriğini yerleştir) -->
</div>
EOC
,
// ... diğer dosya içeriklerini de aynı şekilde ekleyebilirsin ...
    'includes/gallery-shortcode.php' => <<<'EOC'
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode('dropbox_gallery', 'stockline_gallery_shortcode');
// (Buraya gallery-shortcode.php içeriğini ekle)
EOC
];

$zip = new ZipArchive;
$tmp = tempnam(sys_get_temp_dir(), 'stockline_zip');
$zip->open($tmp, ZipArchive::CREATE);
foreach($files as $path => $content) {
    $zip->addFromString("stockline/$path", $content);
}
$zip->close();

header('Content-Type: application/zip');
header('Content-disposition: attachment; filename=stockline.zip');
readfile($tmp);
unlink($tmp);
exit;