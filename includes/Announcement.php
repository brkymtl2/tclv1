<?php
require_once 'config/db.php';

class Announcement {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getPDO();
        
        // Duyurular tablosunu kontrol et, yoksa oluştur
        $this->createAnnouncementsTableIfNotExists();
    }
    
    /**
     * Duyurular tablosunu kontrol eder ve yoksa oluşturur
     */
    private function createAnnouncementsTableIfNotExists() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS announcements (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    content TEXT NOT NULL,
                    created_by INT NOT NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    start_date DATETIME NOT NULL,
                    end_date DATETIME,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
        } catch (PDOException $e) {
            error_log("Duyurular tablosu oluşturulamadı: " . $e->getMessage());
        }
    }
    
    /**
     * Yeni bir duyuru ekler
     * @param string $title Duyuru başlığı
     * @param string $content Duyuru içeriği
     * @param int $createdBy Oluşturan kullanıcı ID
     * @param string $startDate Başlangıç tarihi (YYYY-MM-DD HH:MM:SS formatında)
     * @param string|null $endDate Bitiş tarihi (opsiyonel, YYYY-MM-DD HH:MM:SS formatında)
     * @param bool $isActive Duyuru aktif mi?
     * @return int|false Eklenen duyuru ID'si veya hata durumunda false
     */
    public function addAnnouncement($title, $content, $createdBy, $startDate, $endDate = null, $isActive = true) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO announcements (title, content, created_by, is_active, start_date, end_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([$title, $content, $createdBy, $isActive, $startDate, $endDate]);
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("Duyuru eklenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Duyuru günceller
     * @param int $id Duyuru ID
     * @param array $data Güncellenecek veriler
     * @return bool İşlem başarılı mı?
     */
    public function updateAnnouncement($id, $data) {
        try {
            $allowedFields = ['title', 'content', 'is_active', 'start_date', 'end_date']; // Güncellenebilir alanlar
            $updates = [];
            $params = [];
            
            foreach ($data as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $updates[] = "$field = ?";
                    $params[] = $value;
                }
            }
            
            // Güncellenecek alan yoksa
            if (empty($updates)) {
                return false;
            }
            
            // Duyuru ID'sini parametre olarak ekle
            $params[] = $id;
            
            $stmt = $this->pdo->prepare("
                UPDATE announcements
                SET " . implode(", ", $updates) . "
                WHERE id = ?
            ");
            
            return $stmt->execute($params);
            
        } catch (PDOException $e) {
            error_log("Duyuru güncellenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Duyuru siler
     * @param int $id Duyuru ID
     * @return bool İşlem başarılı mı?
     */
    public function deleteAnnouncement($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM announcements WHERE id = ?");
            return $stmt->execute([$id]);
            
        } catch (PDOException $e) {
            error_log("Duyuru silinemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belirli bir duyuruyu getirir
     * @param int $id Duyuru ID
     * @return array|false Duyuru verileri veya hata durumunda false
     */
    public function getAnnouncement($id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT a.*, u.username as created_by_name
                FROM announcements a
                JOIN users u ON a.created_by = u.id
                WHERE a.id = ?
            ");
            
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() === 0) {
                return false;
            }
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Duyuru getirilemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Tüm duyuruları getirir
     * @param bool $activeOnly Sadece aktif duyuruları getir
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Duyurular listesi
     */
    public function getAllAnnouncements($activeOnly = false, $limit = 100, $offset = 0) {
        try {
            $query = "
                SELECT a.*, u.username as created_by_name
                FROM announcements a
                JOIN users u ON a.created_by = u.id
            ";
            
            $params = [];
            
            if ($activeOnly) {
                $currentDate = date('Y-m-d H:i:s');
                $query .= " WHERE a.is_active = 1 
                            AND a.start_date <= ?
                            AND (a.end_date IS NULL OR a.end_date >= ?)";
                $params[] = $currentDate;
                $params[] = $currentDate;
            }
            
            $query .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Duyurular getirilemedi: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Toplam duyuru sayısını döndürür
     * @param bool $activeOnly Sadece aktif duyuruları say
     * @return int Duyuru sayısı
     */
    public function getAnnouncementCount($activeOnly = false) {
        try {
            $query = "SELECT COUNT(*) as count FROM announcements";
            $params = [];
            
            if ($activeOnly) {
                $currentDate = date('Y-m-d H:i:s');
                $query .= " WHERE is_active = 1 
                            AND start_date <= ?
                            AND (end_date IS NULL OR end_date >= ?)";
                $params[] = $currentDate;
                $params[] = $currentDate;
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetch()['count'];
            
        } catch (PDOException $e) {
            error_log("Duyuru sayısı alınamadı: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Aktif duyuruları getirir (header için)
     * @param int $limit Gösterilecek duyuru sayısı
     * @return array Aktif duyurular
     */
    public function getActiveAnnouncements($limit = 5) {
        try {
            $currentDate = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("
                SELECT a.*, u.username as created_by_name
                FROM announcements a
                JOIN users u ON a.created_by = u.id
                WHERE a.is_active = 1 
                AND a.start_date <= ?
                AND (a.end_date IS NULL OR a.end_date >= ?)
                ORDER BY a.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$currentDate, $currentDate, $limit]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Aktif duyurular getirilemedi: " . $e->getMessage());
            return [];
        }
    }
}
?>