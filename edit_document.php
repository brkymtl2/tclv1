<?php
// Sayfa başlığı
$pageTitle = 'Belge Düzenle';

// Header'ı dahil et
require_once 'includes/header.php';

// Sadece süper admin ve admin erişebilir
if (!Session::hasRole(['super_admin', 'admin'])) {
    Session::setFlashMessage('Bu sayfaya erişim yetkiniz bulunmamaktadır.', 'danger');
    header('Location: dashboard.php');
    exit;
}

// Sınıfları dahil et
require_once 'includes/Document.php';
require_once 'includes/Category.php';

// Belge ve kategori sınıflarını başlat
$document = new Document();
$category = new Category();

// Belge ID'si alın
$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($documentId <= 0) {
    Session::setFlashMessage('Geçersiz belge ID\'si.', 'danger');
    header('Location: documents.php');
    exit;
}

// Belge bilgilerini al
$documentInfo = $document->getDocument($documentId);

if (!$documentInfo) {
    Session::setFlashMessage('Belge bulunamadı.', 'danger');
    header('Location: documents.php');
    exit;
}

// İşlem mesajları
$message = '';
$error = '';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        // Form verilerini al
        $title = isset($_POST['title']) ? Security::sanitizeInput($_POST['title']) : '';
        $description = isset($_POST['description']) ? Security::sanitizeInput($_POST['description']) : '';
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $subcategoryId = isset($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $changeFile = isset($_POST['change_file']) && $_POST['change_file'] == '1';
        
        // Veri doğrulama
        if (empty($title) || $categoryId <= 0) {
            $error = 'Belge adı ve kategori gereklidir.';
        } else {
            // Belge bilgileri güncelleniyor
            $updateData = [
                'title' => $title,
                'description' => $description,
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId ?: null
            ];
            
            $updateResult = $document->updateDocument($documentId, $updateData);
            
            // Dosya değişecek mi?
            if ($changeFile) {
                // Dosya kontrolü
                if (!isset($_FILES['document']) || $_FILES['document']['error'] != UPLOAD_ERR_OK) {
                    $error = 'Lütfen geçerli bir dosya seçin.';
                } else {
                    // Dosya boyutu kontrolü (örn: 20MB)
                    $maxFileSize = 20 * 1024 * 1024; // 20MB
                    if ($_FILES['document']['size'] > $maxFileSize) {
                        $error = 'Dosya boyutu çok büyük. Maksimum 20MB olabilir.';
                    } else {
                        // Dosya türü kontrolü (kabul edilebilir dosya türleri)
                        $allowedTypes = [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-powerpoint',
                            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                            'image/jpeg',
                            'image/png',
                            'text/plain'
                        ];
                        
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $fileType = $finfo->file($_FILES['document']['tmp_name']);
                        
                        if (!in_array($fileType, $allowedTypes)) {
                            $error = 'Desteklenmeyen dosya türü. Lütfen PDF, Word, Excel, PowerPoint, JPEG, PNG veya TXT dosyası yükleyin.';
                        } else {
                            // Dosya güncellemesi
                            $fileUpdateResult = $document->updateDocumentFile($documentId, $_FILES['document']);
                            
                            if (!$fileUpdateResult) {
                                $error = 'Dosya güncellenirken bir hata oluştu.';
                            }
                        }
                    }
                }
            }
            
            // Hata yoksa ve güncellemeler başarılıysa
            if (empty($error) && ($updateResult || ($changeFile && isset($fileUpdateResult) && $fileUpdateResult))) {
                // Log kaydı ekle
                require_once 'includes/Log.php';
                $log = new Log();
                $logMessage = 'Belge güncellendi: ' . $title;
                if ($changeFile) {
                    $logMessage .= ' (dosya değiştirildi)';
                }
                $log->add('update_document', $logMessage, 'document', $documentId);
                
                Session::setFlashMessage('Belge başarıyla güncellendi.', 'success');
                
                // Kategori ve alt kategori parametreleriyle belge görüntüleme sayfasına yönlendir
                header('Location: view_document.php?id=' . $documentId);
                exit;
            } elseif (empty($error)) {
                $error = 'Belge güncellenirken bir hata oluştu.';
            }
        }
    }
}

// Tüm kategorileri al
$categories = $category->getAllCategories();

// Seçilen kategorinin alt kategorilerini al
$subcategories = $category->getSubCategories($documentInfo['category_id']);
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="bi bi-pencil-square"></i> Belge Düzenle: <?php echo htmlspecialchars($documentInfo['title']); ?></h1>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Belge Bilgileri</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Belge Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($documentInfo['title']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($documentInfo['description']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Kategori <span class="text-danger">*</span></label>
                        <select class="form-select" id="category_id" name="category_id" required onchange="loadSubcategories(this.value)">
                            <option value="">Kategori Seçin</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $documentInfo['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subcategory_id" class="form-label">Alt Kategori</label>
                        <select class="form-select" id="subcategory_id" name="subcategory_id" <?php echo empty($subcategories) ? 'disabled' : ''; ?>>
                            <option value="">Alt Kategori Seçin</option>
                            <?php foreach ($subcategories as $subcat): ?>
                                <option value="<?php echo $subcat['id']; ?>" <?php echo $documentInfo['subcategory_id'] == $subcat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subcat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Opsiyonel. Eğer kategori için alt kategori tanımlanmadıysa bu alan devre dışıdır.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="change_file" name="change_file" value="1" onchange="toggleFileUpload()">
                            <label class="form-check-label" for="change_file">
                                Dosyayı Değiştir
                            </label>
                        </div>
                    </div>
                    
                    <div id="file_upload_container" style="display: none;">
                        <div class="mb-3">
                            <label for="document" class="form-label">Yeni Dosya <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="document" name="document">
                            <div class="form-text">Desteklenen dosya türleri: PDF, Word, Excel, PowerPoint, JPEG, PNG, TXT. Maksimum boyut: 20MB.</div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Mevcut Dosya Bilgileri</h6>
                        <ul class="mb-0">
                            <li><strong>Dosya Türü:</strong> <?php echo htmlspecialchars($documentInfo['file_type']); ?></li>
                            <li><strong>Dosya Boyutu:</strong> <?php echo formatFileSize($documentInfo['file_size']); ?></li>
                            <li><strong>Yükleme Tarihi:</strong> <?php echo date('d.m.Y H:i', strtotime($documentInfo['created_at'])); ?></li>
                        </ul>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                        <a href="view_document.php?id=<?php echo $documentId; ?>" class="btn btn-secondary">İptal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Kategori değiştiğinde alt kategorileri yükle
function loadSubcategories(categoryId) {
    var subcategorySelect = document.getElementById('subcategory_id');
    subcategorySelect.innerHTML = '<option value="">Alt Kategori Seçin</option>';
    
    if (!categoryId) {
        subcategorySelect.disabled = true;
        return;
    }
    
    // AJAX ile alt kategorileri getir
    fetch('get_subcategories.php?category_id=' + categoryId)
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                data.forEach(subcat => {
                    var option = document.createElement('option');
                    option.value = subcat.id;
                    option.textContent = subcat.name;
                    subcategorySelect.appendChild(option);
                });
                subcategorySelect.disabled = false;
            } else {
                subcategorySelect.disabled = true;
            }
        })
        .catch(error => {
            console.error('Alt kategoriler yüklenirken hata oluştu:', error);
            subcategorySelect.disabled = true;
        });
}

// Dosya yükleme alanını göster/gizle
function toggleFileUpload() {
    var changeFileCheckbox = document.getElementById('change_file');
    var fileUploadContainer = document.getElementById('file_upload_container');
    var documentInput = document.getElementById('document');
    
    if (changeFileCheckbox.checked) {
        fileUploadContainer.style.display = 'block';
        documentInput.required = true;
    } else {
        fileUploadContainer.style.display = 'none';
        documentInput.required = false;
    }
}

// Sayfa yüklendiğinde alt kategorileri kontrol et
document.addEventListener('DOMContentLoaded', function() {
    // Başlangıçta form durumunu ayarla
    toggleFileUpload();
});
</script>

<?php
// Footer'ı dahil et
require_once 'includes/footer.php';

// Dosya boyutunu formatla
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-