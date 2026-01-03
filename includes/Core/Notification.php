<?php
namespace ProConsultancy\Core;

class Notification {
    
    public static function create(string $userCode, string $type, string $title, string $message, ?string $link = null): bool
    {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_code, type, title, message, link)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("sssss", $userCode, $type, $title, $message, $link);
        return $stmt->execute();
    }
    
    public static function getUnread(string $userCode): array
    {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT * FROM notifications 
            WHERE user_code = ? AND is_read = 0 
            ORDER BY created_at DESC 
            LIMIT 20
        ");
        
        $stmt->bind_param("s", $userCode);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public static function markAsRead(int $id): bool
    {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
    
    public static function getUnreadCount(string $userCode): int
    {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_code = ? AND is_read = 0
        ");
        
        $stmt->bind_param("s", $userCode);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int)$result['count'];
    }
}