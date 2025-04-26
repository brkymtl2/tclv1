<?php
// Header'ı dahil etmiyoruz, çünkü bu sayfa sadece yönlendirme yapacak

require_once 'config/db.php';
require_once 'includes/Session.php';
require_once 'includes/Security.php';
require_once 'includes/User.php';

// Oturumu başlat
Session::start();

// Sadece süper admin erişebilir
if (!Session::hasRole('super_admin')) {
    Session::setFlashMessage('Bu sayfaya erişim yetkiniz bulunmamaktadır.', 'danger');
    header('Location: dashboard.php');
    exit;
}

// Silinecek kullanıcı ID'si
$deleteUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($deleteUserId <= 0) {
    Session::setFlashMessage('Geçersiz kullanıcı ID\'si.', 'danger');
    header('Location: super_admin.php');
    exit;
}

// Kullanıcının kendisini silmesini engelle
if ($deleteUserId === Session::getUserId()) {
    Session::setFlashMessage('Kendi hesabınızı silemezsiniz.', 'danger');
    header('Location: super_admin.php');
    exit;
}

// Kullanıcı sınıfını başlat
$user = new User();

// Kullanıcıyı sil
$result = $user->deleteUser($deleteUserId);

if ($result) {
    Session::setFlashMessage('Kullanıcı başarıyla silindi.', 'success');
} else {
    Session::setFlashMessage('Kullanıcı silinirken bir hata oluştu.', 'danger');
}

// Süper admin paneline yönlendir
header('Location: super_admin.php');
exit;
?>