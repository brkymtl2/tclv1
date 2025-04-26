<?php
// Veritabanı bağlantısı için gerekli dosyaları dahil et
require_once 'config/db.php';

// Test edilecek kullanıcı adı ve şifre
$username = "superadmin";
$testPassword = "tcladmin";

try {
    // Veritabanı bağlantısını al
    $pdo = getPDO();
    
    // Kullanıcının hash'lenen şifresini veritabanından al
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() === 0) {
        echo "Kullanıcı bulunamadı!";
    } else {
        $user = $stmt->fetch();
        $storedHash = $user['password'];
        
        echo "Veritabanındaki Hash: " . $storedHash . "<br>";
        
        // Şifre doğrulaması
        $isValid = password_verify($testPassword, $storedHash);
        
        echo "Şifre Doğrulama: " . ($isValid ? "BAŞARILI (Şifre doğru)" : "BAŞARISIZ (Şifre yanlış)") . "<br>";
    }
} catch (PDOException $e) {
    echo "Hata: " . $e->getMessage();
}
?>