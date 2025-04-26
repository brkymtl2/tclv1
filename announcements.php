<?php
// Sayfa başlığı
$pageTitle = 'Duyurular Yönetimi';

// Header'ı dahil et
require_once 'includes/header.php';

// Sadece süper admin ve admin erişebilir
if (!Session::hasRole(['super_admin', 'admin'])) {
    Session::setFlashMessage('Bu sayfaya erişim yetkiniz bulunmamaktadır.', 'danger');
    header('Location: dashboard.php');
    exit;
}

// Duyurular sınıfını dahil et
require_once 'includes/Announcement.php';
$announcement = new Announcement();

// İşlem mesajları
$message = '';
$error = '';

// Yeni duyuru ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_announcement') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $title = isset($_POST['title']) ? Security::sanitizeInput($_POST['title']) : '';
        $content = isset($_POST['content']) ? Security::sanitizeInput($_POST['content']) : '';
        $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : '';
        $endDate = isset($_POST['end_date']) && !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $isActive = isset($_POST['is_active']) && $_POST['is_active'] == '1';
        
        if (empty($title) || empty($content) || empty($startDate)) {
            $error = 'Başlık, içerik ve başlangıç tarihi gereklidir.';
        } else {
            // Başlangıç ve bitiş tarihlerini formatlama
            $startTimestamp = strtotime($startDate);
            $startDate = date('Y-m-d H:i:s', $startTimestamp);
            
            if ($endDate) {
                $endTimestamp = strtotime($endDate);
                $endDate = date('Y-m-d H:i:s', $endTimestamp);
                
                // Bitiş tarihi başlangıç tarihinden önce olamaz
                if ($endTimestamp < $startTimestamp) {
                    $error = 'Bitiş tarihi başlangıç tarihinden önce olamaz.';
                }
            }
            
            if (empty($error)) {
                $result = $announcement->addAnnouncement(
                    $title,
                    $content,
                    Session::getUserId(),
                    $startDate,
                    $endDate,
                    $isActive
                );
                
                if ($result) {
                    // Log kaydı ekle
                    require_once 'includes/Log.php';
                    $log = new Log();
                    $log->add('create_announcement', 'Yeni duyuru oluşturuldu: ' . $title, 'announcement', $result);
                    
                    $message = 'Duyuru başarıyla oluşturuldu.';
                    
                    // Bildirim göster
                    echo '<script>
                        document.addEventListener("DOMContentLoaded", function() {
                            showSuccess("Duyuru başarıyla oluşturuldu.");
                        });
                    </script>';
                } else {
                    $error = 'Duyuru oluşturulurken bir hata oluştu.';
                    
                    // Hata bildirimi göster
                    echo '<script>
                        document.addEventListener("DOMContentLoaded", function() {
                            showError("Duyuru oluşturulurken bir hata oluştu.");
                        });
                    </script>';
                }
            }
        }
    }
}

// Duyuru düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_announcement') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $announcementId = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;
        $title = isset($_POST['title']) ? Security::sanitizeInput($_POST['title']) : '';
        $content = isset($_POST['content']) ? Security::sanitizeInput($_POST['content']) : '';
        $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : '';
        $endDate = isset($_POST['end_date']) && !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $isActive = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;
        
        if (empty($title) || empty($content) || empty($startDate) || $announcementId <= 0) {
            $error = 'Başlık, içerik, başlangıç tarihi ve duyuru ID\'si gereklidir.';
        } else {
            // Başlangıç ve bitiş tarihlerini formatlama
            $startTimestamp = strtotime($startDate);
            $startDate = date('Y-m-d H:i:s', $startTimestamp);
            
            if ($endDate) {
                $endTimestamp = strtotime($endDate);
                $endDate = date('Y-m-d H:i:s', $endTimestamp);
                
                // Bitiş tarihi başlangıç tarihinden önce olamaz
                if ($endTimestamp < $startTimestamp) {
                    $error = 'Bitiş tarihi başlangıç tarihinden önce olamaz.';
                }
            }
            
            if (empty($error)) {
                $updateData = [
                    'title' => $title,
                    'content' => $content,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_active' => $isActive
                ];
                
                $result = $announcement->updateAnnouncement($announcementId, $updateData);
                
                if ($result) {
                    // Log kaydı ekle
                    require_once 'includes/Log.php';
                    $log = new Log();
                    $log->add('update_announcement', 'Duyuru güncellendi: ' . $title, 'announcement', $announcementId);
                    
                    $message = 'Duyuru başarıyla güncellendi.';
                    
                    // Bildirim göster
                    echo '<script>
                        document.addEventListener("DOMContentLoaded", function() {
                            showSuccess("Duyuru başarıyla güncellendi.");
                        });
                    </script>';
                } else {
                    $error = 'Duyuru güncellenirken bir hata oluştu.';
                    
                    // Hata bildirimi göster
                    echo '<script>
                        document.addEventListener("DOMContentLoaded", function() {
                            showError("Duyuru güncellenirken bir hata oluştu.");
                        });
                    </script>';
                }
            }
        }
    }
}

// Duyuru silme işlemi
if (isset($_GET['action']) && $_GET['action'] === 'delete_announcement' && isset($_GET['id'])) {
    $announcementId = (int)$_GET['id'];
    
    // CSRF token kontrolü
    if (!isset($_GET['token']) || !Security::validateCSRFToken($_GET['token'])) {
        Session::setFlashMessage('Güvenlik doğrulaması başarısız oldu.', 'danger');
    } else {
        // Önce duyuru bilgilerini al
        $announcementData = $announcement->getAnnouncement($announcementId);
        
        if ($announcementData) {
            $result = $announcement->deleteAnnouncement($announcementId);
            
            if ($result) {
                // Log kaydı ekle
                require_once 'includes/Log.php';
                $log = new Log();
                $log->add('delete_announcement', 'Duyuru silindi: ' . $announcementData['title'], 'announcement', $announcementId);
                
                Session::setFlashMessage('Duyuru başarıyla silindi.', 'success');
                
                // JavaScript ile bildirim göster
                echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        showSuccess("Duyuru başarıyla silindi.");
                    });
                </script>';
            } else {
                Session::setFlashMessage('Duyuru silinirken bir hata oluştu.', 'danger');
                
                // JavaScript ile bildirim göster
                echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        showError("Duyuru silinirken bir hata oluştu.");
                    });
                </script>';
            }
        } else {
            Session::setFlashMessage('Duyuru bulunamadı.', 'danger');
        }
    }
    
    // Sayfayı yeniden yükle
    header('Location: announcements.php');
    exit;
}

// Sayfalama
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Toplam duyuru sayısı ve duyurular
$totalAnnouncements = $announcement->getAnnouncementCount(false);
$announcements = $announcement->getAllAnnouncements(false, $perPage, $offset);

// Toplam sayfa sayısı
$totalPages = ceil($totalAnnouncements / $perPage);
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="bi bi-megaphone"></i> Duyurular Yönetimi</h1>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Yeni Duyuru Ekle</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add_announcement">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Başlık <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Duyuru İçeriği <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="start_date" class="form-label">Başlangıç Tarihi <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="end_date" class="form-label">Bitiş Tarihi</label>
                        <input type="datetime-local" class="form-control" id="end_date" name="end_date">
                        <div class="form-text">Belirtilmezse, duyuru sürekli aktif kalır.</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="is_active">Aktif</label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Duyuru Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> Duyuru Listesi</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($announcements)): ?>
                    <div class="p-4 text-center">
                        <p class="text-muted mb-0">Henüz duyuru bulunmamaktadır.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Başlık</th>
                                    <th>Oluşturan</th>
                                    <th>Tarih Aralığı</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($announcements as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['title']); ?></td>
                                        <td><?php echo htmlspecialchars($item['created_by_name']); ?></td>
                                        <td>
                                            <?php 
                                                echo date('d.m.Y H:i', strtotime($item['start_date']));
                                                if (!empty($item['end_date'])) {
                                                    echo '<br><span class="text-muted">→ ' . date('d.m.Y H:i', strtotime($item['end_date'])) . '</span>';
                                                } else {
                                                    echo '<br><span class="text-muted">→ Süresiz</span>';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($item['is_active']): ?>
                                                <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pasif</span>
                                            <?php endif; ?>
                                            
                                            <?php
                                                $currentDate = date('Y-m-d H:i:s');
                                                if ($item['is_active'] && strtotime($item['start_date']) > strtotime($currentDate)) {
                                                    echo '<span class="badge bg-warning ms-1">Beklemede</span>';
                                                } elseif ($item['is_active'] && !empty($item['end_date']) && strtotime($item['end_date']) < strtotime($currentDate)) {
                                                    echo '<span class="badge bg-danger ms-1">Süresi Dolmuş</span>';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info edit-announcement-btn" 
                                                   data-bs-toggle="modal" 
                                                   data-bs-target="#editAnnouncementModal" 
                                                   data-id="<?php echo $item['id']; ?>"
                                                   data-title="<?php echo htmlspecialchars($item['title']); ?>"
                                                   data-content="<?php echo htmlspecialchars($item['content']); ?>"
                                                   data-start-date="<?php echo date('Y-m-d\TH:i', strtotime($item['start_date'])); ?>"
                                                   data-end-date="<?php echo !empty($item['end_date']) ? date('Y-m-d\TH:i', strtotime($item['end_date'])) : ''; ?>"
                                                   data-is-active="<?php echo $item['is_active']; ?>">
                                                <i class="bi bi-pencil"></i> Düzenle
                                            </button>
                                            <a href="announcements.php?action=delete_announcement&id=<?php echo $item['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Bu duyuruyu silmek istediğinizden emin misiniz?');">
                                                <i class="bi bi-trash"></i> Sil
                                            </a>
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
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">
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
                                        echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                        
                                        if ($startPage > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        if ($i == $page) {
                                            echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                        } else {
                                            echo '<li class="page-item"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
                                        }
                                    }
                                    
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">
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
    </div>
</div>

<!-- Duyuru Düzenleme Modal -->
<div class="modal fade" id="editAnnouncementModal" tabindex="-1" aria-labelledby="editAnnouncementModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAnnouncementModalLabel">Duyuru Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="edit_announcement">
                    <input type="hidden" name="announcement_id" id="edit_announcement_id">
                    
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Başlık <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_content" class="form-label">Duyuru İçeriği <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="edit_content" name="content" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_start_date" class="form-label">Başlangıç Tarihi <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="edit_start_date" name="start_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_end_date" class="form-label">Bitiş Tarihi</label>
                        <input type="datetime-local" class="form-control" id="edit_end_date" name="end_date">
                        <div class="form-text">Belirtilmezse, duyuru sürekli aktif kalır.</div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" value="1">
                        <label class="form-check-label" for="edit_is_active">Aktif</label>
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
document.addEventListener('DOMContentLoaded', function() {
    // Duyuru düzenleme modalını hazırla
    var editButtons = document.querySelectorAll('.edit-announcement-btn');
    
    editButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var title = this.getAttribute('data-title');
            var content = this.getAttribute('data-content');
            var startDate = this.getAttribute('data-start-date');
            var endDate = this.getAttribute('data-end-date');
            var isActive = this.getAttribute('data-is-active') === '1';
            
            document.getElementById('edit_announcement_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_content').value = content;
            document.getElementById('edit_start_date').value = startDate;
            document.getElementById('edit_end_date').value = endDate;
            document.getElementById('edit_is_active').checked = isActive;
        });
    });
});
</script>

<?php
// Footer'ı dahil et
require_once 'includes/footer.php';
?>