<?php
namespace Models;

use Core\Model;
use PDO;

class PlayerShip extends Model {
    public $id;
    public $planet_id;
    public $ship_type_id;
    public $quantity;
    
    // Get all ships for a specific planet
    public static function getShipsOnPlanet($planetId) {
        $db = self::getDB();
        $sql = "SELECT ps.*, st.name_de, st.description_de, st.internal_name, 
                st.weapon_power, st.shield_power, st.hull_strength, st.speed, st.cargo_capacity
                FROM player_ships ps
                JOIN static_ship_types st ON ps.ship_type_id = st.id
                WHERE ps.planet_id = :planet_id
                ORDER BY st.id";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
    
    // Get a specific ship on a planet
    public static function getByPlanetAndType($planetId, $shipTypeId) {
        $db = self::getDB();
        $sql = "SELECT ps.*, st.name_de, st.description_de, st.internal_name
                FROM player_ships ps
                JOIN static_ship_types st ON ps.ship_type_id = st.id
                WHERE ps.planet_id = :planet_id AND ps.ship_type_id = :ship_type_id";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->bindParam(':ship_type_id', $shipTypeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }
    
    // Add ships to a planet (or create entry if it doesn't exist)
    public static function addShips($planetId, $shipTypeId, $quantity) {
        $db = self::getDB();
        
        // Check if an entry already exists
        $sql = "SELECT id, quantity FROM player_ships
                WHERE planet_id = :planet_id AND ship_type_id = :ship_type_id";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->bindParam(':ship_type_id', $shipTypeId, PDO::PARAM_INT);
        $stmt->execute();
        $existingShip = $stmt->fetch(PDO::FETCH_OBJ);
        
        if ($existingShip) {
            // Update existing entry
            $sql = "UPDATE player_ships 
                    SET quantity = quantity + :quantity
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->bindParam(':id', $existingShip->id, PDO::PARAM_INT);
            return $stmt->execute();
        } else {
            // Create new entry
            $sql = "INSERT INTO player_ships (planet_id, ship_type_id, quantity)
                    VALUES (:planet_id, :ship_type_id, :quantity)";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->bindParam(':ship_type_id', $shipTypeId, PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            return $stmt->execute();
        }
    }
    
    // Remove ships from a planet
    public static function removeShips($planetId, $shipTypeId, $quantity) {
        $db = self::getDB();
        
        // Get current ship count
        $sql = "SELECT id, quantity FROM player_ships
                WHERE planet_id = :planet_id AND ship_type_id = :ship_type_id";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->bindParam(':ship_type_id', $shipTypeId, PDO::PARAM_INT);
        $stmt->execute();
        $existingShip = $stmt->fetch(PDO::FETCH_OBJ);
        
        if (!$existingShip || $existingShip->quantity < $quantity) {
            // Not enough ships or no ships at all
            return false;
        }
        
        if ($existingShip->quantity == $quantity) {
            // Remove the entry completely
            $sql = "DELETE FROM player_ships WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $existingShip->id, PDO::PARAM_INT);
        } else {
            // Reduce the quantity
            $sql = "UPDATE player_ships 
                    SET quantity = quantity - :quantity
                    WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->bindParam(':id', $existingShip->id, PDO::PARAM_INT);
        }
        
        return $stmt->execute();
    }
    
    // Start building ships
    public static function startBuildingShips($planetId, $shipTypeId, $quantity, $adjustedBuildTimeSeconds) {
        $db = self::getDB();
        
        // Start transaction
        $db->beginTransaction();
        
        try {
            // Get ship type for cost and time calculation
            $shipType = ShipType::getById($shipTypeId);
            if (!$shipType) {
                throw new \Exception("Ship type not found");
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
                    (:player_id, :planet_id, 'ship', :item_id, :quantity, NOW(), :end_time, :duration)";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->bindParam(':item_id', $shipTypeId, PDO::PARAM_INT);
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
            error_log("Error starting ship construction: " . $e->getMessage());
            return false;
        }
    }
    
    // Check and complete ship construction (called by a cron job or on page load)
    public static function checkAndCompleteShipConstruction() {
        $db = self::getDB();
        
        // Find ship construction that has finished
        $sql = "SELECT * FROM construction_queue 
                WHERE item_type = 'ship' AND end_time <= NOW()";
                
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $completedShipConstruction = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        foreach ($completedShipConstruction as $construction) {
            // Start transaction
            $db->beginTransaction();
            
            try {
                // Add ships to planet
                self::addShips(
                    $construction->planet_id, 
                    $construction->item_id, 
                    $construction->target_level_or_quantity
                );
                
                // Remove from construction queue
                $sql = "DELETE FROM construction_queue WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':id', $construction->id, PDO::PARAM_INT);
                $stmt->execute();
                
                // Update player fleet points (assuming points based on Eisen and Silber cost)
                $shipType = ShipType::getById($construction->item_id);
                if ($shipType) {
                    $points = ($shipType->base_cost_eisen + $shipType->base_cost_silber) / 1000 * $construction->target_level_or_quantity; // Using German resource names
                    
                    $sql = "UPDATE players 
                            SET points_fleet = points_fleet + :points
                            WHERE id = :player_id";
                            
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(':points', $points, PDO::PARAM_INT);
                    $stmt->bindParam(':player_id', $construction->player_id, PDO::PARAM_INT);
                    $stmt->execute();
                }
                
                $db->commit();
                
            } catch (\Exception $e) {
                $db->rollBack();
                error_log("Error completing ship construction: " . $e->getMessage());
            }
        }
        
        return count($completedShipConstruction);
    }
}
?>
