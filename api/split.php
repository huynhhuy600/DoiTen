<?php
// api/split.php - Tách PDF dùng Ghostscript (PHP 7.3 compatible)
ob_start();
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/settings.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(['success'=>false,'error'=>'Dữ liệu không hợp lệ']);

$sid        = preg_replace('/[^a-zA-Z0-9_.]/', '', $input['session_id'] ?? '');
logActiveUser($sid);
$groups     = $input['groups'] ?? [];
$mainSerial = preg_replace('/[^A-Za-z0-9_\-\s]/', '', $input['main_serial'] ?? '');
// Số hộp hồ sơ (nếu được nhập sẽ ghi đè lên tên ZIP)
$hopHoSo    = trim(preg_replace('/[^A-Za-z0-9\x{00C0}-\x{024F}\x{1E00}-\x{1EFF}_\-\s]/u', '', $input['hop_ho_so'] ?? ''));
if (!$sid || empty($groups)) jsonResponse(['success'=>false,'error'=>'Thiếu session_id hoặc groups']);

$pdfSrc = UPLOAD_DIR . $sid . DIRECTORY_SEPARATOR . 'original.pdf';
if (!file_exists($pdfSrc)) jsonResponse(['success'=>false,'error'=>'Không tìm thấy file PDF gốc']);

$sessionOutputDir = OUTPUT_DIR . $sid . DIRECTORY_SEPARATOR;
if (!is_dir($sessionOutputDir)) mkdir($sessionOutputDir, 0755, true);

$tmpDir = TEMP_DIR . $sid . DIRECTORY_SEPARATOR;
if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);

// Kiểm tra Ghostscript
$gsTest = shell_exec('"' . GS_PATH . '" --version 2>&1');
if (!$gsTest || !preg_match('/^\d/', trim($gsTest))) {
    jsonResponse(['success'=>false,'error'=>'Ghostscript chưa cài hoặc đường dẫn sai. Mở setup.php để kiểm tra. Path hiện tại: ' . GS_PATH]);
}

/**
 * Trích các trang (có thể không liên tiếp) từ PDF gốc → 1 PDF output
 * Dùng Ghostscript: trích từng trang riêng rồi ghép lại
 */
function extractPages($gsPath, $srcPdf, $pages, $outPdf, $tmpDir) {
    $tempFiles = [];
    foreach ($pages as $p) {
        $tf  = $tmpDir . 'sp_p' . $p . '_' . mt_rand(1000,9999) . '.pdf';
        $cmd = sprintf(
            '"%s" -dNOPAUSE -dBATCH -dSAFER -sDEVICE=pdfwrite -dFirstPage=%d -dLastPage=%d -sOutputFile="%s" "%s" 2>&1',
            $gsPath, (int)$p, (int)$p, $tf, $srcPdf
        );
        exec($cmd, $out, $ret);
        if ($ret === 0 && file_exists($tf) && filesize($tf) > 0) {
            $tempFiles[] = $tf;
        }
    }
    if (empty($tempFiles)) return false;

    if (count($tempFiles) === 1) {
        // Chỉ 1 trang — đổi tên trực tiếp
        return rename($tempFiles[0], $outPdf);
    }

    // Ghép nhiều trang
    $quotedFiles = array_map(function($f){ return '"' . $f . '"'; }, $tempFiles);
    $mergeCmd = sprintf(
        '"%s" -dNOPAUSE -dBATCH -dSAFER -sDEVICE=pdfwrite -sOutputFile="%s" %s 2>&1',
        $gsPath, $outPdf, implode(' ', $quotedFiles)
    );
    exec($mergeCmd, $mout, $mret);

    foreach ($tempFiles as $tf) @unlink($tf);

    return $mret === 0 && file_exists($outPdf) && filesize($outPdf) > 0;
}

$outputFiles = [];
$nameCount   = [];

foreach ($groups as $g) {
    $pages  = array_map('intval', $g['pages'] ?? []);
    // Giữ lại dấu cách, số, chữ
    $serial = preg_replace('/[^A-Za-z0-9\s]/', '', $g['serial'] ?? 'UNKNOWN');
    $code   = preg_replace('/[^A-Za-z0-9_\-]/', '', $g['code'] ?? 'UNKNOWN');
    if (empty($pages)) continue;

    $namingMode = $input['naming_mode'] ?? 'serial_code';
    $base = strtoupper($code);
    if ($namingMode === 'serial_code') {
        $base = strtoupper($serial);
        if ($hopHoSo !== '') {
            $base .= '_' . strtoupper($hopHoSo);
        }
        $base .= '-' . strtoupper($code);
    }
    $nameCount[$base] = isset($nameCount[$base]) ? $nameCount[$base] + 1 : 1;
    $suffix   = $nameCount[$base] > 1 ? '_' . $nameCount[$base] : '';
    $fileName = $base . $suffix . '.pdf';
    $outPath  = $sessionOutputDir . $fileName;

    $ok = extractPages(GS_PATH, $pdfSrc, $pages, $outPath, $tmpDir);
    if ($ok) {
        $outputFiles[] = [
            'file'   => $fileName,
            'pages'  => $pages,
            'serial' => $serial,
            'code'   => $code,
            'size'   => filesize($outPath),
        ];
    }
}

if (empty($outputFiles)) {
    jsonResponse(['success'=>false,'error'=>'Không tạo được file nào. Kiểm tra Ghostscript đã cài đúng chưa (vào setup.php).']);
}

// Tạo ZIP — ưu tiên: Số hộp hồ sơ > Số seri > tên mặc định
if ($hopHoSo !== '') {
    // Có nhập số hộp: dùng nó làm tên ZIP
    $zipBase = $hopHoSo;
} elseif ($mainSerial) {
    // Không nhập hộp, nhưng có số seri
    $zipBase = $mainSerial;
} else {
    // Mặc định
    $zipBase = 'output_' . $sid;
}

$zipName = $zipBase . '.zip';
$zipPath = OUTPUT_DIR . $zipName;
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    jsonResponse(['success'=>false,'error'=>'Không tạo được file ZIP. Kiểm tra extension zip trong PHP.']);
}
foreach ($outputFiles as $f) {
    $zip->addFile($sessionOutputDir . $f['file'], $f['file']);
}
if (!$zip->close()) {
    jsonResponse(['success'=>false,'error'=>'Lỗi khi lưu file ZIP (Permission Denied). Vui lòng cấp quyền Ghi (Write/Modify) cho thư mục "output" trên server.']);
}

$downloadUrl = BASE_URL . '/api/download.php'
    . '?sid=' . urlencode($sid) . '&zip=' . urlencode($zipName);

jsonResponse([
    'success'    => true,
    'file_count' => count($outputFiles),
    'files'      => $outputFiles,
    'zip_name'   => $zipName,
    'zip_url'    => $downloadUrl,
]);
