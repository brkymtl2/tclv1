<?php
// Sayfa başlığı
$pageTitle = 'Belge Yükle';

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
require_once 'includes/Tag.php';

// Belge, kategori ve etiket sınıflarını başlat
$document = new Document();
$category = new Category();
$tag = new Tag();

// Filtreler
$selectedCategoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;
$selectedSubcategoryId = isset($_GET['subcategory']) ? (int)$_GET['subcategory'] : null;

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
        $tags = isset($_POST['tags']) ? Security::sanitizeInput($_POST['tags']) : '';
        $isRenewable = isset($_POST['is_renewable']) && $_POST['is_renewable'] == '1';
        $expiryDate = isset($_POST['expiry_date']) && !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        
        // Dosya kontrolü
        if (empty($title) || $categoryId <= 0) {
            $error = 'Belge adı ve kategori gereklidir.';
        } elseif (!isset($_FILES['document']) || $_FILES['document']['error'] != UPLOAD_ERR_OK) {
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
                    // Yenilenebilir belge kontrolü
                    if ($isRenewable && empty($expiryDate)) {
                        $error = 'Yenilenebilir belgeler için son kullanma tarihi gereklidir.';
                    } else {
                        // Belge yükle
                        $result = $document->uploadDocument(
                            $title,
                            $description,
                            $categoryId,
                            $subcategoryId,
                            $_FILES['document'],
                            Session::getUserId()
                        );
                        
                        if ($result) {
                            // Belge ID'sini al
                            $documentId = $result;
                            
                            // Etiketleri ekle
                            if (!empty($tags)) {
                                $tag->addTagsToDocument($documentId, $tags);
                            }
                            
                            // Yenilenebilir belge ise son kullanma tarihini kaydet
                            if ($isRenewable && !empty($expiryDate)) {
                                $document->setDocumentExpiryDate($documentId, $expiryDate);
                            }
                            
                            // Log kaydı ekle
                            require_once 'includes/Log.php';
                            $log = new Log();
                            $log->add('create_document', 'Yeni belge yüklendi: ' . $title, 'document', $documentId);
                            
                            // Başarılı yükleme
                            Session::setFlashMessage('Belge başarıyla yüklendi.', 'success');
                            
                            // Başarılı bildirim göster
                            echo '<script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    showSuccess("Belge başarıyla yüklendi.");
                                });
                            </script>';
                            
                            // Kategori ve alt kategori parametreleriyle belge sayfasına yönlendir
                            $redirectUrl = 'documents.php?category=' . $categoryId;
                            if ($subcategoryId) {
                                $redirectUrl .= '&subcategory=' . $subcategoryId;
                            }
                            
                            header('Location: ' . $redirectUrl);
                            exit;
                        } else {
                            $error = 'Belge yüklenirken bir hata oluştu.';
                            
                            // Hata bildirimi göster
                            echo '<script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    showError("Belge yüklenirken bir hata oluştu.");
                                });
                            </script>';
                        }
                    }
                }
            }
        }
    }
}

// Tüm kategorileri al
$categories = $category->getAllCategories();

// Seçilen kategorinin alt kategorilerini al
$subcategories = [];
if ($selectedCategoryId) {
    $subcategories = $category->getSubCategories($selectedCategoryId);
}

// Popüler etiketleri al
$popularTags = $tag->getPopularTags(10);
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="bi bi-upload"></i> Belge Yükle</h1>
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
                <h5 class="mb-0"><i class="bi bi-file-earmark-plus"></i> Belge Bilgileri</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Belge Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Kategori <span class="text-danger">*</span></label>
                        <select class="form-select" id="category_id" name="category_id" required onchange="loadSubcategories(this.value)">
                            <option value="">Kategori Seçin</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $selectedCategoryId == $cat['id'] ? 'selected' : ''; ?>>
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
                                <option value="<?php echo $subcat['id']; ?>" <?php echo $selectedSubcategoryId == $subcat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subcat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Opsiyonel. Eğer kategori için alt kategori tanımlanmadıysa bu alan devre dışıdır.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tags" class="form-label">Etiketler</label>
                        <input type="text" class="form-control" id="tags" name="tags" placeholder="Etiketleri virgülle ayırarak yazın...">
                        <div class="form-text">Opsiyonel. Belgeyi sınıflandırmak için etiketler ekleyin (örn: ISO9001, Kalibrasyon, Müşteri).</div>
                        
                        <?php if (!empty($popularTags)): ?>
                            <div class="mt-2">
                                <small class="text-muted">Popüler Etiketler:</small>
                                <?php foreach ($popularTags as $ptag): ?>
                                    <span class="badge bg-secondary tag-badge" onclick="addTag('<?php echo htmlspecialchars($ptag['name']); ?>')" style="cursor: pointer;">
                                        <?php echo htmlspecialchars($ptag['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_renewable" name="is_renewable" value="1" onchange="toggleExpiryDate()">
                        <label class="form-check-label" for="is_renewable">Yenilemeli Belge</label>
                        <div class="form-text">İşaretlenirse, belgenin son kullanma tarihi takip edilir ve yenileme gerektiğinde bildirim yapılır.</div>
                    </div>
                    
                    <div class="mb-3" id="expiry_date_container" style="display: none;">
                        <label for="expiry_date" class="form-label">Son Kullanma Tarihi</label>
                        <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                    </div>
                    
                    <div class="mb-3">
                        <label for="document" class="form-label">Dosya <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="document" name="document" required>
                        <div class="form-text">Desteklenen dosya türleri: PDF, Word, Excel, PowerPoint, JPEG, PNG, TXT. Maksimum boyut: 20MB.</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Belgeyi Yükle</button>
                        <a href="documents.php<?php echo $selectedCategoryId ? '?category=' . $selectedCategoryId . ($selectedSubcategoryId ? '&subcategory=' . $selectedSubcategoryId : '') : ''; ?>" class="btn btn-secondary">İptal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Alt kategorileri yükle
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

// Son kullanma tarihi alanını göster/gizle
function toggleExpiryDate() {
    var isRenewable = document.getElementById('is_renewable').checked;
    var expiryDateContainer = document.getElementById('expiry_date_container');
    
    if (isRenewable) {
        expiryDateContainer.style.display = 'block';
        document.getElementById('expiry_date').required = true;
    } else {
        expiryDateContainer.style.display = 'none';
        document.getElementById('expiry_date').required = false;
    }
}

// Etiket ekle
function addTag(tagName) {
    var tagsInput = document.getElementById('tags');
    var currentTags = tagsInput.value.split(',').map(tag => tag.trim()).filter(tag => tag !== '');
    
    // Etiket zaten var mı kontrol et
    if (!currentTags.includes(tagName)) {
        if (currentTags.length > 0) {
            tagsInput.value = currentTags.join(', ') + ', ' + tagName;
        } else {
            tagsInput.value = tagName;
        }
    }
}

// Sayfa yüklendiğinde alt kategorileri yükle
document.addEventListener('DOMContentLoaded', function() {
    var categorySelect = document.getElementById('category_id');
    
    if (categorySelect.value) {
        loadSubcategories(categorySelect.value);
    }
});
</script>

<?php
// Footer'ı dahil et
require_once 'includes/footer.php';
?>