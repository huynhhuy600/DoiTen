<?php
require __DIR__ . '/config/settings.php';
$docTypes = require __DIR__ . '/config/document_types.php';

// Đảm bảo thư mục tồn tại
$trainDir = __DIR__ . '/training_data';
if (!is_dir($trainDir)) mkdir($trainDir, 0777, true);
foreach ($docTypes as $code => $info) {
    if (!is_dir("$trainDir/$code")) mkdir("$trainDir/$code", 0777, true);
}

// Đếm số file trong mỗi thư mục
$counts = [];
foreach ($docTypes as $code => $info) {
    $dir = "$trainDir/$code";
    $files = glob("$dir/*.pdf");
    $counts[$code] = count($files ?: []);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Học từ khóa OCR - Hệ thống Hồ sơ Đất đai</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="css/style.css">
<style>
.train-box { background: var(--bg2); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 20px; }
.cat-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-top: 16px; }
.cat-item { background: var(--bg1); padding: 16px; border-radius: 8px; border: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.cat-item .info { display: flex; flex-direction: column; gap: 4px; }
.cat-item .badge { padding: 4px 8px; border-radius: 4px; color: #fff; font-size: 12px; font-weight: bold; }
.cat-item .count { font-size: 24px; font-weight: bold; color: var(--cyan); }
#logBox { background: #1e1e1e; color: #d4d4d4; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 13px; height: 300px; overflow-y: auto; line-height: 1.5; margin-top: 16px; display: none; }
#logBox span.ok { color: #4ade80; }
#logBox span.err { color: #f87171; }
#logBox span.info { color: #60a5fa; }
</style>
</head>
<body>

<header class="header">
  <div class="header-logo" onclick="window.location.href='index.php'" style="cursor:pointer;" title="Về trang chủ">🎓</div>
  <div onclick="window.location.href='index.php'" style="cursor:pointer;"><h1>Tự động học từ khóa (Training)</h1><p>Phân tích các file mẫu để tìm từ khóa đặc trưng</p></div>
  <a href="index.php" class="btn btn-secondary" style="margin-left:auto">← Quay lại trang chính</a>
</header>

<div class="app" style="max-width:1000px; margin: 30px auto; display: block;">
  
  <div class="train-box">
    <h3>📂 Dữ liệu huấn luyện</h3>
    <p style="color:var(--text3); font-size:14px; margin-top:8px;">
      Vui lòng copy các file PDF chuẩn của bạn vào các thư mục tương ứng trong <code>DoiTen/training_data/</code>. Hệ thống sẽ tự động quét và đếm số lượng file bên dưới.
    </p>
    
    <div class="cat-list">
      <?php foreach ($docTypes as $code => $info): ?>
      <div class="cat-item">
        <div class="info">
          <span class="badge" style="background:<?= $info['color'] ?>"><?= $code ?></span>
          <span style="font-size:14px; color:var(--text2)"><?= $info['short'] ?></span>
        </div>
        <div class="count"><?= $counts[$code] ?> <span style="font-size:12px; color:var(--text3); font-weight:normal">file</span></div>
      </div>
      <?php endforeach; ?>
    </div>
    
    <div style="margin-top: 24px; text-align: center;">
      <button class="btn btn-primary" id="btnTrain" onclick="startTraining()" style="padding: 12px 24px; font-size: 16px;">
        🚀 Bắt đầu phân tích & Cập nhật từ khóa
      </button>
    </div>
  </div>

  <div class="train-box" id="resultBox" style="display:none;">
    <h3>📊 Quá trình xử lý</h3>
    <div class="progress-wrap" id="trainProgress" style="display:block; margin-top:16px;">
      <div class="progress-bar"><div class="progress-fill" id="trainFill" style="width:0%"></div></div>
      <div class="progress-text" id="trainText" style="text-align:center; margin-top:8px;">Đang khởi động...</div>
    </div>
    
    <div id="logBox"></div>
  </div>

</div>

<script>
async function startTraining() {
  document.getElementById('resultBox').style.display = 'block';
  document.getElementById('logBox').style.display = 'block';
  document.getElementById('btnTrain').disabled = true;
  document.getElementById('btnTrain').textContent = 'Đang xử lý...';
  
  const logBox = document.getElementById('logBox');
  const fill = document.getElementById('trainFill');
  const txt = document.getElementById('trainText');
  
  function log(msg, type='info') {
    logBox.innerHTML += `<span class="${type}">${msg}</span><br>`;
    logBox.scrollTop = logBox.scrollHeight;
  }
  
  log('Bắt đầu quá trình huấn luyện...', 'info');
  fill.style.width = '10%'; txt.textContent = 'Đang quét và OCR văn bản... (có thể mất vài phút)';
  
  try {
    const res = await fetch('api/train.php', { method: 'POST' });
    const text = await res.text();
    let data;
    try {
        data = JSON.parse(text);
    } catch(e) {
        fill.style.width = '100%'; fill.style.background = 'var(--red)';
        txt.textContent = 'Lỗi máy chủ!';
        log('LỖI MÁY CHỦ: Không thể đọc dữ liệu JSON. Chi tiết lỗi từ server:', 'err');
        log(text.substring(0, 500), 'err');
        document.getElementById('btnTrain').disabled = false;
        document.getElementById('btnTrain').textContent = 'Thử lại';
        return;
    }
    
    if (data.success) {
      fill.style.width = '100%';
      txt.textContent = 'Hoàn tất cập nhật!';
      log('<br><b>🎉 HOÀN TẤT PHÂN TÍCH:</b>', 'ok');
      data.logs.forEach(l => log(l, 'info'));
      log('<br>✅ Đã cập nhật file config/document_types.php thành công.', 'ok');
      document.getElementById('btnTrain').textContent = 'Đã cập nhật xong!';
    } else {
      fill.style.width = '100%'; fill.style.background = 'var(--red)';
      txt.textContent = 'Có lỗi xảy ra!';
      log('LỖI: ' + data.error, 'err');
      document.getElementById('btnTrain').disabled = false;
      document.getElementById('btnTrain').textContent = 'Thử lại';
    }
  } catch (err) {
    fill.style.width = '100%'; fill.style.background = 'var(--red)';
    txt.textContent = 'Lỗi kết nối';
    log('LỖI KẾT NỐI: ' + err.message, 'err');
    document.getElementById('btnTrain').disabled = false;
    document.getElementById('btnTrain').textContent = 'Thử lại';
  }
}
</script>
</body>
</html>
