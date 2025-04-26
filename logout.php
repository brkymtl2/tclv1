<?php
require_once 'includes/Session.php';

// Oturumu başlat
Session::start();

// Log kaydı ekle
if (Session::isLoggedIn()) {
    $userId = Session::getUserId();
    $username = Session::getUsername();
    
    require_once 'includes/Log.php';
    $log = new Log();
    $log->add('logout', 'Kullanıcı çıkış yaptı', 'user', $userId);
}

// Oturumu sonlandır
Session::end();

// Ana sayfaya yönlendir
header('Location: login.php');
exit;
?>