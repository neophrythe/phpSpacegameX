<?php
namespace Models;

use Core\Model;
use PDO;
use Models\ResearchType;
use Models\Planet;
use Models\PlayerBuilding;
use Models\BuildingType;
use Services\NotificationService; // Corrected: Removed extra backslashes
use Models\ConstructionQueue; // Corrected: Removed extra backslashes

class PlayerResearch extends Model {
    public $id;
    public $player_id;
    public $research_type_id;
    public $level;
    public $is_under_research;
    public $research_finish_time;
    
    // Get all research for a player
    public static function getAllForPlayer($playerId) {
        $db = self::getDB();
        $sql = "SELECT pr.*, rt.name_de, rt.description_de, rt.internal_name
                FROM player_research pr
                JOIN static_research_types rt ON pr.research_type_id = rt.id
                WHERE pr.player_id = :player_id
                ORDER BY rt.id";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
    
    // Get a specific research for a player
    public static function getByPlayerAndType($playerId, $researchTypeId) {
        $db = self::getDB();
        $sql = "SELECT pr.*, rt.name_de, rt.description_de, rt.internal_name
                FROM player_research pr
                JOIN static_research_types rt ON pr.research_type_id = rt.id
                WHERE pr.player_id = :player_id AND pr.research_type_id = :research_type_id";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':research_type_id', $researchTypeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    // Get all research levels for a player in an associative array
    public static function getResearchLevelsByPlayerId($playerId) {
        $db = self::getDB();
        $sql = "SELECT research_type_id, level FROM player_research WHERE player_id = :player_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        $researchLevels = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $researchLevels[$row['research_type_id']] = $row['level'];
        }
        return $researchLevels;
    }
    
    // Create initial research entries for a new player
    public static function createInitialResearch($playerId) {
        $db = self::getDB();
        
        // Get all research types
        $researchTypes = ResearchType::getAll();
        
        // For each research type, create an entry with level 0
        foreach ($researchTypes as $type) {
            $sql = "INSERT INTO player_research (player_id, research_type_id, level, is_under_research) 
                    VALUES (:player_id, :research_type_id, :level, :is_under_research)";
            
            $stmt = $db->prepare($sql);
            $level = 0;
            $isUnderResearch = false;
            
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->bindParam(':research_type_id', $type->id, PDO::PARAM_INT);
            $stmt->bindParam(':level', $level, PDO::PARAM_INT);
            $stmt->bindParam(':is_under_research', $isUnderResearch, PDO::PARAM_BOOL);
            $stmt->execute();
        }
    }
    
    public static function initiateResearch($playerId, $researchTypeId) {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            // 1. Fetch ResearchType
            $researchType = ResearchType::getById($researchTypeId);
            if (!$researchType) {
                throw new \Exception("Forschungstyp nicht gefunden.");
            }

            // 2. Fetch PlayerResearch (current state)
            $currentResearch = self::getByPlayerAndType($playerId, $researchTypeId);
            if (!$currentResearch) {
                // This case should ideally be handled by createInitialResearch for all players
                // Or, if a research can be started from level 0 without an existing player_research row:
                // self::createInitialResearchEntryForPlayer($playerId, $researchTypeId); // A helper to create one if missing
                // $currentResearch = self::getByPlayerAndType($playerId, $researchTypeId); 
                // For now, assume initial entries exist.
                throw new \Exception("Forschungseintrag für Spieler nicht gefunden. Bitte sicherstellen, dass initiale Forschungen existieren.");
            }
            $currentLevel = $currentResearch->level;
            $targetLevel = $currentLevel + 1;

            // 3. Check if player is already researching something else
            $sqlCheckActiveResearch = "SELECT COUNT(*) FROM player_research 
                                       WHERE player_id = :player_id AND is_under_research = 1";
            $stmtCheckActive = $db->prepare($sqlCheckActiveResearch);
            $stmtCheckActive->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmtCheckActive->execute();
            if ($stmtCheckActive->fetchColumn() > 0) {
                throw new \Exception("Es wird bereits eine andere Forschung durchgeführt.");
            }
            
            // 4. Check if this specific research is already under construction (redundant if 3 is robust, but good for safety)
            if ($currentResearch->is_under_research) {
                throw new \Exception("Diese Forschung wird bereits durchgeführt.");
            }

            // 5. Check Max Level (if applicable, e.g., from $researchType->max_level)
            // if ($researchType->max_level !== null && $targetLevel > $researchType->max_level) {
            //     throw new \Exception("Diese Forschung hat bereits die maximale Stufe erreicht.");
            // }

            // 6. Check Prerequisites
            if (!empty($researchType->requirements_json)) {
                $requirements = json_decode($researchType->requirements_json, true);
                
                // Building prerequisites (typically Forschungszentrum on home planet or highest)
                if (!empty($requirements['buildings'])) {
                    // Determine which planet\'s building levels to check (e.g., home planet, or highest level of a specific building)
                    // For Forschungszentrum, it\'s often the highest level across all player planets or on the home planet.
                    // Let\'s assume for now we check the highest Forschungszentrum.
                    $forschungszentrumTypeId = BuildingType::getByInternalName('forschungszentrum')->id ?? null;
                    $highestLabLevel = 0;
                    if ($forschungszentrumTypeId) {
                        $allPlayerPlanets = Planet::getPlanetsByPlayerId($playerId);
                        foreach($allPlayerPlanets as $pPlanet) {
                            $buildingOnPlanet = PlayerBuilding::getByPlanetAndType($pPlanet->id, $forschungszentrumTypeId);
                            if($buildingOnPlanet && $buildingOnPlanet->level > $highestLabLevel) {
                                $highestLabLevel = $buildingOnPlanet->level;
                            }
                        }
                    }

                    foreach ($requirements['buildings'] as $reqBuildingIdOrName => $reqLevel) {
                        $reqBuildingTypeId = is_numeric($reqBuildingIdOrName) ? (int)$reqBuildingIdOrName : (BuildingType::getByInternalName($reqBuildingIdOrName)->id ?? null);
                        if (!$reqBuildingTypeId) continue;

                        $currentReqBuildingLevel = 0;
                        if ($reqBuildingTypeId === $forschungszentrumTypeId) {
                            $currentReqBuildingLevel = $highestLabLevel;
                        } else {
                            // For other buildings, check highest level across all planets.
                            $highestSpecificBuildingLevel = 0;
                            $allPlayerPlanets = Planet::getPlanetsByPlayerId($playerId);
                            foreach($allPlayerPlanets as $pPlanet) {
                                $buildingOnPlanet = PlayerBuilding::getByPlanetAndType($pPlanet->id, $reqBuildingTypeId);
                                if($buildingOnPlanet && $buildingOnPlanet->level > $highestSpecificBuildingLevel) {
                                    $highestSpecificBuildingLevel = $buildingOnPlanet->level;
                                }
                            }
                            $currentReqBuildingLevel = $highestSpecificBuildingLevel;
                        }
                        
                        if ($currentReqBuildingLevel < $reqLevel) {
                            $requiredBuildingType = BuildingType::getById($reqBuildingTypeId);
                            $reqName = $requiredBuildingType ? $requiredBuildingType->name_de : "Gebäude ID ".$reqBuildingTypeId;
                            throw new \Exception("Voraussetzung nicht erfüllt: ".$reqName." Stufe ".$reqLevel." benötigt.");
                        }
                    }
                }

                // Research prerequisites
                if (!empty($requirements['research'])) {
                    $playerResearchLevels = self::getResearchLevelsByPlayerId($playerId);
                    foreach ($requirements['research'] as $reqResearchIdOrName => $reqLevel) {
                         $reqResearchTypeId = is_numeric($reqResearchIdOrName) ? (int)$reqResearchIdOrName : (ResearchType::getByInternalName($reqResearchIdOrName)->id ?? null);
                         if (!$reqResearchTypeId) continue;

                        $currentReqResearchLevel = isset($playerResearchLevels[$reqResearchTypeId]) ? $playerResearchLevels[$reqResearchTypeId] : 0;
                        if ($currentReqResearchLevel < $reqLevel) {
                            $requiredResearchType = ResearchType::getById($reqResearchTypeId);
                            $reqName = $requiredResearchType ? $requiredResearchType->name_de : "Forschung ID ".$reqResearchTypeId;
                            throw new \Exception("Voraussetzung nicht erfüllt: Forschung ".$reqName." Stufe ".$reqLevel." benötigt.");
                        }
                    }
                }
            }

            // 7. Calculate Costs
            $costs = $researchType->getCostAtLevel($currentLevel);

            // 8. Determine which planet's resources to use (typically home planet)
            $homePlanet = Planet::getHomePlanetByPlayerId($playerId);
            if (!$homePlanet) {
                throw new \Exception("Heimatplanet nicht gefunden, um Ressourcen abzuziehen.");
            }
             // Refresh home planet data to get latest resource counts
            $homePlanet = Planet::getById($homePlanet->id);


            // 9. Check Resource Availability on Home Planet
            if ($homePlanet->eisen < $costs['eisen'] || 
                $homePlanet->silber < $costs['silber'] ||
                $homePlanet->uderon < $costs['uderon'] ||
                $homePlanet->wasserstoff < $costs['wasserstoff'] ||
                $homePlanet->energie < $costs['energie']) { // Assuming energy can be a cost for research
                throw new \Exception("Nicht genügend Ressourcen auf dem Heimatplaneten vorhanden.");
            }

            // 10. Calculate Research Time
            // Research time depends on Forschungszentrum level. Find the highest level.
            $forschungszentrumTypeId = BuildingType::getByInternalName('forschungszentrum')->id ?? null;
            $highestLabLevel = 0;
            if ($forschungszentrumTypeId) {
                $allPlayerPlanets = Planet::getPlanetsByPlayerId($playerId);
                foreach($allPlayerPlanets as $pPlanet) {
                    $labOnPlanet = PlayerBuilding::getByPlanetAndType($pPlanet->id, $forschungszentrumTypeId);
                    if($labOnPlanet && $labOnPlanet->level > $highestLabLevel) {
                        $highestLabLevel = $labOnPlanet->level;
                    }
                }
            }
            if ($highestLabLevel == 0 && $researchType->base_research_time > 0) { // If lab is required and not built
                 // Check if Forschungszentrum itself is a requirement for this research. If so, the prerequisite check would have caught it.
                 // If not, and lab level 0 means infinite time, throw error or set a very long time.
                 // For now, let's assume lab level 0 means it cannot be researched if time > 0, or use 1 if docs imply.
                 // The formula in ResearchType::getResearchTimeAtLevel already handles labLevel > 0 ? labLevel : 1
            }

            $adjustedResearchTimeSeconds = $researchType->getResearchTimeAtLevel($currentLevel, $highestLabLevel);
            $adjustedResearchTimeSeconds = max(1, (int)$adjustedResearchTimeSeconds); // Ensure minimum 1 second

            // 11. Deduct Resources from Home Planet
            $sqlDeduct = "UPDATE planets SET 
                            eisen = eisen - :cost_eisen, 
                            silber = silber - :cost_silber, 
                            uderon = uderon - :cost_uderon, 
                            wasserstoff = wasserstoff - :cost_wasserstoff,
                            energie = energie - :cost_energie 
                          WHERE id = :planet_id";
            $stmtDeduct = $db->prepare($sqlDeduct);
            $stmtDeduct->bindValue(':cost_eisen', $costs['eisen']);
            $stmtDeduct->bindValue(':cost_silber', $costs['silber']);
            $stmtDeduct->bindValue(':cost_uderon', $costs['uderon']);
            $stmtDeduct->bindValue(':cost_wasserstoff', $costs['wasserstoff']);
            $stmtDeduct->bindValue(':cost_energie', $costs['energie']);
            $stmtDeduct->bindParam(':planet_id', $homePlanet->id, PDO::PARAM_INT);
            if (!$stmtDeduct->execute() || $stmtDeduct->rowCount() == 0) {
                throw new \Exception("Fehler beim Abziehen der Ressourcen vom Heimatplaneten.");
            }
            
            // 12. Update player_research table
            $finishTime = date('Y-m-d H:i:s', time() + $adjustedResearchTimeSeconds);
            $sqlUpdateResearch = "UPDATE player_research 
                                  SET is_under_research = 1, research_finish_time = :finish_time
                                  WHERE player_id = :player_id AND research_type_id = :research_type_id";
            $stmtUpdateResearch = $db->prepare($sqlUpdateResearch);
            $stmtUpdateResearch->bindParam(':finish_time', $finishTime, PDO::PARAM_STR);
            $stmtUpdateResearch->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmtUpdateResearch->bindParam(':research_type_id', $researchTypeId, PDO::PARAM_INT);
            if (!$stmtUpdateResearch->execute() || $stmtUpdateResearch->rowCount() == 0) {
                 throw new \Exception("Fehler beim Aktualisieren des Spieler-Forschungsstatus.");
            }

            // 13. Add to construction_queue
            $sqlQueue = "INSERT INTO construction_queue 
                         (player_id, planet_id, item_type, item_id, target_level_or_quantity, start_time, end_time, duration_seconds)
                         VALUES 
                         (:player_id, NULL, 'research', :item_id, :target_level, NOW(), :end_time, :duration)";
            $stmtQueue = $db->prepare($sqlQueue);
            $stmtQueue->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmtQueue->bindParam(':item_id', $researchTypeId, PDO::PARAM_INT);
            $stmtQueue->bindParam(':target_level', $targetLevel, PDO::PARAM_INT);
            $stmtQueue->bindParam(':end_time', $finishTime, PDO::PARAM_STR);
            $stmtQueue->bindParam(':duration', $adjustedResearchTimeSeconds, PDO::PARAM_INT);
            if (!$stmtQueue->execute()) {
                throw new \Exception("Fehler beim Hinzufügen zur Forschungswarteschlange.");
            }

            $db->commit();
            return ['success' => true, 'message' => $researchType->name_de . ' Forschung auf Stufe ' . $targetLevel . ' gestartet.'];

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Forschungsstart fehlgeschlagen für Spieler $playerId, Forschung $researchTypeId: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Complete research (this would be called by a cron job or on page load)
    public static function checkAndCompleteResearch() { // Removed $playerId parameter
        $db = self::getDB();
        $completedCount = 0;

        // Find completed research items in the construction queue
        $sql = "SELECT cq.*, rt.name_de
                FROM construction_queue cq
                JOIN static_research_types rt ON cq.item_id = rt.id
                JOIN players p ON cq.player_id = p.id -- Join to ensure player exists, or if other player data is needed later
                WHERE cq.item_type = 'research' 
                AND cq.end_time <= NOW()"; // Ensure we only process incomplete items

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $completedQueueItems = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        if (empty($completedQueueItems)) {
            return 0; // No research completed
        }        $db->beginTransaction();
        try {
            foreach ($completedQueueItems as $queueItem) {
                $targetLevel = $queueItem->target_level_or_quantity; // Target level is stored in queue

                // Find the corresponding player_research entry
                $sqlResearch = "SELECT * FROM player_research 
                                WHERE player_id = :player_id 
                                AND research_type_id = :research_type_id";
                $stmtResearch = $db->prepare($sqlResearch);
                $stmtResearch->bindParam(':player_id', $queueItem->player_id, PDO::PARAM_INT);
                $stmtResearch->bindParam(':research_type_id', $queueItem->item_id, PDO::PARAM_INT);
                $stmtResearch->execute();
                $playerResearch = $stmtResearch->fetch(PDO::FETCH_OBJ);

                if ($playerResearch) {
                    // Update player_research table: increase level, reset status
                    $sqlUpdate = "UPDATE player_research 
                                  SET level = :new_level, 
                                      is_under_research = 0, 
                                      research_finish_time = NULL
                                  WHERE id = :id";
                    $stmtUpdate = $db->prepare($sqlUpdate);
                    $stmtUpdate->bindParam(':new_level', $targetLevel, PDO::PARAM_INT); // Use target level from queue
                    $stmtUpdate->bindParam(':id', $playerResearch->id, PDO::PARAM_INT);
                    
                    if (!$stmtUpdate->execute() || $stmtUpdate->rowCount() == 0) {
                        // Log error but try to continue if possible, or throw to rollback all
                        error_log("checkAndCompleteResearch: Failed to update player_research for id: " . $playerResearch->id);
                        // Mark queue item as completed to prevent infinite loop
                         $sqlQueue = "UPDATE construction_queue 
                                     SET is_completed = TRUE, notes = CONCAT(COALESCE(notes, \'\'), \' | Error: Failed to update player_research.\')
                                     WHERE id = :id";
                        $stmtQueue = $db->prepare($sqlQueue);
                        $stmtQueue->bindParam(':id', $queueItem->id, PDO::PARAM_INT);
                        $stmtQueue->execute();
                        continue; // Skip this one
                    }
                    
                    // Send notification for completed research
                    NotificationService::researchCompleted(
                        $queueItem->player_id, // Player ID from the queue item
                        $queueItem->name_de, // Research name from the queue item
                        $targetLevel // New level
                    );
                    
                    // Mark item in construction_queue as completed
                    $sqlQueue = "UPDATE construction_queue 
                                 SET is_completed = TRUE 
                                 WHERE id = :id";
                    $stmtQueue = $db->prepare($sqlQueue);
                    $stmtQueue->bindParam(':id', $queueItem->id, PDO::PARAM_INT);
                    $stmtQueue->execute();

                    // Update player research points (e.g., 5 points per research level as per old code)
                    // This value (5) should ideally be configurable or part of static_research_types if it varies.
                    $pointsGained = 5; // Example
                    $sqlPoints = "UPDATE players 
                                  SET points_research = points_research + :points
                                  WHERE id = :player_id";
                    $stmtPoints = $db->prepare($sqlPoints);
                    $stmtPoints->bindParam(':points', $pointsGained, PDO::PARAM_INT);
                    $stmtPoints->bindParam(':player_id', $queueItem->player_id, PDO::PARAM_INT); // Player ID from queue item
                    
                    if (!$stmtPoints->execute()) {
                        error_log("Failed to update player points for research completion. Player ID: " . $queueItem->player_id);
                        // Potentially throw an exception
                    }
                    
                    $completedCount++;
                } else {
                    // Log error: corresponding player_research not found
                    error_log("checkAndCompleteResearch: PlayerResearch not found for queue item ID {$queueItem->id} (Player ID: {$queueItem->player_id}, Research Type ID: {$queueItem->item_id}). Marking queue item as completed to prevent infinite loop.");
                     $sqlQueue = "UPDATE construction_queue 
                                 SET is_completed = TRUE, notes = CONCAT(COALESCE(notes, \'\'), \' | Error: PlayerResearch not found.\')
                                 WHERE id = :id";
                    $stmtQueue = $db->prepare($sqlQueue);
                    $stmtQueue->bindParam(':id', $queueItem->id, PDO::PARAM_INT);
                    $stmtQueue->execute();
                }
            }
            $db->commit(); // Commit for the main successful transaction
        } catch (Exception $e) {
            $db->rollBack(); // Rollback for the main transaction due to an error
            error_log("Error completing research for queue item {$queueItem->id}: " . $e->getMessage());
            
            // Attempt to mark the queue item with an error status in a new, separate transaction
            try {
                $db->beginTransaction(); // Start a new transaction specifically for updating the queue item status
                $sqlQueueError = "UPDATE construction_queue 
                                SET is_completed = TRUE, notes = CONCAT(COALESCE(notes, ''), ' | Processing Error: ', :error_msg)
                                WHERE id = :id";
                $stmtQueueError = $db->prepare($sqlQueueError);
                $errorMsg = substr($e->getMessage(), 0, 100); // Limit error message length
                $stmtQueueError->bindParam(':error_msg', $errorMsg, PDO::PARAM_STR);
                $stmtQueueError->bindParam(':id', $queueItem->id, PDO::PARAM_INT);
                $stmtQueueError->execute();
                $db->commit(); // Commit the queue item status update
            } catch (Exception $innerEx) {
                $db->rollBack(); // Rollback the queue item status update if it fails
                error_log("CRITICAL: Failed to update queue item {$queueItem->id} with error status after a research completion error: " . $innerEx->getMessage());
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
}
?>
