<?php
// api/prepare_pdf.php - Chọn 1 file từ workspace và chuẩn bị (tạo thumbnail)
header('Content-Type: application/json; charset=utf-8');
ob_start();
require_once dirname(__DIR__) . '/config/settings.php';

$ws_sid = preg_replace('/[^a-zA-Z0-9_.]/', '', $_GET['ws_sid'] ?? '');
$filename = preg_replace('/[^a-zA-Z0-9_.\-\s]/', '', $_GET['file'] ?? '');

if (!$ws_sid || !$filename) {
    jsonResponse(['success'=>false, 'error'=>'Thiếu thông tin workspace hoặc file.']);
}

$sourcePdf = UPLOAD_DIR . $ws_sid . DIRECTORY_SEPARATOR . $filename;
if (!file_exists($sourcePdf)) {
    jsonResponse(['success'=>false, 'error'=>'File không tồn tại trong workspace.']);
}

// Tạo session ID mới cho việc xử lý file này
$sessionId = $ws_sid . '_f_' . substr(md5($filename), 0, 6);
$sessionUploadDir = UPLOAD_DIR . $sessionId . DIRECTORY_SEPARATOR;
$sessionTempDir   = TEMP_DIR   . $sessionId . DIRECTORY_SEPARATOR;

if (!is_dir($sessionUploadDir)) mkdir($sessionUploadDir, 0755, true);
if (!is_dir($sessionTempDir)) mkdir($sessionTempDir, 0755, true);

// Xóa file cũ trong temp (nếu có)
array_map('unlink', glob("$sessionTempDir/*.*"));

$pdfPath = $sessionUploadDir . 'original.pdf';
copy($sourcePdf, $pdfPath);

// Render PDF sang THUMBNAIL (DPI 30) để cực nhanh
$imgPattern = $sessionTempDir . 'thumb_%04d.png';
$cmd = sprintf(
    '"%s" -dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r30 -dUseCropBox -sOutputFile="%s" "%s" 2>&1',
    GS_PATH, $imgPattern, $pdfPath
);
exec($cmd, $output, $ret);

if ($ret !== 0) {
    jsonResponse([
        'success' => false,
        'error'   => 'Ghostscript lỗi (code '.$ret.'): ' . implode(' | ', array_slice($output, -3)),
    ]);
}

$pages = [];
$i = 1;
while (file_exists($sessionTempDir . sprintf('thumb_%04d.png', $i))) {
    $pages[] = [
        'page'      => $i,
        'thumb_url' => BASE_URL . '/api/thumb.php?sid=' . urlencode($sessionId) . '&page=' . $i . '&is_thumb=1',
        'img_url'   => BASE_URL . '/api/get_page_image.php?sid=' . urlencode($sessionId) . '&page=' . $i,
    ];
    $i++;
}

jsonResponse([
    'success'    => true,
    'session_id' => $sessionId,
    'filename'   => $filename,
    'page_count' => count($pages),
    'pages'      => $pages,
]);
