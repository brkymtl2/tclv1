<?php
// Sayfa başlığı
$pageTitle = 'Şifre Değiştir';

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
        $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Veri doğrulama
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Tüm şifre alanları gereklidir.';
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
        
        // Hata yoksa şifreyi değiştir
        if (empty($error)) {
            $passwordResult = $userObj->changePassword($userId, $newPassword);
            
            if ($passwordResult) {
                // Log kaydı ekle
                require_once 'includes/Log.php';
                $log = new Log();
                $log->add('change_password', 'Kullanıcı şifresini değiştirdi', 'user', $userId);
                
                $message = 'Şifreniz başarıyla değiştirildi.';
                
                // Bildirim göster
                echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        showNotification("Şifre Değiştirildi", "Şifreniz başarıyla güncellendi.", "success");
                    });
                </script>';
            } else {
                $error = 'Şifreniz değiştirilirken bir hata oluştu.';
                
                // Hata bildirimi göster
                echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        showNotification("Hata", "Şifreniz değiştirilirken bir hata oluştu.", "danger");
                    });
                </script>';
            }
        } else {
            // Hata bildirimi göster
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    showNotification("Hata", "' . $error . '", "danger");
                });
            </script>';
        }
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="bi bi-key"></i> Şifre Değiştir</h1>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Şifre Değiştirme</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Mevcut Şifre</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Yeni Şifre</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div class="form-text">Şifre en az 8 karakter olmalıdır.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Yeni Şifre (Tekrar)</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Şifremi Değiştir</button>
                        <a href="profile.php" class="btn btn-secondary">Profil Sayfasına Dön</a>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                <div class="alert alert-info mb-0">
                    <h6><i class="bi bi-info-circle"></i> Güvenli Şifre Önerileri</h6>
                    <ul class="mb-0">
                        <li>En az 8 karakter uzunluğunda olmalı</li>
                        <li>Büyük ve küçük harfler içermeli</li>
                        <li>Rakam ve özel karakterler içermeli</li>
                        <li>Tahmin edilebilir bilgiler kullanmayın (doğum tarihi, isim vb.)</li>
                        <li>Farklı sistemlerde aynı şifreyi kullanmayın</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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