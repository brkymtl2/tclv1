<?php
// Sayfa başlığı
$pageTitle = 'Sistem Logları';

// Header'ı dahil et
require_once 'includes/header.php';

// Sadece süper admin ve admin erişebilir
if (!Session::hasRole(['super_admin', 'admin'])) {
    Session::setFlashMessage('Bu sayfaya erişim yetkiniz bulunmamaktadır.', 'danger');
    header('Location: dashboard.php');
    exit;
}

// Log sınıfını dahil et
require_once 'includes/Log.php';
$log = new Log();

// User sınıfını dahil et (kullanıcı listesi için)
require_once 'includes/User.php';
$user = new User();

// İşlem mesajları
$message = '';
$error = '';

// Log temizleme işlemi
if (isset($_GET['action']) && $_GET['action'] === 'clear_logs' && Session::hasRole('super_admin')) {
    // CSRF token kontrolü
    if (!isset($_GET['token']) || !Security::validateCSRFToken($_GET['token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        if (isset($_GET['days']) && is_numeric($_GET['days'])) {
            // Belirli gün sayısı öncesine ait logları temizle
            $date = date('Y-m-d', strtotime('-' . (int)$_GET['days'] . ' days'));
            $result = $log->clearLogsBefore($date);
            
            if ($result) {
                Session::setFlashMessage($date . ' tarihinden önceki loglar başarıyla temizlendi.', 'success');
            } else {
                Session::setFlashMessage('Loglar temizlenirken bir hata oluştu.', 'danger');
            }
        } else if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
            // Belirli bir kullanıcıya ait logları temizle
            $userId = (int)$_GET['user_id'];
            $result = $log->clearUserLogs($userId);
            
            if ($result) {
                Session::setFlashMessage('Seçilen kullanıcıya ait loglar başarıyla temizlendi.', 'success');
            } else {
                Session::setFlashMessage('Loglar temizlenirken bir hata oluştu.', 'danger');
            }
        } else if (isset($_GET['all']) && $_GET['all'] === '1') {
            // Tüm logları temizle
            $result = $log->clearAllLogs();
            
            if ($result) {
                Session::setFlashMessage('Tüm loglar başarıyla temizlendi.', 'success');
            } else {
                Session::setFlashMessage('Loglar temizlenirken bir hata oluştu.', 'danger');
            }
        }
        
        // Sayfayı yeniden yükle
        header('Location: logs.php');
        exit;
    }
}

// Filtreler
$filters = [];

// Kullanıcı filtresi
if (isset($_GET['user_id']) && is_numeric($_GET['user_id']) && $_GET['user_id'] > 0) {
    $filters['user_id'] = (int)$_GET['user_id'];
}

// İşlem filtresi
if (isset($_GET['action_filter']) && !empty($_GET['action_filter'])) {
    $filters['action'] = Security::sanitizeInput($_GET['action_filter']);
}

// Nesne türü filtresi
if (isset($_GET['entity_type']) && !empty($_GET['entity_type'])) {
    $filters['entity_type'] = Security::sanitizeInput($_GET['entity_type']);
}

// IP adresi filtresi
if (isset($_GET['ip_address']) && !empty($_GET['ip_address'])) {
    $filters['ip_address'] = Security::sanitizeInput($_GET['ip_address']);
}

// Tarih aralığı filtresi
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $filters['date_from'] = Security::sanitizeInput($_GET['date_from']);
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $filters['date_to'] = Security::sanitizeInput($_GET['date_to']);
}

// Sayfalama
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50; // Sayfa başına log sayısı
$offset = ($page - 1) * $perPage;

// Toplam log sayısı
$totalLogs = $log->getLogCount($filters);
$totalPages = ceil($totalLogs / $perPage);

// Logları al
$logs = $log->getLogs($filters, $perPage, $offset);

// Kullanıcı listesini al (filtre için)
$users = $user->getAllUsers();

// Benzersiz işlem türlerini ve nesne türlerini al (filtre için)
$actionTypes = [];
$entityTypes = [];
$ipAddresses = [];

foreach ($logs as $logItem) {
    if (!empty($logItem['action']) && !in_array($logItem['action'], $actionTypes)) {
        $actionTypes[] = $logItem['action'];
    }
    
    if (!empty($logItem['entity_type']) && !in_array($logItem['entity_type'], $entityTypes)) {
        $entityTypes[] = $logItem['entity_type'];
    }
    
    if (!empty($logItem['ip_address']) && !in_array($logItem['ip_address'], $ipAddresses)) {
        $ipAddresses[] = $logItem['ip_address'];
    }
}

// İşlem türleri için genel bir liste
if (empty($actionTypes)) {
    $actionTypes = ['login', 'logout', 'create_user', 'update_user', 'delete_user', 
                   'create_document', 'update_document', 'delete_document', 
                   'create_category', 'update_category', 'delete_category',
                   'download_document', 'view_document'];
}

// Nesne türleri için genel bir liste
if (empty($entityTypes)) {
    $entityTypes = ['user', 'document', 'category', 'subcategory', 'permission'];
}

// İşlem renklerini belirle (Bootstrap renkleri)
$actionColors = [
    'login' => 'success',
    'logout' => 'secondary',
    'create_user' => 'primary',
    'update_user' => 'info',
    'delete_user' => 'danger',
    'create_document' => 'primary',
    'update_document' => 'info',
    'delete_document' => 'danger',
    'create_category' => 'primary',
    'update_category' => 'info',
    'delete_category' => 'danger',
    'download_document' => 'warning',
    'view_document' => 'light'
];

// Varsayılan renk
$defaultColor = 'secondary';
?>

<div class="row">
    <div class="col-md-8">
        <h1 class="mb-4"><i class="bi bi-journal-text"></i> Sistem Logları</h1>
    </div>
    <?php if (Session::hasRole('super_admin')): ?>
    <div class="col-md-4 text-end">
        <div class="dropdown">
            <button class="btn btn-danger dropdown-toggle" type="button" id="clearLogsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-trash"></i> Logları Temizle
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="clearLogsDropdown">
                <li><a class="dropdown-item" href="logs.php?action=clear_logs&days=30&token=<?php echo $csrf_token; ?>" onclick="return confirm('30 günden eski logları silmek istediğinizden emin misiniz?');">30 Günden Eski Loglar</a></li>
                <li><a class="dropdown-item" href="logs.php?action=clear_logs&days=90&token=<?php echo $csrf_token; ?>" onclick="return confirm('90 günden eski logları silmek istediğinizden emin misiniz?');">90 Günden Eski Loglar</a></li>
                <li><a class="dropdown-item" href="logs.php?action=clear_logs&days=180&token=<?php echo $csrf_token; ?>" onclick="return confirm('180 günden eski logları silmek istediğinizden emin misiniz?');">180 Günden Eski Loglar</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="logs.php?action=clear_logs&all=1&token=<?php echo $csrf_token; ?>" onclick="return confirm('TÜM LOGLARI silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!');">Tüm Logları Temizle</a></li>
            </ul>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Filtreler -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Log Filtreleme</h5>
            </div>
            <div class="card-body">
                <form method="get" action="logs.php" class="row g-3">
                    <div class="col-md-4">
                        <label for="user_id" class="form-label">Kullanıcı</label>
                        <select class="form-select" id="user_id" name="user_id">
                            <option value="">Tüm Kullanıcılar</option>
                            <?php foreach ($users as $userItem): ?>
                                <?php if ($userItem['id'] != Session::getUserId() || Session::hasRole('super_admin')): ?>
                                    <option value="<?php echo $userItem['id']; ?>" <?php echo isset($filters['user_id']) && $filters['user_id'] == $userItem['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($userItem['username']); ?> 
                                        (<?php echo ucfirst($userItem['role']); ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="action_filter" class="form-label">İşlem Türü</label>
                        <select class="form-select" id="action_filter" name="action_filter">
                            <option value="">Tüm İşlemler</option>
                            <?php foreach ($actionTypes as $actionType): ?>
                                <option value="<?php echo $actionType; ?>" <?php echo isset($filters['action']) && $filters['action'] == $actionType ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $actionType)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="entity_type" class="form-label">Nesne Türü</label>
                        <select class="form-select" id="entity_type" name="entity_type">
                            <option value="">Tüm Nesneler</option>
                            <?php foreach ($entityTypes as $entityType): ?>
                                <option value="<?php echo $entityType; ?>" <?php echo isset($filters['entity_type']) && $filters['entity_type'] == $entityType ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($entityType); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="ip_address" class="form-label">IP Adresi</label>
                        <select class="form-select" id="ip_address" name="ip_address">
                            <option value="">Tüm IP Adresleri</option>
                            <?php foreach ($ipAddresses as $ipAddress): ?>
                                <option value="<?php echo $ipAddress; ?>" <?php echo isset($filters['ip_address']) && $filters['ip_address'] == $ipAddress ? 'selected' : ''; ?>>
                                    <?php echo $ipAddress; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="date_from" class="form-label">Başlangıç Tarihi</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo isset($filters['date_from']) ? $filters['date_from'] : ''; ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label for="date_to" class="form-label">Bitiş Tarihi</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo isset($filters['date_to']) ? $filters['date_to'] : ''; ?>">
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Filtrele</button>
                        <a href="logs.php" class="btn btn-secondary">Filtreleri Temizle</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Log Listesi -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-list"></i> Log Kayıtları
                    <span class="badge bg-light text-dark float-end"><?php echo $totalLogs; ?> kayıt</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($logs)): ?>
                    <div class="p-4 text-center">
                        <p class="text-muted mb-0">Hiç log kaydı bulunamadı.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Tarih/Saat</th>
                                    <th>Kullanıcı</th>
                                    <th>İşlem</th>
                                    <th>Açıklama</th>
                                    <th>Nesne Türü</th>
                                    <th>Nesne ID</th>
                                    <th>IP Adresi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $logItem): ?>
                                    <tr>
                                        <td><?php echo date('d.m.Y H:i:s', strtotime($logItem['created_at'])); ?></td>
                                        <td>
                                            <?php if (!empty($logItem['username'])): ?>
                                                <?php echo htmlspecialchars($logItem['username']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Bilinmeyen</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $actionColor = isset($actionColors[$logItem['action']]) ? $actionColors[$logItem['action']] : $defaultColor;
                                                echo '<span class="badge bg-' . $actionColor . '">' . ucfirst(str_replace('_', ' ', $logItem['action'])) . '</span>';
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($logItem['description']); ?></td>
                                        <td>
                                            <?php if (!empty($logItem['entity_type'])): ?>
                                                <?php echo ucfirst($logItem['entity_type']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($logItem['entity_id'])): ?>
                                                <?php echo $logItem['entity_id']; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo $logItem['ip_address']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Sayfalama -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Sayfalama" class="p-3">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php
                                            foreach ($filters as $key => $value) {
                                                echo '&' . $key . '=' . urlencode($value);
                                            }
                                        ?>">
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
                                    echo '<li class="page-item"><a class="page-link" href="?page=1';
                                    foreach ($filters as $key => $value) {
                                        echo '&' . $key . '=' . urlencode($value);
                                    }
                                    echo '">1</a></li>';
                                    
                                    if ($startPage > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    if ($i == $page) {
                                        echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                    } else {
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $i;
                                        foreach ($filters as $key => $value) {
                                            echo '&' . $key . '=' . urlencode($value);
                                        }
                                        echo '">' . $i . '</a></li>';
                                    }
                                }
                                
                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages;
                                    foreach ($filters as $key => $value) {
                                        echo '&' . $key . '=' . urlencode($value);
                                    }
                                    echo '">' . $totalPages . '</a></li>';
                                }
                                ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php
                                            foreach ($filters as $key => $value) {
                                                echo '&' . $key . '=' . urlencode($value);
                                            }
                                        ?>">
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

<?php
// Footer'ı dahil et
require_once 'includes/footer.php';
?>