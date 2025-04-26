<?php
// Oturum sınıfını dahil et
require_once 'includes/Session.php';

// Oturumu başlat
Session::start();

// Kullanıcı giriş yapmış mı?
if (Session::isLoggedIn()) {
    // Kullanıcı rolüne göre yönlendirme
    $userRole = Session::getUserRole();
    
    if ($userRole === 'super_admin') {
        header('Location: super_admin.php');
    } else {
        header('Location: dashboard.php');
    }
} else {
    // Giriş yapmamış ise login sayfasına yönlendir
    header('Location: login.php');
}

exit;
?>