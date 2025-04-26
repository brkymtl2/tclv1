<?php
// Sayfa başlığı
$pageTitle = 'Profil Bilgilerim';

// Header'ı dahil et
require_once 'includes/header.php';

// Kullanıcı sınıfını dahil et
require_once 'includes/User.php';

// Kullanıcı bilgilerini al
$userId = Session::getUserId();
$userObj = new User();

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
        $email = isset($_POST['email']) ? Security::sanitizeInput($_POST['email']) : '';
        $changePassword = isset($_POST['change_password']) && $_POST['change_password'] == '1';
        $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Veri doğrulama
        if (empty($email)) {
            $error = 'E-posta alanı gereklidir.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Geçerli bir e-posta adresi girin.';
        } elseif ($changePassword) {
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $error = 'Şifre değişikliği için tüm şifre alanları gereklidir.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Yeni şifre ve onayı eşleşmiyor.';
            } elseif (strlen($newPassword) < 8) {
                $error = 'Yeni şifre en az 8 karakter olmalıdır.';
            } else {
                // Mevcut şifreyi doğrula
                try {
                    $pdo = getPDO();
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $userData = $stmt->fetch();
                    
                    if (!password_verify($currentPassword, $userData['password'])) {
                        $error = 'Mevcut şifreniz hatalı.';
                    }
                } catch (PDOException $e) {
                    $error = 'Şifre kontrolü sırasında bir hata oluştu.';
                    error_log("Şifre kontrolü hatası: " . $e->getMessage());
                }
            }
        }
        
        // Hata yoksa güncelleme yap
        if (empty($error)) {
            try {
                // Kullanıcı bilgilerini güncelle
                $updateData = [
                    'email' => $email
                ];
                
                $updateResult = $userObj->updateUser($userId, $updateData);
                
                // Şifre değişikliği var mı?
                if ($changePassword) {
                    $passwordResult = $userObj->changePassword($userId, $newPassword);
                    
                    if (!$passwordResult) {
                        $error = 'Şifre güncellenirken bir hata oluştu.';
                    } else {
                        // Log kaydı ekle
                        require_once 'includes/Log.php';
                        $log = new Log();
                        $log->add('change_password', 'Kullanıcı şifresini değiştirdi', 'user', $userId);
                    }
                }
                
                if ($updateResult) {
                    // Log kaydı ekle
                    require_once 'includes/Log.php';
                    $log = new Log();
                    $log->add('update_profile', 'Kullanıcı profil bilgilerini güncelledi', 'user', $userId);
                    
                    $message = 'Profil bilgileriniz başarıyla güncellendi.';
                    
                    // Bildirim ekle
                    echo '<script>
                        document.addEventListener("DOMContentLoaded", function() {
                            showNotification("Profil güncellendi", "Profil bilgileriniz başarıyla güncellendi.", "success");
                        });
                    </script>';
                } else {
                    $error = 'Profil bilgileriniz güncellenirken bir hata oluştu.';
                }
            } catch (Exception $e) {
                $error = 'İşlem sırasında bir hata oluştu: ' . $e->getMessage();
                error_log("Profil güncelleme hatası: " . $e->getMessage());
            }
        }
    }
}

// Kullanıcı bilgilerini al
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT username, email, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Kullanıcı bilgileri alınamadı.';
    error_log("Kullanıcı bilgileri alınamadı: " . $e->getMessage());
    $userData = [
        'username' => $username,
        'email' => '',
        'role' => $userRole,
        'created_at' => ''
    ];
}
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="bi bi-person-circle"></i> Profil Bilgilerim</h1>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-person-badge"></i> Kullanıcı Bilgileri</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($userData['username']); ?>" readonly>
                        <div class="form-text">Kullanıcı adı değiştirilemez.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Kullanıcı Rolü</label>
                        <input type="text" class="form-control" id="role" value="<?php echo ucfirst($userData['role']); ?>" readonly>
                        <div class="form-text">Kullanıcı rolü yalnızca yöneticiler tarafından değiştirilebilir.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="created_at" class="form-label">Kayıt Tarihi</label>
                        <input type="text" class="form-control" id="created_at" value="<?php echo date('d.m.Y H:i', strtotime($userData['created_at'])); ?>" readonly>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="change_password" name="change_password" value="1" onchange="togglePasswordFields()">
                        <label class="form-check-label" for="change_password">Şifremi değiştirmek istiyorum</label>
                    </div>
                    
                    <div id="password_fields" style="display: none;">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mevcut Şifre</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Yeni Şifre</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                            <div class="form-text">Şifre en az 8 karakter olmalıdır.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Yeni Şifre (Tekrar)</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Bilgilerimi Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-activity"></i> Son Aktiviteler</h5>
            </div>
            <div class="card-body p-0">
                <?php
                // Son aktiviteleri getir
                require_once 'includes/Log.php';
                $log = new Log();
                $userLogs = $log->getLogs(['user_id' => $userId], 5, 0);
                
                if (empty($userLogs)):
                ?>
                    <div class="p-3 text-center">
                        <p class="text-muted mb-0">Henüz kayıtlı aktivite bulunmamaktadır.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($userLogs as $activity): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <?php 
                                            $actionText = ucfirst(str_replace('_', ' ', $activity['action']));
                                            echo $actionText;
                                        ?>
                                    </h6>
                                    <small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($activity['created_at'])); ?></small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($activity['description']); ?></p>
                                <small class="text-muted">
                                    <?php if (!empty($activity['entity_type'])): ?>
                                        <?php echo ucfirst($activity['entity_type']); ?>
                                        <?php if (!empty($activity['entity_id'])): ?>
                                            #<?php echo $activity['entity_id']; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-end">
                <a href="logs.php?user_id=<?php echo $userId; ?>" class="btn btn-sm btn-info">Tüm Aktivitelerimi Gör</a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Belge İstatistiklerim</h5>
            </div>
            <div class="card-body">
                <?php
                // Belge istatistiklerini getir
                try {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as uploaded_count 
                        FROM documents 
                        WHERE uploaded_by = ?
                    ");
                    $stmt->execute([$userId]);
                    $uploadedCount = $stmt->fetch()['uploaded_count'];
                    
                    // İndirme sayısını getir (log tablosundan)
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as download_count 
                        FROM activity_logs 
                        WHERE user_id = ? AND action = 'download_document'
                    ");
                    $stmt->execute([$userId]);
                    $downloadCount = $stmt->fetch()['download_count'];
                    
                    // Görüntüleme sayısını getir (log tablosundan)
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as view_count 
                        FROM activity_logs 
                        WHERE user_id = ? AND action = 'view_document'
                    ");
                    $stmt->execute([$userId]);
                    $viewCount = $stmt->fetch()['view_count'];
                } catch (PDOException $e) {
                    $uploadedCount = 0;
                    $downloadCount = 0;
                    $viewCount = 0;
                    error_log("Belge istatistikleri alınamadı: " . $e->getMessage());
                }
                ?>
                
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="stats-item">
                            <h3 class="text-primary"><?php echo $uploadedCount; ?></h3>
                            <p class="text-muted">Yüklenen Belge</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-item">
                            <h3 class="text-success"><?php echo $downloadCount; ?></h3>
                            <p class="text-muted">İndirilen Belge</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-item">
                            <h3 class="text-info"><?php echo $viewCount; ?></h3>
                            <p class="text-muted">Görüntülenen Belge</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordFields() {
    var passwordFields = document.getElementById('password_fields');
    var changePasswordCheckbox = document.getElementById('change_password');
    
    if (changePasswordCheckbox.checked) {
        passwordFields.style.display = 'block';
    } else {
        passwordFields.style.display = 'none';
    }
}

// Bildirim gösterme fonksiyonu
function showNotification(title, message, type = 'info') {
    // Bootstrap Toast bildirimi için CSS ekle
    var cssId = 'toastCSS';
    if (!document.getElementById(cssId)) {
        var head = document.getElementsByTagName('head')[0];
        var style = document.createElement('style');
        style.id = cssId;
        style.innerHTML = `
            .notification-container {
                position: fixed;
                bottom: 15px;
                right: 15px;
                z-index: 9999;
            }
            .toast {
                min-width: 300px;
            }
        `;
        head.appendChild(style);
    }
    
    // Container oluştur
    var container = document.querySelector('.notification-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'notification-container';
        document.body.appendChild(container);
    }
    
    // Toast bildirimini oluştur
    var toastId = 'toast-' + Date.now();
    var backgroundColor = 'bg-info';
    var icon = '<i class="bi bi-info-circle"></i>';
    
    if (type === 'success') {
        backgroundColor = 'bg-success';
        icon = '<i class="bi bi-check-circle"></i>';
    } else if (type === 'danger' || type === 'error') {
        backgroundColor = 'bg-danger';
        icon = '<i class="bi bi-exclamation-circle"></i>';
    } else if (type === 'warning') {
        backgroundColor = 'bg-warning';
        icon = '<i class="bi bi-exclamation-triangle"></i>';
    }
    
    var toast = document.createElement('div');
    toast.className = 'toast';
    toast.id = toastId;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    toast.setAttribute('data-bs-delay', '5000');
    toast.innerHTML = `
        <div class="toast-header ${backgroundColor} text-white">
            ${icon} <strong class="me-auto">${title}</strong>
            <small>Şimdi</small>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            ${message}
        </div>
    `;
    
    container.appendChild(toast);
    
    // Bootstrap Toast'ı başlat
    var toastElement = new bootstrap.Toast(document.getElementById(toastId));
    toastElement.show();
    
    // Toast otomatik temizleme (DOM'dan kaldırma)
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}
</script>

<?php
// Footer'ı dahil et
require_once 'includes/footer.php';
?>