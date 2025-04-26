<?php
// Sadece AJAX isteklerine yanıt ver
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    // AJAX isteği değilse normal sayfa isteği olarak işle
    if (isset($_GET['category_id'])) {
        header('Content-Type: application/json');
        require_once 'config/db.php';
        require_once 'includes/Category.php';
        
        $categoryId = (int)$_GET['category_id'];
        
        if ($categoryId > 0) {
            $category = new Category();
            $subcategories = $category->getSubCategories($categoryId);
            
            echo json_encode($subcategories);
            exit;
        }
    }
    
    // Geçersiz istek
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Geçersiz istek']);
    exit;
}

// Kategori ID gerekli
if (!isset($_GET['category_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Kategori ID gerekli']);
    exit;
}

// Gerekli dosyaları dahil et
require_once 'config/db.php';
require_once 'includes/Category.php';

$categoryId = (int)$_GET['category_id'];

if ($categoryId <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Geçersiz kategori ID']);
    exit;
}

// Alt kategorileri getir
$category = new Category();
$subcategories = $category->getSubCategories($categoryId);

// JSON formatında yanıt döndür
header('Content-Type: application/json');
echo json_encode($subcategories);
?>