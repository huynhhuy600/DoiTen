<?php
// api/upload_multiple.php - Tải lên nhiều file PDF vào một session (Workspace)
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/settings.php';

// Tạo session mới hoặc lấy session cũ từ client truyền lên
$sid = preg_replace('/[^a-zA-Z0-9_.]/', '', $_POST['session_id'] ?? '');
if (!$sid) {
    $sid = 'ws_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6);
}
$uploadDir = UPLOAD_DIR . $sid . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

logActiveUser($sid);

$uploadedFiles = [];
$errors = [];

if (!empty($_FILES['pdfs']['name'][0])) {
    $count = count($_FILES['pdfs']['name']);
    for ($i = 0; $i < $count; $i++) {
        $name = $_FILES['pdfs']['name'][$i];
        $tmp = $_FILES['pdfs']['tmp_name'][$i];
        $err = $_FILES['pdfs']['error'][$i];
        
        if ($err !== UPLOAD_ERR_OK) continue;
        
        // Kiểm tra PDF
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $errors[] = "$name không phải file PDF.";
            continue;
        }

        // Tạo tên an toàn
        $safeName = preg_replace('/[^a-zA-Z0-9_.\-\s]/', '_', $name);
        // Đảm bảo tên file duy nhất trong thư mục
        $dest = $uploadDir . $safeName;
        $counter = 1;
        while (file_exists($dest)) {
            $dest = $uploadDir . pathinfo($safeName, PATHINFO_FILENAME) . "_$counter.pdf";
            $counter++;
        }
        
        if (move_uploaded_file($tmp, $dest)) {
            $uploadedFiles[] = basename($dest);
        } else {
            $errors[] = "Lỗi khi lưu $name.";
        }
    }
}

// Quét lại thư mục để lấy danh sách file hiện có
$allFiles = [];
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..' && strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'pdf') {
            // Không tính file original.pdf (file đang xử lý hiện tại)
            if ($f === 'original.pdf') continue;
            
            $allFiles[] = [
                'name' => $f,
                'size' => filesize($uploadDir . $f),
                'time' => filemtime($uploadDir . $f)
            ];
        }
    }
}

jsonResponse([
    'success' => true,
    'session_id' => $sid,
    'new_uploads' => count($uploadedFiles),
    'files' => $allFiles,
    'errors' => $errors
]);
