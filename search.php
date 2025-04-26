<?php
// Sayfa başlığı
$pageTitle = 'Gelişmiş Arama';

// Header'ı dahil et
require_once 'includes/header.php';

// Sınıfları dahil et
require_once 'includes/Document.php';
require_once 'includes/Category.php';
require_once 'includes/User.php';

// Nesneleri başlat
$document = new Document();
$category = new Category();
$user = new User();

// Arama parametrelerini al
$searchTerm = isset($_GET['search']) ? Security::sanitizeInput($_GET['search']) : '';
$categoryId = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$subcategoryId = isset($_GET['subcategory_id']) && is_numeric($_GET['subcategory_id']) ? (int)$_GET['subcategory_id'] : null;
$uploadedBy = isset($_GET['uploaded_by']) && is_numeric($_GET['uploaded_by']) ? (int)$_GET['uploaded_by'] : null;
$dateFrom = isset($_GET['date_from']) && !empty($_GET['date_from']) ? $_GET['date_from'] : null;
$dateTo = isset($_GET['date_to']) && !empty($_GET['date_to']) ? $_GET['date_to'] : null;
$fileType = isset($_GET['file_type']) && !empty($_GET['file_type']) ? Security::sanitizeInput($_GET['file_type']) : null;

// Sayfalama
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20; // Sayfa başına sonuç sayısı
$offset = ($page - 1) * $perPage;

// Arama yapıldı mı?
$isSearched = !empty($searchTerm) || $categoryId || $subcategoryId || $uploadedBy || $dateFrom || $dateTo || $fileType;

// Arama sonuçları
$searchResults = [];
$totalResults = 0;

if ($isSearched) {
    // Arama sorgusunu oluştur
    try {
        $query = "
            SELECT d.*, c.name as category_name, u.username as uploaded_by_name, sc.name as subcategory_name
            FROM documents d
            JOIN categories c ON d.category_id = c.id
            JOIN users u ON d.uploaded_by = u.id
            LEFT JOIN subcategories sc ON d.subcategory_id = sc.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Arama terimi varsa
        if (!empty($searchTerm)) {
            $query .= " AND (d.title LIKE ? OR d.description LIKE ?)";
            $params[] = "%$searchTerm%";
            $params[] = "%$searchTerm%";
        }
        
        // Kategori filtresi
        if ($categoryId) {
            $query .= " AND d.category_id = ?";
            $params[] = $categoryId;
        }
        
        // Alt kategori filtresi
        if ($subcategoryId) {
            $query .= " AND d.subcategory_id = ?";
            $params[] = $subcategoryId;
        }
        
        // Yükleyen kullanıcı filtresi
        if ($uploadedBy) {
            $query .= " AND d.uploaded_by = ?";
            $params[] = $uploadedBy;
        }
        
        // Tarih aralığı filtresi
        if ($dateFrom) {
            $query .= " AND d.created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        
        if ($dateTo) {
            $query .= " AND d.created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }
        
        // Dosya türü filtresi
        if ($fileType) {
            $query .= " AND d.file_type = ?";
            $params[] = $fileType;
        }
        
        // Toplam sonuç sayısını al
        $countQuery = str_replace("d.*, c.name as category_name, u.username as uploaded_by_name, sc.name as subcategory_name", "COUNT(*) as count", $query);
        $pdo = getPDO();
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($params);
        $totalResults = $stmt->fetch()['count'];
        
        // Sayfalama için limit ve offset ekle
        $query .= " ORDER BY d.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        // Arama sonuçlarını getir
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $searchResults = $stmt->fetchAll();
        
        // Log kaydı ekle
        require_once 'includes/Log.php';
        $log = new Log();
        $log->add('search', 'Gelişmiş arama yapıldı: ' . $searchTerm, 'search');
        
    } catch (PDOException $e) {
        error_log("Arama hatası: " . $e->getMessage());
        $error = "Arama sırasında bir hata oluştu.";
    }
}

// Toplam sayfa sayısını hesapla
$totalPages = $totalResults > 0 ? ceil($totalResults / $perPage) : 0;

// Kategorileri getir
$categories = $category->getAllCategories();

// Alt kategorileri getir (eğer kategori seçilmişse)
$subcategories = $categoryId ? $category->getSubCategories($categoryId) : [];

// Kullanıcıları getir
$users = $user->getAllUsers();

// Dosya türlerini getir (benzersiz listesi)
$fileTypes = [];
try {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT DISTINCT file_type FROM documents ORDER BY file_type");
    while ($row = $stmt->fetch()) {
        $fileTypes[] = $row['file_type'];
    }
} catch (PDOException $e) {
    error_log("Dosya türleri getirilemedi: " . $e->getMessage());
}

// Dosya türlerini daha kullanıcı dostu hale getir
$fileTypeNames = [
    'application/pdf' => 'PDF',
    'application/msword' => 'Word (DOC)',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word (DOCX)',
    'application/vnd.ms-excel' => 'Excel (XLS)',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel (XLSX)',
    'application/vnd.ms-powerpoint' => 'PowerPoint (PPT)',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PowerPoint (PPTX)',
    'image/jpeg' => 'Resim (JPEG)',
    'image/png' => 'Resim (PNG)',
    'text/plain' => 'Metin Dosyası (TXT)'
];
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1><i class="bi bi-search"></i> Gelişmiş Arama</h1>
    </div>
</div>

<!-- Arama Formu -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-funnel"></i> Arama Filtreleri</h5>
    </div>
    <div class="card-body">
        <form method="get" id="searchForm">
            <div class="row g-3">
                <!-- Arama Terimi -->
                <div class="col-md-12">
                    <label for="search" class="form-label">Arama Terimi</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Belge adı veya açıklaması..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
                
                <!-- Kategori -->
                <div class="col-md-6">
                    <label for="category_id" class="form-label">Kategori</label>
                    <select class="form-select" id="category_id" name="category_id" onchange="loadSubcategories(this.value)">
                        <option value="">Tümü</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Alt Kategori -->
                <div class="col-md-6">
                    <label for="subcategory_id" class="form-label">Alt Kategori</label>
                    <select class="form-select" id="subcategory_id" name="subcategory_id" <?php echo empty($subcategories) ? 'disabled' : ''; ?>>
                        <option value="">Tümü</option>
                        <?php foreach ($subcategories as $subcat): ?>
                            <option value="<?php echo $subcat['id']; ?>" <?php echo $subcategoryId == $subcat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subcat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Yükleyen -->
                <div class="col-md-4">
                    <label for="uploaded_by" class="form-label">Yükleyen</label>
                    <select class="form-select" id="uploaded_by" name="uploaded_by">
                        <option value="">Tümü</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $uploadedBy == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Dosya Türü -->
                <div class="col-md-4">
                    <label for="file_type" class="form-label">Dosya Türü</label>
                    <select class="form-select" id="file_type" name="file_type">
                        <option value="">Tümü</option>
                        <?php foreach ($fileTypes as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $fileType == $type ? 'selected' : ''; ?>>
                                <?php echo isset($fileTypeNames[$type]) ? $fileTypeNames[$type] : $type; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Tarih Aralığı -->
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Başlangıç Tarihi</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Bitiş Tarihi</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                </div>
                
                <!-- Butonlar -->
                <div class="col-md-12 text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Ara
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearForm()">
                        <i class="bi bi-x-circle"></i> Temizle
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Arama Sonuçları -->
<?php if ($isSearched): ?>
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">
                <i class="bi bi-list-ul"></i> Arama Sonuçları
                <span class="badge bg-light text-dark float-end"><?php echo $totalResults; ?> sonuç</span>
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($searchResults)): ?>
                <div class="p-4 text-center">
                    <p class="text-muted mb-0">Aramanıza uygun sonuç bulunamadı.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Belge Adı</th>
                                <th>Kategori</th>
                                <th>Alt Kategori</th>
                                <th>Yükleyen</th>
                                <th>Tarih</th>
                                <th>Dosya Türü</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($searchResults as $result): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($result['title']); ?>
                                        <?php if (!empty($result['description'])): ?>
                                            <small class="d-block text-muted"><?php echo htmlspecialchars(substr($result['description'], 0, 50)) . (strlen($result['description']) > 50 ? '...' : ''); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($result['category_name']); ?></td>
                                    <td>
                                        <?php if (!empty($result['subcategory_name'])): ?>
                                            <?php echo htmlspecialchars($result['subcategory_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($result['uploaded_by_name']); ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($result['created_at'])); ?></td>
                                    <td>
                                        <?php 
                                            $type = $result['file_type'];
                                            echo isset($fileTypeNames[$type]) ? $fileTypeNames[$type] : $type;
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view_document.php?id=<?php echo $result['id']; ?>" class="btn btn-sm btn-primary" title="Görüntüle">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="download_document.php?id=<?php echo $result['id']; ?>" class="btn btn-sm btn-success" title="İndir">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <?php if (Session::hasRole(['super_admin', 'admin'])): ?>
                                                <a href="edit_document.php?id=<?php echo $result['id']; ?>" class="btn btn-sm btn-info" title="Düzenle">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="delete_document.php?id=<?php echo $result['id']; ?>" class="btn btn-sm btn-danger" title="Sil" onclick="return confirm('Bu belgeyi silmek istediğinizden emin misiniz?');">
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
                    <div class="p-3">
                        <nav aria-label="Sayfalama">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
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
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                                    
                                    if ($startPage > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    if ($i == $page) {
                                        echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                    } else {
                                        echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '">' . $i . '</a></li>';
                                    }
                                }
                                
                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) . '">' . $totalPages . '</a></li>';
                                }
                                ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
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
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
// Alt kategorileri yükle
function loadSubcategories(categoryId) {
    var subcategorySelect = document.getElementById('subcategory_id');
    subcategorySelect.innerHTML = '<option value="">Tümü</option>';
    
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

// Formu temizle
function clearForm() {
    document.getElementById('search').value = '';
    document.getElementById('category_id').value = '';
    document.getElementById('subcategory_id').value = '';
    document.getElementById('subcategory_id').disabled = true;
    document.getElementById('uploaded_by').value = '';
    document.getElementById('file_type').value = '';
    document.getElementById('date_from').value = '';
    document.getElementById('date_to').value = '';
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