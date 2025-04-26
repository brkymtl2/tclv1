<?php
// Oturumu başlat
require_once 'includes/Session.php';
require_once 'includes/Security.php';
Session::start();

// Kullanıcı giriş yapmış mı?
if (!Session::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Sadece süper admin ve admin erişebilir
if (!Session::hasRole(['super_admin', 'admin'])) {
    Session::setFlashMessage('Bu işlem için yetkiniz bulunmamaktadır.', 'danger');
    header('Location: documents.php');
    exit;
}

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

// Onay istendiğinde
if (isset($_GET['confirm']) && $_GET['confirm'] == '1') {
    // CSRF token kontrolü (GET ile geldiyse)
    if (!isset($_GET['token']) || !Security::validateCSRFToken($_GET['token'])) {
        Session::setFlashMessage('Güvenlik doğrulaması başarısız oldu.', 'danger');
        header('Location: documents.php');
        exit;
    }
    
    // Belgeyi sil
    $result = $document->deleteDocument($documentId);
    
    if ($result) {
        // Log kaydı ekle
        require_once 'includes/Log.php';
        $log = new Log();
        $log->add('delete_document', 'Belge silindi: ' . $documentInfo['title'], 'document', $documentId);
        
        Session::setFlashMessage('Belge başarıyla silindi.', 'success');
    } else {
        Session::setFlashMessage('Belge silinirken bir hata oluştu.', 'danger');
    }
    
    
    // Yönlendirme yapılacak sayfa
    $redirectUrl = 'documents.php';
    if (isset($_GET['category'])) {
        $redirectUrl .= '?category=' . (int)$_GET['category'];
        if (isset($_GET['subcategory'])) {
            $redirectUrl .= '&subcategory=' . (int)$_GET['subcategory'];
        }
    }
    
    header('Location: ' . $redirectUrl);
    exit;
}

// Sayfa başlığı
$pageTitle = 'Belge Sil';

// Header'ı dahil et
require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="bi bi-trash"></i> Belge Sil</h1>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Belge Silme Onayı</h5>
            </div>
            <div class="card-body">
                <p class="lead">Aşağıdaki belgeyi silmek istediğinize emin misiniz?</p>
                
                <div class="alert alert-warning">
                    <h5><i class="bi bi-file-earmark-text"></i> <?php echo htmlspecialchars($documentInfo['title']); ?></h5>
                    <p><?php echo !empty($documentInfo['description']) ? htmlspecialchars($documentInfo['description']) : '<em>Açıklama yok</em>'; ?></p>
                    <ul class="list-unstyled">
                        <li><strong>Kategori:</strong> <?php echo htmlspecialchars($documentInfo['category_name']); ?></li>
                        <?php if (!empty($documentInfo['subcategory_name'])): ?>
                            <li><strong>Alt Kategori:</strong> <?php echo htmlspecialchars($documentInfo['subcategory_name']); ?></li>
                        <?php endif; ?>
                        <li><strong>Yükleyen:</strong> <?php echo htmlspecialchars($documentInfo['uploaded_by_name']); ?></li>
                        <li><strong>Yükleme Tarihi:</strong> <?php echo date('d.m.Y H:i', strtotime($documentInfo['created_at'])); ?></li>
                    </ul>
                </div>
                
                <div class="alert alert-danger">
                    <strong>DİKKAT:</strong> Bu işlem geri alınamaz. Belge kalıcı olarak silinecektir.
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="documents.php<?php echo isset($_GET['category']) ? '?category=' . (int)$_GET['category'] . (isset($_GET['subcategory']) ? '&subcategory=' . (int)$_GET['subcategory'] : '') : ''; ?>" class="btn btn-secondary me-md-2">
                        <i class="bi bi-x-circle"></i> İptal
                    </a>
                    <a href="delete_document.php?id=<?php echo $documentId; ?>&confirm=1&token=<?php echo $csrf_token; ?><?php echo isset($_GET['category']) ? '&category=' . (int)$_GET['category'] : ''; ?><?php echo isset($_GET['subcategory']) ? '&subcategory=' . (int)$_GET['subcategory'] : ''; ?>" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Evet, Belgeyi Sil
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
require_once 'includes/footer.php';
?>