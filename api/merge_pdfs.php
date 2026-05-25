<?php
// api/merge_pdfs.php - Ghép nhiều file PDF trong workspace thành 1 file
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/settings.php';

$sid = preg_replace('/[^a-zA-Z0-9_.]/', '', $_POST['session_id'] ?? '');
if (!$sid) {
    jsonResponse(['success' => false, 'error' => 'Thiếu session_id']);
}

$uploadDir = UPLOAD_DIR . $sid . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir)) {
    jsonResponse(['success' => false, 'error' => 'Phiên làm việc không tồn tại']);
}

$requestedFiles = json_decode($_POST['files'] ?? '[]', true);
if (!is_array($requestedFiles) || count($requestedFiles) < 2) {
    jsonResponse(['success' => false, 'error' => 'Cần chọn ít nhất 2 file PDF để ghép.']);
}

$pdfFiles = [];
foreach ($requestedFiles as $f) {
    // Chỉ lấy tên file, loại bỏ path traversal để bảo mật
    $fName = basename($f);
    if ($fName !== 'original.pdf' && strtolower(pathinfo($fName, PATHINFO_EXTENSION)) === 'pdf') {
        if (file_exists($uploadDir . $fName)) {
            $pdfFiles[] = $fName;
        }
    }
}

if (count($pdfFiles) < 2) {
    jsonResponse(['success' => false, 'error' => 'Các file PDF đã chọn không tồn tại hoặc không hợp lệ.']);
}

// Sắp xếp theo tên file để đảm bảo thứ tự ghép đúng
sort($pdfFiles);

// Tên file xuất ra
$mergedName = 'Merged_Hoso_' . date('His') . '.pdf';
$mergedPath = $uploadDir . $mergedName;

// Chuẩn bị tham số cho Ghostscript
// Bao bọc các đường dẫn bằng dấu nháy kép để xử lý khoảng trắng
$cmdArgs = [];
foreach ($pdfFiles as $pdf) {
    $cmdArgs[] = escapeshellarg($uploadDir . $pdf);
}
$inputFilesStr = implode(' ', $cmdArgs);

// Lệnh Ghostscript ghép PDF
$cmd = sprintf(
    '"%s" -dNOPAUSE -sDEVICE=pdfwrite -sOUTPUTFILE="%s" -dBATCH %s 2>&1',
    GS_PATH,
    $mergedPath,
    $inputFilesStr
);

exec($cmd, $output, $ret);

if ($ret !== 0 || !file_exists($mergedPath)) {
    jsonResponse([
        'success' => false,
        'error'   => 'Lỗi ghép file (code '.$ret.'): ' . implode(' | ', array_slice($output, -3)),
        'cmd'     => $cmd
    ]);
}

// Quét lại thư mục để trả về danh sách file cập nhật
$allFiles = [];
$files = scandir($uploadDir);
foreach ($files as $f) {
    if ($f !== '.' && $f !== '..' && strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'pdf') {
        if ($f === 'original.pdf') continue;
        $allFiles[] = [
            'name' => $f,
            'size' => filesize($uploadDir . $f),
            'time' => filemtime($uploadDir . $f)
        ];
    }
}

jsonResponse([
    'success' => true,
    'merged_file' => $mergedName,
    'files' => $allFiles
]);
