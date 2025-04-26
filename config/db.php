<?php
// Veritabanı bağlantı bilgileri
$db_host = 'localhost';
$db_user = 'kortekgsmdatalog_tclsuperadmin'; // cPanel MySQL kullanıcı adı
$db_pass = 'Berkay0100'; // cPanel MySQL şifresi
$db_name = 'kortekgsmdatalog_tclkalibrasyon';

// PDO veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    // Hata modunu ayarla
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Varsayılan fetch modu
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Emulated prepared statements kapatma (güvenlik için)
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    // Prodüksiyon ortamında hata mesajlarını gösterme
    // echo "Bağlantı hatası: " . $e->getMessage();
    die("Veritabanı bağlantısı sağlanamadı. Lütfen sistem yöneticisi ile iletişime geçin.");
}

// Güvenli bir şekilde PDO nesnesini döndür
function getPDO() {
    global $pdo;
    return $pdo;
}
?>