<?php
// Oturumu başlat (güvenlik kontrolü için)
require_once 'includes/Session.php';
Session::start();

// Kullanıcı giriş yapmış mı?
if (!Session::isLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Erişim reddedildi.');
}

// Gerekli parametreler
$fileName = isset($_GET['file']) ? $_GET['file'] : '';
$fileType = isset($_GET['type']) ? $_GET['type'] : '';

// Dosya adı ve türü kontrolü
if (empty($fileName) || empty($fileType)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Geçersiz istek.');
}

// Güvenlik: Dosya adında yol değiştirici karakterleri temizle
$fileName = basename($fileName);

// Geçici klasör yolu
$tempDir = 'temp';
$filePath = $tempDir . '/' . $fileName;

// Dosya var mı?
if (!file_exists($filePath)) {
    header('HTTP/1.1 404 Not Found');
    exit('Dosya bulunamadı.');
}

// Dosya okunabilir mi?
if (!is_readable($filePath)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Dosya okunamıyor.');
}

// MIME türünü ayarla
header('Content-Type: ' . $fileType);

// Dosya içeriğini oku ve gönder
readfile($filePath);
?>