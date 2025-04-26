<?php
class Security {
    // Şifreleme için kullanılacak anahtar (güvenli bir yerde saklanmalı)
    private static $encryptionKey = 'N1xIZhe3DRH8SFjoEjI8WqXt4sZrmvzwaFjt34e8C79E6St5m67MXqmKriW9qhgK'; // Üretim ortamında değiştirilmeli!
    
    /**
     * Bir string'i şifreler
     * @param string $data Şifrelenecek veri
     * @return string Şifrelenmiş veri (base64 formatında)
     */
    public static function encrypt($data) {
        $method = 'AES-256-CBC';
        $ivlen = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $encrypted = openssl_encrypt($data, $method, self::$encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Şifrelenmiş bir string'i çözer
     * @param string $data Çözülecek veri (base64 formatında)
     * @return string|false Çözülmüş veri veya hata durumunda false
     */
    public static function decrypt($data) {
        $method = 'AES-256-CBC';
        $data = base64_decode($data);
        $ivlen = openssl_cipher_iv_length($method);
        $iv = substr($data, 0, $ivlen);
        $encrypted = substr($data, $ivlen);
        return openssl_decrypt($encrypted, $method, self::$encryptionKey, 0, $iv);
    }
    
    /**
     * Bir dosyayı şifreler
     * @param string $source Kaynak dosya yolu
     * @param string $destination Hedef dosya yolu
     * @return bool İşlem başarılı mı?
     */
    public static function encryptFile($source, $destination) {
        $content = file_get_contents($source);
        if ($content === false) return false;
        
        $encrypted = self::encrypt($content);
        return file_put_contents($destination, $encrypted) !== false;
    }
    
    /**
     * Şifrelenmiş bir dosyayı çözer
     * @param string $source Kaynak şifreli dosya
     * @param string $destination Hedef çözülmüş dosya
     * @return bool İşlem başarılı mı?
     */
    public static function decryptFile($source, $destination) {
        $content = file_get_contents($source);
        if ($content === false) return false;
        
        $decrypted = self::decrypt($content);
        if ($decrypted === false) return false;
        
        return file_put_contents($destination, $decrypted) !== false;
    }
    
    /**
     * CSRF token oluşturur ve session'a kaydeder
     * @return string CSRF token
     */
    public static function generateCSRFToken() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * CSRF token doğrulama
     * @param string $token Kontrol edilecek token
     * @return bool Token geçerli mi?
     */
    public static function validateCSRFToken($token) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !isset($token)) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * XSS saldırılarına karşı verileri temizler
     * @param string $data Temizlenecek veri
     * @return string Temizlenmiş veri
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitizeInput($value);
            }
            return $data;
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}
?>