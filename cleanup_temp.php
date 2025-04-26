<?php
// Oturumu başlat (güvenlik kontrolü için)
require_once 'includes/Session.php';
Session::start();

// Kullanıcı giriş yapmış mı?
if (!Session::isLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Erişim reddedildi.');
}

// Gerekli parametre
$fileName = isset($_GET['file']) ? $_GET['file'] : '';

// Dosya adı kontrolü
if (empty($fileName)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Geçersiz istek.');
}

// Güvenlik: Dosya adında yol değiştirici karakterleri temizle
$fileName = basename($fileName);

// Geçici klasör yolu
$tempDir = 'temp';
$filePath = $tempDir . '/' . $fileName;

// Dosya var mı?
if (file_exists($filePath)) {
    // Dosyayı sil
    @unlink($filePath);
    
    // Başarılı yanıt
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Dosya başarıyla temizlendi.']);
} else {
    // Dosya zaten silinmiş veya yok
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Dosya bulunamadı.']);
}
?>