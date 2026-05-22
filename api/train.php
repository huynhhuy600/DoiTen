<?php
// api/train.php - Tự động học từ khóa từ file mẫu
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 1);
ini_set('memory_limit', '1024M');
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/settings.php';

$docTypesFile = dirname(__DIR__) . '/config/document_types.php';
$docTypes = require $docTypesFile;
$trainDir = dirname(__DIR__) . '/training_data';

set_time_limit(3600); // 1 hour

$logs = [];
function addLog($msg) { global $logs; $logs[] = $msg; }

// Normalize text for n-gram extraction
function normalizeTextTrain($text) {
    // lowercase
    $text = mb_strtolower($text, 'UTF-8');
    // keep only letters and numbers
    $text = preg_replace('/[^\p{L}0-9\s]/u', ' ', $text);
    // remove multiple spaces
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

// Extract n-grams (2-grams, 3-grams, 4-grams)
function getNGrams($text) {
    $words = explode(' ', $text);
    $ngrams = [];
    $len = count($words);
    for ($i = 0; $i < $len; $i++) {
        if ($i < $len - 1) $ngrams[] = $words[$i] . ' ' . $words[$i+1];
        if ($i < $len - 2) $ngrams[] = $words[$i] . ' ' . $words[$i+1] . ' ' . $words[$i+2];
        if ($i < $len - 3) $ngrams[] = $words[$i] . ' ' . $words[$i+1] . ' ' . $words[$i+2] . ' ' . $words[$i+3];
    }
    return $ngrams;
}

$categoryTexts = []; // code => combined text of all files

// 1. OCR or Read Cache
foreach ($docTypes as $code => $info) {
    $dir = "$trainDir/$code";
    if (!is_dir($dir)) continue;
    $files = glob("$dir/*.pdf");
    $catText = '';
    
    foreach ($files as $file) {
        $cacheFile = $file . '.txt';
        if (file_exists($cacheFile)) {
            $catText .= ' ' . file_get_contents($cacheFile);
        } else {
            // Need to OCR
            $basename = basename($file);
            $tmpPrefix = TEMP_DIR . 'train_' . $code . '_' . md5($file);
            // Convert to images using fast grayscale and lower DPI
            exec(sprintf('"%s" -dNOPAUSE -sDEVICE=pnggray -r%d -dUseCropBox -sOutputFile="%s_%%d.png" -dBATCH "%s" 2>&1', GS_PATH, OCR_DPI, $tmpPrefix, $file));

            
            $pageNum = 1;
            $fullText = '';
            while (file_exists($imgFile = sprintf('%s_%d.png', $tmpPrefix, $pageNum))) {
                $outBase = sprintf('%s_ocr_%d', $tmpPrefix, $pageNum);
                exec(sprintf('"%s" "%s" "%s" -l vie+eng --psm 11 2>&1', TESSERACT_PATH, $imgFile, $outBase));
                if (file_exists($outBase.'.txt')) {
                    $fullText .= ' ' . file_get_contents($outBase.'.txt');
                    @unlink($outBase.'.txt');
                }
                @unlink($imgFile);
                $pageNum++;
            }
            file_put_contents($cacheFile, $fullText);
            $catText .= ' ' . $fullText;
        }
    }
    $categoryTexts[$code] = normalizeTextTrain($catText);
    addLog("- Đã đọc " . count($files) . " file mẫu nhóm $code.");
}

// 2. Extract and count N-grams for each category
$ngramFreqs = []; // code => [ngram => count]
$allNgrams = [];  // all unique ngrams across all cats
foreach ($categoryTexts as $code => $text) {
    $ngrams = getNGrams($text);
    $ngramFreqs[$code] = array_count_values($ngrams);
    foreach ($ngramFreqs[$code] as $ng => $count) {
        // filter out n-grams that are too short
        if (mb_strlen($ng) < 8) continue;
        // filter out n-grams that contain numbers only
        if (preg_match('/^[0-9\s]+$/', $ng)) continue;
        
        $allNgrams[$ng] = true;
    }
}

// 3. TF-IDF like scoring (find distinctive n-grams)
$newKeywords = [];
foreach ($docTypes as $code => $info) {
    $scores = [];
    if (!isset($ngramFreqs[$code])) continue;
    
    foreach ($ngramFreqs[$code] as $ng => $count) {
        if (!isset($allNgrams[$ng])) continue;
        if ($count < 2) continue; // must appear at least twice in this category
        
        // Count how many OTHER categories have this n-gram
        $inOtherCats = 0;
        foreach ($docTypes as $otherCode => $otherInfo) {
            if ($code !== $otherCode && isset($ngramFreqs[$otherCode][$ng])) {
                $inOtherCats += $ngramFreqs[$otherCode][$ng];
            }
        }
        
        // If it rarely appears in other categories, it's distinctive
        if ($inOtherCats === 0) {
            $scores[$ng] = $count * 5; // highly distinctive
        } elseif ($count > $inOtherCats * 3) {
            $scores[$ng] = $count; // moderately distinctive
        }
    }
    
    arsort($scores);
    // Take top 15 new keywords
    $top = array_slice($scores, 0, 15, true);
    $newKeywords[$code] = $top;
    addLog("- Tìm thấy " . count($top) . " từ khóa mới cho $code.");
}

// 4. Update config/document_types.php
foreach ($docTypes as $code => &$info) {
    if (isset($newKeywords[$code])) {
        foreach ($newKeywords[$code] as $kw => $scoreRaw) {
            // Assign a bounded score
            $score = min(20, max(8, intval($scoreRaw)));
            // Only add if not strictly conflicting with an existing higher score
            if (!isset($info['keywords'][$kw])) {
                $info['keywords'][$kw] = $score;
            }
        }
        // sort keywords by score desc
        arsort($info['keywords']);
    }
}

// Generate the new PHP file content
$phpCode = "<?php\n// Danh sách loại giấy tờ / hồ sơ đất đai (3 nhóm chính)\n// Tự động học từ khóa (" . date('Y-m-d H:i:s') . ")\nreturn [\n";
foreach ($docTypes as $code => $info) {
    $phpCode .= "    '$code'  => [\n";
    $phpCode .= "        'name' => '" . addslashes($info['name']) . "',\n";
    $phpCode .= "        'short'=> '" . addslashes($info['short']) . "',\n";
    $phpCode .= "        'color'=> '" . addslashes($info['color']) . "',\n";
    $phpCode .= "        'keywords' => [\n";
    foreach ($info['keywords'] as $kw => $score) {
        $phpCode .= "            '" . addslashes($kw) . "' => $score,\n";
    }
    $phpCode .= "        ]\n";
    $phpCode .= "    ],\n";
}
$phpCode .= "];\n";

file_put_contents($docTypesFile, $phpCode);
addLog("=> Đã ghi lại cấu hình vào config/document_types.php");

echo json_encode(['success'=>true, 'logs'=>$logs]);
