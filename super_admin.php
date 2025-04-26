<?php
// Sayfa başlığı
$pageTitle = 'Süper Admin Paneli';

// Header'ı dahil et
require_once 'includes/header.php';

// Sadece süper admin erişebilir
if (!Session::hasRole('super_admin')) {
    Session::setFlashMessage('Bu sayfaya erişim yetkiniz bulunmamaktadır.', 'danger');
    header('Location: dashboard.php');
    exit;
}

// İzin ve kullanıcı sınıflarını dahil et
require_once 'includes/Permission.php';
require_once 'includes/User.php';

$permission = new Permission();
$user = new User();

// İşlem yapılacak mı?
$message = '';
$error = '';

// İzin atama işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_permission') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $role = isset($_POST['role']) ? Security::sanitizeInput($_POST['role']) : '';
        $permissionId = isset($_POST['permission_id']) ? (int)$_POST['permission_id'] : 0;
        
        if (empty($role) || $permissionId <= 0) {
            $error = 'Rol ve izin seçimi gereklidir.';
        } else {
            if ($permission->assignPermissionToRole($role, $permissionId)) {
                $message = 'İzin başarıyla atandı.';
            } else {
                $error = 'İzin atanırken bir hata oluştu.';
            }
        }
    }
}

// İzin kaldırma işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_permission') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $role = isset($_POST['role']) ? Security::sanitizeInput($_POST['role']) : '';
        $permissionId = isset($_POST['permission_id']) ? (int)$_POST['permission_id'] : 0;
        
        if (empty($role) || $permissionId <= 0) {
            $error = 'Rol ve izin seçimi gereklidir.';
        } else {
            if ($permission->removePermissionFromRole($role, $permissionId)) {
                $message = 'İzin başarıyla kaldırıldı.';
            } else {
                $error = 'İzin kaldırılırken bir hata oluştu.';
            }
        }
    }
}

// Yeni izin ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_permission') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $permissionName = isset($_POST['permission_name']) ? Security::sanitizeInput($_POST['permission_name']) : '';
        $permissionDescription = isset($_POST['permission_description']) ? Security::sanitizeInput($_POST['permission_description']) : '';
        
        if (empty($permissionName)) {
            $error = 'İzin adı gereklidir.';
        } else {
            if ($permission->createPermission($permissionName, $permissionDescription)) {
                $message = 'Yeni izin başarıyla oluşturuldu.';
            } else {
                $error = 'İzin oluşturulurken bir hata oluştu.';
            }
        }
    }
}

// Tüm izinleri al
$allPermissions = $permission->getAllPermissions();

// Rol bazlı izinleri al
$adminPermissions = $permission->getPermissionsByRole('admin');
$staffPermissions = $permission->getPermissionsByRole('staff');

// İzin ID'lerini dizi olarak al (kolay kontrol için)
$adminPermissionIds = array_column($adminPermissions, 'id');
$staffPermissionIds = array_column($staffPermissions, 'id');

// Tüm kullanıcıları al
$allUsers = $user->getAllUsers();
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="bi bi-shield-lock"></i> Süper Admin Paneli</h1>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- İzin Yönetimi -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-key"></i> İzin Yönetimi</h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="permissionTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions" type="button" role="tab" aria-controls="permissions" aria-selected="true">
                            İzinler
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="add-permission-tab" data-bs-toggle="tab" data-bs-target="#add-permission" type="button" role="tab" aria-controls="add-permission" aria-selected="false">
                            Yeni İzin Ekle
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content p-3" id="permissionTabsContent">
                    <!-- İzinler Tablosu -->
                    <div class="tab-pane fade show active" id="permissions" role="tabpanel" aria-labelledby="permissions-tab">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>İzin Adı</th>
                                        <th>Açıklama</th>
                                        <th>Admin</th>
                                        <th>Personel</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allPermissions as $perm): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($perm['name']); ?></td>
                                            <td><?php echo htmlspecialchars($perm['description']); ?></td>
                                            <td>
                                                <?php if (in_array($perm['id'], $adminPermissionIds)): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="action" value="remove_permission">
                                                        <input type="hidden" name="role" value="admin">
                                                        <input type="hidden" name="permission_id" value="<?php echo $perm['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="İzni kaldır">
                                                            <i class="bi bi-check-circle-fill"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="action" value="assign_permission">
                                                        <input type="hidden" name="role" value="admin">
                                                        <input type="hidden" name="permission_id" value="<?php echo $perm['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="İzin ver">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (in_array($perm['id'], $staffPermissionIds)): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="action" value="remove_permission">
                                                        <input type="hidden" name="role" value="staff">
                                                        <input type="hidden" name="permission_id" value="<?php echo $perm['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="İzni kaldır">
                                                            <i class="bi bi-check-circle-fill"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <input type="hidden" name="action" value="assign_permission">
                                                        <input type="hidden" name="role" value="staff">
                                                        <input type="hidden" name="permission_id" value="<?php echo $perm['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="İzin ver">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Yeni İzin Ekleme Formu -->
                    <div class="tab-pane fade" id="add-permission" role="tabpanel" aria-labelledby="add-permission-tab">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="add_permission">
                            
                            <div class="mb-3">
                                <label for="permission_name" class="form-label">İzin Adı</label>
                                <input type="text" class="form-control" id="permission_name" name="permission_name" required>
                                <div class="form-text">İzin adı benzersiz olmalıdır (örn. "view_reports")</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="permission_description" class="form-label">Açıklama</label>
                                <input type="text" class="form-control" id="permission_description" name="permission_description">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">İzin Ekle</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Kullanıcı Yönetimi -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-people"></i> Kullanıcı Yönetimi</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Kullanıcı Adı</th>
                                <th>E-posta</th>
                                <th>Rol</th>
                                <th>Oluşturulma Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $u): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $u['role'] === 'super_admin' ? 'danger' : ($u['role'] === 'admin' ? 'info' : 'secondary'); ?>">
                                            <?php echo ucfirst($u['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Düzenle
                                        </a>
                                        <?php if ($u['id'] != $userId): // Kendini silememeli ?>
                                            <a href="delete_user.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?');">
                                                <i class="bi bi-trash"></i> Sil
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <a href="add_user.php" class="btn btn-success">
                        <i class="bi bi-person-plus"></i> Yeni Kullanıcı Ekle
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sistem Bilgileri -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Sistem Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <strong>PHP Versiyonu:</strong>
                            <span class="badge bg-primary"><?php echo phpversion(); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>MySQL Versiyonu:</strong>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT VERSION() as version");
                                $mysqlVersion = $stmt->fetch()['version'];
                                echo '<span class="badge bg-primary">' . $mysqlVersion . '</span>';
                            } catch (PDOException $e) {
                                echo '<span class="badge bg-danger">Alınamadı</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <strong>Belge Sayısı:</strong>
                            <span class="badge bg-info"><?php echo $stats['total_documents']; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Kategori Sayısı:</strong>
                            <span class="badge bg-info"><?php echo $stats['total_categories']; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Kullanıcı Sayısı:</strong>
                            <span class="badge bg-info"><?php echo $stats['total_users']; ?></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <strong>Sunucu:</strong>
                            <span class="badge bg-secondary"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Son Güncelleme:</strong>
                            <span class="badge bg-secondary"><?php echo date('d.m.Y H:i'); ?></span>
                        </div>
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