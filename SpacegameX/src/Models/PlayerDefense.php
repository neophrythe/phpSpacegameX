<?php
namespace Models;

use Core\Model;
use PDO;

class PlayerDefense extends Model {
    public $id;
    public $planet_id;
    public $defense_type_id;
    public $quantity;

    // Get all defense units for a specific planet
    public static function getDefenseOnPlanet($planetId, PDO $db = null) { // Added $db parameter
        if (!$db) $db = self::getDB();
        $sql = "SELECT pd.*, dt.name_de, dt.description_de, dt.internal_name,
                dt.weapon_power, dt.shield_power, dt.hull_strength
                FROM player_defense pd
                JOIN static_defense_types dt ON pd.defense_type_id = dt.id
                WHERE pd.planet_id = :planet_id
                ORDER BY dt.id";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // Get a specific defense unit on a planet
    public static function getByPlanetAndType($planetId, $defenseTypeId, PDO $db = null) { // Added $db parameter
        if (!$db) $db = self::getDB();
        $sql = "SELECT pd.*, dt.name_de, dt.description_de, dt.internal_name
                FROM player_defense pd
                JOIN static_defense_types dt ON pd.defense_type_id = dt.id
                WHERE pd.planet_id = :planet_id AND pd.defense_type_id = :defense_type_id";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->bindParam(':defense_type_id', $defenseTypeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    // Add defense units to a planet (or create entry if it doesn't exist)
    public static function addDefense($planetId, $defenseTypeId, $quantity, PDO $db = null) { // Added $db parameter
        if (!$db) $db = self::getDB();

        // Check if an entry already exists
        $sql = "SELECT id, quantity FROM player_defense
                WHERE planet_id = :planet_id AND defense_type_id = :defense_type_id";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->bindParam(':defense_type_id', $defenseTypeId, PDO::PARAM_INT);
        $stmt->execute();
        $existingDefense = $stmt->fetch(PDO::FETCH_OBJ);

        if ($existingDefense) {
            // Update existing entry
            $sql = "UPDATE player_defense
                    SET quantity = quantity + :quantity
                    WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->bindParam(':id', $existingDefense->id, PDO::PARAM_INT);
            return $stmt->execute();
        } else {
            // Create new entry
            $sql = "INSERT INTO player_defense (planet_id, defense_type_id, quantity)
                    VALUES (:planet_id, :defense_type_id, :quantity)";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->bindParam(':defense_type_id', $defenseTypeId, PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            return $stmt->execute();
        }
    }

    // Remove defense units from a planet
    public static function removeDefense($planetId, $defenseTypeId, $quantity, PDO $db = null) { // Added $db parameter
        if (!$db) $db = self::getDB();

        // Get current defense count
        $sql = "SELECT id, quantity FROM player_defense
                WHERE planet_id = :planet_id AND defense_type_id = :defense_type_id";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->bindParam(':defense_type_id', $defenseTypeId, PDO::PARAM_INT);
        $stmt->execute();
        $existingDefense = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$existingDefense || $existingDefense->quantity < $quantity) {
            // Not enough defense units or none at all
            return false;
        }

        if ($existingDefense->quantity == $quantity) {
            // Remove the entry completely
            $sql = "DELETE FROM player_defense WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $existingDefense->id, PDO::PARAM_INT);
        } else {
            // Reduce the quantity
            $sql = "UPDATE player_defense
                    SET quantity = quantity - :quantity
                    WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->bindParam(':id', $existingDefense->id, PDO::PARAM_INT);
        }

        return $stmt->execute();
    }

    // Start building defense units
    public static function startBuildingDefense($planetId, $defenseTypeId, $quantity, $adjustedBuildTimeSeconds, PDO $db = null) { // Added $db parameter
        if (!$db) $db = self::getDB();
        
        // Start transaction
        $db->beginTransaction();
        
        try {
            // Get defense type for cost and time calculation
            $defenseType = DefenseType::getById($defenseTypeId);
            if (!$defenseType) {
                throw new \Exception("Defense type not found");
            }
            
            // Cost check and deduction are handled in the controller before calling this method.
            // This method assumes resources have already been checked and deducted.
            
            $finishTime = date('Y-m-d H:i:s', time() + $adjustedBuildTimeSeconds);
              
            // Get player id from planet
            $stmt = $db->prepare('SELECT player_id FROM planets WHERE id = :planet_id');
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();
            $playerId = $stmt->fetchColumn();
            
            // Add to construction queue
            $sql = "INSERT INTO construction_queue 
                    (player_id, planet_id, item_type, item_id, target_level_or_quantity, start_time, end_time, duration_seconds)
                    VALUES 
                    (:player_id, :planet_id, 'defense', :item_id, :quantity, NOW(), :end_time, :duration)";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->bindParam(':item_id', $defenseTypeId, PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->bindParam(':end_time', $finishTime, PDO::PARAM_STR);
            $stmt->bindParam(':duration', $adjustedBuildTimeSeconds, PDO::PARAM_INT);
            $stmt->execute();
            
            // Commit transaction
            $db->commit();
            return true;
            
        } catch (\Exception $e) {
            // Roll back on failure
            $db->rollBack();
            // Log error
            error_log("Error starting defense construction: " . $e->getMessage());
            return false;
        }
    }

    // Check and complete defense construction (called by a cron job or on page load)
    public static function checkAndCompleteDefenseConstruction(PDO $db = null) { // Added $db parameter
        if (!$db) $db = self::getDB();
        
        // Find defense construction that has finished
        $sql = "SELECT * FROM construction_queue 
                WHERE item_type = 'defense' AND end_time <= NOW()";
                
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $completedDefenseConstruction = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        foreach ($completedDefenseConstruction as $construction) {
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Add defense units to planet
                self::addDefense(
                    $construction->planet_id, 
                    $construction->item_id, 
                    $construction->target_level_or_quantity
                );
                
                // Remove from construction queue
                $sql = "DELETE FROM construction_queue WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':id', $construction->id, PDO::PARAM_INT);
                $stmt->execute();
                
                // Update player defense points (assuming points based on Eisen and Silber cost)
                $defenseType = DefenseType::getById($construction->item_id);
                if ($defenseType) {
                    $points = ($defenseType->base_cost_eisen + $defenseType->base_cost_silber) / 1000 * $construction->target_level_or_quantity; // Using German resource names
                    
                    $sql = "UPDATE players 
                            SET points_defense = points_defense + :points
                            WHERE id = :player_id";
                            
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(':points', $points, PDO::PARAM_INT);
                    $stmt->bindParam(':player_id', $construction->player_id, PDO::PARAM_INT);
                    $stmt->execute();
                }
                
                $db->commit();
                
            } catch (\Exception $e) {
                $db->rollBack();
                error_log("Error completing defense construction: " . $e->getMessage());
            }
        }
        
        return count($completedDefenseConstruction);
    }
}
?>
