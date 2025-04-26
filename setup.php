<?php
require_once 'config/db.php';
require_once 'includes/User.php';
require_once 'includes/Permission.php';
require_once 'includes/Security.php';

// Setup sayfasına sadece yerel ağdan erişilebilsin
// Not: Kurulum tamamlandıktan sonra bu dosyayı silmelisiniz!
//$localIPs = ['127.0.0.1', '::1'];
//if (!in_array($_SERVER['REMOTE_ADDR'], $localIPs)) {
//    die('Bu sayfaya erişim yetkiniz bulunmamaktadır.');
//}

$message = '';
$error = '';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Veri tabanı tablolarını oluştur
    try {
        $pdo = getPDO();
        
        // Kategoriler tablosu
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                description VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Kullanıcılar tablosu
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                role ENUM('super_admin', 'admin', 'staff') NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // İzinler tablosu
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                description VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Rol-İzin ilişki tablosu
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS role_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role ENUM('super_admin', 'admin', 'staff') NOT NULL,
                permission_id INT NOT NULL,
                FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
                UNIQUE KEY role_permission (role, permission_id)
            )
        ");
        
        // Belgeler tablosu
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(100) NOT NULL,
                description TEXT,
                category_id INT NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                file_size INT NOT NULL,
                file_type VARCHAR(50) NOT NULL,
                encrypted BOOLEAN DEFAULT TRUE,
                uploaded_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id),
                FOREIGN KEY (uploaded_by) REFERENCES users(id)
            )
        ");
        
        // Dosya yükleme klasörünü oluştur
        if (!file_exists('uploads')) {
            mkdir('uploads', 0755, true);
            file_put_contents('uploads/index.php', '<?php // Silence is golden');
            
            // Harici erişimi engelle (.htaccess)
            file_put_contents('uploads/.htaccess', 'Deny from all');
        }
        
        // Varsayılan izinleri oluştur
        $permission = new Permission();
        $permission->createDefaultPermissions();
        $permission->assignDefaultRolePermissions();
        
        // Süper admin kullanıcısını oluştur
        $superAdminUsername = isset($_POST['username']) ? Security::sanitizeInput($_POST['username']) : 'superadmin';
        $superAdminPassword = isset($_POST['password']) ? $_POST['password'] : '';
        $superAdminEmail = isset($_POST['email']) ? Security::sanitizeInput($_POST['email']) : '';
        
        if (empty($superAdminUsername) || empty($superAdminPassword) || empty($superAdminEmail)) {
            $error = 'Süper admin kullanıcı bilgileri eksik.';
        } else {
            $user = new User();
            $result = $user->createUser($superAdminUsername, $superAdminPassword, $superAdminEmail, 'super_admin');
            
            if ($result) {
                $message = 'Sistem başarıyla kuruldu! Süper admin hesabı oluşturuldu.';
            } else {
                $error = 'Süper admin oluşturulurken bir hata oluştu.';
            }
        }
        
    } catch (PDOException $e) {
        $error = 'Kurulum sırasında bir hata oluştu: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Kurulumu - Kalibrasyon Belge Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .setup-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="setup-container">
            <div class="logo">
                <h2>Kalibrasyon Belge Yönetimi</h2>
                <h4>Sistem Kurulumu</h4>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
                <div class="mb-4">
                    <p>Kurulum başarıyla tamamlandı! Şimdi <a href="login.php" class="btn btn-primary">Giriş Sayfasına</a> gidebilirsiniz.</p>
                    <p><strong>Önemli:</strong> Güvenlik nedeniyle bu setup.php dosyasını sunucunuzdan silmenizi öneririz.</p>
                </div>
            <?php elseif (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (empty($message)): ?>
                <form method="post" action="setup.php">
                    <div class="mb-3">
                        <label for="username" class="form-label">Süper Admin Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="username" name="username" value="superadmin" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Süper Admin E-posta</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Süper Admin Şifre</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <div class="alert alert-warning">
                            <strong>Uyarı:</strong> Bu işlem veri tabanını sıfırlayacak ve yeni tablolar oluşturacaktır. Eğer daha önce kurulum yaptıysanız, mevcut verileriniz kaybolabilir.
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Kurulumu Başlat</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>