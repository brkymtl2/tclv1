<?php
require_once 'config/db.php';
require_once 'includes/Session.php';
require_once 'includes/Security.php';
require_once 'includes/User.php';

// Oturumu başlat
Session::start();

// Oturum kontrolü
if (!Session::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Oturum zaman aşımı kontrolü
if (!Session::checkSessionTimeout()) {
    Session::setFlashMessage('Oturumunuz zaman aşımına uğradı. Lütfen tekrar giriş yapın.', 'warning');
    header('Location: login.php');
    exit;
}

// Kullanıcının bilgilerini al
$userId = Session::getUserId();
$username = Session::getUsername();
$userRole = Session::getUserRole();

// CSRF token
$csrf_token = Security::generateCSRFToken();

// Flash mesajı
$flashMessage = Session::getFlashMessage();

// IP adresini al
function getClientIP() {
    $ipAddress = '';
    
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
        $ipAddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipAddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipAddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipAddress = 'UNKNOWN';
        
    return $ipAddress;
}

$clientIP = getClientIP();
$currentDateTime = date('d.m.Y H:i:s');

// Aktif duyuruları al
require_once 'includes/Announcement.php';
$announcement = new Announcement();
$activeAnnouncements = $announcement->getActiveAnnouncements(3); // En fazla 3 duyuru göster
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Kalibrasyon Belge Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (isset($extraStyles)) echo $extraStyles; ?>
    
    <!-- Bildirim sistemi ve animasyonlar için JavaScript -->
    <script src="assets/js/notifications.js"></script>
    
    <style>
        /* Header duyuru sistemi için stil */
        .announcements-dropdown .dropdown-menu {
            min-width: 350px;
            max-width: 350px;
            padding: 0;
        }
        
        .announcements-dropdown .dropdown-item {
            white-space: normal;
            border-bottom: 1px solid rgba(0,0,0,.1);
            padding: 10px 15px;
        }
        
        .announcements-dropdown .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .announcements-dropdown .announcement-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .announcements-dropdown .announcement-content {
            font-size: 0.9rem;
            margin-bottom: 5px;
            color: #333;
        }
        
        .announcements-dropdown .announcement-date {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        /* Bildirim işareti */
        .announcements-indicator {
            position: absolute;
            top: 0.25rem;
            right: 0.1rem;
            width: 0.75rem;
            height: 0.75rem;
            border-radius: 50%;
            background-color: #dc3545;
        }
        
        /* Sayfa geçiş animasyonu */
        .container {
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">Kalibrasyon Belge Yönetimi</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? ' active' : ''; ?>" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Kontrol Paneli
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo basename($_SERVER['PHP_SELF']) == 'documents.php' ? ' active' : ''; ?>" href="documents.php">
                            <i class="bi bi-file-earmark-text"></i> Belgeler
                        </a>
                    </li>
                    
                    <?php if (Session::hasRole(['super_admin', 'admin'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear"></i> Yönetim
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li>
                                <a class="dropdown-item" href="categories.php">
                                    <i class="bi bi-folder"></i> Kategoriler
                                </a>
                            </li>
                            <?php if (Session::hasRole('super_admin')): ?>
                            <li>
                                <a class="dropdown-item" href="users.php">
                                    <i class="bi bi-people"></i> Kullanıcılar
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="permissions.php">
                                    <i class="bi bi-shield-lock"></i> İzinler
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (Session::hasRole(['super_admin', 'admin'])): ?>
                    <li class="nav-item">
                        <a class="nav-link<?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? ' active' : ''; ?>" href="logs.php">
                            <i class="bi bi-journal-text"></i> Loglar
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link<?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? ' active' : ''; ?>" href="announcements.php">
                            <i class="bi bi-megaphone"></i> Duyurular
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <!-- Duyurular Dropdown -->
                    <li class="nav-item dropdown announcements-dropdown">
                        <a class="nav-link position-relative" href="#" id="announcementsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                            <?php if (!empty($activeAnnouncements)): ?>
                                <span class="announcements-indicator"></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="announcementsDropdown">
                            <li><h6 class="dropdown-header">Duyurular</h6></li>
                            
                            <?php if (empty($activeAnnouncements)): ?>
                                <li><span class="dropdown-item text-muted">Aktif duyuru bulunmamaktadır.</span></li>
                            <?php else: ?>
                                <?php foreach ($activeAnnouncements as $ann): ?>
                                    <li>
                                        <div class="dropdown-item">
                                            <div class="announcement-title"><?php echo htmlspecialchars($ann['title']); ?></div>
                                            <div class="announcement-content">
                                                <?php echo nl2br(htmlspecialchars(substr($ann['content'], 0, 100) . (strlen($ann['content']) > 100 ? '...' : ''))); ?>
                                            </div>
                                            <div class="announcement-date">
                                                <?php echo date('d.m.Y', strtotime($ann['created_at'])); ?>
                                                &middot; <?php echo htmlspecialchars($ann['created_by_name']); ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                                
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center" href="#" data-bs-toggle="modal" data-bs-target="#allAnnouncementsModal">Tüm Duyurular</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <!-- Tarih, Saat ve IP Bilgisi -->
                    <li class="nav-item me-3">
                        <span class="nav-link">
                            <i class="bi bi-clock"></i> <?php echo $currentDateTime; ?>
                            <span class="ms-3"><i class="bi bi-ethernet"></i> IP: <?php echo $clientIP; ?></span>
                        </span>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($username); ?>
                            <span class="badge bg-light text-dark"><?php echo ucfirst($userRole); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item" href="profile.php">
                                    <i class="bi bi-person"></i> Profil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="change_password.php">
                                    <i class="bi bi-key"></i> Şifre Değiştir
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="logout.php">
                                    <i class="bi bi-box-arrow-right"></i> Çıkış Yap
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Tüm Duyurular Modal -->
    <div class="modal fade" id="allAnnouncementsModal" tabindex="-1" aria-labelledby="allAnnouncementsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="allAnnouncementsModalLabel">Tüm Duyurular</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <?php 
                    // Tüm aktif duyuruları getir
                    $allActiveAnnouncements = $announcement->getAllAnnouncements(true, 50, 0);
                    
                    if (empty($allActiveAnnouncements)): 
                    ?>
                        <p class="text-muted text-center">Aktif duyuru bulunmamaktadır.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($allActiveAnnouncements as $ann): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($ann['title']); ?></h5>
                                        <small class="text-muted">
                                            <?php echo date('d.m.Y', strtotime($ann['created_at'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($ann['created_by_name']); ?> tarafından oluşturuldu
                                        <?php if (!empty($ann['end_date'])): ?>
                                            &middot; Bitiş: <?php echo date('d.m.Y', strtotime($ann['end_date'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ana içerik -->
    <div class="container mt-4">
        <!-- Flash mesajları -->
        <?php if ($flashMessage): ?>
            <div class="alert alert-<?php echo $flashMessage['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $flashMessage['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>