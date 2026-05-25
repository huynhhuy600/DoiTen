<?php
// api/ocr_page.php - OCR 1 trang + phân loại theo text OCR, màu ảnh và luật ưu tiên GCN/GTPL/NVTC
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/settings.php';

$sid  = preg_replace('/[^a-zA-Z0-9_.]/', '', $_GET['sid']  ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
if (!$sid || $page <= 0) {
    jsonResponse(['success'=>false,'error'=>'Thiếu tham số']);
}

logActiveUser($sid);

$sessionTempDir = TEMP_DIR . $sid . DIRECTORY_SEPARATOR;
$imgFile = $sessionTempDir . sprintf('page_%04d.png', $page);

// Nếu ảnh chất lượng cao chưa tồn tại, tạo nó từ PDF gốc
if (!file_exists($imgFile)) {
    $pdfPath = UPLOAD_DIR . $sid . DIRECTORY_SEPARATOR . 'original.pdf';
    if (!file_exists($pdfPath)) {
        jsonResponse(['success' => false, 'error' => 'Không tìm thấy file PDF gốc để render ảnh']);
    }

    if (!is_dir($sessionTempDir)) {
        @mkdir($sessionTempDir, 0775, true);
    }

    $cmd = sprintf(
        '"%s" -dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r%d -dUseCropBox -dFirstPage=%d -dLastPage=%d -sOutputFile="%s" "%s" 2>&1',
        GS_PATH,
        OCR_DPI,
        $page,
        $page,
        $imgFile,
        $pdfPath
    );
    exec($cmd, $output, $ret);

    if ($ret !== 0 || !file_exists($imgFile)) {
        jsonResponse(['success' => false, 'error' => 'Không thể render ảnh chất lượng cao cho trang này.']);
    }
}

$types = require dirname(__DIR__) . '/config/document_types.php';

// =====================================================
// 1. CHUẨN HÓA TEXT
// =====================================================

function removeVietnameseToneOA(string $str): string
{
    $unicode = [
        'a' => 'á|à|ả|ã|ạ|ă|ắ|ằ|ẳ|ẵ|ặ|â|ấ|ầ|ẩ|ẫ|ậ',
        'd' => 'đ',
        'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
        'i' => 'í|ì|ỉ|ĩ|ị',
        'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
        'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
        'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
        'A' => 'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ằ|Ẳ|Ẵ|Ặ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
        'D' => 'Đ',
        'E' => 'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
        'I' => 'Í|Ì|Ỉ|Ĩ|Ị',
        'O' => 'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
        'U' => 'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
        'Y' => 'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
    ];

    foreach ($unicode as $nonUnicode => $uni) {
        $str = preg_replace("/($uni)/u", $nonUnicode, $str);
    }
    return $str;
}

function normalizeTextOA(string $t): string
{
    $t = mb_strtolower($t, 'UTF-8');

    $replace = [
        "\n" => ' ', "\r" => ' ', "\t" => ' ',

        // QSDĐ
        'qsdđ' => 'qsdd', 'qsd đ' => 'qsdd', 'qsd d' => 'qsdd',
        'q.s.d.đ' => 'qsdd', 'q.s.d.d' => 'qsdd',

        // Lỗi OCR GCN
        'giây' => 'giấy', 'giắy' => 'giấy', 'chửng' => 'chứng', 'chưng' => 'chứng',
        'chửng nhận' => 'chứng nhận', 'quyên' => 'quyền',
        'sử đụng' => 'sử dụng', 'sư dụng' => 'sử dụng', 'sù dụng' => 'sử dụng', 'sừ dụng' => 'sử dụng',
        'thửa đắt' => 'thửa đất', 'thửa dất' => 'thửa đất',
        'thua dat' => 'thửa đất', 'so do thua dat' => 'sơ đồ thửa đất',

        // Lỗi OCR GTPL
        'hop dong' => 'hợp đồng', 'thue dat' => 'thuê đất', 'bo xung' => 'bo sung',
        'quyet dinh' => 'quyết định', 'ban sao' => 'bản sao',
        'dang ky kinh doanh' => 'đăng ký kinh doanh',

        // Lỗi OCR NVTC
        'truoc ba' => 'trước bạ', 'le phi truoc ba' => 'lệ phí trước bạ',
        'nghia vu tai chinh' => 'nghĩa vụ tài chính', 'thong bao nop tien' => 'thông báo nộp tiền',
        'giay nop tien' => 'giấy nộp tiền', 'phieu chuyen thong tin' => 'phiếu chuyển thông tin',
        'chi cuc thue' => 'chi cục thuế', 'kho bac nha nuoc' => 'kho bạc nhà nước',
    ];

    $t = str_replace(array_keys($replace), array_values($replace), $t);
    $t = preg_replace('/[^\p{L}\p{N}\s\/\-\.\:\,]/u', ' ', $t);
    $t = preg_replace('/\s+/', ' ', $t);
    return trim($t);
}

function normalizeNoToneOA(string $t): string
{
    $t = normalizeTextOA($t);
    $t = removeVietnameseToneOA($t);
    $t = mb_strtolower($t, 'UTF-8');
    $t = preg_replace('/\s+/', ' ', $t);
    return trim($t);
}

function getNormalizedLinesOA(string $text): array
{
    $lines = preg_split('/\R/u', $text);
    $out = [];
    foreach ($lines as $line) {
        $lineNorm = trim(normalizeNoToneOA($line));
        if ($lineNorm !== '') $out[] = $lineNorm;
    }
    return $out;
}

// =====================================================
// 2. NHẬN DIỆN DẤU HIỆU TEXT ĐẶC THÙ
// =====================================================

function hasStrictGcnTitleOA(string $rawText): bool
{
    $lines = getNormalizedLinesOA($rawText);
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];

        // GIẤY CHỨNG NHẬN QUYỀN SỬ DỤNG ĐẤT
        if (preg_match('/^giay\s+chung\s+nhan\s+quyen\s+su\s+dung\s+dat\b/u', $line)) return true;

        // GIẤY CHỨNG NHẬN / QUYỀN SỬ DỤNG ĐẤT
        if (preg_match('/^giay\s+chung\s+nhan\s*$/u', $line)) {
            $next1 = $lines[$i + 1] ?? '';
            $next2 = $lines[$i + 2] ?? '';
            if (preg_match('/^quyen\s+su\s+dung\s+dat\b/u', $next1) || preg_match('/^quyen\s+su\s+dung\s+dat\b/u', $next2)) return true;
        }

        // GCN mẫu mới: GIẤY CHỨNG NHẬN / QUYỀN SỬ DỤNG ĐẤT / QUYỀN SỞ HỮU NHÀ Ở...
        if (preg_match('/^giay\s+chung\s+nhan\s*$/u', $line)) {
            $block = trim(($lines[$i + 1] ?? '') . ' ' . ($lines[$i + 2] ?? '') . ' ' . ($lines[$i + 3] ?? ''));
            if (mb_strpos($block, 'quyen su dung dat') !== false && mb_strpos($block, 'tai san khac gan lien voi dat') !== false) return true;
        }
    }
    return false;
}


function hasWhiteBlackGcnPageOA(string $textNoTone): bool
{
    $keywords = [
        'giay chung nhan quyen su dung dat',
        'giay chung nhan',
        'quyen su dung dat',
        'vi nhung thay doi sau khi cap giay chung nhan quyen su dung dat',
        'nhung thay doi sau khi cap giay chung nhan quyen su dung dat',
        'nguoi duoc cap giay chung nhan quyen su dung dat can chu y',
        'noi dung thay doi va co so phap ly',
        'xac nhan cua co quan co tham quyen',
        'so phat hanh',
        'so vao so cap giay',
        'so vao so cap giay chung nhan quyen su dung dat',
        'so do thua dat',
    ];

    $found = 0;
    foreach ($keywords as $kw) {
        if (mb_strpos($textNoTone, $kw) !== false) $found++;
    }

    // GCN trắng đen/scan mất màu thường còn ít nhất 2 dấu hiệu đặc trưng.
    return $found >= 2;
}

function hasNonGcnCertificatePhraseOA(string $textNoTone): bool
{
    $badPhrases = [
        'cap giay chung nhan', 'cho thue dat va cap giay chung nhan',
        'giay chung nhan dang ky kinh doanh', 'giay chung nhan ket hon',
        'trang bo sung giay chung nhan', 'sau khi cap giay chung nhan',
        'nguoi duoc cap giay chung nhan', 'so vao so cap giay chung nhan',
    ];
    foreach ($badPhrases as $kw) if (mb_strpos($textNoTone, $kw) !== false) return true;
    return false;
}

function hasTrichLucBanDoOA(string $textNoTone): bool
{
    $keywords = [
        'trich luc ban do', 'trich luc ban do dia chinh', 'trich luc ban do khu dat',
        'trich luc khu dat', 'trich luc thua dat', 'ban do dia chinh',
        'khu dat boi thuong', 'khu dat xin cap giay chung nhan',
        'ranh gioi khu dat', 'ranh gioi su dung dat',
        'nguoi trich luc', 'nguoi trichluc', 'nguoi thich luc', 'nguoi kiem tra',
    ];
    foreach ($keywords as $kw) if (mb_strpos($textNoTone, $kw) !== false) return true;
    return false;
}

function hasNguoiTrichLucOA(string $textNoTone): bool
{
    $keywords = [
        'nguoi trich luc',
        'nguoi trichluc',
        'nguoi thich luc',
        'nguoi trich luc ban do',
        'nguoi trich luc khu dat',
        'nguoi trich luc thua dat',
        'nguoi kiem tra',
    ];

    foreach ($keywords as $kw) {
        if (mb_strpos($textNoTone, $kw) !== false) return true;
    }
    return false;
}

function hasDonDangKyBienDongOA(string $textNoTone): bool
{
    $keywords = [
        'don xin dang ky bien dong ve su dung dat', 'don dang ky bien dong ve su dung dat',
        'dang ky bien dong ve su dung dat', 'mau so 14 dk', 'mau so 14/dk', 'mau so 14dk',
        'ke khai cua nguoi su dung dat', 'noi dung xin dang ky bien dong', 'noi dung dang ky bien dong',
        'giay chung nhan quyen su dung dat da cap',
        'ket qua tham tra cua van phong dang ky quyen su dung dat',
        'huong dan viet don', 'don nay dung trong cac truong hop',
        'thay doi han che quyen su dung dat', 'thay doi nghia vu tai chinh ve dat dai',
        'nguoi viet don ky va ghi ro ho ten',
    ];
    foreach ($keywords as $kw) if (mb_strpos($textNoTone, $kw) !== false) return true;
    return false;
}


function hasDonDangKyDatDaiOA(string $textNoTone): bool
{
    $keywords = [
        'don xin xac nhan nguon goc dat',
        'xac nhan nguon goc dat',
        'don dang ky dat dai tai san gan lien voi dat',
        'don dang ky dat dai',
        'tai san gan lien voi dat',
        'mau so 15/dk',
        'mau so 15 dk',
        'mau so 15a',
        'mau so 15b',
        'mau so 15c',
        'nguoi lam don',
        'nguoi ke khai',
        'nguoi su dung dat nguoi ke khai',
        'niem yet cong khai',
        'niem yet cong khai danh sach ho so dang ky dat dai',
        'ho so dang ky dat dai',
        'thong bao',
    ];

    foreach ($keywords as $kw) {
        if (mb_strpos($textNoTone, $kw) !== false) return true;
    }
    return false;
}

function hasAnyNoToneOA(string $textNoTone, array $keywords): bool
{
    foreach ($keywords as $kw) {
        $kwNoTone = normalizeNoToneOA($kw);
        if ($kwNoTone !== '' && mb_strpos($textNoTone, $kwNoTone) !== false) return true;
    }
    return false;
}

function countFoundNoToneOA(string $textNoTone, array $keywords): int
{
    $count = 0;
    foreach ($keywords as $kw) {
        $kwNoTone = normalizeNoToneOA($kw);
        if ($kwNoTone !== '' && mb_strpos($textNoTone, $kwNoTone) !== false) $count++;
    }
    return $count;
}

// =====================================================
// 3. PHÂN TÍCH MÀU ẢNH
// =====================================================

function emptyColorInfoOA(): array
{
    return [
        'ok' => false,
        'width' => 0,
        'height' => 0,
        'sampled' => 0,
        'red_ratio' => 0,
        'strong_red_ratio' => 0,
        'yellow_ratio' => 0,
        'pale_yellow_ratio' => 0,
        'pink_security_ratio' => 0,
        'white_ratio' => 0,
        'black_ratio' => 0,
        'is_red_gcn_cover' => false,
        'is_yellow_gcn_page' => false,
        'is_pink_gcn_page' => false,
        'gcn_color_score' => 0,
    ];
}

function loadImageOA(string $imgFile)
{
    if (!file_exists($imgFile)) return false;
    $info = @getimagesize($imgFile);
    if (!$info || empty($info['mime'])) return false;

    switch ($info['mime']) {
        case 'image/png':  return function_exists('imagecreatefrompng') ? @imagecreatefrompng($imgFile) : false;
        case 'image/jpeg': return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($imgFile) : false;
        case 'image/webp': return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($imgFile) : false;
        default: return false;
    }
}

function analyzeImageColorOA(string $imgFile): array
{
    $img = loadImageOA($imgFile);
    if (!$img) return emptyColorInfoOA();

    $w = imagesx($img);
    $h = imagesy($img);
    if ($w <= 0 || $h <= 0) {
        @imagedestroy($img);
        return emptyColorInfoOA();
    }

    // Lấy mẫu tối đa khoảng 25.000 điểm để chạy nhanh.
    $targetSamples = 25000;
    $step = max(1, (int)floor(sqrt(($w * $h) / $targetSamples)));

    $total = 0;
    $red = 0;
    $strongRed = 0;
    $yellow = 0;
    $paleYellow = 0;
    $pinkSecurity = 0;
    $white = 0;
    $black = 0;

    for ($y = 0; $y < $h; $y += $step) {
        for ($x = 0; $x < $w; $x += $step) {
            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $total++;

            // Bìa GCN đỏ: nền đỏ đậm chiếm diện tích lớn.
            if ($r > 145 && $g < 105 && $b < 105 && ($r - max($g, $b)) > 45) $red++;
            if ($r > 175 && $g < 80 && $b < 80 && ($r - max($g, $b)) > 80) $strongRed++;

            // Quốc huy/chữ vàng trên bìa đỏ hoặc hoa văn vàng của GCN cũ.
            if ($r > 170 && $g > 125 && $b < 95 && abs($r - $g) < 95) $yellow++;
            if ($r > 205 && $g > 185 && $b > 105 && $b < 190) $paleYellow++;

            // GCN mẫu mới nền hồng nhạt/hoa văn bảo an màu hồng.
            if ($r > 210 && $g > 150 && $b > 155 && $r >= $g && $g >= ($b - 25) && ($r - $g) < 75) $pinkSecurity++;

            if ($r > 238 && $g > 238 && $b > 238) $white++;
            if ($r < 45 && $g < 45 && $b < 45) $black++;
        }
    }

    @imagedestroy($img);

    if ($total <= 0) return emptyColorInfoOA();

    $redRatio = $red / $total;
    $strongRedRatio = $strongRed / $total;
    $yellowRatio = $yellow / $total;
    $paleYellowRatio = $paleYellow / $total;
    $pinkRatio = $pinkSecurity / $total;
    $whiteRatio = $white / $total;
    $blackRatio = $black / $total;

    // Điều kiện màu có chủ ý để tránh nhầm con dấu đỏ trên GTPL/NVTC.
    $isRedGcnCover = ($redRatio >= 0.45 && $yellowRatio >= 0.002) || ($strongRedRatio >= 0.32 && $yellowRatio >= 0.0015);
    $isYellowGcnPage = (
        ($paleYellowRatio >= 0.08 && $yellowRatio >= 0.004 && $whiteRatio < 0.94)
        ||
        ($paleYellowRatio >= 0.12 && $whiteRatio < 0.96)
        ||
        ($yellowRatio >= 0.012 && $blackRatio >= 0.004 && $whiteRatio < 0.96)
    );
    $isPinkGcnPage = (
        ($pinkRatio >= 0.18 && $redRatio >= 0.002 && $whiteRatio < 0.90)
        ||
        ($pinkRatio >= 0.35 && $whiteRatio < 0.92)
    );

    $score = 0;
    if ($isRedGcnCover) $score += 500;
    if ($isYellowGcnPage) $score += 320;
    if ($isPinkGcnPage) $score += 380;

    return [
        'ok' => true,
        'width' => $w,
        'height' => $h,
        'sampled' => $total,
        'red_ratio' => round($redRatio, 4),
        'strong_red_ratio' => round($strongRedRatio, 4),
        'yellow_ratio' => round($yellowRatio, 4),
        'pale_yellow_ratio' => round($paleYellowRatio, 4),
        'pink_security_ratio' => round($pinkRatio, 4),
        'white_ratio' => round($whiteRatio, 4),
        'black_ratio' => round($blackRatio, 4),
        'is_red_gcn_cover' => $isRedGcnCover,
        'is_yellow_gcn_page' => $isYellowGcnPage,
        'is_pink_gcn_page' => $isPinkGcnPage,
        'gcn_color_score' => $score,
    ];
}

function mergeColorInfosOA(array $infos): array
{
    if (empty($infos)) return emptyColorInfoOA();

    $out = emptyColorInfoOA();
    $out['ok'] = true;
    $out['pages'] = count($infos);
    $maxScore = 0;
    $redCover = false;
    $yellowPage = false;
    $pinkPage = false;

    $sumSampled = 0;
    $sumRed = $sumStrongRed = $sumYellow = $sumPaleYellow = $sumPink = $sumWhite = $sumBlack = 0.0;

    foreach ($infos as $info) {
        $sampled = max(1, (int)($info['sampled'] ?? 0));
        $sumSampled += $sampled;
        $sumRed += ($info['red_ratio'] ?? 0) * $sampled;
        $sumStrongRed += ($info['strong_red_ratio'] ?? 0) * $sampled;
        $sumYellow += ($info['yellow_ratio'] ?? 0) * $sampled;
        $sumPaleYellow += ($info['pale_yellow_ratio'] ?? 0) * $sampled;
        $sumPink += ($info['pink_security_ratio'] ?? 0) * $sampled;
        $sumWhite += ($info['white_ratio'] ?? 0) * $sampled;
        $sumBlack += ($info['black_ratio'] ?? 0) * $sampled;

        $maxScore = max($maxScore, (int)($info['gcn_color_score'] ?? 0));
        $redCover = $redCover || !empty($info['is_red_gcn_cover']);
        $yellowPage = $yellowPage || !empty($info['is_yellow_gcn_page']);
        $pinkPage = $pinkPage || !empty($info['is_pink_gcn_page']);
    }

    $den = max(1, $sumSampled);
    $out['sampled'] = $sumSampled;
    $out['red_ratio'] = round($sumRed / $den, 4);
    $out['strong_red_ratio'] = round($sumStrongRed / $den, 4);
    $out['yellow_ratio'] = round($sumYellow / $den, 4);
    $out['pale_yellow_ratio'] = round($sumPaleYellow / $den, 4);
    $out['pink_security_ratio'] = round($sumPink / $den, 4);
    $out['white_ratio'] = round($sumWhite / $den, 4);
    $out['black_ratio'] = round($sumBlack / $den, 4);
    $out['is_red_gcn_cover'] = $redCover;
    $out['is_yellow_gcn_page'] = $yellowPage;
    $out['is_pink_gcn_page'] = $pinkPage;
    $out['gcn_color_score'] = $maxScore;

    return $out;
}

// =====================================================
// 4. SERI + OCR
// =====================================================

function detectSerialOA(string $text): string
{
    $text = strtoupper($text);
    if (preg_match('/\b([A-Z]{2,3})[\s\-\.]*(\d{5,8})\b/u', $text, $m)) return $m[1] . ' ' . $m[2];
    if (preg_match('/\b([A-Z])[\s\-\.]+([A-Z])[\s\-\.]*(\d{5,8})\b/u', $text, $m)) return $m[1] . $m[2] . ' ' . $m[3];
    if (preg_match('/\b([A-Z])[\s\-\.]*(\d{5,8})\b/u', $text, $m)) return $m[1] . ' ' . $m[2];
    return '';
}

function runTesseractOA(string $imgFile, string $txtBase): string
{
    if (file_exists($txtBase . '.txt')) @unlink($txtBase . '.txt');

    $cmd = sprintf(
        '"%s" "%s" "%s" -l vie+eng --oem 1 --psm 6 -c preserve_interword_spaces=1 2>&1',
        TESSERACT_PATH,
        $imgFile,
        $txtBase
    );
    exec($cmd, $output, $code);

    $txtFile = $txtBase . '.txt';
    return file_exists($txtFile) ? file_get_contents($txtFile) : '';
}

// =====================================================
// 5. PHÂN LOẠI HỒ SƠ
// =====================================================

function detectDocTypeOA(string $text, array $types, int $pageCount = 0, array $colorInfo = []): array
{
    if (mb_strlen(trim(preg_replace('/\s+/', '', $text))) < 15 && empty($colorInfo['gcn_color_score'])) {
        return [
            'code' => 'BLANK',
            'confidence' => 100,
            'candidates' => [['code' => 'BLANK', 'name' => 'Trang trắng', 'score' => 100]],
            'scores' => ['BLANK' => 100],
        ];
    }

    $norm = normalizeTextOA($text);
    $normNoTone = normalizeNoToneOA($text);

    $strictGcnTitle = hasStrictGcnTitleOA($text);
    $isWhiteBlackGcnPage = hasWhiteBlackGcnPageOA($normNoTone);
    $hasNonGcnCertificatePhrase = hasNonGcnCertificatePhraseOA($normNoTone);
    $isTrichLucBanDo = hasTrichLucBanDoOA($normNoTone);
    $hasNguoiTrichLuc = hasNguoiTrichLucOA($normNoTone);
    $isDonDangKyBienDong = hasDonDangKyBienDongOA($normNoTone);
    $isDonDangKyDatDai = hasDonDangKyDatDaiOA($normNoTone);

    $scores = [];
    $matchedKeywords = [];

    foreach ($types as $code => $info) {
        $score = 0;
        $matchedKeywords[$code] = [];
        if (!isset($info['keywords']) || !is_array($info['keywords'])) {
            $scores[$code] = 0;
            continue;
        }
        foreach ($info['keywords'] as $kw => $weight) {
            $kwNoTone = normalizeNoToneOA((string)$kw);
            if ($kwNoTone !== '' && mb_strpos($normNoTone, $kwNoTone) !== false) {
                $score += (int)$weight;
                $matchedKeywords[$code][] = (string)$kw;
            }
        }
        $scores[$code] = $score;
    }

    foreach (['GCN', 'GTPL', 'NVTC'] as $code) if (!isset($scores[$code])) $scores[$code] = 0;

    // ---------------- GCN TEXT BOOST ----------------
    $gcnStrong = [
        'giấy chứng nhận quyền sử dụng đất', 'số vào sổ cấp giấy chứng nhận quyền sử dụng đất',
        'số vào sổ cấp giấy', 'số vào sổ cấp gcn', 'sơ đồ thửa đất', 'v. sơ đồ thửa đất',
        'những thay đổi sau khi cấp giấy chứng nhận quyền sử dụng đất',
        'người được cấp giấy chứng nhận quyền sử dụng đất cần chú ý', 'thửa đất được quyền sử dụng',
        'quyền sở hữu nhà ở và tài sản khác gắn liền với đất',
        'người sử dụng đất, chủ sở hữu nhà ở và tài sản khác gắn liền với đất',
    ];
    $gcnFound = countFoundNoToneOA($normNoTone, $gcnStrong);

    if ($strictGcnTitle) {
        if ($gcnFound >= 1) $scores['GCN'] += 100;
        if ($gcnFound >= 2) $scores['GCN'] += 180;
        if ($gcnFound >= 3) $scores['GCN'] += 250;
    } else {
        if ($gcnFound >= 2) $scores['GCN'] += 20;
    }

    // GCN trắng đen / scan mất màu nhưng còn tiêu đề, quốc huy/bảng thay đổi
    if ($isWhiteBlackGcnPage && !$isTrichLucBanDo && !$hasNguoiTrichLuc && !$isDonDangKyBienDong && !$isDonDangKyDatDai) {
        $scores['GCN'] += 220;
        $scores['GTPL'] -= 80;
        $scores['NVTC'] -= 80;
    }

    if ($pageCount >= 2 && $pageCount <= 4) $scores['GCN'] += 25;

    if ($strictGcnTitle && hasAnyNoToneOA($normNoTone, ['sơ đồ thửa đất', 'số vào sổ cấp giấy', 'số vào sổ cấp gcn'])) {
        $scores['GCN'] += 180;
    }

    if ($strictGcnTitle && !hasAnyNoToneOA($normNoTone, ['thuế', 'lệ phí trước bạ', 'giấy nộp tiền', 'thông báo nộp tiền', 'phiếu chuyển thông tin'])) {
        $scores['GCN'] += 150;
    }

    // GCN có cấu trúc ruột rõ: CHỨNG NHẬN + sơ đồ + số vào sổ
    if (
        hasAnyNoToneOA($normNoTone, ['chứng nhận', 'chung nhan']) &&
        hasAnyNoToneOA($normNoTone, ['sơ đồ thửa đất', 'so do thua dat']) &&
        hasAnyNoToneOA($normNoTone, ['số vào sổ cấp giấy chứng nhận quyền sử dụng đất', 'so vao so cap giay chung nhan quyen su dung dat'])
    ) {
        $scores['GCN'] += 180;
    }

    // ---------------- COLOR BOOST GCN ----------------
    $colorBoost = (int)($colorInfo['gcn_color_score'] ?? 0);
    if ($colorBoost > 0) {
        $scores['GCN'] += $colorBoost;
        // Màu bìa đỏ/nền bảo an GCN rất đặc trưng; hạ loại khác để tránh con dấu đỏ gây nhiễu.
        if (!empty($colorInfo['is_red_gcn_cover'])) {
            $scores['GTPL'] -= 120;
            $scores['NVTC'] -= 120;
        } elseif (!empty($colorInfo['is_yellow_gcn_page']) || !empty($colorInfo['is_pink_gcn_page'])) {
            $scores['GTPL'] -= 130;
            $scores['NVTC'] -= 130;
        }
    }

    // ---------------- GTPL BOOST ----------------
    $gtplStrong = [
        'bản sao', 'chứng thực bản sao', 'quyết định', 'hợp đồng', 'hợp đồng thuê đất',
        'hợp đồng chuyển nhượng', 'hợp đồng tặng cho', 'hợp đồng mua bán', 'hợp đồng ủy quyền',
        'văn bản ủy quyền', 'đơn xin', 'đơn đăng ký', 'đơn đề nghị', 'đăng ký kinh doanh',
        'giấy chứng nhận đăng ký kinh doanh', 'trích lục bản đồ', 'trích lục khu đất', 'người trích lục',
        'trích đo', 'bản án', 'căn cước công dân', 'chứng minh nhân dân', 'mẫu số 14',
        'đơn xin xác nhận nguồn gốc đất', 'xác nhận nguồn gốc đất',
        'đơn đăng ký đất đai', 'đơn đăng ký đất đai tài sản gắn liền với đất',
        'mẫu số 15', 'mẫu số 15/dk', 'mẫu số 15 đk',
        'người làm đơn', 'người kê khai', 'niêm yết công khai', 'hồ sơ đăng ký đất đai', 'thông báo',
    ];
    $gtplFound = countFoundNoToneOA($normNoTone, $gtplStrong);
    if ($gtplFound >= 1) $scores['GTPL'] += 35;
    if ($gtplFound >= 2) $scores['GTPL'] += 85;
    if ($gtplFound >= 3) $scores['GTPL'] += 140;

    if (hasAnyNoToneOA($normNoTone, ['bản sao', 'chứng thực bản sao']) && hasAnyNoToneOA($normNoTone, ['quyết định', 'hợp đồng', 'đăng ký kinh doanh'])) {
        $scores['GTPL'] += 170;
    }

    if (hasAnyNoToneOA($normNoTone, ['hợp đồng', 'quyết định']) && !$strictGcnTitle && !hasAnyNoToneOA($normNoTone, ['số vào sổ cấp giấy', 'sơ đồ thửa đất'])) {
        $scores['GTPL'] += 110;
        $scores['GCN'] -= 30;
    }

    // ---------------- NVTC BOOST ----------------
    $nvtcStrong = [
        'giấy nộp tiền', 'giấy nộp tiền vào ngân sách nhà nước', 'thông báo nộp tiền',
        'thông báo nộp tiền sử dụng đất', 'thông báo nộp lệ phí trước bạ',
        'thông báo nộp lệ phí trước bạ nhà đất', 'lệ phí trước bạ', 'tờ khai lệ phí trước bạ',
        'phiếu chuyển thông tin địa chính', 'phiếu chuyển thông tin', 'xác định nghĩa vụ tài chính',
        'nghĩa vụ tài chính', 'chi cục thuế', 'cơ quan thuế', 'cục thuế', 'kho bạc nhà nước',
        'ngân sách nhà nước', 'biên lai', 'mã số thuế', 'người nộp thuế', 'người nộp tiền',
        'số tiền phải nộp', 'tiền sử dụng đất', 'tiền thuê đất', 'phạt chậm nộp',
        'xử lý phạt chậm nộp tiền', 'vpbank', 'cash deposit requirement', 'cash collecting document',
    ];
    $nvtcFound = countFoundNoToneOA($normNoTone, $nvtcStrong);
    if ($nvtcFound >= 1) $scores['NVTC'] += 70;
    if ($nvtcFound >= 2) $scores['NVTC'] += 150;
    if ($nvtcFound >= 3) $scores['NVTC'] += 240;

    if (hasAnyNoToneOA($normNoTone, ['thông báo nộp tiền', 'thông báo nộp lệ phí trước bạ', 'giấy nộp tiền', 'phiếu chuyển thông tin địa chính', 'nghĩa vụ tài chính', 'lệ phí trước bạ', 'tờ khai lệ phí trước bạ', 'chi cục thuế', 'kho bạc nhà nước'])) {
        $scores['NVTC'] += 200;
        $scores['GTPL'] -= 60;
        $scores['GCN'] -= 60;
    }

    if (hasAnyNoToneOA($normNoTone, ['thuế', 'chi cục thuế', 'cơ quan thuế', 'kho bạc', 'ngân sách', 'vpbank']) && hasAnyNoToneOA($normNoTone, ['số tiền', 'phải nộp', 'nộp tiền', 'đồng', 'bằng chữ'])) {
        $scores['NVTC'] += 180;
        $scores['GTPL'] -= 50;
        $scores['GCN'] -= 50;
    }

    if (hasAnyNoToneOA($normNoTone, ['phiếu chuyển thông tin', 'phiếu chuyển thông tin địa chính']) && hasAnyNoToneOA($normNoTone, ['nghĩa vụ tài chính', 'chi cục thuế', 'cơ quan thuế'])) {
        $scores['NVTC'] += 220;
        $scores['GTPL'] -= 50;
        $scores['GCN'] -= 50;
    }

    // ---------------- XỬ LÝ XUNG ĐỘT ----------------
    if ($nvtcFound >= 2) $scores['NVTC'] += 120;

    if (!$strictGcnTitle && !$isWhiteBlackGcnPage && $hasNonGcnCertificatePhrase) {
        $scores['GCN'] -= 120;
    }

    if (hasAnyNoToneOA($normNoTone, ['giấy chứng nhận đăng ký kinh doanh'])) {
        $scores['GTPL'] += 250;
        $scores['GCN'] -= 200;
    }

    if (!$strictGcnTitle && hasAnyNoToneOA($normNoTone, ['cấp giấy chứng nhận quyền sử dụng đất', 'cho thuê đất và cấp giấy chứng nhận'])) {
        $scores['GTPL'] += 120;
        $scores['GCN'] -= 150;
    }

    if ($isTrichLucBanDo) {
        $scores['GTPL'] += 250;
        $scores['GCN'] -= 220;
        $scores['NVTC'] -= 80;
    }

    // Có chữ "người trích lục" hoặc "người kiểm tra" thì là GTPL, thường là trích lục bản đồ/khu đất.
    if ($hasNguoiTrichLuc) {
        $scores['GTPL'] += 280;
        $scores['GCN'] -= 250;
        $scores['NVTC'] -= 80;
    }

    if ($isDonDangKyBienDong) {
        $scores['GTPL'] += 300;
        $scores['GCN'] -= 250;
        $scores['NVTC'] -= 80;
    }

    // Đơn xin xác nhận nguồn gốc đất / Đơn đăng ký đất đai / Mẫu số 15 => GTPL.
    if ($isDonDangKyDatDai) {
        $scores['GTPL'] += 320;
        $scores['GCN'] -= 260;
        $scores['NVTC'] -= 80;
    }

    // Nếu màu bìa đỏ GCN xuất hiện thì ưu tiên GCN mạnh nhất, kể cả OCR đọc thiếu chữ.
    if (!empty($colorInfo['is_red_gcn_cover'])) {
        $scores['GCN'] += 250;
        $scores['GTPL'] -= 150;
        $scores['NVTC'] -= 150;
    }

    // Nhưng nếu text là trích lục/đơn biến động/NVTC rất rõ thì không cho màu con dấu kéo nhầm sang GCN.
    if ($isTrichLucBanDo || $isDonDangKyBienDong || $nvtcFound >= 2) {
        if (empty($colorInfo['is_red_gcn_cover'])) {
            $scores['GCN'] -= 180;
        }
    }

    // GCN mẫu mới nền hồng bảo an: ưu tiên GCN khi không có dấu hiệu GTPL/NVTC rõ.
    if (!empty($colorInfo['is_pink_gcn_page'])) {
        if (!$isTrichLucBanDo && !$hasNguoiTrichLuc && !$isDonDangKyBienDong && !$isDonDangKyDatDai && $nvtcFound < 2) {
            $scores['GCN'] += 250;
            $scores['GTPL'] -= 120;
            $scores['NVTC'] -= 120;
        }
    }

    
    // GCN nền vàng nhạt hoặc nền hồng bảo an: ưu tiên GCN.
    // Không áp dụng nếu text rõ ràng là trích lục, đơn, hoặc NVTC mạnh.
    if (!empty($colorInfo['is_yellow_gcn_page']) || !empty($colorInfo['is_pink_gcn_page'])) {
        if (!$isTrichLucBanDo && !$hasNguoiTrichLuc && !$isDonDangKyBienDong && !$isDonDangKyDatDai && $nvtcFound < 2) {
            $scores['GCN'] += 250;
            $scores['GTPL'] -= 140;
            $scores['NVTC'] -= 140;
        }
    }

    foreach ($scores as $key => $value) if ($scores[$key] < 0) $scores[$key] = 0;

    arsort($scores);
    $topCode = array_key_first($scores);
    $topScore = $scores[$topCode] ?? 0;
    $scoreValues = array_values($scores);
    $secondScore = $scoreValues[1] ?? 0;

    if ($topScore <= 0) {
        $topCode = 'GTPL';
        $confidence = 0;
    } else {
        $gap = $topScore - $secondScore;
        $confidence = min(100, max(40, intval(50 + ($gap / max(1, $topScore)) * 50)));
    }

    $cands = [];
    foreach (array_slice($scores, 0, 3, true) as $c => $s) {
        $cands[] = [
            'code' => $c,
            'name' => $types[$c]['short'] ?? $c,
            'score' => $s,
            'matched' => array_slice($matchedKeywords[$c] ?? [], 0, 10),
        ];
    }

    return [
        'code' => $topCode,
        'confidence' => $confidence,
        'candidates' => $cands,
        'scores' => $scores,
        'flags' => [
            'strict_gcn_title' => $strictGcnTitle,
            'white_black_gcn_page' => $isWhiteBlackGcnPage,
            'non_gcn_certificate_phrase' => $hasNonGcnCertificatePhrase,
            'trich_luc_ban_do' => $isTrichLucBanDo,
            'nguoi_trich_luc' => $hasNguoiTrichLuc,
            'don_dang_ky_bien_dong' => $isDonDangKyBienDong,
            'don_dang_ky_dat_dai' => $isDonDangKyDatDai,
            'color_gcn' => !empty($colorInfo['gcn_color_score']),
        ],
    ];
}


// =====================================================
// CHẠY OCR + PHÂN TÍCH MÀU + PHÂN LOẠI 1 TRANG
// =====================================================

$txtBase = $sessionTempDir . sprintf('ocr_%04d', $page);
$pageColor = analyzeImageColorOA($imgFile);
$rawText = runTesseractOA($imgFile, $txtBase);
$serial = detectSerialOA($rawText);
$match = detectDocTypeOA($rawText, $types, 1, $pageColor);

$docCode = $match['code'] ?? 'UNKNOWN';

jsonResponse([
    'success' => true,
    'page' => $page,
    'serial' => $serial,

    'doc_code' => $docCode,
    'doc_name' => isset($types[$docCode]) ? $types[$docCode]['name'] : (($docCode === 'BLANK') ? 'Trang trắng' : 'Không nhận dạng được'),
    'doc_short' => isset($types[$docCode]) ? $types[$docCode]['short'] : (($docCode === 'BLANK') ? 'Trang trắng' : '?'),
    'doc_color' => isset($types[$docCode]) ? $types[$docCode]['color'] : (($docCode === 'BLANK') ? '#e5e7eb' : '#6b7280'),
    'confidence' => $match['confidence'] ?? 0,
    'candidates' => $match['candidates'] ?? [],
    'scores' => $match['scores'] ?? [],
    'flags' => $match['flags'] ?? [],
    'color_analysis' => $pageColor,

    'ocr_text' => mb_substr($rawText, 0, 1000, 'UTF-8'),
]);
