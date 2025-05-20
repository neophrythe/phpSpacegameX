<?php
namespace Models;

use Core\\\\Model;
use PDO;
use \\\\Exception; // For throwing and catching exceptions
use \\\\PDOException; // For database specific exceptions

class Alliance extends Model {
    public $id;
    public $name;
    public $tag;
    public $description;
    public $creation_date;
    public $leader_player_id;
    public $eisen; // Alliance treasury
    public $silber; // Alliance treasury
    public $uderon; // Alliance treasury
    public $wasserstoff; // Alliance treasury
    public $energie; // Alliance treasury
    public $tax_rate; // Alliance tax rate

    public static function create($name, $tag, $description = null, $leaderPlayerId = null) {
        $db = self::getDB();
        $sql = "INSERT INTO alliances (name, tag, description, creation_date, leader_player_id, eisen, silber, uderon, wasserstoff, energie)
                VALUES (:name, :tag, :description, NOW(), :leader_player_id, 0, 0, 0, 0, 0)"; // Initialize treasury to 0
        $stmt = $db->prepare($sql);
        
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':tag', $tag, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':leader_player_id', $leaderPlayerId, PDO::PARAM_INT);
        
        $stmt->execute();
        return $db->lastInsertId();
    }

    public static function findById($id) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM alliances WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    public static function findByName($name) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM alliances WHERE name = :name');
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    public static function findByTag($tag) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM alliances WHERE tag = :tag');
        $stmt->bindParam(':tag', $tag, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    public static function getAll() {
        $db = self::getDB();
        $stmt = $db->query('SELECT * FROM alliances');
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }

    public function getMembers() {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM players WHERE alliance_id = :alliance_id');
        $stmt->bindParam(':alliance_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, '\Models\Player'); // Assuming Player model exists
    }

    public function addMember($playerId, $rank = 'test_member') {
        $db = self::getDB();
        $stmt = $db->prepare('UPDATE players SET alliance_id = :alliance_id, alliance_rank = :alliance_rank WHERE id = :player_id');
        $stmt->bindParam(':alliance_id', $this->id, PDO::PARAM_INT);
        $stmt->bindParam(':alliance_rank', $rank, PDO::PARAM_STR);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function removeMember($playerId) {
        $db = self::getDB();
        $stmt = $db->prepare('UPDATE players SET alliance_id = NULL, alliance_rank = NULL WHERE id = :player_id AND alliance_id = :alliance_id');
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':alliance_id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function updateMemberRank($playerId, $rank) {
        $db = self::getDB();
        $stmt = $db->prepare('UPDATE players SET alliance_rank = :alliance_rank WHERE id = :player_id AND alliance_id = :alliance_id');
        $stmt->bindParam(':alliance_rank', $rank, PDO::PARAM_STR);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':alliance_id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function updateDetails($name = null, $tag = null, $description = null) {
        $db = self::getDB();
        $sql = 'UPDATE alliances SET ';
        $updates = [];
        $params = [':id' => $this->id];

        if ($name !== null) {
            $updates[] = 'name = :name';
            $params[':name'] = $name;
        }
        if ($tag !== null) {
            $updates[] = 'tag = :tag';
            $params[':tag'] = $tag;
        }
        if ($description !== null) {
            $updates[] = 'description = :description';
            $params[':description'] = $description;
        }

        if (empty($updates)) {
            return false; // Nothing to update
        }

        $sql .= implode(', ', $updates) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete() {
        $db = self::getDB();
        $db->beginTransaction();
        try {
            // Remove alliance from all members
            $stmt = $db->prepare('UPDATE players SET alliance_id = NULL, alliance_rank = NULL WHERE alliance_id = :alliance_id');
            $stmt->bindParam(':alliance_id', $this->id, PDO::PARAM_INT);
            $stmt->execute();

            // Delete alliance buildings (assuming ON DELETE CASCADE is not set)
            $stmt = $db->prepare('DELETE FROM alliance_buildings WHERE alliance_id = :alliance_id');
            $stmt->bindParam(':alliance_id', $this->id, PDO::PARAM_INT);
            $stmt->execute();

            // Delete alliance research (assuming ON DELETE CASCADE is not set)
            $stmt = $db->prepare('DELETE FROM alliance_research WHERE alliance_id = :alliance_id');
            $stmt->bindParam(':alliance_id', $this->id, PDO::PARAM_INT);
            $stmt->execute();

            // Delete diplomacy entries involving this alliance
            $stmt = $db->prepare('DELETE FROM alliance_diplomacy WHERE alliance_id_1 = :alliance_id OR alliance_id_2 = :alliance_id');
            $stmt->bindParam(':alliance_id', $this->id, PDO::PARAM_INT);
            $stmt->execute();


            // Delete the alliance
            $stmt = $db->prepare('DELETE FROM alliances WHERE id = :id');
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $result = $stmt->execute();

            $db->commit();
            return $result;
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Error deleting alliance: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add resources to the alliance treasury.
     *
     * @param string $resourceType The type of resource (e.g., 'eisen', 'silber').
     * @param float $quantity The amount of resource to add.
     * @return bool True on success, false on failure.
     */
    public function addResourcesToTreasury($resourceType, $quantity) {
        $db = self::getDB();
        // Validate resource type
        $validResourceTypes = ['eisen', 'silber', 'uderon', 'wasserstoff', 'energie'];
        if (!in_array($resourceType, $validResourceTypes)) {
            error_log("Invalid resource type '{$resourceType}' for alliance treasury.");
            return false;
        }

        $sql = "UPDATE alliances SET {$resourceType} = {$resourceType} + :quantity WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_STR);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Deduct resources from the alliance treasury.
     *
     * @param string $resourceType The type of resource (e.g., 'eisen', 'silber').
     * @param float $quantity The amount of resource to deduct.
     * @return bool True on success, false on failure (e.g., insufficient resources).
     */
    public function deductResourcesFromTreasury($resourceType, $quantity) {
        $db = self::getDB();
        // Validate resource type
        $validResourceTypes = ['eisen', 'silber', 'uderon', 'wasserstoff', 'energie'];
        if (!in_array($resourceType, $validResourceTypes)) {
            error_log("Invalid resource type '{$resourceType}' for alliance treasury.");
            return false;
        }

        // Check if sufficient resources are available
        $stmt = $db->prepare("SELECT {$resourceType} FROM alliances WHERE id = :id");
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        $stmt->execute();
        $currentQuantity = $stmt->fetchColumn();

        if ($currentQuantity < $quantity) {
            return false; // Insufficient resources
        }

        $sql = "UPDATE alliances SET {$resourceType} = {$resourceType} - :quantity WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_STR);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Get the tax rate of the alliance.
     *
     * @return float The tax rate (e.g., 0.05 for 5%).
     */
    public function getTaxRate(): float
    {
        // The tax rate is a fixed value stored in the database.
        return (float)$this->tax_rate;
    }

    /**
     * Set the tax rate for the alliance.
     * Only alliance leaders should be able to call this.
     *
     * @param float $rate The new tax rate (e.g., 0.05 for 5%).
     * @return bool True on success, false on failure.
     */
    public function setTaxRate(float $rate): bool
    {
        if ($rate < 0 || $rate > 1) { // Assuming tax rate is a percentage between 0 and 1
            // Optionally, log an error or throw an exception for invalid rate
            return false;
        }
        $this->tax_rate = $rate;
        return $this->save(); // Assuming a save method exists to persist changes to the database
    }

    /**
     * Add a diplomacy relationship with another alliance.
     *
     * @param int $targetAllianceId The ID of the other alliance.
     * @param string $type The type of diplomacy ('nap', 'bündnis', 'kriegserklärung').
     * @param string|null $endDate Optional end date for the diplomacy (YYYY-MM-DD HH:MM:SS format).
     * @return bool True on success, false on failure.
     */
    public function addDiplomacy($targetAllianceId, $type, $endDate = null) {
        $db = self::getDB();
        // Ensure alliance_id_1 is always the smaller ID to prevent duplicate inverse entries
        $allianceId1 = min($this->id, $targetAllianceId);
        $allianceId2 = max($this->id, $targetAllianceId);

        // Validate diplomacy type
        $validTypes = ['nap', 'bündnis', 'kriegserklärung'];
        if (!in_array($type, $validTypes)) {
            error_log("Invalid diplomacy type '{$type}' for alliance diplomacy.");
            return false;
        }

        // Check for existing relationship
        $stmtCheck = $db->prepare("SELECT type FROM alliance_diplomacy 
                                   WHERE alliance_id_1 = :id1 AND alliance_id_2 = :id2");
        $stmtCheck->execute([':id1' => $allianceId1, ':id2' => $allianceId2]);
        $existing = $stmtCheck->fetchColumn();

        if ($existing) {
            // Update existing relationship if different, or extend end date
            $sqlUpdate = "UPDATE alliance_diplomacy SET type = :type, end_date = :end_date, initiated_by_alliance_id = :initiator 
                          WHERE alliance_id_1 = :id1 AND alliance_id_2 = :id2";
            $stmt = $db->prepare($sqlUpdate);
        } else {
            // Insert new relationship
            $sqlInsert = "INSERT INTO alliance_diplomacy (alliance_id_1, alliance_id_2, type, start_date, end_date, initiated_by_alliance_id) 
                          VALUES (:id1, :id2, :type, NOW(), :end_date, :initiator)";
            $stmt = $db->prepare($sqlInsert);
        }
        
        $stmt->bindParam(':id1', $allianceId1, PDO::PARAM_INT);
        $stmt->bindParam(':id2', $allianceId2, PDO::PARAM_INT);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $endDate, $endDate ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindParam(':initiator', $this->id, PDO::PARAM_INT); // Record which alliance initiated
        
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Database error adding/updating diplomacy: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove a diplomacy relationship with another alliance.
     *
     * @param int $targetAllianceId The ID of the other alliance.
     * @param string $type The type of diplomacy to remove.
     * @return bool True on success, false on failure.
     */
    public function removeDiplomacy($targetAllianceId, $type) {
        $db = self::getDB();
        $allianceId1 = min($this->id, $targetAllianceId);
        $allianceId2 = max($this->id, $targetAllianceId);

        // Validate diplomacy type
        $validTypes = ['nap', 'bündnis', 'kriegserklärung'];
        if (!in_array($type, $validTypes)) {
            error_log("Invalid diplomacy type '{$type}' for alliance diplomacy removal.");
            return false;
        }

        $sql = "DELETE FROM alliance_diplomacy 
                WHERE alliance_id_1 = :id1 AND alliance_id_2 = :id2 AND type = :type";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id1', $allianceId1, PDO::PARAM_INT);
        $stmt->bindParam(':id2', $allianceId2, PDO::PARAM_INT);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        
        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Database error removing diplomacy: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all diplomacy relationships for this alliance.
     *
     * @return array Array of diplomacy relationships.
     */
    public function getDiplomacy() {
        $db = self::getDB();
        $sql = "SELECT ad.*, a1.name as alliance1_name, a1.tag as alliance1_tag, 
                       a2.name as alliance2_name, a2.tag as alliance2_tag
                FROM alliance_diplomacy ad
                JOIN alliances a1 ON ad.alliance_id_1 = a1.id
                JOIN alliances a2 ON ad.alliance_id_2 = a2.id
                WHERE ad.alliance_id_1 = :alliance_id OR ad.alliance_id_2 = :alliance_id
                ORDER BY ad.start_date DESC";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':alliance_id', $this->id, PDO::PARAM_INT);
        
        try {
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error getting diplomacy: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get the diplomacy status between two specific alliances.
     *
     * @param int $allianceId1 The ID of the first alliance.
     * @param int $allianceId2 The ID of the second alliance.
     * @return string|null The diplomacy type ('nap', 'bündnis', 'kriegserklärung') or null if no specific relationship.
     */
    public static function getDiplomacyStatus($allianceId1, $allianceId2) {
        $db = self::getDB();
        // Ensure alliance_id_1 is always the smaller ID for consistent lookup
        $id1 = min($allianceId1, $allianceId2);
        $id2 = max($allianceId1, $allianceId2);

        $sql = "SELECT type FROM alliance_diplomacy 
                WHERE alliance_id_1 = :id1 AND alliance_id_2 = :id2 
                AND (end_date IS NULL OR end_date > NOW())"; // Check for active relationships
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id1', $id1, PDO::PARAM_INT);
        $stmt->bindParam(':id2', $id2, PDO::PARAM_INT);
        
        try {
            $stmt->execute();
            $result = $stmt->fetchColumn();
            return $result ?: null; // Return type or null if no active relationship
        } catch (PDOException $e) {
            error_log("Database error getting diplomacy status: " . $e->getMessage());
            return null;
        }
    }
}
?>
