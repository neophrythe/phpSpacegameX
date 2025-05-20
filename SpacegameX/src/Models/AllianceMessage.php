<?php
namespace Models;

use Core\Model;
use PDO;

class AllianceMessage extends Model {
    public int $id;
    public int $alliance_id;
    public int $sender_id;
    public string $subject;
    public string $body;
    public string $created_at;
    public string $updated_at;
    public ?string $sender_username; // For display purposes

    private const TABLE_NAME = 'alliance_messages';

    /**
     * Create a new alliance message.
     *
     * @param int $allianceId
     * @param int $senderId
     * @param string $subject
     * @param string $body
     * @return int|false The ID of the newly created message or false on failure.
     */
    public static function create(int $allianceId, int $senderId, string $subject, string $body): int|false
    {
        $db = static::getDB();
        $sql = "INSERT INTO " . self::TABLE_NAME . " (alliance_id, sender_id, subject, body) 
                VALUES (:alliance_id, :sender_id, :subject, :body)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':alliance_id', $allianceId, PDO::PARAM_INT);
        $stmt->bindParam(':sender_id', $senderId, PDO::PARAM_INT);
        $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
        $stmt->bindParam(':body', $body, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return (int)$db->lastInsertId();
        }
        return false;
    }

    /**
     * Get a message by its ID.
     *
     * @param int $messageId
     * @return self|null
     */
    public static function findById(int $messageId): ?self
    {
        $db = static::getDB();
        $sql = "SELECT am.*, p.username as sender_username 
                FROM " . self::TABLE_NAME . " am
                JOIN players p ON am.sender_id = p.id
                WHERE am.id = :message_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':message_id', $messageId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchObject(self::class);
        return $result ?: null;
    }

    /**
     * Get all messages for a specific alliance, ordered by creation date.
     * Includes sender's username.
     *
     * @param int $allianceId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getMessagesByAllianceId(int $allianceId, int $limit = 25, int $offset = 0): array
    {
        $db = static::getDB();
        $sql = "SELECT am.*, p.username as sender_username
                FROM " . self::TABLE_NAME . " am
                JOIN players p ON am.sender_id = p.id
                WHERE am.alliance_id = :alliance_id
                ORDER BY am.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':alliance_id', $allianceId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, self::class);
    }
    
    /**
     * Count total messages for an alliance.
     * @param int $allianceId
     * @return int
     */
    public static function countMessagesByAllianceId(int $allianceId): int
    {
        $db = static::getDB();
        $sql = "SELECT COUNT(*) FROM " . self::TABLE_NAME . " WHERE alliance_id = :alliance_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':alliance_id', $allianceId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    // Note: Actual deletion of messages might be complex due to recipients.
    // For now, we might rely on recipients marking their copies as deleted.
    // A full delete might be an admin function or based on retention policies.
}
?>
