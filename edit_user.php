<?php
// Sayfa başlığı
$pageTitle = 'Kullanıcı Düzenle';

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

// Düzenlenecek kullanıcı ID'si
$editUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($editUserId <= 0) {
    Session::setFlashMessage('Geçersiz kullanıcı ID\'si.', 'danger');
    header('Location: super_admin.php');
    exit;
}

$user = new User();

// Kullanıcı bilgilerini al
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$editUserId]);
    
    if ($stmt->rowCount() === 0) {
        Session::setFlashMessage('Kullanıcı bulunamadı.', 'danger');
        header('Location: super_admin.php');
        exit;
    }
    
    $userData = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Kullanıcı bilgileri alınamadı: " . $e->getMessage());
    Session::setFlashMessage('Kullanıcı bilgileri alınamadı.', 'danger');
    header('Location: super_admin.php');
    exit;
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        // Form verilerini al
        $email = isset($_POST['email']) ? Security::sanitizeInput($_POST['email']) : '';
        $role = isset($_POST['role']) ? Security::sanitizeInput($_POST['role']) : '';
        $changePassword = isset($_POST['change_password']) && $_POST['change_password'] == '1';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Veri doğrulama
        if (empty($email) || empty($role)) {
            $error = 'E-posta ve rol alanları gereklidir.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Geçerli bir e-posta adresi girin.';
        } elseif ($changePassword) {
            if (empty($password)) {
                $error = 'Şifre alanı gereklidir.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Şifreler eşleşmiyor.';
            } elseif (strlen($password) < 8) {
                $error = 'Şifre en az 8 karakter olmalıdır.';
            }
        }
        
        if (empty($error)) {
            // Kullanıcı bilgilerini güncelle
            $updateData = [
                'email' => $email,
                'role' => $role
            ];
            
            $updateResult = $user->updateUser($editUserId, $updateData);
            
            // Şifre değişikliği yapılacak mı?
            if ($changePassword) {
                $passwordResult = $user->changePassword($editUserId, $password);
                
                if (!$passwordResult) {
                    $error = 'Şifre güncellenirken bir hata oluştu.';
                }
            }
            
            if ($updateResult) {
                Session::setFlashMessage('Kullanıcı bilgileri başarıyla güncellendi.', 'success');
                header('Location: super_admin.php');
                exit;
            } else {
                $error = 'Kullanıcı bilgileri güncellenirken bir hata oluştu.';
            }
        }
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4"><i class="bi bi-person-gear"></i> Kullanıcı Düzenle: <?php echo htmlspecialchars($userData['username']); ?></h1>
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
                <h5 class="mb-0"><i class="bi bi-person-gear"></i> Kullanıcı Bilgileri</h5>
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
                        <select class="form-select" id="role" name="role" required>
                            <option value="super_admin" <?php echo $userData['role'] === 'super_admin' ? 'selected' : ''; ?>>Süper Admin</option>
                            <option value="admin" <?php echo $userData['role'] === 'admin' ? 'selected' : ''; ?>>Yönetici</option>
                            <option value="staff" <?php echo $userData['role'] === 'staff' ? 'selected' : ''; ?>>Personel</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="change_password" name="change_password" value="1" onchange="togglePasswordFields()">
                        <label class="form-check-label" for="change_password">Şifre Değiştir</label>
                    </div>
                    
                    <div id="password_fields" style="display: none;">
                        <div class="mb-3">
                            <label for="password" class="form-label">Yeni Şifre</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <div class="form-text">Şifre en az 8 karakter olmalıdır.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Yeni Şifre (Tekrar)</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="super_admin.php" class="btn btn-secondary me-md-2">İptal</a>
                        <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                    </div>
                </form>
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
</script>

<?php
// Footer'ı dahil et
require_once 'includes/footer.php';
?>