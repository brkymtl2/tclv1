<?php
require_once 'config/db.php';

class Permission {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getPDO();
    }
    
    /**
     * Yeni bir izin oluşturur
     * @param string $name İzin adı
     * @param string $description İzin açıklaması
     * @return int|false Oluşturulan iznin ID'si veya hata durumunda false
     */
    public function createPermission($name, $description = '') {
        try {
            // İzin adı kontrolü
            $stmt = $this->pdo->prepare("SELECT id FROM permissions WHERE name = ?");
            $stmt->execute([$name]);
            
            if ($stmt->rowCount() > 0) {
                return false; // İzin adı zaten mevcut
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO permissions (name, description)
                VALUES (?, ?)
            ");
            
            $stmt->execute([$name, $description]);
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("İzin oluşturulamadı: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Tüm izinleri getirir
     * @return array İzinler listesi
     */
    public function getAllPermissions() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM permissions ORDER BY name");
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("İzinler getirilemedi: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Belirli bir role izin atar
     * @param string $role Rol adı (super_admin, admin, staff)
     * @param int $permissionId İzin ID
     * @return bool İşlem başarılı mı?
     */
    public function assignPermissionToRole($role, $permissionId) {
        try {
            // Önce aynı rol ve izin kombinasyonu var mı kontrol et
            $stmt = $this->pdo->prepare("
                SELECT id FROM role_permissions
                WHERE role = ? AND permission_id = ?
            ");
            
            $stmt->execute([$role, $permissionId]);
            
            if ($stmt->rowCount() > 0) {
                return true; // Zaten atanmış
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO role_permissions (role, permission_id)
                VALUES (?, ?)
            ");
            
            return $stmt->execute([$role, $permissionId]);
            
        } catch (PDOException $e) {
            error_log("İzin atanamadı: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Bir rolden izni kaldırır
     * @param string $role Rol adı
     * @param int $permissionId İzin ID
     * @return bool İşlem başarılı mı?
     */
    public function removePermissionFromRole($role, $permissionId) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM role_permissions
                WHERE role = ? AND permission_id = ?
            ");
            
            return $stmt->execute([$role, $permissionId]);
            
        } catch (PDOException $e) {
            error_log("İzin kaldırılamadı: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Bir role ait tüm izinleri getirir
     * @param string $role Rol adı
     * @return array İzinler listesi
     */
    public function getPermissionsByRole($role) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.*
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role = ?
                ORDER BY p.name
            ");
            
            $stmt->execute([$role]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Rol izinleri getirilemedi: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * İzin siler
     * @param int $permissionId İzin ID
     * @return bool İşlem başarılı mı?
     */
    public function deletePermission($permissionId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM permissions WHERE id = ?");
            return $stmt->execute([$permissionId]);
            
        } catch (PDOException $e) {
            error_log("İzin silinemedi: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Temel izinleri oluşturur (kurulum için)
     * @return bool İşlem başarılı mı?
     */
    public function createDefaultPermissions() {
        $defaultPermissions = [
            ['view_documents', 'Belgeleri görüntüleme'],
            ['upload_documents', 'Belge yükleme'],
            ['edit_documents', 'Belge düzenleme'],
            ['delete_documents', 'Belge silme'],
            ['manage_categories', 'Kategori yönetimi'],
            ['manage_users', 'Kullanıcı yönetimi'],
            ['manage_permissions', 'İzin yönetimi'],
            ['view_reports', 'Raporları görüntüleme']
        ];
        
        $success = true;
        
        foreach ($defaultPermissions as $permission) {
            $result = $this->createPermission($permission[0], $permission[1]);
            if ($result === false) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Varsayılan rol-izin atamalarını yapar
     * @return bool İşlem başarılı mı?
     */
    public function assignDefaultRolePermissions() {
        try {
            // Tüm izinleri al
            $permissions = $this->getAllPermissions();
            
            // Super Admin için tüm izinleri ata
            foreach ($permissions as $permission) {
                $this->assignPermissionToRole('super_admin', $permission['id']);
            }
            
            // Admin için bazı izinleri ata
            $adminPermissions = ['view_documents', 'upload_documents', 'edit_documents', 
                                'delete_documents', 'manage_categories', 'view_reports'];
            
            foreach ($permissions as $permission) {
                if (in_array($permission['name'], $adminPermissions)) {
                    $this->assignPermissionToRole('admin', $permission['id']);
                }
            }
            
            // Personel için temel izinleri ata
            $staffPermissions = ['view_documents', 'upload_documents'];
            
            foreach ($permissions as $permission) {
                if (in_array($permission['name'], $staffPermissions)) {
                    $this->assignPermissionToRole('staff', $permission['id']);
                }
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Varsayılan izinler atanamadı: " . $e->getMessage());
            return false;
        }
    }
}
?>