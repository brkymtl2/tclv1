<?php
// Sayfa başlığı
$pageTitle = 'Kontrol Paneli';

// Header'ı dahil et
require_once 'includes/header.php';

// Document sınıfını dahil et (eğer henüz oluşturulmadıysa, sonradan oluşturulacak)
// require_once 'includes/Document.php';

// İstatistikleri al
$stats = [
    'total_documents' => 0,
    'total_categories' => 0,
    'total_users' => 0
];

try {
    $pdo = getPDO();
    
    // Belge sayısı
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM documents");
    $stats['total_documents'] = $stmt->fetch()['count'];
    
    // Kategori sayısı
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories");
    $stats['total_categories'] = $stmt->fetch()['count'];
    
    // Kullanıcı sayısı (sadece süper admin görebilir)
    if (Session::hasRole('super_admin')) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $stats['total_users'] = $stmt->fetch()['count'];
    }
    
    // Son yüklenen belgeler
    $stmt = $pdo->query("
        SELECT d.id, d.title, d.created_at, c.name as category, u.username as uploaded_by
        FROM documents d
        JOIN categories c ON d.category_id = c.id
        JOIN users u ON d.uploaded_by = u.id
        ORDER BY d.created_at DESC
        LIMIT 5
    ");
    $recentDocuments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard hata: " . $e->getMessage());
}
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="bi bi-speedometer2"></i> Kontrol Paneli</h1>
    </div>
</div>

<!-- İstatistik kartları -->
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card border-primary">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <i class="bi bi-file-earmark-text text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <div>
                        <h5 class="card-title">Toplam Belge</h5>
                        <h2 class="card-text"><?php echo $stats['total_documents']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="documents.php" class="btn btn-sm btn-primary">Belgeleri Görüntüle</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card border-success">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <i class="bi bi-folder text-success" style="font-size: 3rem;"></i>
                    </div>
                    <div>
                        <h5 class="card-title">Toplam Kategori</h5>
                        <h2 class="card-text"><?php echo $stats['total_categories']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="categories.php" class="btn btn-sm btn-success">Kategorileri Yönet</a>
            </div>
        </div>
    </div>
    
    <?php if (Session::hasRole('super_admin')): ?>
    <div class="col-md-4 mb-4">
        <div class="card border-info">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <i class="bi bi-people text-info" style="font-size: 3rem;"></i>
                    </div>
                    <div>
                        <h5 class="card-title">Toplam Kullanıcı</h5>
                        <h2 class="card-text"><?php echo $stats['total_users']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="users.php" class="btn btn-sm btn-info">Kullanıcıları Yönet</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Son yüklenen belgeler -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Son Yüklenen Belgeler</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentDocuments)): ?>
                    <p class="text-muted">Henüz belge yüklenmemiş.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Belge Adı</th>
                                    <th>Kategori</th>
                                    <th>Yükleyen</th>
                                    <th>Tarih</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentDocuments as $doc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                        <td><?php echo htmlspecialchars($doc['category']); ?></td>
                                        <td><?php echo htmlspecialchars($doc['uploaded_by']); ?></td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($doc['created_at'])); ?></td>
                                        <td>
                                            <a href="view_document.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> Görüntüle
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-end">
                <a href="documents.php" class="btn btn-primary">Tüm Belgeleri Görüntüle</a>
            </div>
        </div>
    </div>
</div>

<!-- Hızlı Erişim Linkleri -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-lightning-charge"></i> Hızlı Erişim</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="upload_document.php" class="btn btn-outline-primary w-100 p-3">
                            <i class="bi bi-upload" style="font-size: 1.5rem;"></i><br>
                            Belge Yükle
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="documents.php?category=1" class="btn btn-outline-success w-100 p-3">
                            <i class="bi bi-clipboard-check" style="font-size: 1.5rem;"></i><br>
                            Kalite Belgeleri
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="documents.php?category=2" class="btn btn-outline-info w-100 p-3">
                            <i class="bi bi-file-earmark-text" style="font-size: 1.5rem;"></i><br>
                            Teklif Dosyaları
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="search.php" class="btn btn-outline-dark w-100 p-3">
                            <i class="bi bi-search" style="font-size: 1.5rem;"></i><br>
                            Belge Ara
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
require_once 'includes/footer.php';
?>