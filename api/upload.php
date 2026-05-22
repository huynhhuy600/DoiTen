<?php
// api/upload.php - Nhận file PDF, render trang bằng Ghostscript
header('Content-Type: application/json; charset=utf-8');
// Tắt output buffering để tránh lỗi header
ob_start();

require_once dirname(__DIR__) . '/config/settings.php';

// Tự tạo thư mục nếu chưa có
foreach ([UPLOAD_DIR, OUTPUT_DIR, TEMP_DIR] as $d) {
    if (!is_dir($d)) mkdir($d, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success'=>false,'error'=>'Method not allowed'], 405);
}

$file = $_FILES['pdf'] ?? null;
if (!$file) {
    jsonResponse(['success'=>false,'error'=>'Không nhận được file. Kiểm tra PHP upload_max_filesize và post_max_size trong php.ini']);
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errMap = [
        UPLOAD_ERR_INI_SIZE   => 'File vượt quá upload_max_filesize trong php.ini',
        UPLOAD_ERR_FORM_SIZE  => 'File vượt quá MAX_FILE_SIZE trong form',
        UPLOAD_ERR_PARTIAL    => 'File chỉ được upload một phần',
        UPLOAD_ERR_NO_FILE    => 'Không có file nào được upload',
        UPLOAD_ERR_NO_TMP_DIR => 'Thiếu thư mục tmp',
        UPLOAD_ERR_CANT_WRITE => 'Không thể ghi file lên disk',
        UPLOAD_ERR_EXTENSION  => 'PHP extension chặn upload',
    ];
    jsonResponse(['success'=>false,'error'=>$errMap[$file['error']] ?? 'Lỗi upload code: '.$file['error']]);
}

if ($file['size'] > MAX_FILE_SIZE) {
    jsonResponse(['success'=>false,'error'=>'File quá lớn (tối đa ' . round(MAX_FILE_SIZE/1024/1024) . 'MB)']);
}

// Kiểm tra MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if ($mime !== 'application/pdf') {
    jsonResponse(['success'=>false,'error'=>'Chỉ chấp nhận file PDF (phát hiện: '.$mime.')']);
}

// Tạo session ID và lưu file
$sessionId        = uniqid('pdf_', true);
$sessionUploadDir = UPLOAD_DIR . $sessionId . DIRECTORY_SEPARATOR;
$sessionTempDir   = TEMP_DIR   . $sessionId . DIRECTORY_SEPARATOR;
if (!mkdir($sessionUploadDir, 0755, true)) {
    jsonResponse(['success'=>false,'error'=>'Không tạo được thư mục upload. Kiểm tra quyền ghi vào: ' . UPLOAD_DIR]);
}
mkdir($sessionTempDir, 0755, true);

$pdfPath = $sessionUploadDir . 'original.pdf';
if (!move_uploaded_file($file['tmp_name'], $pdfPath)) {
    jsonResponse(['success'=>false,'error'=>'Không thể di chuyển file upload. Kiểm tra quyền ghi.']);
}

// Kiểm tra Ghostscript tồn tại
if (!file_exists(GS_PATH)) {
    // Trả về thành công nhưng ghi chú GS chưa cài
    jsonResponse([
        'success'     => false,
        'error'       => 'Ghostscript chưa được cài hoặc đường dẫn sai. Truy cập setup.php để kiểm tra. Đường dẫn hiện tại: ' . GS_PATH,
        'setup_url'   => BASE_URL . '/setup.php',
    ]);
}

// Render PDF sang ảnh PNG bằng Ghostscript
$imgPattern = $sessionTempDir . 'page_%04d.png';
$cmd = sprintf(
    '"%s" -dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r%d -dUseCropBox -sOutputFile="%s" "%s" 2>&1',
    GS_PATH, OCR_DPI, $imgPattern, $pdfPath
);
exec($cmd, $output, $ret);

if ($ret !== 0) {
    jsonResponse([
        'success' => false,
        'error'   => 'Ghostscript lỗi (code '.$ret.'): ' . implode(' | ', array_slice($output, -3)),
    ]);
}

// Đếm số trang đã render
$pages = [];
$i = 1;
while (file_exists($sessionTempDir . sprintf('page_%04d.png', $i))) {
    $pages[] = [
        'page'      => $i,
        'thumb_url' => BASE_URL . '/api/thumb.php?sid=' . urlencode($sessionId) . '&page=' . $i,
        'img_url'   => BASE_URL . '/api/thumb.php?sid=' . urlencode($sessionId) . '&page=' . $i . '&full=1',
    ];
    $i++;
}

if (empty($pages)) {
    jsonResponse(['success'=>false,'error'=>'Ghostscript chạy xong nhưng không tạo được ảnh. Kiểm tra file PDF hợp lệ.']);
}

jsonResponse([
    'success'    => true,
    'session_id' => $sessionId,
    'filename'   => htmlspecialchars($file['name']),
    'page_count' => count($pages),
    'pages'      => $pages,
]);
