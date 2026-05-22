<?php
// api/get_page_image.php - Tạo/Trả về ảnh DPI 150 (On-demand) cho 1 trang cụ thể
require_once dirname(__DIR__) . '/config/settings.php';

$sid  = preg_replace('/[^a-zA-Z0-9_.]/', '', $_GET['sid']  ?? '');
$page = max(1, intval($_GET['page'] ?? 1));

if (!$sid) { http_response_code(404); exit; }

$sessionUploadDir = UPLOAD_DIR . $sid . DIRECTORY_SEPARATOR;
$sessionTempDir   = TEMP_DIR . $sid . DIRECTORY_SEPARATOR;

$imgFile = $sessionTempDir . sprintf('page_%04d.png', $page);

if (!file_exists($imgFile)) {
    // Nếu ảnh chất lượng cao chưa có, tạo mới từ file gốc
    $pdfPath = $sessionUploadDir . 'original.pdf';
    if (!file_exists($pdfPath)) {
        http_response_code(404); exit;
    }
    
    // Ghostscript command chỉ render đúng 1 trang (-dFirstPage và -dLastPage)
    $cmd = sprintf(
        '"%s" -dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r%d -dUseCropBox -dFirstPage=%d -dLastPage=%d -sOutputFile="%s" "%s" 2>&1',
        GS_PATH, OCR_DPI, $page, $page, $imgFile, $pdfPath
    );
    exec($cmd, $output, $ret);
    
    if ($ret !== 0 || !file_exists($imgFile)) {
        http_response_code(500); exit;
    }
}

header('Content-Type: image/png');
// Cache 1 ngày
header('Cache-Control: max-age=86400');
readfile($imgFile);
exit;
