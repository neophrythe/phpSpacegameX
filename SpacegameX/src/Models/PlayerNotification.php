<?php
namespace Models;

use Core\Model;
use PDO;

class PlayerNotification extends Model {
    public $id;
    public $player_id;
    public $type;
    public $message;
    public $is_read;
    public $created_at;
    public $link; // Added link property, as used in BattleReport and EspionageReport

    // Notification Types
    const TYPE_GENERIC = 'generic';
    const TYPE_PLAYER_MESSAGE_RECEIVED = 'player_message';
    const TYPE_BATTLE_REPORT = 'battle_report';
    const TYPE_ESPIONAGE_REPORT = 'espionage_report';
    const TYPE_ESPIONAGE_ACTIVITY = 'espionage_activity'; // Added
    const TYPE_BUILDING_COMPLETE = 'building_complete';
    const TYPE_RESEARCH_COMPLETE = 'research_complete';
    const TYPE_SHIPYARD_COMPLETE = 'shipyard_complete';
    const TYPE_FLEET_ARRIVAL = 'fleet_arrival';
    const TYPE_FLEET_RETURN = 'fleet_return';
    const TYPE_ATTACK_INCOMING = 'attack_incoming';
    const TYPE_RESOURCE_TRANSFER = 'resource_transfer';
    const TYPE_ASTEROID_EVENT = 'asteroid_event';
    const TYPE_CAPITAL_CHANGE = 'capital_change';
    const TYPE_ALLIANCE_EVENT = 'alliance_event'; // General alliance notifications
    const TYPE_ALLIANCE_MESSAGE = 'alliance_message'; // For new alliance messages
    const TYPE_SYSTEM_MESSAGE = 'system_message';
    // Add more specific types as needed, e.g., ALLIANCE_JOIN_REQUEST, ALLIANCE_WAR_DECLARATION etc.

    /**
     * Get a notification by its ID
     */
    public static function getById($id) {
        $db = self::getDB();
        $sql = 'SELECT * FROM player_notifications WHERE id = :id';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    /**
     * Get notifications for a specific player
     */
    public static function getNotificationsByPlayerId($playerId, $unreadOnly = false) {
        $db = self::getDB();
        $sql = 'SELECT * FROM player_notifications WHERE player_id = :player_id';
        if ($unreadOnly) {
            $sql .= ' AND is_read = FALSE';
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }
    
    /**
     * Count unread notifications for a player
     */
    public static function countUnreadByPlayerId($playerId) {
        $db = self::getDB();
        $sql = 'SELECT COUNT(*) as count FROM player_notifications WHERE player_id = :player_id AND is_read = FALSE';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    public static function createNotification($playerId, $type, $message, $link = null) { // Added $link parameter
        $db = self::getDB();
        $sql = "INSERT INTO player_notifications (player_id, type, message, link, created_at) 
                VALUES (:player_id, :type, :message, :link, NOW())"; // Added link to SQL
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':message', $message, PDO::PARAM_STR);
        $stmt->bindParam(':link', $link, PDO::PARAM_STR); // Bind link
        $stmt->execute();
        return $db->lastInsertId();
    }

    public function markAsRead() {
        $db = self::getDB();
        $sql = "UPDATE player_notifications SET is_read = TRUE WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Delete a notification
     * 
     * @return bool Success status
     */
    public function delete() {
        $db = self::getDB();
        $sql = "DELETE FROM player_notifications WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $this->id, \PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    /**
     * Delete multiple notifications by ID for a specific player
     * 
     * @param int $playerId Player ID
     * @param array $notificationIds Array of notification IDs
     * @return bool Success status
     */
    public static function deleteMultiple($playerId, $notificationIds) {
        if (empty($notificationIds)) {
            return true;
        }
        
        $db = self::getDB();
        
        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
        
        $sql = "DELETE FROM player_notifications 
                WHERE player_id = ? AND id IN ({$placeholders})";
        
        $stmt = $db->prepare($sql);
        
        // Bind player ID as first parameter
        $params = [$playerId];
        
        // Bind notification IDs
        foreach ($notificationIds as $index => $id) {
            $params[] = $id;
        }
        
        return $stmt->execute($params);
    }
    
    /**
     * Mark a notification as read based on its link.
     * Useful when a player visits a page that a notification links to.
     */
    public static function markReadByLink($link, $playerId, PDO $db = null) { // Added $playerId parameter
        if ($db === null) {
            $db = self::getDB();
        }
        $sql = "UPDATE player_notifications SET is_read = TRUE WHERE link = :link AND player_id = :player_id AND is_read = FALSE"; // Added player_id to query
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':link', $link, PDO::PARAM_STR);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT); // Bind player_id
        return $stmt->execute();
    }

    /**
     * Delete all notifications for a specific player.
     * 
     * @param int $playerId Player ID
     * @return bool|int Number of rows affected or false on failure.
     */
    public static function deleteAllByPlayerId($playerId, PDO $db = null) {
        if ($db === null) {
            $db = self::getDB();
        }
        $sql = "DELETE FROM player_notifications WHERE player_id = :player_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        if ($stmt->execute()) {
            return $stmt->rowCount(); // Return number of deleted rows
        }
        return false;
    }
}
?>
