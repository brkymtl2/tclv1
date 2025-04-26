<?php
// Sayfa başlığı
$pageTitle = 'Belge Görüntüle';

// Header'ı dahil et
require_once 'includes/header.php';

// Belge sınıfını dahil et
require_once 'includes/Document.php';

// Belge ID'si alın
$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($documentId <= 0) {
    Session::setFlashMessage('Geçersiz belge ID\'si.', 'danger');
    header('Location: documents.php');
    exit;
}

// Belge sınıfını başlat
$document = new Document();

// Belge bilgilerini al
$documentInfo = $document->getDocument($documentId);

if (!$documentInfo) {
    Session::setFlashMessage('Belge bulunamadı.', 'danger');
    header('Location: documents.php');
    exit;
}
if (!$isPopup) {
    require_once 'includes/Log.php';
    $log = new Log();
    $log->add('view_document', 'Belge görüntülendi: ' . $documentInfo['title'], 'document', $documentId);
}
// Belgeyi görüntüleme için hazırla
$documentFile = $document->viewDocument($documentId);

if (!$documentFile) {
    Session::setFlashMessage('Belge görüntülenemedi.', 'danger');
    header('Location: documents.php');
    exit;
}

// İsteğin türünü kontrol et
$isInline = isset($_GET['inline']) && $_GET['inline'] == '1';

// Popup modunda görüntülenip görüntülenmeyeceğini belirle
$isPopup = isset($_GET['popup']) && $_GET['popup'] == '1';

// Eğer popup modundaysa tam sayfa görüntüleme için header'ı dahil etmeyelim
if ($isPopup) {
    ob_end_clean(); // Önceki çıktıları temizle
    
    // Basit bir header ekleyelim
    echo '<!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($documentInfo['title']) . ' - Görüntüle</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { padding: 0; margin: 0; overflow: hidden; }
            .document-container { width: 100%; height: 100vh; }
            .toolbar { background-color: #f8f9fa; padding: 10px; border-bottom: 1px solid #dee2e6; }
        </style>
    </head>
    <body>';
    
    // Araç çubuğu
    echo '<div class="toolbar">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8">
                    <h5>' . htmlspecialchars($documentInfo['title']) . '</h5>
                </div>
                <div class="col-md-4 text-end">
                    <a href="download_document.php?id=' . $documentId . '" class="btn btn-sm btn-success" target="_blank">
                        <i class="bi bi-download"></i> İndir
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="window.close();">
                        <i class="bi bi-x-circle"></i> Kapat
                    </button>
                </div>
            </div>
        </div>
    </div>';
}
?>

<?php if (!$isPopup): ?>
<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="bi bi-eye"></i> Belge Görüntüle: <?php echo htmlspecialchars($documentInfo['title']); ?></h1>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Belge Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Belge Adı</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($documentInfo['title']); ?></dd>
                            
                            <dt class="col-sm-4">Kategori</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($documentInfo['category_name']); ?></dd>
                            
                            <?php if (!empty($documentInfo['subcategory_name'])): ?>
                            <dt class="col-sm-4">Alt Kategori</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($documentInfo['subcategory_name']); ?></dd>
                            <?php endif; ?>
                            
                            <dt class="col-sm-4">Açıklama</dt>
                            <dd class="col-sm-8">
                                <?php echo !empty($documentInfo['description']) ? htmlspecialchars($documentInfo['description']) : '<em>Açıklama yok</em>'; ?>
                            </dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-sm-4">Dosya Türü</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($documentInfo['file_type']); ?></dd>
                            
                            <dt class="col-sm-4">Dosya Boyutu</dt>
                            <dd class="col-sm-8"><?php echo formatFileSize($documentInfo['file_size']); ?></dd>
                            
                            <dt class="col-sm-4">Yükleyen</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($documentInfo['uploaded_by_name']); ?></dd>
                            
                            <dt class="col-sm-4">Yükleme Tarihi</dt>
                            <dd class="col-sm-8"><?php echo date('d.m.Y H:i', strtotime($documentInfo['created_at'])); ?></dd>
                        </dl>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="documents.php<?php echo isset($_GET['category']) ? '?category=' . (int)$_GET['category'] . (isset($_GET['subcategory']) ? '&subcategory=' . (int)$_GET['subcategory'] : '') : ''; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Belgelere Dön
                    </a>
                    <a href="download_document.php?id=<?php echo $documentId; ?>" class="btn btn-success">
                        <i class="bi bi-download"></i> İndir
                    </a>
                    <a href="view_document.php?id=<?php echo $documentId; ?>&popup=1" class="btn btn-primary" target="_blank">
                        <i class="bi bi-fullscreen"></i> Tam Ekran Görüntüle
                    </a>
                    <?php if (Session::hasRole(['super_admin', 'admin'])): ?>
                        <a href="edit_document.php?id=<?php echo $documentId; ?>" class="btn btn-info">
                            <i class="bi bi-pencil"></i> Düzenle
                        </a>
                        <a href="delete_document.php?id=<?php echo $documentId; ?>" class="btn btn-danger" onclick="return confirm('Bu belgeyi silmek istediğinizden emin misiniz?');">
                            <i class="bi bi-trash"></i> Sil
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Dosya türüne göre görüntüleme
$filePath = $documentFile['path'];
$originalName = $documentFile['name'];
$fileType = $documentFile['type'];

// Görüntülenebilen dosya türleri
$viewableTypes = [
    'application/pdf' => true,
    'image/jpeg' => true,
    'image/png' => true,
    'text/plain' => true
];

// Dosya görüntülenebiliyor mu?
$canView = isset($viewableTypes[$fileType]);

// Tam yol ve URL
$filePath = realpath($filePath);
$fileUrl = 'temp_view.php?file=' . basename($filePath) . '&type=' . urlencode($fileType);
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Belge İçeriği</h5>
            </div>
            <div class="card-body p-0">
                <?php if ($canView): ?>
                    <?php if ($fileType === 'application/pdf'): ?>
                        <!-- PDF dosyası için embed kullan -->
                        <div class="ratio ratio-16x9" style="height: <?php echo $isPopup ? '90vh' : '600px'; ?>;">
                            <iframe src="<?php echo $fileUrl; ?>" class="embed-responsive-item" allowfullscreen></iframe>
                        </div>
                    <?php elseif (strpos($fileType, 'image/') === 0): ?>
                        <!-- Resim dosyaları için img kullan -->
                        <div class="text-center p-3">
                            <img src="<?php echo $fileUrl; ?>" alt="<?php echo htmlspecialchars($originalName); ?>" class="img-fluid">
                        </div>
                    <?php elseif ($fileType === 'text/plain'): ?>
                        <!-- Metin dosyaları için iframe kullan -->
                        <div class="ratio ratio-16x9" style="height: <?php echo $isPopup ? '90vh' : '600px'; ?>;">
                            <iframe src="<?php echo $fileUrl; ?>" class="embed-responsive-item"></iframe>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info m-3">
                        <h4 class="alert-heading">Bu dosya türü doğrudan görüntülenemiyor.</h4>
                        <p>Bu belge türü (<?php echo htmlspecialchars($fileType); ?>) tarayıcıda doğrudan görüntülenemiyor. Belgeyi görüntülemek için lütfen indirin.</p>
                        <hr>
                        <p class="mb-0">
                            <a href="download_document.php?id=<?php echo $documentId; ?>" class="btn btn-success">
                                <i class="bi bi-download"></i> Belgeyi İndir
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!$isPopup): ?>
<!-- Temizleme işlemi için JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sayfa kapatıldığında geçici dosyaları temizle
    window.addEventListener('beforeunload', function() {
        fetch('cleanup_temp.php?file=<?php echo basename($filePath); ?>', {
            method: 'GET',
            keepalive: true
        });
    });
});
</script>
<?php
// Footer'ı dahil et
require_once 'includes/footer.php';
endif;

if ($isPopup) {
    echo '</body></html>';
}

// Dosya boyutunu formatla
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>