<?php
// Sayfa başlığı
$pageTitle = 'Kategoriler';

// Header'ı dahil et
require_once 'includes/header.php';

// Sadece süper admin ve admin erişebilir
if (!Session::hasRole(['super_admin', 'admin'])) {
    Session::setFlashMessage('Bu sayfaya erişim yetkiniz bulunmamaktadır.', 'danger');
    header('Location: dashboard.php');
    exit;
}

// Category sınıfını dahil et
require_once 'includes/Category.php';
$category = new Category();

// İşlem mesajları
$message = '';
$error = '';

// Yeni kategori ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $name = isset($_POST['name']) ? Security::sanitizeInput($_POST['name']) : '';
        $description = isset($_POST['description']) ? Security::sanitizeInput($_POST['description']) : '';
        
        if (empty($name)) {
            $error = 'Kategori adı gereklidir.';
        } else {
            $result = $category->createCategory($name, $description);
            
            if ($result) {
                // Log kaydı ekle
                require_once 'includes/Log.php';
                $log = new Log();
                $log->add('create_category', 'Yeni kategori oluşturuldu: ' . $name, 'category', $result);
                
                $message = 'Kategori başarıyla oluşturuldu.';
            } else {
                $error = 'Kategori oluşturulurken bir hata oluştu. Kategori adı benzersiz olmalıdır.';
            }
        }
    }
}

// Alt kategori ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_subcategory') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $parentCategoryId = isset($_POST['parent_category_id']) ? (int)$_POST['parent_category_id'] : 0;
        $name = isset($_POST['name']) ? Security::sanitizeInput($_POST['name']) : '';
        $description = isset($_POST['description']) ? Security::sanitizeInput($_POST['description']) : '';
        
        if (empty($name) || $parentCategoryId <= 0) {
            $error = 'Kategori ve alt kategori adı gereklidir.';
        } else {
            $result = $category->createSubCategory($parentCategoryId, $name, $description);
            
            if ($result) {
                // Log kaydı ekle
                require_once 'includes/Log.php';
                $log = new Log();
                $log->add('create_subcategory', 'Yeni alt kategori oluşturuldu: ' . $name, 'subcategory', $result);
                
                $message = 'Alt kategori başarıyla oluşturuldu.';
            } else {
                $error = 'Alt kategori oluşturulurken bir hata oluştu. Alt kategori adı benzersiz olmalıdır.';
            }
        }
    }
}

// Kategori düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_category') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $name = isset($_POST['name']) ? Security::sanitizeInput($_POST['name']) : '';
        $description = isset($_POST['description']) ? Security::sanitizeInput($_POST['description']) : '';
        
        if (empty($name) || $categoryId <= 0) {
            $error = 'Kategori ID ve adı gereklidir.';
        } else {
            $result = $category->updateCategory($categoryId, $name, $description);
            
            if ($result) {
                // Log kaydı ekle
                require_once 'includes/Log.php';
                $log = new Log();
                $log->add('update_category', 'Kategori güncellendi: ' . $name, 'category', $categoryId);
                
                $message = 'Kategori başarıyla güncellendi.';
            } else {
                $error = 'Kategori güncellenirken bir hata oluştu. Kategori adı benzersiz olmalıdır.';
            }
        }
    }
}

// Alt kategori düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_subcategory') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $subcategoryId = isset($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : 0;
        $name = isset($_POST['name']) ? Security::sanitizeInput($_POST['name']) : '';
        $description = isset($_POST['description']) ? Security::sanitizeInput($_POST['description']) : '';
        
        if (empty($name) || $subcategoryId <= 0) {
            $error = 'Alt kategori ID ve adı gereklidir.';
        } else {
            $result = $category->updateSubCategory($subcategoryId, $name, $description);
            
            if ($result) {
                // Log kaydı ekle
                require_once 'includes/Log.php';
                $log = new Log();
                $log->add('update_subcategory', 'Alt kategori güncellendi: ' . $name, 'subcategory', $subcategoryId);
                
                $message = 'Alt kategori başarıyla güncellendi.';
            } else {
                $error = 'Alt kategori güncellenirken bir hata oluştu. Alt kategori adı benzersiz olmalıdır.';
            }
        }
    }
}

// Kategori silme işlemi (GET ile)
if (isset($_GET['action']) && $_GET['action'] === 'delete_category' && isset($_GET['id'])) {
    $categoryId = (int)$_GET['id'];
    $result = $category->deleteCategory($categoryId);
    
    if ($result) {
        // Log kaydı ekle
        require_once 'includes/Log.php';
        $log = new Log();
        $log->add('delete_category', 'Kategori silindi: ID ' . $categoryId, 'category', $categoryId);
        
        Session::setFlashMessage('Kategori başarıyla silindi.', 'success');
    } else {
        Session::setFlashMessage('Kategori silinemedi. Kategori içinde belgeler bulunuyor olabilir.', 'danger');
    }
    
    // Sayfayı yeniden yükle
    header('Location: categories.php');
    exit;
}

// Alt kategori silme işlemi (GET ile)
if (isset($_GET['action']) && $_GET['action'] === 'delete_subcategory' && isset($_GET['id'])) {
    $subcategoryId = (int)$_GET['id'];
    $result = $category->deleteSubCategory($subcategoryId);
    
    if ($result) {
        // Log kaydı ekle
        require_once 'includes/Log.php';
        $log = new Log();
        $log->add('delete_subcategory', 'Alt kategori silindi: ID ' . $subcategoryId, 'subcategory', $subcategoryId);
        
        Session::setFlashMessage('Alt kategori başarıyla silindi.', 'success');
    } else {
        Session::setFlashMessage('Alt kategori silinemedi. Alt kategori içinde belgeler bulunuyor olabilir.', 'danger');
    }
    
    // Sayfayı yeniden yükle
    header('Location: categories.php');
    exit;
}

// Tüm kategorileri al
$categories = $category->getAllCategories();

// Her kategori için alt kategorileri al
foreach ($categories as &$cat) {
    $cat['subcategories'] = $category->getSubCategories($cat['id']);
}
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="bi bi-folder"></i> Kategoriler</h1>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-list"></i> Kategori Listesi</h5>
            </div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <p class="text-muted">Henüz kategori bulunmamaktadır.</p>
                <?php else: ?>
                    <div class="accordion" id="categoriesAccordion">
                        <?php foreach ($categories as $cat): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?php echo $cat['id']; ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $cat['id']; ?>" aria-expanded="false" aria-controls="collapse<?php echo $cat['id']; ?>">
                                        <strong><?php echo htmlspecialchars($cat['name']); ?></strong>
                                        <?php if (!empty($cat['description'])): ?>
                                            <small class="text-muted ms-2"><?php echo htmlspecialchars($cat['description']); ?></small>
                                        <?php endif; ?>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $cat['id']; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $cat['id']; ?>" data-bs-parent="#categoriesAccordion">
                                    <div class="accordion-body">
                                        <div class="mb-3">
                                            <a href="documents.php?category=<?php echo $cat['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-file-earmark-text"></i> Belgeleri Görüntüle
                                            </a>
                                            <a href="#" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editCategoryModal" 
                                               data-category-id="<?php echo $cat['id']; ?>" 
                                               data-category-name="<?php echo htmlspecialchars($cat['name']); ?>" 
                                               data-category-description="<?php echo htmlspecialchars($cat['description']); ?>">
                                                <i class="bi bi-pencil"></i> Düzenle
                                            </a>
                                            <?php if (Session::hasRole('super_admin')): ?>
                                                <a href="categories.php?action=delete_category&id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bu kategoriyi silmek istediğinizden emin misiniz?');">
                                                    <i class="bi bi-trash"></i> Sil
                                                </a>
                                            <?php endif; ?>
                                            <a href="#" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addSubcategoryModal" data-parent-id="<?php echo $cat['id']; ?>">
                                                <i class="bi bi-plus"></i> Alt Kategori Ekle
                                            </a>
                                        </div>
                                        
                                        <?php if (!empty($cat['subcategories'])): ?>
                                            <h6 class="mt-3">Alt Kategoriler:</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Alt Kategori Adı</th>
                                                            <th>Açıklama</th>
                                                            <th>İşlemler</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($cat['subcategories'] as $subcat): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($subcat['name']); ?></td>
                                                                <td><?php echo htmlspecialchars($subcat['description']); ?></td>
                                                                <td>
                                                                    <a href="documents.php?category=<?php echo $cat['id']; ?>&subcategory=<?php echo $subcat['id']; ?>" class="btn btn-sm btn-primary">
                                                                        <i class="bi bi-file-earmark-text"></i> Belgeler
                                                                    </a>
                                                                    <a href="#" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editSubcategoryModal" 
                                                                       data-subcategory-id="<?php echo $subcat['id']; ?>" 
                                                                       data-subcategory-name="<?php echo htmlspecialchars($subcat['name']); ?>" 
                                                                       data-subcategory-description="<?php echo htmlspecialchars($subcat['description']); ?>">
                                                                        <i class="bi bi-pencil"></i> Düzenle
                                                                    </a>
                                                                    <?php if (Session::hasRole('super_admin')): ?>
                                                                        <a href="categories.php?action=delete_subcategory&id=<?php echo $subcat['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bu alt kategoriyi silmek istediğinizden emin misiniz?');">
                                                                            <i class="bi bi-trash"></i> Sil
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">Bu kategoriye ait alt kategori bulunmamaktadır.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Yeni Kategori Ekle</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add_category">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Kategori Adı</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success">Kategori Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Kategori Düzenleme Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Kategori Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Kategori Adı</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Alt Kategori Ekleme Modal -->
<div class="modal fade" id="addSubcategoryModal" tabindex="-1" aria-labelledby="addSubcategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSubcategoryModalLabel">Alt Kategori Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add_subcategory">
                    <input type="hidden" name="parent_category_id" id="parent_category_id">
                    
                    <div class="mb-3">
                        <label for="subcategory_name" class="form-label">Alt Kategori Adı</label>
                        <input type="text" class="form-control" id="subcategory_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subcategory_description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="subcategory_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">Alt Kategori Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Alt Kategori Düzenleme Modal -->
<div class="modal fade" id="editSubcategoryModal" tabindex="-1" aria-labelledby="editSubcategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSubcategoryModalLabel">Alt Kategori Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="edit_subcategory">
                    <input type="hidden" name="subcategory_id" id="edit_subcategory_id">
                    
                    <div class="mb-3">
                        <label for="edit_subcategory_name" class="form-label">Alt Kategori Adı</label>
                        <input type="text" class="form-control" id="edit_subcategory_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_subcategory_description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="edit_subcategory_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Kategori düzenleme modalını hazırla
document.addEventListener('DOMContentLoaded', function() {
    var editCategoryModal = document.getElementById('editCategoryModal');
    if (editCategoryModal) {
        editCategoryModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var categoryId = button.getAttribute('data-category-id');
            var categoryName = button.getAttribute('data-category-name');
            var categoryDescription = button.getAttribute('data-category-description');
            
            var modal = this;
            modal.querySelector('#edit_category_id').value = categoryId;
            modal.querySelector('#edit_name').value = categoryName;
            modal.querySelector('#edit_description').value = categoryDescription;
        });
    }
    
    // Alt kategori ekleme modalını hazırla
    var addSubcategoryModal = document.getElementById('addSubcategoryModal');
    if (addSubcategoryModal) {
        addSubcategoryModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var parentId = button.getAttribute('data-parent-id');
            
            var modal = this;
            modal.querySelector('#parent_category_id').value = parentId;
        });
    }
    
    // Alt kategori düzenleme modalını hazırla
    var editSubcategoryModal = document.getElementById('editSubcategoryModal');
    if (editSubcategoryModal) {
        editSubcategoryModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var subcategoryId = button.getAttribute('data-subcategory-id');
            var subcategoryName = button.getAttribute('data-subcategory-name');
            var subcategoryDescription = button.getAttribute('data-subcategory-description');
            
            var modal = this;
            modal.querySelector('#edit_subcategory_id').value = subcategoryId;
            modal.querySelector('#edit_subcategory_name').value = subcategoryName;
            modal.querySelector('#edit_subcategory_description').value = subcategoryDescription;
        });
    }
});
</script>

<?php
// Footer'ı dahil et
require_once 'includes/footer.php';
?>