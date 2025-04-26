<?php
class Session {
    /**
     * Oturumu başlatır
     */
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            // Güvenli oturum ayarları
            ini_set('session.use_only_cookies', 1);
            ini_set('session.use_strict_mode', 1);
            
            $cookieParams = session_get_cookie_params();
            session_set_cookie_params(
                $cookieParams["lifetime"],
                $cookieParams["path"],
                $cookieParams["domain"],
                true,  // secure flag (HTTPS only)
                true   // httponly flag
            );
            
            session_name('calibration_secure_session');
            session_start();
            
            // CSRF token oluştur
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            
            // Oturum sabitleme saldırılarına karşı önlem
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } else if (time() - $_SESSION['created'] > 1800) {
                // 30 dakikada bir oturum ID'sini yenile
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    /**
     * Kullanıcı oturumunu başlatır
     * @param array $user Kullanıcı verileri
     */
    public static function setUser($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Oturum açmış kullanıcıyı kontrol eder
     * @return bool Kullanıcı giriş yapmış mı?
     */
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Oturum süresi kontrolü
     * @param int $timeout Zaman aşımı süresi (saniye)
     * @return bool Oturum geçerli mi?
     */
    public static function checkSessionTimeout($timeout = 1800) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        if (time() - $_SESSION['last_activity'] > $timeout) {
            // Oturum zaman aşımına uğradı
            self::end();
            return false;
        }
        
        // Son aktivite zamanını güncelle
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Oturumu sonlandırır
     */
    public static function end() {
        // Oturum değişkenlerini temizle
        $_SESSION = [];
        
        // Oturum çerezini sil
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Oturumu yok et
        session_destroy();
    }
    
    /**
     * Kullanıcı rolünü kontrol eder
     * @param string|array $roles İzin verilen rol(ler)
     * @return bool Kullanıcı izin verilen role sahip mi?
     */
    public static function hasRole($roles) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        return in_array($_SESSION['user_role'], $roles);
    }
    
    /**
     * Geçerli kullanıcı ID'sini döndürür
     * @return int|null Kullanıcı ID
     */
    public static function getUserId() {
        return self::isLoggedIn() ? $_SESSION['user_id'] : null;
    }
    
    /**
     * Geçerli kullanıcı rolünü döndürür
     * @return string|null Kullanıcı rolü
     */
    public static function getUserRole() {
        return self::isLoggedIn() ? $_SESSION['user_role'] : null;
    }
    
    /**
     * Geçerli kullanıcı adını döndürür
     * @return string|null Kullanıcı adı
     */
    public static function getUsername() {
        return self::isLoggedIn() ? $_SESSION['username'] : null;
    }
    
    /**
     * Flash mesajı oluşturur (bir kez gösterilecek mesaj)
     * @param string $message Mesaj
     * @param string $type Mesaj tipi (success, error, warning, info)
     */
    public static function setFlashMessage($message, $type = 'info') {
        $_SESSION['flash_message'] = [
            'message' => $message,
            'type' => $type
        ];
    }
    
    /**
     * Flash mesajını alır ve siler
     * @return array|null Mesaj bilgileri
     */
    public static function getFlashMessage() {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $message;
        }
        return null;
    }
}
?>