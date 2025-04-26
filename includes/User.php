<?php
require_once 'includes/Security.php';
require_once 'config/db.php';

class User {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getPDO();
    }
    
    /**
     * Yeni kullanıcı oluşturur
     * @param string $username Kullanıcı adı
     * @param string $password Şifre (plain text)
     * @param string $email E-posta
     * @param string $role Kullanıcı rolü (super_admin, admin, staff)
     * @return int|false Oluşturulan kullanıcının ID'si veya hata durumunda false
     */
    public function createUser($username, $password, $email, $role = 'staff') {
        try {
            // Kullanıcı adı ve e-posta kontrolü
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                return false; // Kullanıcı adı veya e-posta zaten kullanımda
            }
            
            // Şifreyi hashle
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Kullanıcıyı ekle
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, password, email, role)
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([$username, $hashedPassword, $email, $role]);
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            // Hata logla
            error_log("Kullanıcı oluşturulamadı: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kullanıcı girişi doğrular
     * @param string $username Kullanıcı adı
     * @param string $password Şifre
     * @return array|false Kullanıcı bilgileri veya hata durumunda false
     */
    public function login($username, $password) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->rowCount() === 0) {
                return false; // Kullanıcı bulunamadı
            }
            
            $user = $stmt->fetch();
            
            // Şifre doğrulama
            if (password_verify($password, $user['password'])) {
                // Şifre doğru, şifre haricindeki bilgileri döndür
                unset($user['password']);
                return $user;
            }
            
            return false; // Şifre yanlış
            
        } catch (PDOException $e) {
            error_log("Giriş hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kullanıcı bilgilerini günceller
     * @param int $userId Kullanıcı ID
     * @param array $data Güncellenecek veriler
     * @return bool İşlem başarılı mı?
     */
    public function updateUser($userId, $data) {
        try {
            $allowedFields = ['email', 'role']; // Güncellenebilir alanlar
            $updates = [];
            $params = [];
            
            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $updates[] = "$field = ?";
                    $params[] = $value;
                }
            }
            
            // Güncellenecek alan yoksa
            if (empty($updates)) {
                return false;
            }
            
            // Kullanıcı ID'sini parametre olarak ekle
            $params[] = $userId;
            
            $stmt = $this->pdo->prepare("
                UPDATE users
                SET " . implode(", ", $updates) . "
                WHERE id = ?
            ");
            
            return $stmt->execute($params);
            
        } catch (PDOException $e) {
            error_log("Kullanıcı güncellenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kullanıcı şifresini değiştirir
     * @param int $userId Kullanıcı ID
     * @param string $newPassword Yeni şifre
     * @return bool İşlem başarılı mı?
     */
    public function changePassword($userId, $newPassword) {
        try {
            // Şifreyi hashle
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $this->pdo->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
            ");
            
            return $stmt->execute([$hashedPassword, $userId]);
            
        } catch (PDOException $e) {
            error_log("Şifre değiştirilemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kullanıcıyı siler
     * @param int $userId Kullanıcı ID
     * @return bool İşlem başarılı mı?
     */
    public function deleteUser($userId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            return $stmt->execute([$userId]);
            
        } catch (PDOException $e) {
            error_log("Kullanıcı silinemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Tüm kullanıcıları getirir
     * @return array Kullanıcılar listesi
     */
    public function getAllUsers() {
        try {
            $stmt = $this->pdo->query("
                SELECT id, username, email, role, created_at, updated_at
                FROM users
                ORDER BY username
            ");
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Kullanıcılar getirilemedi: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Kullanıcı izinlerini kontrol eder
     * @param int $userId Kullanıcı ID
     * @param string $permissionName İzin adı
     * @return bool Kullanıcı izne sahip mi?
     */
    public function hasPermission($userId, $permissionName) {
        try {
            // Önce kullanıcının rolünü bul
            $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            if ($stmt->rowCount() === 0) {
                return false; // Kullanıcı bulunamadı
            }
            
            $user = $stmt->fetch();
            $role = $user['role'];
            
            // Super admin her zaman tüm izinlere sahiptir
            if ($role === 'super_admin') {
                return true;
            }
            
            // İzni kontrol et
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role = ? AND p.name = ?
            ");
            
            $stmt->execute([$role, $permissionName]);
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("İzin kontrolü hatası: " . $e->getMessage());
            return false;
        }
    }
}
?>