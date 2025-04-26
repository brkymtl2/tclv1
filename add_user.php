<?php
// Sayfa başlığı
$pageTitle = 'Yeni Kullanıcı Ekle';

// Header'ı dahil et
require_once 'includes/header.php';

// Sadece süper admin erişebilir
if (!Session::hasRole('super_admin')) {
    Session::setFlashMessage('Bu sayfaya erişim yetkiniz bulunmamaktadır.', 'danger');
    header('Location: dashboard.php');
    exit;
}

// Kullanıcı sınıfını dahil et
require_once 'includes/User.php';

// İşlem yapılacak mı?
$message = '';
$error = '';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        // Form verilerini al
        $username = isset($_POST['username']) ? Security::sanitizeInput($_POST['username']) : '';
        $email = isset($_POST['email']) ? Security::sanitizeInput($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['password'] : '';
        $role = isset($_POST['role']) ? Security::sanitizeInput($_POST['role']) : '';
        
        // Veri doğrulama
        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            $error = 'Tüm alanlar gereklidir.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Şifreler eşleşmiyor.';
        } elseif (strlen($password) < 8) {
            $error = 'Şifre en az 8 karakter olmalıdır.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Geçerli bir e-posta adresi girin.';
        } else {
            // Kullanıcı oluştur
            $user = new User();
            $result = $user->createUser($username, $password, $email, $role);
            
            if ($result) {
                Session::setFlashMessage('Kullanıcı başarıyla oluşturuldu.', 'success');
                header('Location: super_admin.php');
                exit;
            } else {
                $error = 'Kullanıcı oluşturulurken bir hata oluştu. Kullanıcı adı veya e-posta zaten kullanımda olabilir.';
            }
        }
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="bi bi-person-plus"></i> Yeni Kullanıcı Ekle</h1>
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
                <h5 class="mb-0"><i class="bi bi-person-plus"></i> Kullanıcı Bilgileri</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="form-text">Şifre en az 8 karakter olmalıdır.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Şifre (Tekrar)</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Kullanıcı Rolü</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Rol Seçin</option>
                            <option value="super_admin">Süper Admin</option>
                            <option value="admin">Yönetici</option>
                            <option value="staff">Personel</option>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="super_admin.php" class="btn btn-secondary me-md-2">İptal</a>
                        <button type="submit" class="btn btn-primary">Kullanıcı Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
require_once 'includes/footer.php';
?>