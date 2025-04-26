<?php
// Sayfa başlığı
$pageTitle = 'Belgeler';

// Header'ı dahil et
require_once 'includes/header.php';

// Sınıfları dahil et
require_once 'includes/Document.php';
require_once 'includes/Category.php';

// Belge ve kategori sınıflarını başlat
$document = new Document();
$category = new Category();

// Filtreler
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;
$subcategoryId = isset($_GET['subcategory']) ? (int)$_GET['subcategory'] : null;
$searchTerm = isset($_GET['search']) ? Security::sanitizeInput($_GET['search']) : '';

// Sayfalama
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Kategori ve alt kategori bilgilerini al
$categoryName = '';
$subcategoryName = '';

if ($categoryId) {
    $categoryInfo = $category->getCategory($categoryId);
    if ($categoryInfo) {
        $categoryName = $categoryInfo['name'];
    }
}

if ($subcategoryId) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT name FROM subcategories WHERE id = ?");
        $stmt->execute([$subcategoryId]);
        
        if ($stmt->rowCount() > 0) {
            $subcategoryName = $stmt->fetch()['name'];
        }
    } catch (PDOException $e) {
        // Alt kategori bilgisi alınamazsa boş kalır
    }
}

// Belgeleri al
if (!empty($searchTerm)) {
    $documents = $document->searchDocuments($searchTerm, $categoryId, $subcategoryId);
    $totalDocuments = count($documents);
    $documents = array_slice($documents, $offset, $perPage);
} else {
    $totalDocuments = $document->getDocumentCount($categoryId, $subcategoryId);
    $documents = $document->getAllDocuments($categoryId, $subcategoryId, $perPage, $offset);
}

// Toplam sayfa sayısı
$totalPages = ceil($totalDocuments / $perPage);

// Tüm kategorileri al (filtreleme için)
$categories = $category->getAllCategories();

// Seçilen kategorinin alt kategorilerini al
$subcategories = [];
if ($categoryId) {
    $subcategories = $category->getSubCategories($categoryId);
}

// Başlık oluştur
$title = 'Belgeler';
if (!empty($categoryName)) {
    $title = $categoryName;
    if (!empty($subcategoryName)) {
        $title .= ' / ' . $subcategoryName;
    }
    $title .= ' Belgeleri';
}
?>

<div class="row">
    <div class="col-md-8">
        <h1 class="mb-4">
            <i class="bi bi-file-earmark-text"></i> <?php echo $title; ?>
            <?php if (!empty($searchTerm)): ?>
                <small class="text-muted">"<?php echo htmlspecialchars($searchTerm); ?>" için arama sonuçları</small>
            <?php endif; ?>
        </h1>
    </div>
    <?php if (Session::hasRole(['super_admin', 'admin'])): ?>
    <div class="col-md-4 text-end">
        <a href="upload_document.php<?php echo $categoryId ? '?category=' . $categoryId . ($subcategoryId ? '&subcategory=' . $subcategoryId : '') : ''; ?>" class="btn btn-primary">
            <i class="bi bi-upload"></i> Belge Yükle
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Filtreler -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Filtreler</h5>
            </div>
            <div class="card-body">
                <form method="get" action="documents.php" class="row g-3">
                    <div class="col-md-4">
                        <label for="category" class="form-label">Kategori</label>
                        <select class="form-select" id="category" name="category" onchange="loadSubcategories(this.value)">
                            <option value="">Tüm Kategoriler</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="subcategory" class="form-label">Alt Kategori</label>
                        <select class="form-select" id="subcategory" name="subcategory" <?php echo empty($subcategories) ? 'disabled' : ''; ?>>
                            <option value="">Tüm Alt Kategoriler</option>
                            <?php foreach ($subcategories as $subcat): ?>
                                <option value="<?php echo $subcat['id']; ?>" <?php echo $subcategoryId == $subcat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subcat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="search" class="form-label">Arama</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="search" name="search" placeholder="Belge adı veya açıklaması..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                            <button class="btn btn-outline-primary" type="submit">Ara</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Belge Listesi -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-list"></i> Belge Listesi
                    <span class="badge bg-light text-dark float-end"><?php echo $totalDocuments; ?> belge</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($documents)): ?>
                    <p class="text-muted">Belge bulunamadı.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Belge Adı</th>
                                    <th>Kategori</th>
                                    <?php if (empty($subcategoryId)): ?>
                                        <th>Alt Kategori</th>
                                    <?php endif; ?>
                                    <th>Yükleyen</th>
                                    <th>Tarih</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($doc['title']); ?>
                                            <?php if (!empty($doc['description'])): ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars(substr($doc['description'], 0, 50)) . (strlen($doc['description']) > 50 ? '...' : ''); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['category_name']); ?></td>
                                        <?php if (empty($subcategoryId)): ?>
                                            <td>
                                                <?php if (!empty($doc['subcategory_name'])): ?>
                                                    <?php echo htmlspecialchars($doc['subcategory_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($doc['uploaded_by_name']); ?></td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($doc['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="view_document.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-primary" title="Görüntüle">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="download_document.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-success" title="İndir">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <?php if (Session::hasRole(['super_admin', 'admin'])): ?>
                                                    <a href="edit_document.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-info" title="Düzenle">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="delete_document.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-danger" title="Sil" onclick="return confirm('Bu belgeyi silmek istediğinizden emin misiniz?');">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Sayfalama -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Sayfalama">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $categoryId ? '&category=' . $categoryId : ''; ?><?php echo $subcategoryId ? '&subcategory=' . $subcategoryId : ''; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                                            <i class="bi bi-chevron-left"></i> Önceki
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="bi bi-chevron-left"></i> Önceki</span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                if ($startPage > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1' . 
                                         ($categoryId ? '&category=' . $categoryId : '') . 
                                         ($subcategoryId ? '&subcategory=' . $subcategoryId : '') . 
                                         (!empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '') . 
                                         '">1</a></li>';
                                    
                                    if ($startPage > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    if ($i == $page) {
                                        echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                    } else {
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $i . 
                                             ($categoryId ? '&category=' . $categoryId : '') . 
                                             ($subcategoryId ? '&subcategory=' . $subcategoryId : '') . 
                                             (!empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '') . 
                                             '">' . $i . '</a></li>';
                                    }
                                }
                                
                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . 
                                         ($categoryId ? '&category=' . $categoryId : '') . 
                                         ($subcategoryId ? '&subcategory=' . $subcategoryId : '') . 
                                         (!empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '') . 
                                         '">' . $totalPages . '</a></li>';
                                }
                                ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $categoryId ? '&category=' . $categoryId : ''; ?><?php echo $subcategoryId ? '&subcategory=' . $subcategoryId : ''; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                                            Sonraki <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Sonraki <i class="bi bi-chevron-right"></i></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Kategori değiştiğinde alt kategorileri yükle
function loadSubcategories(categoryId) {
    var subcategorySelect = document.getElementById('subcategory');
    subcategorySelect.innerHTML = '<option value="">Tüm Alt Kategoriler</option>';
    
    if (!categoryId) {
        subcategorySelect.disabled = true;
        return;
    }
    
    // Form submit et
    document.querySelector('form').submit();
}

// Sayfa yüklendiğinde alt kategorileri kontrol et
document.addEventListener('DOMContentLoaded', function() {
    var categorySelect = document.getElementById('category');
    var subcategorySelect = document.getElementById('subcategory');
    
    if (categorySelect.value) {
        subcategorySelect.disabled = false;
    } else {
        subcategorySelect.disabled = true;
    }
});
</script>

<?php
// Footer'ı dahil et
require_once 'includes/footer.php';
?>