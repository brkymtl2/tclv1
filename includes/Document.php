<?php
require_once 'config/db.php';
require_once 'includes/Security.php';
require_once 'includes/User.php';

class Document {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getPDO();
    }
    
    /**
     * Yeni belge ekler
     * @param string $title Belge başlığı
     * @param string $description Belge açıklaması
     * @param int $categoryId Kategori ID
     * @param int|null $subcategoryId Alt kategori ID (opsiyonel)
     * @param array $file Dosya bilgileri ($_FILES)
     * @param int $uploadedBy Yükleyen kullanıcı ID
     * @param array $tags Etiketler (opsiyonel)
     * @return int|false Belge ID veya hata durumunda false
     */
    public function uploadDocument($title, $description, $categoryId, $subcategoryId = null, $file, $uploadedBy, $tags = []) {
        try {
            // Dosya kontrolü
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                return false;
            }
            
            // Dosya tipini ve boyutunu kontrol et
            $fileType = $file['type'];
            $fileSize = $file['size'];
            $fileName = $file['name'];
            
            // Dosya adını güvenli hale getir
            $safeFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $fileName);
            
            // uploads klasörü yoksa oluştur
            if (!file_exists('uploads')) {
                mkdir('uploads', 0755, true);
                file_put_contents('uploads/index.php', '<?php // Silence is golden');
                file_put_contents('uploads/.htaccess', 'Deny from all');
            }
            
            // Şifreli dosya yolu
            $encryptedFilePath = 'uploads/' . $safeFileName . '.enc';
            
            // Dosyayı şifrele ve kaydet
            if (!Security::encryptFile($file['tmp_name'], $encryptedFilePath)) {
                return false;
            }
            
            // documents tablosunda subcategory_id sütunu kontrolü
            try {
                $this->pdo->query("SELECT subcategory_id FROM documents LIMIT 1");
            } catch (PDOException $e) {
                // Sütun yoksa oluştur
                $this->pdo->exec("
                    ALTER TABLE documents 
                    ADD COLUMN subcategory_id INT NULL,
                    ADD FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL
                ");
            }
            
            // Veritabanına kaydet
            $stmt = $this->pdo->prepare("
                INSERT INTO documents (title, description, category_id, subcategory_id, file_path, file_size, file_type, encrypted, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
            ");
            
            $stmt->execute([$title, $description, $categoryId, $subcategoryId, $encryptedFilePath, $fileSize, $fileType, $uploadedBy]);
            $documentId = $this->pdo->lastInsertId();
            
            // Etiketleri ekle
            if (!empty($tags)) {
                $this->addTagsToDocument($documentId, $tags);
            }
            
            return $documentId;
            
        } catch (PDOException $e) {
            error_log("Belge yüklenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belgeyi siler
     * @param int $documentId Belge ID
     * @return bool İşlem başarılı mı?
     */
    public function deleteDocument($documentId) {
        try {
            // Önce belge bilgilerini al
            $stmt = $this->pdo->prepare("SELECT file_path FROM documents WHERE id = ?");
            $stmt->execute([$documentId]);
            
            if ($stmt->rowCount() === 0) {
                return false;
            }
            
            $document = $stmt->fetch();
            
            // Dosyayı sil
            if (file_exists($document['file_path'])) {
                unlink($document['file_path']);
            }
            
            // İlişkili etiketleri sil
            $this->removeAllTagsFromDocument($documentId);
            
            // Veritabanından sil
            $stmt = $this->pdo->prepare("DELETE FROM documents WHERE id = ?");
            return $stmt->execute([$documentId]);
            
        } catch (PDOException $e) {
            error_log("Belge silinemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belge bilgilerini günceller (dosya hariç)
     * @param int $documentId Belge ID
     * @param array $data Güncellenecek veriler
     * @param array $tags Güncellenecek etiketler (opsiyonel)
     * @return bool İşlem başarılı mı?
     */
    public function updateDocument($documentId, $data, $tags = null) {
        try {
            $allowedFields = ['title', 'description', 'category_id', 'subcategory_id']; // Güncellenebilir alanlar
            $updates = [];
            $params = [];
            
            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $updates[] = "$field = ?";
                    $params[] = $value;
                }
            }
            
            // Güncellenecek alan yoksa ve etiket güncellemesi de yoksa
            if (empty($updates) && $tags === null) {
                return false;
            }
            
            // Belge bilgilerini güncelle
            if (!empty($updates)) {
                // Belge ID'sini parametre olarak ekle
                $params[] = $documentId;
                
                $stmt = $this->pdo->prepare("
                    UPDATE documents
                    SET " . implode(", ", $updates) . "
                    WHERE id = ?
                ");
                
                $stmt->execute($params);
            }
            
            // Etiketleri güncelle (eğer belirtilmişse)
            if ($tags !== null) {
                $this->removeAllTagsFromDocument($documentId);
                if (!empty($tags)) {
                    $this->addTagsToDocument($documentId, $tags);
                }
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Belge güncellenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belge dosyasını günceller
     * @param int $documentId Belge ID
     * @param array $file Yeni dosya bilgileri ($_FILES)
     * @return bool İşlem başarılı mı?
     */
    public function updateDocumentFile($documentId, $file) {
        try {
            // Dosya kontrolü
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                return false;
            }
            
            // Belge bilgilerini al
            $stmt = $this->pdo->prepare("SELECT file_path FROM documents WHERE id = ?");
            $stmt->execute([$documentId]);
            
            if ($stmt->rowCount() === 0) {
                return false;
            }
            
            $document = $stmt->fetch();
            
            // Eski dosyayı sil
            if (file_exists($document['file_path'])) {
                unlink($document['file_path']);
            }
            
            // Yeni dosya bilgileri
            $fileType = $file['type'];
            $fileSize = $file['size'];
            $fileName = $file['name'];
            
            // Dosya adını güvenli hale getir
            $safeFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $fileName);
            
            // Şifreli dosya yolu
            $encryptedFilePath = 'uploads/' . $safeFileName . '.enc';
            
            // Dosyayı şifrele ve kaydet
            if (!Security::encryptFile($file['tmp_name'], $encryptedFilePath)) {
                return false;
            }
            
            // Veritabanını güncelle
            $stmt = $this->pdo->prepare("
                UPDATE documents
                SET file_path = ?, file_size = ?, file_type = ?
                WHERE id = ?
            ");
            
            return $stmt->execute([$encryptedFilePath, $fileSize, $fileType, $documentId]);
            
        } catch (PDOException $e) {
            error_log("Belge dosyası güncellenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Tüm belgeleri getirir
     * @param int|null $categoryId Kategori filtresi (opsiyonel)
     * @param int|null $subcategoryId Alt kategori filtresi (opsiyonel)
     * @param int $limit Limit (opsiyonel)
     * @param int $offset Offset (opsiyonel)
     * @param string|null $tagName Etiket filtresi (opsiyonel)
     * @return array Belgeler listesi
     */
    public function getAllDocuments($categoryId = null, $subcategoryId = null, $limit = 100, $offset = 0, $tagName = null) {
        try {
            $params = [];
            
            // Etiket filtresi varsa, belgeleri etiketle filtrele
            if ($tagName !== null) {
                $query = "
                    SELECT d.*, c.name as category_name, u.username as uploaded_by_name
                    FROM documents d
                    JOIN categories c ON d.category_id = c.id
                    JOIN users u ON d.uploaded_by = u.id
                    JOIN document_tags dt ON d.id = dt.document_id
                    JOIN tags t ON dt.tag_id = t.id
                    WHERE t.name = ?
                ";
                $params[] = $tagName;
            } else {
                $query = "
                    SELECT d.*, c.name as category_name, u.username as uploaded_by_name
                    FROM documents d
                    JOIN categories c ON d.category_id = c.id
                    JOIN users u ON d.uploaded_by = u.id
                ";
            }
            
            $whereConditions = [];
            
            if ($categoryId !== null) {
                $whereConditions[] = "d.category_id = ?";
                $params[] = $categoryId;
            }
            
            if ($subcategoryId !== null) {
                // Alt kategori ID sütunu var mı kontrol et
                try {
                    $this->pdo->query("SELECT subcategory_id FROM documents LIMIT 1");
                    $whereConditions[] = "d.subcategory_id = ?";
                    $params[] = $subcategoryId;
                } catch (PDOException $e) {
                    // Sütun yoksa filtre eklenmiyor
                }
            }
            
            if (!empty($whereConditions)) {
                $query .= ($tagName !== null ? " AND " : " WHERE ") . implode(" AND ", $whereConditions);
            }
            
            if ($tagName !== null) {
                $query .= " GROUP BY d.id";
            }
            
            $query .= " ORDER BY d.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            $results = $stmt->fetchAll();
            
            // Alt kategori bilgisini ekle (eğer varsa)
            if (!empty($results)) {
                // Subcategories tablosu var mı kontrol et
                try {
                    $this->pdo->query("SELECT 1 FROM subcategories LIMIT 1");
                    
                    // Alt kategori adlarını al
                    $subcategoryIds = array_filter(array_column($results, 'subcategory_id'));
                    
                    if (!empty($subcategoryIds)) {
                        $placeholders = implode(',', array_fill(0, count($subcategoryIds), '?'));
                        $stmt = $this->pdo->prepare("
                            SELECT id, name FROM subcategories 
                            WHERE id IN ($placeholders)
                        ");
                        $stmt->execute($subcategoryIds);
                        $subcategories = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                        
                        // Alt kategori adlarını ekle
                        foreach ($results as &$result) {
                            if (!empty($result['subcategory_id']) && isset($subcategories[$result['subcategory_id']])) {
                                $result['subcategory_name'] = $subcategories[$result['subcategory_id']];
                            } else {
                                $result['subcategory_name'] = null;
                            }
                        }
                    }
                } catch (PDOException $e) {
                    // Tablo yoksa, alt kategori adı eklenmez
                }
            }
            
            // Belgelerin etiketlerini ekle
            if (!empty($results)) {
                $this->addTagsToResults($results);
            }
            
            return $results;
            
        } catch (PDOException $e) {
            error_log("Belgeler getirilemedi: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Belirli bir belgeyi getirir
     * @param int $documentId Belge ID
     * @return array|false Belge bilgileri veya hata durumunda false
     */
    public function getDocument($documentId) {
        try {
            $query = "
                SELECT d.*, c.name as category_name, u.username as uploaded_by_name
                FROM documents d
                JOIN categories c ON d.category_id = c.id
                JOIN users u ON d.uploaded_by = u.id
                WHERE d.id = ?
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$documentId]);
            
            if ($stmt->rowCount() === 0) {
                return false;
            }
            
            $result = $stmt->fetch();
            
            // Alt kategori bilgisi varsa ekle
            if (!empty($result['subcategory_id'])) {
                try {
                    $stmt = $this->pdo->prepare("
                        SELECT name FROM subcategories WHERE id = ?
                    ");
                    $stmt->execute([$result['subcategory_id']]);
                    if ($stmt->rowCount() > 0) {
                        $result['subcategory_name'] = $stmt->fetch()['name'];
                    }
                } catch (PDOException $e) {
                    // Alt kategori bilgisi alınamazsa, null olarak kalır
                    $result['subcategory_name'] = null;
                }
            } else {
                $result['subcategory_name'] = null;
            }
            
            // Belgenin etiketlerini ekle
            $result['tags'] = $this->getDocumentTags($documentId);
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Belge getirilemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belgeyi indirilecek formatta getirir
     * @param int $documentId Belge ID
     * @param string $tempPath Geçici dosya yolu
     * @return array|false Dosya bilgileri veya hata durumunda false
     */
    public function downloadDocument($documentId, $tempPath = 'temp') {
        try {
            // Belge bilgilerini al
            $document = $this->getDocument($documentId);
            
            if (!$document) {
                return false;
            }
            
            // Geçici klasör yoksa oluştur
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
                file_put_contents($tempPath . '/index.php', '<?php // Silence is golden');
                file_put_contents($tempPath . '/.htaccess', 'Deny from all');
            }
            
            // Orijinal dosya adını ayıkla
            $originalFileName = pathinfo($document['file_path'], PATHINFO_FILENAME);
            $originalFileName = preg_replace('/^\d+_/', '', $originalFileName); // Zaman damgasını kaldır
            
            // Orijinal dosya uzantısını belirleme
            $extension = '';
            // MIME tipine göre yaygın dosya uzantılarını belirle
            $mimeToExt = [
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.ms-excel' => 'xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.ms-powerpoint' => 'ppt',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'text/plain' => 'txt'
            ];
            
            if (isset($mimeToExt[$document['file_type']])) {
                $extension = '.' . $mimeToExt[$document['file_type']];
            }
            
            // Geçici dosya yolu
            $tempFilePath = $tempPath . '/' . uniqid() . '_' . $originalFileName . $extension;
            
            // Dosyayı çöz
            if (!Security::decryptFile($document['file_path'], $tempFilePath)) {
                return false;
            }
            
            return [
                'path' => $tempFilePath,
                'name' => $originalFileName . $extension,
                'type' => $document['file_type'],
                'size' => $document['file_size']
            ];
            
        } catch (PDOException $e) {
            error_log("Belge indirilemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belge sayısını döndürür
     * @param int|null $categoryId Kategori filtresi (opsiyonel)
     * @param int|null $subcategoryId Alt kategori filtresi (opsiyonel)
     * @param string|null $tagName Etiket filtresi (opsiyonel)
     * @return int Belge sayısı
     */
    public function getDocumentCount($categoryId = null, $subcategoryId = null, $tagName = null) {
        try {
            $params = [];
            
            if ($tagName !== null) {
                $query = "
                    SELECT COUNT(DISTINCT d.id) as count 
                    FROM documents d
                    JOIN document_tags dt ON d.id = dt.document_id
                    JOIN tags t ON dt.tag_id = t.id
                    WHERE t.name = ?
                ";
                $params[] = $tagName;
            } else {
                $query = "SELECT COUNT(*) as count FROM documents";
            }
            
            $whereConditions = [];
            
            if ($categoryId !== null) {
                $whereConditions[] = "d.category_id = ?";
                $params[] = $categoryId;
            }
            
            if ($subcategoryId !== null) {
                // Alt kategori ID sütunu var mı kontrol et
                try {
                    $this->pdo->query("SELECT subcategory_id FROM documents LIMIT 1");
                    $whereConditions[] = "d.subcategory_id = ?";
                    $params[] = $subcategoryId;
                } catch (PDOException $e) {
                    // Sütun yoksa filtre eklenmiyor
                }
            }
            
            if (!empty($whereConditions)) {
                $query .= ($tagName !== null ? " AND " : " WHERE ") . implode(" AND ", $whereConditions);
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetch()['count'];
            
        } catch (PDOException $e) {
            error_log("Belge sayısı alınamadı: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Belgeleri arar
     * @param string $searchTerm Arama terimi
     * @param int|null $categoryId Kategori filtresi (opsiyonel)
     * @param int|null $subcategoryId Alt kategori filtresi (opsiyonel)
     * @param string|null $tagName Etiket filtresi (opsiyonel)
     * @return array Arama sonuçları
     */
    public function searchDocuments($searchTerm, $categoryId = null, $subcategoryId = null, $tagName = null) {
        try {
            $params = [];
            
            if ($tagName !== null) {
                $query = "
                    SELECT DISTINCT d.*, c.name as category_name, u.username as uploaded_by_name
                    FROM documents d
                    JOIN categories c ON d.category_id = c.id
                    JOIN users u ON d.uploaded_by = u.id
                    JOIN document_tags dt ON d.id = dt.document_id
                    JOIN tags t ON dt.tag_id = t.id
                    WHERE t.name = ? AND (d.title LIKE ? OR d.description LIKE ?)
                ";
                $params[] = $tagName;
                $params[] = "%$searchTerm%";
                $params[] = "%$searchTerm%";
            } else {
                $query = "
                    SELECT d.*, c.name as category_name, u.username as uploaded_by_name
                    FROM documents d
                    JOIN categories c ON d.category_id = c.id
                    JOIN users u ON d.uploaded_by = u.id
                    WHERE (d.title LIKE ? OR d.description LIKE ?)
                ";
                $params[] = "%$searchTerm%";
                $params[] = "%$searchTerm%";
            }
            
            if ($categoryId !== null) {
                $query .= " AND d.category_id = ?";
                $params[] = $categoryId;
            }
            
            if ($subcategoryId !== null) {
                // Alt kategori ID sütunu var mı kontrol et
                try {
                    $this->pdo->query("SELECT subcategory_id FROM documents LIMIT 1");
                    $query .= " AND d.subcategory_id = ?";
                    $params[] = $subcategoryId;
                } catch (PDOException $e) {
                    // Sütun yoksa filtre eklenmiyor
                }
            }
            
            $query .= " ORDER BY d.created_at DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            $results = $stmt->fetchAll();
            
            // Alt kategori bilgilerini ekle (eğer varsa)
            if (!empty($results)) {
                try {
                    $this->pdo->query("SELECT 1 FROM subcategories LIMIT 1");
                    
                    // Alt kategori adlarını al
                    $subcategoryIds = array_filter(array_column($results, 'subcategory_id'));
                    
                    if (!empty($subcategoryIds)) {
                        $placeholders = implode(',', array_fill(0, count($subcategoryIds), '?'));
                        $stmt = $this->pdo->prepare("
                            SELECT id, name FROM subcategories 
                            WHERE id IN ($placeholders)
                        ");
                        $stmt->execute($subcategoryIds);
                        $subcategories = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                        
                        // Alt kategori adlarını ekle
                        foreach ($results as &$result) {
                            if (!empty($result['subcategory_id']) && isset($subcategories[$result['subcategory_id']])) {
                                $result['subcategory_name'] = $subcategories[$result['subcategory_id']];
                            } else {
                                $result['subcategory_name'] = null;
                            }
                        }
                    }
                } catch (PDOException $e) {
                    // Tablo yoksa, alt kategori adı eklenmez
                }
            }
            
            // Belgelerin etiketlerini ekle
            if (!empty($results)) {
                $this->addTagsToResults($results);
            }
            
            return $results;
            
        } catch (PDOException $e) {
            error_log("Belge araması yapılamadı: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Belgeyi görüntüleme için hazırlar
     * @param int $documentId Belge ID
     * @param string $tempPath Geçici dosya yolu
     * @return array|false Dosya bilgileri veya hata durumunda false
     */
    public function viewDocument($documentId, $tempPath = 'temp') {
        // İndirme fonksiyonu ile aynı işlemi yapar, ancak farklı amaç için
        return $this->downloadDocument($documentId, $tempPath);
    }
    
    /**
     * Etiket ekler veya mevcut etiketi döndürür
     * @param string $tagName Etiket adı
     * @return int Etiket ID
     */
    private function addTag($tagName) {
        try {
            // Önce tags tablosunun varlığını kontrol et, yoksa oluştur
            $this->checkTagsTable();
            
            // Etiketin zaten var olup olmadığını kontrol et
            $stmt = $this->pdo->prepare("SELECT id FROM tags WHERE name = ?");
            $stmt->execute([$tagName]);
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch()['id'];
            }
            
            // Yeni etiket ekle
            $stmt = $this->pdo->prepare("INSERT INTO tags (name) VALUES (?)");
            $stmt->execute([$tagName]);
            
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("Etiket eklenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belgeye etiketler ekler
     * @param int $documentId Belge ID
     * @param array $tags Etiketler
     * @return bool İşlem başarılı mı?
     */
    public function addTagsToDocument($documentId, $tags) {
        try {
            // Önce document_tags tablosunun varlığını kontrol et, yoksa oluştur
            $this->checkDocumentTagsTable();
            
            // Belgenin var olup olmadığını kontrol et
            $stmt = $this->pdo->prepare("SELECT id FROM documents WHERE id = ?");
            $stmt->execute([$documentId]);
            
            if ($stmt->rowCount() === 0) {
                return false;
            }
            
            foreach ($tags as $tagName) {
                $tagId = $this->addTag($tagName);
                
                if ($tagId) {
                    // Bu etiket bu belgeye daha önce eklendi mi?
                    $stmt = $this->pdo->prepare("
                        SELECT 1 FROM document_tags 
                        WHERE document_id = ? AND tag_id = ?
                    ");
                    $stmt->execute([$documentId, $tagId]);
                    
                    if ($stmt->rowCount() === 0) {
                        // Etiket-belge ilişkisini ekle
                        $stmt = $this->pdo->prepare("
                            INSERT INTO document_tags (document_id, tag_id)
                            VALUES (?, ?)
                        ");
                        $stmt->execute([$documentId, $tagId]);
                    }
                }
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Belgeye etiket eklenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belgeden belirli bir etiketi kaldırır
     * @param int $documentId Belge ID
     * @param string $tagName Etiket adı
     * @return bool İşlem başarılı mı?
     */
    public function removeTagFromDocument($documentId, $tagName) {
        try {
            // Etiket ID'sini bul
            $stmt = $this->pdo->prepare("SELECT id FROM tags WHERE name = ?");
            $stmt->execute([$tagName]);
            
            if ($stmt->rowCount() === 0) {
                return false; // Etiket bulunamadı
            }
            
            $tagId = $stmt->fetch()['id'];
            
            // Etiket-belge ilişkisini kaldır
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
            // document_tags tablosunun varlığını kontrol et
            try {
                $this->pdo->query("SELECT 1 FROM document_tags LIMIT 1");
            } catch (PDOException $e) {
                // Tablo yoksa oluşturulması gerekir, ancak silme işlemi için gerekli değil
                return true; // Tablo yoksa, silinecek etiket de yoktur
            }
            
            // Tüm etiket ilişkilerini kaldır
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
            // document_tags tablosunun varlığını kontrol et
            try {
                $this->pdo->query("SELECT 1 FROM document_tags LIMIT 1");
            } catch (PDOException $e) {
                // Tablo yoksa boş dizi döndür
                return [];
            }
            
            $stmt = $this->pdo->prepare("
                SELECT t.name 
                FROM tags t
                JOIN document_tags dt ON t.id = dt.tag_id
                WHERE dt.document_id = ?
                ORDER BY t.name
            ");
            
            $stmt->execute([$documentId]);
            return array_column($stmt->fetchAll(), 'name');
            
        } catch (PDOException $e) {
            error_log("Belge etiketleri alınamadı: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Tüm etiketleri getirir
     * @return array Etiketler listesi
     */
    public function getAllTags() {
        try {
            // tags tablosunun varlığını kontrol et
            try {
                $this->pdo->query("SELECT 1 FROM tags LIMIT 1");
            } catch (PDOException $e) {
                // Tablo yoksa boş dizi döndür
                return [];
            }
            
            $stmt = $this->pdo->prepare("
                SELECT t.name, COUNT(dt.document_id) as count
                FROM tags t
                LEFT JOIN document_tags dt ON t.id = dt.tag_id
                GROUP BY t.id, t.name
                ORDER BY count DESC, t.name
            ");
            
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Etiketler alınamadı: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Sonuç dizisine etiketleri ekler
     * @param array &$results Belge sonuçları
     */
    private function addTagsToResults(&$results) {
        if (empty($results)) {
            return;
        }
        
        try {
            // document_tags tablosunun varlığını kontrol et
            try {
                $this->pdo->query("SELECT 1 FROM document_tags LIMIT 1");
            } catch (PDOException $e) {
                // Tablo yoksa işlem yapma
                return;
            }
            
            // Belge ID'lerini al
            $documentIds = array_column($results, 'id');
            $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
            
            // Tüm belgelerin etiketlerini tek sorguda al
            $stmt = $this->pdo->prepare("
                SELECT dt.document_id, t.name
                FROM document_tags dt
                JOIN tags t ON dt.tag_id = t.id
                WHERE dt.document_id IN ($placeholders)
                ORDER BY t.name
            ");
            
            $stmt->execute($documentIds);
            $allTags = $stmt->fetchAll();
            
            // Belge ID'sine göre etiketleri grupla
            $tagsByDocument = [];
            foreach ($allTags as $tag) {
                $tagsByDocument[$tag['document_id']][] = $tag['name'];
            }
            
            // Her belgenin sonucuna etiketleri ekle
            foreach ($results as &$result) {
                $result['tags'] = isset($tagsByDocument[$result['id']]) ? $tagsByDocument[$result['id']] : [];
            }
            
        } catch (PDOException $e) {
            error_log("Sonuçlara etiketler eklenemedi: " . $e->getMessage());
        }
    }
    
    /**
     * Bir etiketle ilişkili tüm belgeleri getirir
     * @param string $tagName Etiket adı
     * @param int $limit Limit (opsiyonel)
     * @param int $offset Offset (opsiyonel)
     * @return array Belgeler listesi
     */
    public function getDocumentsByTag($tagName, $limit = 100, $offset = 0) {
        return $this->getAllDocuments(null, null, $limit, $offset, $tagName);
    }
    
    /**
     * tags tablosunu kontrol eder ve yoksa oluşturur
     */
    private function checkTagsTable() {
        try {
            $this->pdo->query("SELECT 1 FROM tags LIMIT 1");
        } catch (PDOException $e) {
            // Tablo yoksa oluştur
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS tags (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) UNIQUE NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB;
            ");
        }
    }
    
    /**
     * document_tags tablosunu kontrol eder ve yoksa oluşturur
     */
    private function checkDocumentTagsTable() {
        try {
            $this->pdo->query("SELECT 1 FROM document_tags LIMIT 1");
        } catch (PDOException $e) {
            // Tablo yoksa oluştur
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS document_tags (
                    document_id INT NOT NULL,
                    tag_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (document_id, tag_id),
                    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
                    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
                ) ENGINE=InnoDB;
            ");
        }
    }
}