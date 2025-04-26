<?php
// Oturumu başlat
require_once 'includes/Session.php';
require_once 'includes/Security.php';
require_once 'includes/Log.php';
Session::start();

// Kullanıcı giriş yapmış mı?
if (!Session::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Belge sınıfını dahil et
require_once 'includes/Document.php';

// Belge ID'si alın
$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($documentId <= 0) {
    Session::setFlashMessage('Geçersiz belge ID\'si.', 'danger');
    header('Location: documents.php');
    exit;
}

// Belge sınıfını başlat
$document = new Document();

// Belge bilgilerini al
$documentInfo = $document->getDocument($documentId);

if (!$documentInfo) {
    Session::setFlashMessage('Belge bulunamadı.', 'danger');
    header('Location: documents.php');
    exit;
}

// Belgeyi indir
$documentFile = $document->downloadDocument($documentId);

if (!$documentFile) {
    Session::setFlashMessage('Belge indirilemedi.', 'danger');
    header('Location: documents.php');
    exit;
}

// Dosya yolu ve adı
$filePath = $documentFile['path'];
$fileName = $documentFile['name'];
$fileType = $documentFile['type'];
$fileSize = $documentFile['size'];

// Dosya var mı?
if (!file_exists($filePath)) {
    Session::setFlashMessage('Dosya bulunamadı.', 'danger');
    header('Location: documents.php');
    exit;
}

// Dosya okunabilir mi?
if (!is_readable($filePath)) {
    Session::setFlashMessage('Dosya okunamıyor.', 'danger');
    header('Location: documents.php');
    exit;
}

// İndirme başlıkları
header('Content-Description: File Transfer');
header('Content-Type: ' . $fileType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

// Çıktı tamponlamasını kapat
ob_clean();
flush();

// Dosyayı gönder
readfile($filePath);

// Geçici dosyayı temizle
@unlink($filePath);
// Log kaydı ekle

$log = new Log();
$log->add('download_document', 'Belge indirildi: ' . $documentInfo['title'], 'document', $documentId);

// Çıktı tamponlamasını kapat
ob_clean();
flush();

// Dosyayı gönder
readfile($filePath);

// Geçici dosyayı temizle
@unlink($filePath);

exit;
exit;
?>