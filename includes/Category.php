<?php
require_once 'config/db.php';

class Category {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getPDO();
    }
    
    /**
     * Yeni kategori oluşturur
     * @param string $name Kategori adı
     * @param string $description Kategori açıklaması
     * @return int|false Oluşturulan kategorinin ID'si veya hata durumunda false
     */
    public function createCategory($name, $description = '') {
        try {
            // Kategori adı kontrolü
            $stmt = $this->pdo->prepare("SELECT id FROM categories WHERE name = ?");
            $stmt->execute([$name]);
            
            if ($stmt->rowCount() > 0) {
                return false; // Kategori adı zaten mevcut
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO categories (name, description)
                VALUES (?, ?)
            ");
            
            $stmt->execute([$name, $description]);
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("Kategori oluşturulamadı: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Tüm kategorileri getirir
     * @return array Kategoriler listesi
     */
    public function getAllCategories() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY name");
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Kategoriler getirilemedi: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Belirli bir kategoriyi getirir
     * @param int $categoryId Kategori ID
     * @return array|false Kategori bilgileri veya hata durumunda false
     */
    public function getCategory($categoryId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt->execute([$categoryId]);
            
            if ($stmt->rowCount() === 0) {
                return false;
            }
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Kategori getirilemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kategori bilgilerini günceller
     * @param int $categoryId Kategori ID
     * @param string $name Yeni kategori adı
     * @param string $description Yeni kategori açıklaması
     * @return bool İşlem başarılı mı?
     */
    public function updateCategory($categoryId, $name, $description = '') {
        try {
            // Kategori adı benzersiz mi?
            $stmt = $this->pdo->prepare("
                SELECT id FROM categories
                WHERE name = ? AND id != ?
            ");
            $stmt->execute([$name, $categoryId]);
            
            if ($stmt->rowCount() > 0) {
                return false; // Başka bir kategori aynı ada sahip
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE categories
                SET name = ?, description = ?
                WHERE id = ?
            ");
            
            return $stmt->execute([$name, $description, $categoryId]);
            
        } catch (PDOException $e) {
            error_log("Kategori güncellenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kategori siler
     * @param int $categoryId Kategori ID
     * @return bool İşlem başarılı mı?
     */
    public function deleteCategory($categoryId) {
        try {
            // Önce bu kategoriye ait belge var mı kontrol et
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM documents WHERE category_id = ?");
            $stmt->execute([$categoryId]);
            $documentCount = $stmt->fetch()['count'];
            
            if ($documentCount > 0) {
                return false; // Kategori içinde belge varsa silinemez
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id = ?");
            return $stmt->execute([$categoryId]);
            
        } catch (PDOException $e) {
            error_log("Kategori silinemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kategori altında alt kategori (tablo) oluşturur
     * @param int $parentCategoryId Üst kategori ID
     * @param string $name Alt kategori adı
     * @param string $description Alt kategori açıklaması
     * @return int|false Oluşturulan alt kategorinin ID'si veya hata durumunda false
     */
    public function createSubCategory($parentCategoryId, $name, $description = '') {
        try {
            // Önce üst kategori var mı kontrol et
            $parentCategory = $this->getCategory($parentCategoryId);
            if (!$parentCategory) {
                return false; // Üst kategori bulunamadı
            }
            
            // Alt kategori adı kontrolü
            $stmt = $this->pdo->prepare("
                SELECT id FROM subcategories 
                WHERE parent_category_id = ? AND name = ?
            ");
            $stmt->execute([$parentCategoryId, $name]);
            
            if ($stmt->rowCount() > 0) {
                return false; // Alt kategori adı zaten mevcut
            }
            
            // Alt kategori tablosunu kontrol et, yoksa oluştur
            $checkTableQuery = "
                CREATE TABLE IF NOT EXISTS subcategories (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    parent_category_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (parent_category_id) REFERENCES categories(id) ON DELETE CASCADE,
                    UNIQUE KEY parent_name (parent_category_id, name)
                )
            ";
            $this->pdo->exec($checkTableQuery);
            
            // Alt kategoriyi ekle
            $stmt = $this->pdo->prepare("
                INSERT INTO subcategories (parent_category_id, name, description)
                VALUES (?, ?, ?)
            ");
            
            $stmt->execute([$parentCategoryId, $name, $description]);
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("Alt kategori oluşturulamadı: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belirli bir kategoriye ait tüm alt kategorileri getirir
     * @param int $parentCategoryId Üst kategori ID
     * @return array Alt kategoriler listesi
     */
    public function getSubCategories($parentCategoryId) {
        try {
            // Alt kategori tablosunu kontrol et
            $checkTableQuery = "
                SELECT 1 FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = 'subcategories'
            ";
            $tableExists = $this->pdo->query($checkTableQuery)->rowCount() > 0;
            
            if (!$tableExists) {
                return []; // Tablo yoksa boş dizi döndür
            }
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM subcategories
                WHERE parent_category_id = ?
                ORDER BY name
            ");
            $stmt->execute([$parentCategoryId]);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Alt kategoriler getirilemedi: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Alt kategori bilgilerini günceller
     * @param int $subCategoryId Alt kategori ID
     * @param string $name Yeni alt kategori adı
     * @param string $description Yeni alt kategori açıklaması
     * @return bool İşlem başarılı mı?
     */
    public function updateSubCategory($subCategoryId, $name, $description = '') {
        try {
            // Önce alt kategoriyi al
            $stmt = $this->pdo->prepare("
                SELECT parent_category_id FROM subcategories
                WHERE id = ?
            ");
            $stmt->execute([$subCategoryId]);
            
            if ($stmt->rowCount() === 0) {
                return false; // Alt kategori bulunamadı
            }
            
            $subCategory = $stmt->fetch();
            $parentCategoryId = $subCategory['parent_category_id'];
            
            // Alt kategori adı benzersiz mi?
            $stmt = $this->pdo->prepare("
                SELECT id FROM subcategories
                WHERE parent_category_id = ? AND name = ? AND id != ?
            ");
            $stmt->execute([$parentCategoryId, $name, $subCategoryId]);
            
            if ($stmt->rowCount() > 0) {
                return false; // Başka bir alt kategori aynı ada sahip
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE subcategories
                SET name = ?, description = ?
                WHERE id = ?
            ");
            
            return $stmt->execute([$name, $description, $subCategoryId]);
            
        } catch (PDOException $e) {
            error_log("Alt kategori güncellenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Alt kategori siler
     * @param int $subCategoryId Alt kategori ID
     * @return bool İşlem başarılı mı?
     */
    public function deleteSubCategory($subCategoryId) {
        try {
            // Önce alt kategoriye ait belge var mı kontrol et
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count FROM documents 
                WHERE subcategory_id = ?
            ");
            
            // documents tablosunda subcategory_id sütunu yoksa, oluştur
            try {
                $this->pdo->query("
                    SELECT subcategory_id FROM documents LIMIT 1
                ");
            } catch (PDOException $e) {
                // Sütun yoksa oluştur
                $this->pdo->exec("
                    ALTER TABLE documents 
                    ADD COLUMN subcategory_id INT NULL,
                    ADD FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL
                ");
            }
            
            $stmt->execute([$subCategoryId]);
            $documentCount = $stmt->fetch()['count'];
            
            if ($documentCount > 0) {
                return false; // Alt kategori içinde belge varsa silinemez
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM subcategories WHERE id = ?");
            return $stmt->execute([$subCategoryId]);
            
        } catch (PDOException $e) {
            error_log("Alt kategori silinemedi: " . $e->getMessage());
            return false;
        }
    }
}
?>