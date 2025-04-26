<?php
require_once 'config/db.php';

class Tag {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getPDO();
        
        // Etiket tablolarını kontrol et, yoksa oluştur
        $this->createTagTablesIfNotExist();
    }
    
    /**
     * Etiket tablolarını kontrol eder ve yoksa oluşturur
     */
    private function createTagTablesIfNotExist() {
        try {
            // Etiketler tablosu
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS tags (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(50) NOT NULL UNIQUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Belge-Etiket ilişki tablosu
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS document_tags (
                    document_id INT NOT NULL,
                    tag_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (document_id, tag_id),
                    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
                    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
                )
            ");
        } catch (PDOException $e) {
            error_log("Etiket tabloları oluşturulamadı: " . $e->getMessage());
        }
    }
    
    /**
     * Yeni bir etiket ekler
     * @param string $name Etiket adı
     * @return int|false Eklenen etiket ID'si veya hata durumunda false
     */
    public function addTag($name) {
        try {
            // Önce etiketin var olup olmadığını kontrol et
            $stmt = $this->pdo->prepare("SELECT id FROM tags WHERE name = ?");
            $stmt->execute([strtolower($name)]);
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch()['id']; // Etiket zaten varsa ID'sini döndür
            }
            
            // Etiket yoksa ekle
            $stmt = $this->pdo->prepare("INSERT INTO tags (name) VALUES (?)");
            $stmt->execute([strtolower($name)]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Etiket eklenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belgeye etiket ekler
     * @param int $documentId Belge ID
     * @param int $tagId Etiket ID
     * @return bool İşlem başarılı mı?
     */
    public function addTagToDocument($documentId, $tagId) {
        try {
            // Önce ilişkinin var olup olmadığını kontrol et
            $stmt = $this->pdo->prepare("
                SELECT 1 FROM document_tags 
                WHERE document_id = ? AND tag_id = ?
            ");
            $stmt->execute([$documentId, $tagId]);
            
            if ($stmt->rowCount() > 0) {
                return true; // İlişki zaten var
            }
            
            // İlişki yoksa ekle
            $stmt = $this->pdo->prepare("
                INSERT INTO document_tags (document_id, tag_id) 
                VALUES (?, ?)
            ");
            
            return $stmt->execute([$documentId, $tagId]);
        } catch (PDOException $e) {
            error_log("Belgeye etiket eklenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belgeye birden fazla etiket ekler (virgülle ayrılmış)
     * @param int $documentId Belge ID
     * @param string $tagsString Virgülle ayrılmış etiketler
     * @return array Eklenen etiket ID'leri
     */
    public function addTagsToDocument($documentId, $tagsString) {
        $tagIds = [];
        
        // Boş string kontrolü
        if (empty($tagsString)) {
            return $tagIds;
        }
        
        // Etiketleri ayır ve temizle
        $tagNames = array_map('trim', explode(',', $tagsString));
        
        foreach ($tagNames as $tagName) {
            if (empty($tagName)) {
                continue;
            }
            
            // Etiket ekle veya var olanı al
            $tagId = $this->addTag($tagName);
            
            if ($tagId) {
                // Belgeye etiket ilişkisi ekle
                $this->addTagToDocument($documentId, $tagId);
                $tagIds[] = $tagId;
            }
        }
        
        return $tagIds;
    }
    
    /**
     * Belgeden etiketi kaldırır
     * @param int $documentId Belge ID
     * @param int $tagId Etiket ID
     * @return bool İşlem başarılı mı?
     */
    public function removeTagFromDocument($documentId, $tagId) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM document_tags 
                WHERE document_id = ? AND tag_id = ?
            ");
            
            return $stmt->execute([$documentId, $tagId]);
        } catch (PDOException $e) {
            error_log("Belgeden etiket kaldırılamadı: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belgeden tüm etiketleri kaldırır
     * @param int $documentId Belge ID
     * @return bool İşlem başarılı mı?
     */
    public function removeAllTagsFromDocument($documentId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM document_tags WHERE document_id = ?");
            return $stmt->execute([$documentId]);
        } catch (PDOException $e) {
            error_log("Belgeden tüm etiketler kaldırılamadı: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belgenin etiketlerini getirir
     * @param int $documentId Belge ID
     * @return array Etiketler listesi
     */
    public function getDocumentTags($documentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT t.id, t.name
                FROM tags t
                JOIN document_tags dt ON t.id = dt.tag_id
                WHERE dt.document_id = ?
                ORDER BY t.name
            ");
            
            $stmt->execute([$documentId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Belge etiketleri getirilemedi: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Belgenin etiketlerini virgülle ayrılmış string olarak getirir
     * @param int $documentId Belge ID
     * @return string Virgülle ayrılmış etiketler
     */
    public function getDocumentTagsString($documentId) {
        $tags = $this->getDocumentTags($documentId);
        $tagNames = array_column($tags, 'name');
        return implode(', ', $tagNames);
    }
    
    /**
     * Tüm etiketleri getirir
     * @return array Etiketler listesi
     */
    public function getAllTags() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM tags ORDER BY name");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Etiketler getirilemedi: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * En çok kullanılan etiketleri getirir
     * @param int $limit Limit
     * @return array Etiketler listesi (kullanım sayısı ile birlikte)
     */
    public function getPopularTags($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT t.id, t.name, COUNT(dt.document_id) as usage_count
                FROM tags t
                JOIN document_tags dt ON t.id = dt.tag_id
                GROUP BY t.id
                ORDER BY usage_count DESC, t.name
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Popüler etiketler getirilemedi: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Etikete göre belgeleri getirir
     * @param int $tagId Etiket ID
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Belgeler listesi
     */
    public function getDocumentsByTag($tagId, $limit = 100, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT d.*, c.name as category_name, u.username as uploaded_by_name
                FROM documents d
                JOIN categories c ON d.category_id = c.id
                JOIN users u ON d.uploaded_by = u.id
                JOIN document_tags dt ON d.id = dt.document_id
                WHERE dt.tag_id = ?
                ORDER BY d.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->execute([$tagId, $limit, $offset]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Etiket belgeleri getirilemedi: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Etiket ile ilişkili belge sayısını getirir
     * @param int $tagId Etiket ID
     * @return int Belge sayısı
     */
    public function getDocumentCountByTag($tagId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM document_tags
                WHERE tag_id = ?
            ");
            
            $stmt->execute([$tagId]);
            return $stmt->fetch()['count'];
        } catch (PDOException $e) {
            error_log("Etiket belge sayısı getirilemedi: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Etiketi siler (eğer hiçbir belge ile ilişkisi yoksa)
     * @param int $tagId Etiket ID
     * @return bool İşlem başarılı mı?
     */
    public function deleteTag($tagId) {
        try {
            // Önce etiketin kullanımda olup olmadığını kontrol et
            $count = $this->getDocumentCountByTag($tagId);
            
            if ($count > 0) {
                return false; // Etiket kullanımda, silinemez
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM tags WHERE id = ?");
            return $stmt->execute([$tagId]);
        } catch (PDOException $e) {
            error_log("Etiket silinemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Etiketi günceller
     * @param int $tagId Etiket ID
     * @param string $newName Yeni etiket adı
     * @return bool İşlem başarılı mı?
     */
    public function updateTag($tagId, $newName) {
        try {
            // Önce aynı isimde başka bir etiket var mı kontrol et
            $stmt = $this->pdo->prepare("
                SELECT id FROM tags
                WHERE name = ? AND id != ?
            ");
            $stmt->execute([strtolower($newName), $tagId]);
            
            if ($stmt->rowCount() > 0) {
                return false; // Aynı isimde başka bir etiket var
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE tags
                SET name = ?
                WHERE id = ?
            ");
            
            return $stmt->execute([strtolower($newName), $tagId]);
        } catch (PDOException $e) {
            error_log("Etiket güncellenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Etiket araması yapar
     * @param string $term Arama terimi
     * @param int $limit Limit
     * @return array Etiketler listesi
     */
    public function searchTags($term, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name
                FROM tags
                WHERE name LIKE ?
                ORDER BY name
                LIMIT ?
            ");
            
            $stmt->execute(["%$term%", $limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Etiket araması yapılamadı: " . $e->getMessage());
            return [];
        }
    }
}
?>