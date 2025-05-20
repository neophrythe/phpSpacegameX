<?php
namespace Models;

use Core\Model;
use PDO;

class PlayerMessage extends Model {
    // Properties corresponding to the database table columns (assuming a 'player_messages' table)
    public $id;
    public $player_id;
    public $sender_id; // Could be System (0) or another player ID
    public $subject;
    public $content;
    public $sent_at;
    public $is_read;
    public $message_type; // e.g., 'system', 'player', 'alliance'

    // Assuming a table structure like:
    // CREATE TABLE player_messages (
    //     id INT AUTO_INCREMENT PRIMARY KEY,
    //     player_id INT NOT NULL, -- The recipient player
    //     sender_id INT NULL, -- The sender (NULL for system messages)
    //     subject VARCHAR(255),
    //     content TEXT,
    //     sent_at DATETIME,
    //     is_read BOOLEAN DEFAULT FALSE,
    //     message_type VARCHAR(50), -- e.g., 'system', 'player', 'alliance'
    //     deleted_by_sender BOOLEAN DEFAULT FALSE, -- Added for sender deletion
    //     FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    //     FOREIGN KEY (sender_id) REFERENCES players(id) ON DELETE SET NULL
    // );

    /**
     * Get old system messages for a player.
     *
     * @param int $playerId The ID of the player.
     * @param PDO $db Database connection.
     * @return array An array of PlayerMessage objects.
     */
    public static function getOldSystemMessagesForPlayer(int $playerId, ?PDO $db = null): array {
        $db = $db ?? self::getDB(); // Use self::getDB() if no connection passed
        $sql = "SELECT * FROM player_messages
                WHERE player_id = :player_id
                AND message_type = 'system'
                AND is_read = TRUE
                ORDER BY sent_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, self::class);
    }

    // Add other message related methods here as needed (e.g., createMessage, markAsRead, etc.)

    /**
     * Create a new message.
     *
     * @param int $senderId ID of the sender (0 or null for system)
     * @param int $recipientId ID of the recipient player
     * @param string $subject Subject of the message
     * @param string $content Content of the message
     * @param string $messageType Type of message (e.g., 'system', 'player', 'alliance')
     * @param PDO|null $db Optional database connection
     * @return int|false The ID of the newly created message or false on failure.
     */
    public static function createMessage(int $senderId, int $recipientId, string $subject, string $content, string $messageType = 'player', ?PDO $db = null): int|false {
        $db = $db ?? self::getDB();
        $sql = "INSERT INTO player_messages (player_id, sender_id, subject, content, sent_at, is_read, message_type)
                VALUES (:recipient_id, :sender_id, :subject, :content, NOW(), FALSE, :message_type)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':recipient_id', $recipientId, PDO::PARAM_INT);
        $stmt->bindParam(':sender_id', $senderId, ($senderId === 0 || $senderId === null) ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
        $stmt->bindParam(':content', $content, PDO::PARAM_STR);
        $stmt->bindParam(':message_type', $messageType, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return (int)$db->lastInsertId();
        }
        return false;
    }

    /**
     * Mark a message as read.
     *
     * @param int $messageId ID of the message
     * @param int $playerId ID of the player (must be the recipient)
     * @param PDO|null $db Optional database connection
     * @return bool True on success, false on failure.
     */
    public static function markAsRead(int $messageId, int $playerId, ?PDO $db = null): bool {
        $db = $db ?? self::getDB();
        $sql = "UPDATE player_messages SET is_read = TRUE
                WHERE id = :message_id AND player_id = :player_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Delete a message.
     * Note: This is a soft delete for the recipient. True deletion might be an admin task.
     * Or, implement a "deleted_by_recipient" flag if messages should persist for sender.
     * For simplicity, this example performs a hard delete if the player is the recipient.
     *
     * @param int $messageId ID of the message
     * @param int $playerId ID of the player (must be the recipient)
     * @param PDO|null $db Optional database connection
     * @return bool True on success, false on failure.
     */
    public static function deleteMessage(int $messageId, int $playerId, ?PDO $db = null): bool {
        $db = $db ?? self::getDB();
        // This method is for recipient deleting their copy of the message.
        $sql = "DELETE FROM player_messages
                WHERE id = :message_id AND player_id = :player_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Mark a message as deleted by the sender (soft delete for sender's view).
     *
     * @param int $messageId ID of the message
     * @param int $senderId ID of the sender
     * @param PDO|null $db Optional database connection
     * @return bool True on success, false on failure.
     */
    public static function markDeletedBySender(int $messageId, int $senderId, ?PDO $db = null): bool {
        $db = $db ?? self::getDB();
        $sql = "UPDATE player_messages SET deleted_by_sender = TRUE
                WHERE id = :message_id AND sender_id = :sender_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
        $stmt->bindParam(':sender_id', $senderId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Get inbox messages for a player.
     *
     * @param int $playerId ID of the player
     * @param bool $unreadOnly Filter by unread messages
     * @param int $limit Number of messages to retrieve
     * @param int $offset Offset for pagination
     * @param PDO|null $db Optional database connection
     * @return array An array of PlayerMessage objects.
     */
    public static function getInbox(int $playerId, bool $unreadOnly = false, int $limit = 25, int $offset = 0, ?PDO $db = null): array {
        $db = $db ?? self::getDB();
        $sql = "SELECT pm.*, p_sender.username as sender_username
                FROM player_messages pm
                LEFT JOIN players p_sender ON pm.sender_id = p_sender.id
                WHERE pm.player_id = :player_id AND pm.message_type != 'system_internal' "; // Exclude purely system internal if any
        if ($unreadOnly) {
            $sql .= " AND pm.is_read = FALSE";
        }
        $sql .= " ORDER BY pm.sent_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, self::class);
    }

    /**
     * Get sent messages for a player.
     *
     * @param int $playerId ID of the sender
     * @param int $limit Number of messages to retrieve
     * @param int $offset Offset for pagination
     * @param PDO|null $db Optional database connection
     * @return array An array of PlayerMessage objects.
     */
    public static function getSentItems(int $playerId, int $limit = 25, int $offset = 0, ?PDO $db = null): array {
        $db = $db ?? self::getDB();
        $sql = "SELECT pm.*, p_recipient.username as recipient_username
                FROM player_messages pm
                LEFT JOIN players p_recipient ON pm.player_id = p_recipient.id
                WHERE pm.sender_id = :player_id
                AND pm.message_type != 'system_internal'
                AND pm.deleted_by_sender = FALSE"; // Exclude messages deleted by sender
        $sql .= " ORDER BY pm.sent_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, self::class);
    }

    /**
     * Get a specific message by its ID, ensuring player has rights to view it.
     *
     * @param int $messageId ID of the message
     * @param int $playerId ID of the player (must be recipient or sender)
     * @param PDO|null $db Optional database connection
     * @return PlayerMessage|false Message object or false if not found/not authorized.
     */
    public static function getMessageById(int $messageId, int $playerId, ?PDO $db = null): PlayerMessage|false {
        $db = $db ?? self::getDB();
        $sql = "SELECT pm.*, p_sender.username as sender_username, p_recipient.username as recipient_username
                FROM player_messages pm
                LEFT JOIN players p_sender ON pm.sender_id = p_sender.id
                LEFT JOIN players p_recipient ON pm.player_id = p_recipient.id
                WHERE pm.id = :message_id AND (pm.player_id = :player_id OR pm.sender_id = :player_id)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        $message = $stmt->fetchObject(self::class);

        // If the message was fetched and the current player is the recipient and it was unread, mark it as read.
        if ($message && $message->player_id == $playerId && !$message->is_read) {
            self::markAsRead($messageId, $playerId, $db);
            $message->is_read = true; // Update the object property
        }
        return $message;
    }

    /**
     * Count messages for a player (inbox or sent).
     *
     * @param int $playerId Player ID
     * @param bool $isSender True to count sent messages, false for inbox
     * @param bool $unreadOnly True to count only unread messages (applies to inbox only)
     * @param PDO|null $db Optional database connection
     * @return int Count of messages.
     */
    public static function countMessages(int $playerId, bool $isSender = false, bool $unreadOnly = false, ?PDO $db = null): int {
        $db = $db ?? self::getDB();
        $sql = "SELECT COUNT(*) FROM player_messages WHERE ";
        if ($isSender) {
            $sql .= "sender_id = :player_id AND deleted_by_sender = FALSE"; // Exclude messages deleted by sender
        } else {
            $sql .= "player_id = :player_id";
            if ($unreadOnly) {
                $sql .= " AND is_read = FALSE";
            }
        }
        $sql .= " AND message_type != 'system_internal'";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get the count of unread messages for a player.
     *
     * @param int $playerId Player ID
     * @param PDO|null $db Optional database connection
     * @return int Count of unread messages.
     */
    public static function countUnreadMessages(int $playerId, ?PDO $db = null): int {
        return self::countMessages($playerId, false, true, $db);
    }

    /**
     * Get new system messages for a player.
     *
     * @param int $playerId The ID of the player.
     * @param PDO|null $db Optional database connection.
     * @return array An array of PlayerMessage objects.
     */
    public static function getNewSystemMessagesForPlayer(int $playerId, ?PDO $db = null): array {
        $db = $db ?? self::getDB(); // Use self::getDB() if no connection passed
        $sql = "SELECT * FROM player_messages
                WHERE player_id = :player_id
                AND message_type = 'system'
                AND is_read = FALSE
                ORDER BY sent_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, self::class);
    }
}
?>
