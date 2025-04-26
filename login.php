<?php
require_once 'config/db.php';
require_once 'includes/User.php';
require_once 'includes/Session.php';
require_once 'includes/Security.php';

// Oturumu başlat
Session::start();

// Kullanıcı zaten giriş yapmış ise dashboard'a yönlendir
if (Session::isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        // Giriş bilgilerini doğrula
        $username = isset($_POST['username']) ? Security::sanitizeInput($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($username) || empty($password)) {
            $error = 'Kullanıcı adı ve şifre gereklidir.';
        } else {
            $user = new User();
            $userData = $user->login($username, $password);
            
            if ($userData) {
                // Başarılı giriş
                Session::setUser($userData);
                
                // Log kaydı ekle
                require_once 'includes/Log.php';
                $log = new Log();
                $log->add('login', 'Kullanıcı başarıyla giriş yaptı', 'user', $userData['id']);
                
                // Kullanıcının rolüne göre yönlendirme
                if ($userData['role'] === 'super_admin') {
                    header('Location: super_admin.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            } else {
                $error = 'Kullanıcı adı veya şifre hatalı.';
                
                // Başarısız giriş denemesi log kaydı
                require_once 'includes/Log.php';
                $log = new Log();
                $log->add('login_failed', 'Başarısız giriş denemesi: ' . $username, 'user');
            }
        }
    }
}

// CSRF token oluştur
$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Kalibrasyon Belge Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo">
                <h2>Kalibrasyon Belge Yönetimi</h2>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="post" action="login.php">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label">Kullanıcı Adı</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Şifre</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Giriş Yap</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>