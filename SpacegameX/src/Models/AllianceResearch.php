<?php
namespace Models;

use Core\Model;
use PDO;
use \\\\Exception; // Ensure Exception class is imported

// Add missing use statements
use Models\\\\Alliance; // Needed for treasury deduction and member count
use Models\\\\AllianceBuilding; // Needed for building requirements
use Models\\\\AllianceBuildingType; // Needed for building requirements
use Models\\\\AllianceResearchType; // Needed for research requirements
use Models\\\\NotificationService; // Needed for notifications


class AllianceResearch extends Model {
    public $id;
    public $alliance_id;
    public $research_type_id;
    public $level;
    public $is_under_research;
    public $research_finish_time;

    // Alliance research types and their bonuses
    // Note: This data should ideally come from a static data table or config.
    // Keeping it here for now as it was in the provided stub.
    const RESEARCH_TYPES = [
        'bergbauforschung' => [
            'description' => 'Erhöht die Produktion von Eisen, Silber, Uderon und Wasserstoff',
            'effects' => [
                'resource_production' => [0, 0.02, 0.04, 0.06, 0.08, 0.10, 0.12, 0.14, 0.16, 0.18, 0.20]
            ],
            'max_level' => 10
        ],
        'forschungstechnik' => [
            'description' => 'Reduziert die Forschungszeit für allen Spielern der Allianz',
            'effects' => [
                'research_time' => [0, 0.02, 0.04, 0.06, 0.08, 0.10, 0.12, 0.14, 0.16, 0.18, 0.20]
            ],
            'max_level' => 10
        ],
        'raumschifftechnik' => [
            'description' => 'Reduziert die Bauzeit von Schiffen für alle Spieler der Allianz',
            'effects' => [
                'ship_build_time' => [0, 0.02, 0.04, 0.06, 0.08, 0.10, 0.12, 0.14, 0.16, 0.18, 0.20]
            ],
            'max_level' => 10
        ],
        'spionagetechnik' => [
            'description' => 'Erhöht die Effektivität von Spionageaktionen und verbessert den Spionageschutz',
            'effects' => [
                'espionage_strength' => [0, 0.05, 0.10, 0.15, 0.20, 0.25, 0.30, 0.35, 0.40, 0.45, 0.50],
                'espionage_defense' => [0, 0.05, 0.10, 0.15, 0.20, 0.25, 0.30, 0.35, 0.40, 0.45, 0.50]
            ],
            'max_level' => 10
        ],
        'verteidigungstechnik' => [
            'description' => 'Verstärkt die planetaren Verteidigungsanlagen aller Allianzmitglieder',
            'effects' => [
                'defense_strength' => [0, 0.03, 0.06, 0.09, 0.12, 0.15, 0.18, 0.21, 0.24, 0.27, 0.30]
            ],
            'max_level' => 10
        ],
        'antriebstechnik' => [
            'description' => 'Erhöht die Geschwindigkeit aller Schiffe der Allianz',
            'effects' => [
                'ship_speed' => [0, 0.02, 0.04, 0.06, 0.08, 0.10, 0.12, 0.14, 0.16, 0.18, 0.20]
            ],
            'max_level' => 10
        ],
        'waffensystemtechnik' => [
            'description' => 'Verbessert die Waffensysteme aller Schiffe der Allianz',
            'effects' => [
                'weapon_strength' => [0, 0.02, 0.04, 0.06, 0.08, 0.10, 0.12, 0.14, 0.16, 0.18, 0.20]
            ],
            'max_level' => 10
        ]
    ];


    public static function getAllForAlliance($allianceId) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT ar.*, sart.internal_name, sart.name_de FROM alliance_research ar JOIN static_alliance_research_types sart ON ar.research_type_id = sart.id WHERE ar.alliance_id = :alliance_id');
        $stmt->bindParam(':alliance_id', $allianceId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function getByAllianceAndType($allianceId, $researchTypeId) {
        $db = self::getDB();
        $sql = 'SELECT ar.*, sart.internal_name, sart.name_de FROM alliance_research ar JOIN static_alliance_research_types sart ON ar.research_type_id = sart.id WHERE ar.alliance_id = :alliance_id AND ar.research_type_id = :research_type_id';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':alliance_id', $allianceId, PDO::PARAM_INT);
        $stmt->bindParam(':research_type_id', $researchTypeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    public static function create($allianceId, $researchTypeId, $level = 0) {
        $db = self::getDB();
        $sql = "INSERT INTO alliance_research (alliance_id, research_type_id, level) 
                VALUES (:alliance_id, :research_type_id, :level)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':alliance_id', $allianceId, PDO::PARAM_INT);
        $stmt->bindParam(':research_type_id', $researchTypeId, PDO::PARAM_INT);
        $stmt->bindParam(':level', $level, PDO::PARAM_INT);
        $stmt->execute();
        return $db->lastInsertId();
    }

    public function upgrade() {
        $db = self::getDB();
        $sql = "UPDATE alliance_research SET level = level + 1 WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    /**
     * Initialize research for a new alliance
     *
     * @param int $allianceId The alliance ID
     * @return bool Success status
     */
    public static function createInitialResearch($allianceId) {
        $db = self::getDB();
        
        // Get all alliance research types
        $researchTypes = AllianceResearchType::getAll();
        
        // Create all research types for this alliance at level 0
        foreach ($researchTypes as $researchType) {
            self::create($allianceId, $researchType->id);
        }
        
        return true;
    }
    
    /**
     * Calculate research time for alliance research
     * 
     * @param int $researchTypeId The research type ID
     * @param int $currentLevel Current research level
     * @param int $allianceMemberCount Number of members in the alliance
     * @return int Research time in seconds
     */
    public static function calculateResearchTime($researchTypeId, $currentLevel, $allianceMemberCount) {
        $researchType = AllianceResearchType::getById($researchTypeId);
        if (!$researchType) {
            return 0;
        }
        
        // Base time calculation
        $baseTime = $researchType->base_research_time ?? 86400; // Default 1 day
        
        // Level multiplier (higher levels take longer)
        $levelMultiplier = 1 + ($currentLevel * 0.5);
        
        // Member count bonus (more members = faster research)
        // Formula from MD: (Anzahl der Mitglieder / 10) * 1% Reduktion
        $memberReductionPercentage = ($allianceMemberCount / 10) * 0.01;
        $memberBonusFactor = 1 - min(0.5, $memberReductionPercentage); // Cap reduction at 50%
        
        return ceil($baseTime * $levelMultiplier * $memberBonusFactor);
    }
    
    /**
     * Calculate resource cost for alliance research
     * 
     * @param int $researchTypeId The research type ID
     * @param int $currentLevel Current research level
     * @param int $allianceMemberCount Number of members in the alliance
     * @return array Resource costs array
     */
    public static function calculateResearchCost($researchTypeId, $currentLevel, $allianceMemberCount) {
        $researchType = AllianceResearchType::getById($researchTypeId);
        if (!$researchType) {
            return ['eisen' => 0, 'silber' => 0, 'uderon' => 0, 'wasserstoff' => 0];
        }
        
        // Get base costs
        $baseCosts = [
            'eisen' => $researchType->base_cost_eisen,
            'silber' => $researchType->base_cost_silber,
            'uderon' => $researchType->base_cost_uderon,
            'wasserstoff' => $researchType->base_cost_wasserstoff
        ];
        
        // Level multiplier (higher levels cost more)
        $levelMultiplier = pow(1.7, $currentLevel);
        
        // Member count multiplier (more members = higher costs)
        // Formula from MD: (Anzahl der Mitglieder / 10) * 1% Erhöhung
        $memberIncreasePercentage = ($allianceMemberCount / 10) * 0.01;
        $memberMultiplier = 1 + $memberIncreasePercentage;
        
        $finalCosts = [];
        foreach ($baseCosts as $resource => $cost) {
            $finalCosts[$resource] = ceil($cost * $levelMultiplier * $memberMultiplier);
        }
        
        return $finalCosts;
    }
    
    /**
     * Start a new alliance research project
     * 
     * @param int $allianceId The alliance ID
     * @param int $researchTypeId The research type ID
     * @return bool Success status
     */
    public static function startResearch($allianceId, $researchTypeId) {
        $db = self::getDB();
        $db->beginTransaction();
        
        try {
            // Check if the alliance is already researching something
            $sql = "SELECT COUNT(*) FROM alliance_research 
                   WHERE alliance_id = :alliance_id AND is_under_research = 1";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':alliance_id', $allianceId, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Alliance is already researching something.");
            }
            
            // Get the current research level
            $research = self::getByAllianceAndType($allianceId, $researchTypeId);
            if (!$research) {
                // This should not happen if createInitialResearch is called for new alliances
                throw new Exception("Research type not initialized for this alliance.");
            }
            
            // Check if already at max level
            $researchType = AllianceResearchType::getById($researchTypeId);
            if (!$researchType) {
                 throw new Exception("Invalid research type ID.");
            }
            if ($researchType->max_level !== null && $research->level >= $researchType->max_level) {
                throw new Exception("Research already at maximum level ({$research->level}).");
            }
            
            // Check requirements for the next level
            $targetLevel = $research->level + 1;
            if ($researchType->requirements_json) {
                $requirements = json_decode($researchType->requirements_json, true);

                // Check Alliance Building requirements
                if (isset($requirements['alliance_building'])) {
                    $allianceBuildingLevels = AllianceBuilding::getBuildingLevelsForAlliance($allianceId);
                    foreach ($requirements['alliance_building'] as $internalName => $levelRequired) {
                        $requiredBuildingType = AllianceBuildingType::getByInternalName($internalName);
                        if ($requiredBuildingType) {
                            $currentBuildingLevel = $allianceBuildingLevels[$requiredBuildingType->id] ?? 0;
                            if ($currentBuildingLevel < $levelRequired) {
                                throw new Exception("Requirement not met: Alliance building '{$requiredBuildingType->name_de}' Level {$levelRequired} needed for Research '{$researchType->name_de}' Level {$targetLevel}.");
                            }
                        } else {
                             error_log("AllianceResearch::startResearch: Required alliance building type not found: {$internalName}");
                        }
                    }
                }

                // Check Alliance Research requirements
                if (isset($requirements['alliance_research'])) {
                    $allianceResearchLevels = self::getAllForAlliance($allianceId);
                    $allianceResearchMap = [];
                    foreach ($allianceResearchLevels as $ar) {
                        $allianceResearchMap[$ar->research_type_id] = $ar->level;
                    }
                    foreach ($requirements['alliance_research'] as $internalName => $levelRequired) {
                        $requiredResearchType = AllianceResearchType::getByInternalName($internalName);
                        if ($requiredResearchType) {
                            $currentResearchLevel = $allianceResearchMap[$requiredResearchType->id] ?? 0;
                            if ($currentResearchLevel < $levelRequired) {
                                throw new Exception("Requirement not met: Alliance research '{$requiredResearchType->name_de}' Level {$levelRequired} needed for Research '{$researchType->name_de}' Level {$targetLevel}.");
                            }
                        } else {
                             error_log("AllianceResearch::startResearch: Required alliance research type not found: {$internalName}");
                        }
                    }
                }
            }

            // Get alliance details for member count and treasury
            $alliance = Alliance::findById($allianceId);
            if (!$alliance) {
                throw new Exception("Alliance not found.");
            }
            
            $memberCount = count($alliance->getMembers()); // Use getMembers() to get actual count

            // Calculate research time and cost
            $researchTime = self::calculateResearchTime($researchTypeId, $research->level, $memberCount);
            $researchCost = self::calculateResearchCost($researchTypeId, $research->level, $memberCount);
            
            // Check if alliance treasury has enough resources
            $hasEnoughResources = true;
            $missingResources = [];
            foreach ($researchCost as $resType => $cost) {
                if ($alliance->$resType < $cost) {
                    $hasEnoughResources = false;
                    $missingResources[] = ucfirst($resType) . " (" . number_format($cost) . " needed)";
                }
            }

            if (!$hasEnoughResources) {
                throw new Exception("Not enough resources in alliance treasury: " . implode(', ', $missingResources));
            }

            // Deduct resources from the treasury
            foreach ($researchCost as $resType => $cost) {
                if ($cost > 0) {
                    $alliance->deductResourcesFromTreasury($resType, $cost);
                }
            }
            
            // Update the research entry to start research
            $finishTime = date('Y-m-d H:i:s', time() + $researchTime);
            $sql = "UPDATE alliance_research 
                   SET is_under_research = 1, research_finish_time = :finish_time 
                   WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':finish_time', $finishTime);
            $stmt->bindParam(':id', $research->id, PDO::PARAM_INT);
            $stmt->execute();
            
            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Alliance research start error for alliance {$allianceId}, research type {$researchTypeId}: " . $e->getMessage());
            // Re-throw the exception so the controller can catch it and show an error message
            throw $e; 
        }
    }

    /**
     * Process completed alliance research projects.
     * This method should be called by a cron job or game update script.
     */
    public static function processCompletedResearch() {
        $db = self::getDB();
        
        // Find research projects that are completed
        $sql = "SELECT ar.*, sart.name_de, a.name as alliance_name
                FROM alliance_research ar
                JOIN static_alliance_research_types sart ON ar.research_type_id = sart.id
                JOIN alliances a ON ar.alliance_id = a.id
                WHERE ar.is_under_research = 1 AND ar.research_finish_time <= NOW()";
                
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $completedResearch = $stmt->fetchAll(PDO::FETCH_OBJ);

        $processedCount = 0;

        foreach ($completedResearch as $research) {
            $db->beginTransaction();
            try {
                // Increment the research level
                $sql = "UPDATE alliance_research 
                       SET level = level + 1, is_under_research = 0, research_finish_time = NULL 
                       WHERE id = :id";
                $stmtUpdate = $db->prepare($sql);
                $stmtUpdate->bindParam(':id', $research->id, PDO::PARAM_INT);
                $stmtUpdate->execute();

                // Notify alliance members
                $alliance = Alliance::findById($research->alliance_id, $db); // Pass DB connection
                if ($alliance) {
                    $members = $alliance->getMembers();
                    $message = "Allianzforschung '{$research->name_de}' wurde auf Stufe {$research->level + 1} abgeschlossen!";
                    foreach ($members as $member) {
                        NotificationService::createNotification($member->id, 'Allianzforschung Abgeschlossen', $message, 'info');
                    }
                } else {
                    error_log("AllianceResearch::processCompletedResearch: Alliance ID {$research->alliance_id} not found for completed research ID {$research->id}.");
                }

                $db->commit();
                $processedCount++;
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error processing completed alliance research ID {$research->id}: " . $e->getMessage());
                // Optionally mark research as errored if needed
            }
        }
        return $processedCount;
    }
    
    /**
     * Get the resource production bonus for an alliance member
     * 
     * @param int $allianceId The alliance ID
     * @param PDO $db Database connection
     * @return float Production multiplier (1.0 = no bonus)
     */
    public static function getResourceProductionBonus($allianceId, PDO $db) {
        $bergbauForschungType = AllianceResearchType::getByInternalName('bergbauforschung', $db);
        if (!$bergbauForschungType) {
            return 1.0;
        }
        
        $research = self::getByAllianceAndType($allianceId, $bergbauForschungType->id);
        if (!$research) {
            return 1.0;
        }
        
        $level = $research->level;
        // Bonus values from RESEARCH_TYPES constant (index corresponds to level)
        $bonusValues = self::RESEARCH_TYPES['bergbauforschung']['effects']['resource_production'] ?? [];
        
        return 1.0 + ($bonusValues[$level] ?? 0);
    }
    
    /**
     * Get the research time reduction for an alliance member
     * 
     * @param int $allianceId The alliance ID
     * @param PDO $db Database connection
     * @return float Time multiplier (lower is better, 1.0 = no reduction)
     */
    public static function getResearchTimeReduction($allianceId, PDO $db) {
        $forschungsTechnikType = AllianceResearchType::getByInternalName('forschungstechnik', $db);
        if (!$forschungsTechnikType) {
            return 1.0;
        }
        
        $research = self::getByAllianceAndType($allianceId, $forschungsTechnikType->id);
        if (!$research) {
            return 1.0;
        }
        
        $level = $research->level;
        // Reduction values from RESEARCH_TYPES constant (index corresponds to level)
        $reductionValues = self::RESEARCH_TYPES['forschungstechnik']['effects']['research_time'] ?? [];
        
        return 1.0 - min(0.5, $reductionValues[$level] ?? 0); // Max 50% reduction
    }
    
    /**
     * Get the ship build time reduction for an alliance member
     * 
     * @param int $allianceId The alliance ID
     * @param PDO $db Database connection
     * @return float Time multiplier (lower is better, 1.0 = no reduction)
     */
    public static function getShipBuildTimeReduction($allianceId, PDO $db) {
        $schiffstechnikType = AllianceResearchType::getByInternalName('raumschifftechnik', $db);
        if (!$schiffstechnikType) {
            return 1.0;
        }
        
        $research = self::getByAllianceAndType($allianceId, $schiffstechnikType->id);
        if (!$research) {
            return 1.0;
        }
        
        $level = $research->level;
        // Reduction values from RESEARCH_TYPES constant (index corresponds to level)
        $reductionValues = self::RESEARCH_TYPES['raumschifftechnik']['effects']['ship_build_time'] ?? [];
        
        return 1.0 - min(0.5, $reductionValues[$level] ?? 0); // Max 50% reduction
    }
    
    /**
     * Get the defense strength bonus for an alliance member
     * 
     * @param int $allianceId The alliance ID
     * @param PDO $db Database connection
     * @return float Strength multiplier (1.0 = no bonus)
     */
    public static function getDefenseStrengthBonus($allianceId, PDO $db) {
        $defenseTechType = AllianceResearchType::getByInternalName('verteidigungstechnik', $db);
        if (!$defenseTechType) {
            return 1.0;
        }
        
        $research = self::getByAllianceAndType($allianceId, $defenseTechType->id);
        if (!$research) {
            return 1.0;
        }
        
        $level = $research->level;
        // Bonus values from RESEARCH_TYPES constant (index corresponds to level)
        $bonusValues = self::RESEARCH_TYPES['verteidigungstechnik']['effects']['defense_strength'] ?? [];
        
        return 1.0 + ($bonusValues[$level] ?? 0);
    }
    
    /**
     * Get the ship speed bonus for an alliance member
     * 
     * @param int $allianceId The alliance ID
     * @param PDO $db Database connection
     * @return float Speed multiplier (1.0 = no bonus)
     */
    public static function getShipSpeedBonus($allianceId, PDO $db) {
        $antriebsTechType = AllianceResearchType::getByInternalName('antriebstechnik', $db);
        if (!$antriebsTechType) {
            return 1.0;
        }
        
        $research = self::getByAllianceAndType($allianceId, $antriebsTechType->id);
        if (!$research) {
            return 1.0;
        }
        
        $level = $research->level;
        // Bonus values from RESEARCH_TYPES constant (index corresponds to level)
        $bonusValues = self::RESEARCH_TYPES['antriebstechnik']['effects']['ship_speed'] ?? [];
        
        return 1.0 + ($bonusValues[$level] ?? 0);
    }
    
    /**
     * Get the weapon strength bonus for an alliance member
     * 
     * @param int $allianceId The alliance ID
     * @param PDO $db Database connection
     * @return float Strength multiplier (1.0 = no bonus)
     */
    public static function getWeaponStrengthBonus($allianceId, PDO $db) {
        $weaponTechType = AllianceResearchType::getByInternalName('waffensystemtechnik', $db);
        if (!$weaponTechType) {
            return 1.0;
        }
        
        $research = self::getByAllianceAndType($allianceId, $weaponTechType->id);
        if (!$research) {
            return 1.0;
        }
        
        $level = $research->level;
        // Bonus values from RESEARCH_TYPES constant (index corresponds to level)
        $bonusValues = self::RESEARCH_TYPES['waffensystemtechnik']['effects']['weapon_strength'] ?? [];
        
        return 1.0 + ($bonusValues[$level] ?? 0);
    }
    
    /**
     * Get espionage-related bonuses for an alliance member
     * 
     * @param int $allianceId The alliance ID
     * @param PDO $db Database connection
     * @return array [strength_bonus, defense_bonus]
     */
    public static function getEspionageBonuses($allianceId, PDO $db) {
        $espionageTechType = AllianceResearchType::getByInternalName('spionagetechnik', $db);
        if (!$espionageTechType) {
            return [1.0, 1.0];
        }
        
        $research = self::getByAllianceAndType($allianceId, $espionageTechType->id);
        if (!$research) {
            return [1.0, 1.0];
        }
        
        $level = $research->level;
        // Bonus values from RESEARCH_TYPES constant (index corresponds to level)
        $strengthBonusValues = self::RESEARCH_TYPES['spionagetechnik']['effects']['espionage_strength'] ?? [];
        $defenseBonusValues = self::RESEARCH_TYPES['spionagetechnik']['effects']['espionage_defense'] ?? [];
        
        $strengthBonus = 1.0 + ($strengthBonusValues[$level] ?? 0);
        $defenseBonus = 1.0 + ($defenseBonusValues[$level] ?? 0);
        
        return [$strengthBonus, $defenseBonus];
    }
}
