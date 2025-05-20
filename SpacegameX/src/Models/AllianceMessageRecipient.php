<?php
namespace Models;

use Core\Model;
use PDO;

class AllianceMessageRecipient extends Model {
    public int $id;
    public int $message_id;
    public int $recipient_id; // Player ID
    public bool $is_read;
    public bool $is_deleted; // Soft delete by recipient
    public string $received_at;

    private const TABLE_NAME = 'alliance_message_recipients';

    /**
     * Add recipients for an alliance message.
     *
     * @param int $messageId
     * @param array $recipientIds Array of player IDs
     * @return bool True on success, false on failure.
     */
    public static function addRecipients(int $messageId, array $recipientIds): bool
    {
        if (empty($recipientIds)) {
            return true;
        }
        $db = static::getDB();
        $sql = "INSERT INTO " . self::TABLE_NAME . " (message_id, recipient_id) VALUES ";
        $placeholders = [];
        $values = [];
        foreach ($recipientIds as $recipientId) {
            $placeholders[] = "(:message_id_" . $recipientId . ", :recipient_id_" . $recipientId . ")";
            $values[':message_id_' . $recipientId] = $messageId;
            $values[':recipient_id_' . $recipientId] = $recipientId;
        }
        $sql .= implode(", ", $placeholders);
        $stmt = $db->prepare($sql);

        return $stmt->execute($values);
    }

    /**
     * Mark a message as read for a specific recipient.
     *
     * @param int $messageId
     * @param int $recipientId
     * @return bool
     */
    public static function markAsRead(int $messageId, int $recipientId): bool
    {
        $db = static::getDB();
        $sql = "UPDATE " . self::TABLE_NAME . " 
                SET is_read = TRUE 
                WHERE message_id = :message_id AND recipient_id = :recipient_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
        $stmt->bindParam(':recipient_id', $recipientId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Mark a message as deleted for a specific recipient (soft delete).
     *
     * @param int $messageId
     * @param int $recipientId
     * @return bool
     */
    public static function markAsDeleted(int $messageId, int $recipientId): bool
    {
        $db = static::getDB();
        $sql = "UPDATE " . self::TABLE_NAME . " 
                SET is_deleted = TRUE 
                WHERE message_id = :message_id AND recipient_id = :recipient_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
        $stmt->bindParam(':recipient_id', $recipientId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Get messages for a player (recipient) that are not deleted by them.
     * Joins with alliance_messages to get message details.
     *
     * @param int $playerId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getPlayerAllianceMessages(int $playerId, int $limit = 25, int $offset = 0): array
    {
        $db = static::getDB();
        $sql = "SELECT am.*, amr.is_read, amr.received_at, p_sender.username as sender_username
                FROM " . self::TABLE_NAME . " amr
                JOIN alliance_messages am ON amr.message_id = am.id
                JOIN players p_sender ON am.sender_id = p_sender.id
                WHERE amr.recipient_id = :player_id AND amr.is_deleted = FALSE
                ORDER BY am.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        // Fetching as associative array as it combines fields from multiple tables into a non-model structure
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count unread alliance messages for a player.
     *
     * @param int $playerId
     * @return int
     */
    public static function countUnreadPlayerAllianceMessages(int $playerId): int
    {
        $db = static::getDB();
        $sql = "SELECT COUNT(*)
                FROM " . self::TABLE_NAME . " amr
                WHERE amr.recipient_id = :player_id AND amr.is_read = FALSE AND amr.is_deleted = FALSE";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Count total (non-deleted) alliance messages for a player.
     * @param int $playerId
     * @return int
     */
    public static function countPlayerAllianceMessages(int $playerId): int
    {
        $db = static::getDB();
        $sql = "SELECT COUNT(*)
                FROM " . self::TABLE_NAME . " amr
                WHERE amr.recipient_id = :player_id AND amr.is_deleted = FALSE";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Check if a specific player is a recipient of a message and has not deleted it.
     *
     * @param int $messageId
     * @param int $playerId
     * @return bool
     */
    public static function isRecipient(int $messageId, int $playerId): bool
    {
        $db = static::getDB();
        $sql = "SELECT COUNT(*) FROM " . self::TABLE_NAME . " 
                WHERE message_id = :message_id AND recipient_id = :recipient_id AND is_deleted = FALSE";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
        $stmt->bindParam(':recipient_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }
}
?>
