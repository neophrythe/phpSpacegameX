<?php
namespace Models;

use Core\\\\Model;
use PDO;
use Models\\\\Planet; // Assuming Planet model is in Models namespace
use Models\\\\PlayerResearch; // Added for research prerequisite checking
use Models\\\\ResearchType; // Added for fetching research type names
use Services\\\\NotificationService; // Added for building completion notifications
use Models\\\\ConstructionQueue; // Added for interacting with the queue

class PlayerBuilding extends Model {
    public $id;
    public $planet_id;
    public $building_type_id;
    public $level;
    public $is_under_construction;
    public $construction_finish_time;

    // Define constants for Zentrale
    private const ZENTRALE_INTERNAL_NAME = 'zentrale';
    private const ZENTRALE_BUILD_TIME_REDUCTION_FACTOR = 0.05; // 5% reduction per level
    
    // Get all buildings on a planet
    public static function getAllForPlanet($planetId) {
        $db = self::getDB();
        $sql = "SELECT pb.*, bt.name_de, bt.description_de, bt.internal_name, 
                       bt.base_production_eisen, bt.base_production_silber, 
                       bt.base_production_uderon, bt.base_production_wasserstoff, bt.base_production_energie,
                       bt.base_consumption_wasserstoff, bt.base_consumption_energie
                FROM player_buildings pb
                JOIN static_building_types bt ON pb.building_type_id = bt.id
                WHERE pb.planet_id = :planet_id
                ORDER BY bt.id";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
    
    // Get a specific building on a planet
    public static function getByPlanetAndType($planetId, $buildingTypeId) {
        $db = self::getDB();
        $sql = "SELECT pb.*, bt.name_de, bt.description_de, bt.internal_name
                FROM player_buildings pb
                JOIN static_building_types bt ON pb.building_type_id = bt.id
                WHERE pb.planet_id = :planet_id AND pb.building_type_id = :building_type_id";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->bindParam(':building_type_id', $buildingTypeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    // Get a player's research lab level (assuming one research lab per player, perhaps on home planet)
    public static function getResearchLabLevelForPlayer($playerId) {
        $db = self::getDB();
        // Assuming the research lab is on the home planet and its internal name is 'forschungszentrum'
        $sql = "SELECT pb.level FROM player_buildings pb
                JOIN planets p ON pb.planet_id = p.id
                JOIN static_building_types bt ON pb.building_type_id = bt.id
                WHERE p.player_id = :player_id AND p.is_capital = TRUE AND bt.internal_name = 'forschungszentrum'";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn() ?: 0;
    }
    
    // Create initial buildings for a new planet
    public static function createInitialBuildings($planetId) {
        $db = self::getDB();
        
        // Get all building types
        $buildingTypes = BuildingType::getAll();
        
        // For each building type, create an entry with level 0
        foreach ($buildingTypes as $type) {
            $sql = "INSERT INTO player_buildings (planet_id, building_type_id, level, is_under_construction) 
                    VALUES (:planet_id, :building_type_id, :level, :is_under_construction)";
            
            $stmt = $db->prepare($sql);
            $level = 0;
            $isUnderConstruction = false;
            
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->bindParam(':building_type_id', $type->id, PDO::PARAM_INT);
            $stmt->bindParam(':level', $level, PDO::PARAM_INT);
            $stmt->bindParam(':is_under_construction', $isUnderConstruction, PDO::PARAM_BOOL);
            $stmt->execute();
        }
    }

    public static function getBuildingLevelsByPlanetId($planetId) {
        $db = self::getDB();
        $sql = "SELECT building_type_id, level FROM player_buildings WHERE planet_id = :planet_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, \PDO::PARAM_INT);
        $stmt->execute();
        $levels = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $levels[$row['building_type_id']] = $row['level'];
        }
        return $levels;
    }

    public static function initiateUpgrade($playerId, $planetId, $buildingTypeId) {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            // 1. Fetch Planet and verify ownership
            $planet = Planet::getById($planetId); 
            if (!$planet) {
                throw new \Exception("Planet not found.");
            }
            if ($planet->player_id != $playerId) {
                throw new \Exception("You do not own this planet.");
            }

            // 2. Fetch BuildingType
            $buildingType = BuildingType::getById($buildingTypeId);
            if (!$buildingType) {
                throw new \Exception("Building type not found.");
            }

            // 3. Fetch PlayerBuilding (current state)
            $currentBuilding = self::getByPlanetAndType($planetId, $buildingTypeId);
            if (!$currentBuilding) {
                throw new \Exception("Building record not found on this planet. Ensure initial buildings are set up.");
            }
            $currentLevel = $currentBuilding->level;
            $targetLevel = $currentLevel + 1;

            // 4. Check Max Level
            if ($buildingType->max_level !== null && $targetLevel > $buildingType->max_level) {
                throw new \Exception("Building is already at its maximum level.");
            }

            // 5. Check if already under construction (on player_buildings table)
            if ($currentBuilding->is_under_construction) {
                throw new \Exception("This building is already under construction.");
            }
            
            // 5b. Check if already in construction_queue (more robust check)
            $sqlCheckQueue = "SELECT COUNT(*) FROM construction_queue 
                              WHERE planet_id = :planet_id 
                              AND item_type = 'building' 
                              AND item_id = :building_type_id 
                              AND end_time > NOW()";
            $stmtCheckQueue = $db->prepare($sqlCheckQueue);
            $stmtCheckQueue->bindParam(':planet_id', $planetId, \PDO::PARAM_INT);
            $stmtCheckQueue->bindParam(':building_type_id', $buildingTypeId, \PDO::PARAM_INT);
            $stmtCheckQueue->execute();
            if ($stmtCheckQueue->fetchColumn() > 0) {
                 throw new \Exception("This building is already in the construction queue.");
            }

            // 6. Check Prerequisites
            if (!empty($buildingType->requirements_json)) {
                $requirements = json_decode($buildingType->requirements_json, true);
                $playerBuildingLevels = self::getBuildingLevelsByPlanetId($planetId);
                $playerResearchLevels = PlayerResearch::getResearchLevelsByPlayerId($playerId); 

                if (!empty($requirements['buildings'])) {
                    foreach ($requirements['buildings'] as $reqBuildingId => $reqLevel) {
                        $currentReqBuildingLevel = isset($playerBuildingLevels[$reqBuildingId]) ? $playerBuildingLevels[$reqBuildingId] : 0;
                        if ($currentReqBuildingLevel < $reqLevel) {
                            $requiredBuildingType = BuildingType::getById($reqBuildingId);
                            $reqName = $requiredBuildingType ? $requiredBuildingType->name_de : "ID ".$reqBuildingId;
                            throw new \Exception("Requirement not met: ".$reqName." Level ".$reqLevel." required.");
                        }
                    }
                }
                if (!empty($requirements['research'])) {
                    foreach ($requirements['research'] as $reqResearchId => $reqLevel) {
                        $currentReqResearchLevel = isset($playerResearchLevels[$reqResearchId]) ? $playerResearchLevels[$reqResearchId] : 0;
                        if ($currentReqResearchLevel < $reqLevel) {
                            $requiredResearchType = ResearchType::getById($reqResearchId); // Assuming ResearchType model and getById
                            $reqName = $requiredResearchType ? $requiredResearchType->name_de : "Forschung ID ".$reqResearchId;
                            throw new \Exception("Voraussetzung nicht erfüllt: Forschung ".$reqName." Stufe ".$reqLevel." benötigt.");
                        }
                    }
                }
            }

            // 7. Calculate Costs
            $costs = $buildingType->getCostAtLevel($currentLevel);

            // 8. Calculate Build Time
            $baseBuildTimeSeconds = $buildingType->getBaseBuildTimeAtLevel($currentLevel); 

            $zentraleBuildingType = BuildingType::getByInternalName(self::ZENTRALE_INTERNAL_NAME);
            $adjustedBuildTimeSeconds = $baseBuildTimeSeconds;

            if ($zentraleBuildingType) {
                $zentraleOnPlanet = self::getByPlanetAndType($planetId, $zentraleBuildingType->id);
                if ($zentraleOnPlanet && $zentraleOnPlanet->level > 0) {
                    $zentraleLevel = $zentraleOnPlanet->level;
                    $divisor = 1 + ($zentraleLevel * self::ZENTRALE_BUILD_TIME_REDUCTION_FACTOR);
                    if ($divisor > 0) { 
                        $adjustedBuildTimeSeconds = round($baseBuildTimeSeconds / $divisor);
                    }
                }
            }
            $adjustedBuildTimeSeconds = max(1, (int)$adjustedBuildTimeSeconds); 


            // 9. Check Resource Availability
            if ($planet->eisen < $costs['eisen'] || 
                $planet->silber < $costs['silber'] ||
                $planet->uderon < $costs['uderon'] ||
                $planet->wasserstoff < $costs['wasserstoff'] ||
                $planet->energie < $costs['energie']) { 
                throw new \Exception("Insufficient resources to start upgrade.");
            }

            // 10. Deduct Resources
            $sqlDeduct = "UPDATE planets SET 
                            eisen = eisen - :cost_eisen, 
                            silber = silber - :cost_silber, 
                            uderon = uderon - :cost_uderon, 
                            wasserstoff = wasserstoff - :cost_wasserstoff,
                            energie = energie - :cost_energie
                          WHERE id = :planet_id AND player_id = :player_id"; // Added player_id for safety
            $stmtDeduct = $db->prepare($sqlDeduct);
            // Bind float values as STR to avoid precision issues with some DB drivers if costs are float
            $stmtDeduct->bindValue(':cost_eisen', $costs['eisen']);
            $stmtDeduct->bindValue(':cost_silber', $costs['silber']);
            $stmtDeduct->bindValue(':cost_uderon', $costs['uderon']);
            $stmtDeduct->bindValue(':cost_wasserstoff', $costs['wasserstoff']);
            $stmtDeduct->bindValue(':cost_energie', $costs['energie']);
            $stmtDeduct->bindParam(':planet_id', $planetId, \PDO::PARAM_INT);
            $stmtDeduct->bindParam(':player_id', $playerId, \PDO::PARAM_INT);
            
            if (!$stmtDeduct->execute()) {
                throw new \Exception("Failed to deduct resources (execution error).");
            }
            if ($stmtDeduct->rowCount() == 0) {
                 throw new \Exception("Failed to deduct resources (planet not found for player or no change).");
            }

            // 11. Call startConstruction
            $constructionStarted = self::startConstruction($planetId, $buildingTypeId, $targetLevel, $adjustedBuildTimeSeconds);
            
            if (!$constructionStarted) {
                throw new \Exception("Failed to start construction process. Check logs from startConstruction.");
            }

            // 12. Commit Transaction
            $db->commit();
            return ['success' => true, 'message' => $buildingType->name_de . ' Ausbau auf Stufe ' . $targetLevel . ' gestartet.'];

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Upgrade initiation failed for player $playerId, planet $planetId, building $buildingTypeId: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // Start building construction
    public static function startConstruction($planetId, $buildingTypeId, $targetLevel, $adjustedBuildTimeSeconds) {
        $db = self::getDB();
        
        // Start transaction 
        $db->beginTransaction();
        
        try {
            // Check if building exists on planet
            $building = self::getByPlanetAndType($planetId, $buildingTypeId);
            if (!$building) {
                throw new \Exception("Building not found on this planet");
            }
            
            // Make sure it's not already under construction
            if ($building->is_under_construction) {
                throw new \Exception("Building is already under construction");
            }
            
            // Get building type for cost calculation
            $buildingType = BuildingType::getById($buildingTypeId);
            
            // Check if at max level
            if ($buildingType->max_level !== null && $targetLevel > $buildingType->max_level) {
                throw new \Exception("Building is already at maximum level");
            }
            
            // Calculate cost (assuming getCostAtLevel method exists in BuildingType)
            $cost = $buildingType->getCostAtLevel($building->level); // Calculate cost for the NEXT level (current level + 1)
              
            // Resources check and deduction are handled in the controller before calling this method.
            // This method assumes resources have already been checked and deducted.

            $finishTime = date('Y-m-d H:i:s', time() + $adjustedBuildTimeSeconds);
            
            // Update building status
            $sql = "UPDATE player_buildings 
                    SET is_under_construction = 1, construction_finish_time = :finish_time
                    WHERE planet_id = :planet_id AND building_type_id = :building_type_id";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':finish_time', $finishTime, PDO::PARAM_STR);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->bindParam(':building_type_id', $buildingTypeId, PDO::PARAM_INT);
            $stmt->execute();
              
            // Add to construction queue
            $sql = "INSERT INTO construction_queue 
                    (player_id, planet_id, item_type, item_id, target_level_or_quantity, start_time, end_time, duration_seconds)
                    VALUES 
                    ((SELECT player_id FROM planets WHERE id = :planet_id), :planet_id, 'building', :item_id, :target_level, NOW(), :end_time, :duration)";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->bindParam(':item_id', $buildingTypeId, PDO::PARAM_INT);
            $stmt->bindParam(':target_level', $targetLevel, PDO::PARAM_INT);
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
            error_log($e->getMessage());
            return false;
        }
    }
    
    // Complete a building construction (this would be called by a cron job or on page load)
    public static function checkAndCompleteConstructions() { // Removed $planetId parameter
        $db = self::getDB();
        $completedCount = 0;

        // Find completed building items in the construction queue
        $sql = "SELECT cq.*, bt.name_de, p.player_id as owner_player_id, p.name as planet_name
                FROM construction_queue cq
                JOIN static_building_types bt ON cq.item_id = bt.id
                JOIN planets p ON cq.planet_id = p.id
                WHERE cq.queue_type = 'building' 
                AND cq.end_time <= NOW()
                AND cq.is_completed = FALSE"; // Ensure we only process incomplete items

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $completedQueueItems = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        foreach ($completedQueueItems as $queueItem) {
            $db->beginTransaction();
            try {
                // Find the corresponding player_building entry
                $sqlBuilding = "SELECT * FROM player_buildings 
                                WHERE planet_id = :planet_id 
                                AND building_type_id = :building_type_id";
                $stmtBuilding = $db->prepare($sqlBuilding);
                $stmtBuilding->bindParam(':planet_id', $queueItem->planet_id, PDO::PARAM_INT);
                $stmtBuilding->bindParam(':building_type_id', $queueItem->item_id, PDO::PARAM_INT);
                $stmtBuilding->execute();
                $playerBuilding = $stmtBuilding->fetch(PDO::FETCH_OBJ);

                if ($playerBuilding) {
                    // Update player_building: increase level, reset construction status
                    $sql = "UPDATE player_buildings 
                            SET level = :new_level, 
                                is_under_construction = 0, 
                                construction_finish_time = NULL
                            WHERE id = :id";
                    $stmt = $db->prepare($sql);
                    $newLevel = $playerBuilding->level + 1; // Increment level
                    $stmt->bindParam(':new_level', $newLevel, PDO::PARAM_INT);
                    $stmt->bindParam(':id', $playerBuilding->id, PDO::PARAM_INT);
                    $stmt->execute();

                    // Mark item in construction_queue as completed
                    $sqlQueue = "UPDATE construction_queue 
                                 SET is_completed = TRUE 
                                 WHERE id = :id";
                    $stmtQueue = $db->prepare($sqlQueue);
                    $stmtQueue->bindParam(':id', $queueItem->id, PDO::PARAM_INT);
                    $stmtQueue->execute();

                    // Send notification
                    NotificationService::buildingCompleted(
                        $queueItem->owner_player_id, // Player ID from the queue item
                        $queueItem->name_de, // Building name from the queue item
                        $newLevel, // New level
                        $queueItem->planet_name // Planet name from the queue item
                    );
                    $completedCount++;
                } else {
                    // Log error: corresponding player_building not found
                    error_log("checkAndCompleteConstructions: PlayerBuilding not found for queue item ID {$queueItem->id} (Planet ID: {$queueItem->planet_id}, Building Type ID: {$queueItem->item_id}). Marking queue item as completed to prevent infinite loop.");
                     $sqlQueue = "UPDATE construction_queue 
                                 SET is_completed = TRUE, notes = CONCAT(COALESCE(notes, \'\'), \' | Error: PlayerBuilding not found.\')
                                 WHERE id = :id";
                    $stmtQueue = $db->prepare($sqlQueue);
                    $stmtQueue->bindParam(':id', $queueItem->id, PDO::PARAM_INT);
                    $stmtQueue->execute();
                }
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error completing building construction for queue item {$queueItem->id}: " . $e->getMessage());
                // Optionally mark queue item with an error status to prevent retrying
                 try {
                     $db->beginTransaction();
                     $sqlQueue = "UPDATE construction_queue 
                                 SET is_completed = TRUE, notes = CONCAT(COALESCE(notes, \'\'), \' | Processing Error: \', :error_msg)
                                 WHERE id = :id";
                     $stmtQueue = $db->prepare($sqlQueue);
                     $errorMsg = substr($e->getMessage(), 0, 100);
                     $stmtQueue->bindParam(':error_msg', $errorMsg, PDO::PARAM_STR);
                     $stmtQueue->bindParam(':id', $queueItem->id, PDO::PARAM_INT);
                     $stmtQueue->execute();
                     $db->commit();
                 } catch (Exception $e2) {
                     if ($db->inTransaction()) $db->rollBack();
                     error_log("Critical error marking construction queue item {$queueItem->id} as completed after processing error: " . $e2->getMessage());
                 }
            }
        }
        
        // Note: Deleting completed items from construction_queue can be done periodically by a separate cleanup process
        // or here if preferred, but marking as completed is safer to avoid losing track of items.
        // For now, let's add a cleanup step here.
        $sqlCleanup = "DELETE FROM construction_queue WHERE is_completed = TRUE";
        $stmtCleanup = $db->prepare($sqlCleanup);
        $stmtCleanup->execute();

        return $completedCount;
    }

    /**
     * Get the level of a specific building on a specific planet for a player.
     *
     * @param int $planetId The ID of the planet.
     * @param string $buildingInternalName The internal name of the building (e.g., 'werft').
     * @return int The level of the building, or 0 if not found or not built.
     */
    public function getBuildingLevel(int $planetId, string $buildingInternalName): int
    {
        $sql = "SELECT pb.level
                FROM player_buildings pb
                JOIN static_building_types sbt ON pb.building_type_id = sbt.id
                WHERE pb.planet_id = :planet_id
                  AND sbt.internal_name = :building_internal_name";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->bindParam(':building_internal_name', $buildingInternalName, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['level'] : 0;
    }

    /**
     * Get all buildings and their levels for a specific planet.
     *
     * @param int $planetId The ID of the planet.
     * @return array An associative array where keys are building internal names and values are their levels.
     */
    public function getBuildingsByPlanet(int $planetId): array
    {
        $sql = "SELECT sbt.internal_name, pb.level
                FROM player_buildings pb
                JOIN static_building_types sbt ON pb.building_type_id = sbt.id
                WHERE pb.planet_id = :planet_id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->execute();

        $buildings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $buildings[$row['internal_name']] = (int)$row['level'];
        }
        return $buildings;
    }
}
?>
