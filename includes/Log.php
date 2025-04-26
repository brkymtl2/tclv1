<?php
require_once 'config/db.php';
require_once 'includes/Session.php';

class Log {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getPDO();
        
        // Log tablosunu kontrol et, yoksa oluştur
        $this->createLogTableIfNotExists();
    }
    
    /**
     * Log tablosunu kontrol eder ve yoksa oluşturur
     */
    private function createLogTableIfNotExists() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS activity_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT,
                    username VARCHAR(50),
                    action VARCHAR(100) NOT NULL,
                    description TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    entity_type VARCHAR(50),
                    entity_id INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                )
            ");
        } catch (PDOException $e) {
            error_log("Log tablosu oluşturulamadı: " . $e->getMessage());
        }
    }
    
    /**
     * Yeni bir log kaydı ekler
     * @param string $action İşlem adı (örn. 'login', 'create_document', 'delete_user')
     * @param string $description İşlemin açıklaması
     * @param string $entityType İşlemin ilgili olduğu nesne türü (örn. 'user', 'document', 'category')
     * @param int|null $entityId İşlemin ilgili olduğu nesnenin ID'si (opsiyonel)
     * @return bool İşlem başarılı mı?
     */
    public function add($action, $description = '', $entityType = null, $entityId = null) {
        try {
            // Kullanıcı bilgilerini al
            $userId = Session::isLoggedIn() ? Session::getUserId() : null;
            $username = Session::isLoggedIn() ? Session::getUsername() : null;
            
            // IP adresi ve User Agent bilgilerini al
            $ipAddress = $this->getClientIP();
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, username, action, description, ip_address, user_agent, entity_type, entity_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $userId, 
                $username, 
                $action, 
                $description, 
                $ipAddress, 
                $userAgent, 
                $entityType, 
                $entityId
            ]);
            
        } catch (PDOException $e) {
            error_log("Log kaydı eklenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log kayıtlarını filtreli şekilde getirir
     * @param array $filters Filtreler (opsiyonel)
     * @param int $limit Limit (opsiyonel)
     * @param int $offset Offset (opsiyonel)
     * @return array Log kayıtları
     */
    public function getLogs($filters = [], $limit = 100, $offset = 0) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Filtreler
            if (isset($filters['user_id']) && !empty($filters['user_id'])) {
                $whereConditions[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (isset($filters['username']) && !empty($filters['username'])) {
                $whereConditions[] = "username LIKE ?";
                $params[] = '%' . $filters['username'] . '%';
            }
            
            if (isset($filters['action']) && !empty($filters['action'])) {
                $whereConditions[] = "action LIKE ?";
                $params[] = '%' . $filters['action'] . '%';
            }
            
            if (isset($filters['entity_type']) && !empty($filters['entity_type'])) {
                $whereConditions[] = "entity_type = ?";
                $params[] = $filters['entity_type'];
            }
            
            if (isset($filters['entity_id']) && !empty($filters['entity_id'])) {
                $whereConditions[] = "entity_id = ?";
                $params[] = $filters['entity_id'];
            }
            
            if (isset($filters['ip_address']) && !empty($filters['ip_address'])) {
                $whereConditions[] = "ip_address LIKE ?";
                $params[] = '%' . $filters['ip_address'] . '%';
            }
            
            if (isset($filters['date_from']) && !empty($filters['date_from'])) {
                $whereConditions[] = "created_at >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (isset($filters['date_to']) && !empty($filters['date_to'])) {
                $whereConditions[] = "created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            // SQL sorgusu oluştur
            $sql = "SELECT * FROM activity_logs";
            
            if (!empty($whereConditions)) {
                $sql .= " WHERE " . implode(" AND ", $whereConditions);
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Log kayıtları getirilemedi: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Toplam log sayısını döndürür
     * @param array $filters Filtreler (opsiyonel)
     * @return int Log sayısı
     */
    public function getLogCount($filters = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Filtreler
            if (isset($filters['user_id']) && !empty($filters['user_id'])) {
                $whereConditions[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (isset($filters['username']) && !empty($filters['username'])) {
                $whereConditions[] = "username LIKE ?";
                $params[] = '%' . $filters['username'] . '%';
            }
            
            if (isset($filters['action']) && !empty($filters['action'])) {
                $whereConditions[] = "action LIKE ?";
                $params[] = '%' . $filters['action'] . '%';
            }
            
            if (isset($filters['entity_type']) && !empty($filters['entity_type'])) {
                $whereConditions[] = "entity_type = ?";
                $params[] = $filters['entity_type'];
            }
            
            if (isset($filters['entity_id']) && !empty($filters['entity_id'])) {
                $whereConditions[] = "entity_id = ?";
                $params[] = $filters['entity_id'];
            }
            
            if (isset($filters['ip_address']) && !empty($filters['ip_address'])) {
                $whereConditions[] = "ip_address LIKE ?";
                $params[] = '%' . $filters['ip_address'] . '%';
            }
            
            if (isset($filters['date_from']) && !empty($filters['date_from'])) {
                $whereConditions[] = "created_at >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (isset($filters['date_to']) && !empty($filters['date_to'])) {
                $whereConditions[] = "created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            // SQL sorgusu oluştur
            $sql = "SELECT COUNT(*) as count FROM activity_logs";
            
            if (!empty($whereConditions)) {
                $sql .= " WHERE " . implode(" AND ", $whereConditions);
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetch()['count'];
            
        } catch (PDOException $e) {
            error_log("Log sayısı alınamadı: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Belirli bir kullanıcıya ait log kayıtlarını temizler
     * @param int $userId Kullanıcı ID
     * @return bool İşlem başarılı mı?
     */
    public function clearUserLogs($userId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM activity_logs WHERE user_id = ?");
            return $stmt->execute([$userId]);
            
        } catch (PDOException $e) {
            error_log("Kullanıcı logları temizlenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Belirli bir tarihten önce oluşturulan log kayıtlarını temizler
     * @param string $date Tarih (YYYY-MM-DD formatında)
     * @return bool İşlem başarılı mı?
     */
    public function clearLogsBefore($date) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM activity_logs WHERE created_at < ?");
            return $stmt->execute([$date . ' 00:00:00']);
            
        } catch (PDOException $e) {
            error_log("Eski loglar temizlenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Tüm log kayıtlarını temizler (sadece süper admin için)
     * @return bool İşlem başarılı mı?
     */
    public function clearAllLogs() {
        try {
            // Sadece süper admin erişebilir
            if (!Session::hasRole('super_admin')) {
                return false;
            }
            
            $stmt = $this->pdo->prepare("TRUNCATE TABLE activity_logs");
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Tüm loglar temizlenemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * İstemcinin IP adresini alır
     * @return string IP adresi
     */
    private function getClientIP() {
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
}
?>