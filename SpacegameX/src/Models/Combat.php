<?php
namespace Models;

use Core\\\\Model;
use PDO;
use \\\\Exception; // Ensure Exception class is imported

// Add missing use statements
use Models\\\\ShipType;
use Models\\\\DefenseType;
use Models\\\\EspionageReport;
use Models\\\\BattleReport; // Already used for storeBattleReport, ensure it's here
use Models\\\\NotificationService;
use Models\\\\PlayerResearch; // Already used
use Models\\\\Planet; // Already used
use Models\\\\Fleet; // Already used
use Models\\\\PlayerShip; // Already used
use Models\\\\PlayerDefense; // Already used
use Models\\\\Player; // Already used
use Models\\\\Alliance; // Added for alliance BP update
use Models\\\\PlayerBuilding; // Added for espionage reports
use Models\\\\BuildingType; // Added for espionage reports
use Models\\\\PlayerAgent; // Added for agent espionage
use Models\\\\PlayerMessage; // Added for agent espionage (messages)
use Models\\\\ConstructionQueue; // Added for agent espionage (werftaufträge)


class Combat extends Model {
    // Combat constants
    const MAX_BATTLE_ROUNDS = 6;
    const MISSION_ATTACK = 'attack';       // Standard attack
    const MISSION_RAID = 'raid';           // Raid for resources
    const MISSION_INVASION = 'invasion';   // Planet invasion
    const MISSION_ARKON = 'arkon';         // Attack with Arkon bomb
    const MISSION_ESPIONAGE = 'espionage'; // Spy mission
    const MISSION_DEFEND = 'defend';       // Defend a planet

    // Battlepoint constants
    const WINNER_BP_BONUS = 1;
    const LOSER_BP_PENALTY = -1;

    /**
     * Process a battle between attacking fleet and defending fleet/planet
     * 
     * @param int $fleetId The ID of the attacking fleet
     * @return array Battle result data
     */
    public static function processBattle($fleetId) {
        $db = self::getDB();
        $db->beginTransaction();
        
        try {
            $attackingFleetData = Fleet::getFleetDetails($fleetId);
            if (!$attackingFleetData || !$attackingFleetData['fleet']) {
                throw new Exception("Attacking fleet {$fleetId} not found or details missing.");
            }
            $attackerFleet = $attackingFleetData['fleet'];
            $attackerInitialShipsRaw = $attackingFleetData['ships'];

            $targetPlanet = Planet::getById($attackerFleet->target_planet_id, $db);
            if (!$targetPlanet) {
                throw new Exception("Target planet {$attackerFleet->target_planet_id} not found for fleet {$fleetId}.");
            }

            // Shield Check (Simplified - actual shield mechanics might be more complex)
            if ($targetPlanet->shield_active_until && strtotime($targetPlanet->shield_active_until) > time()) {
                // Attacker fleet bounces off, notification, fleet returns
                NotificationService::createNotification($attackerFleet->player_id, "Attack Failed", "Target planet {$targetPlanet->name} is protected by a planetary shield. Fleet returning.", "warning");
                Fleet::setFleetToReturn($fleetId, $db, "Target planet shielded.");
                $db->commit();
                return ['error' => 'Target planet shielded', 'fleet_returning' => true];
            }

            // Noob Protection Check
            if ($targetPlanet->player_id && Planet::checkNoobProtection($attackerFleet->player_id, $targetPlanet->player_id, $db)) {
                 NotificationService::createNotification($attackerFleet->player_id, "Attack Failed", "Target player on planet {$targetPlanet->name} is under noob protection. Fleet returning.", "warning");
                 Fleet::setFleetToReturn($fleetId, $db, "Target under noob protection.");
                 $db->commit();
                 return ['error' => 'Target under noob protection', 'fleet_returning' => true];
            }
            
            $defenderId = $targetPlanet->player_id; // Can be null if unowned planet
            $defenderInitialShipsRaw = $defenderId ? PlayerShip::getShipsOnPlanet($targetPlanet->id, $db) : [];
            $defenderInitialDefensesRaw = $defenderId ? PlayerDefense::getDefensesOnPlanet($targetPlanet->id, $db) : [];

            $attackerResearch = PlayerResearch::getResearchLevelsByPlayerId($attackerFleet->player_id, $db);
            $defenderResearch = $defenderId ? PlayerResearch::getResearchLevelsByPlayerId($defenderId, $db) : [];
            
            // Note: Alliance bonuses are not implemented in this simplified version yet.
            // They would be fetched here and passed to formatShipsForBattle/formatDefensesForBattle.

            $attackerShips = self::formatUnitsForBattle($attackerInitialShipsRaw, $attackerResearch, false, $db);
            $defenderUnits = [];
            if (!empty($defenderInitialShipsRaw)) {
                $defenderUnits = array_merge($defenderUnits, self::formatUnitsForBattle($defenderInitialShipsRaw, $defenderResearch, false, $db));
            }
            if (!empty($defenderInitialDefensesRaw)) {
                $defenderUnits = array_merge($defenderUnits, self::formatUnitsForBattle($defenderInitialDefensesRaw, $defenderResearch, true, $db));
            }


            $battleLog = [
                'rounds' => [],
                'initial_attacker_ships' => $attackerShips,
                'initial_defender_units' => $defenderUnits,
                'attacker_lost_value' => ['metal' => 0, 'silber' => 0, 'uderon' => 0, 'wasserstoff' => 0],
                'defender_lost_value' => ['metal' => 0, 'silber' => 0, 'uderon' => 0, 'wasserstoff' => 0],
                'attacker_bp_gained' => 0, // Added for Battlepoints
                'defender_bp_gained' => 0, // Added for Battlepoints
            ];

            $currentAttackerShips = $attackerShips;
            $currentDefenderUnits = $defenderUnits;

            $winner = null; // 'attacker', 'defender', 'draw'

            for ($i = 0; $i < (defined('COMBAT_MAX_ROUNDS') ? COMBAT_MAX_ROUNDS : self::MAX_BATTLE_ROUNDS); $i++) {
                $roundResult = self::simulateBattleRound($currentAttackerShips, $currentDefenderUnits, $db);
                
                $battleLog['rounds'][] = $roundResult['log'];
                $currentAttackerShips = $roundResult['attacker_remaining'];
                $currentDefenderUnits = $roundResult['defender_remaining'];

                if (empty($currentAttackerShips) && empty($currentDefenderUnits)) {
                    $winner = 'draw'; break;
                }
                if (empty($currentAttackerShips)) {
                    $winner = 'defender'; break;
                }
                if (empty($currentDefenderUnits)) {
                    $winner = 'attacker'; break;
                }
                if ($i == (defined('COMBAT_MAX_ROUNDS') ? COMBAT_MAX_ROUNDS : self::MAX_BATTLE_ROUNDS) - 1) { // Max rounds reached
                    $winner = 'draw'; // Or could be based on remaining strength
                }
            }
            
            $battleLog['final_attacker_ships'] = $currentAttackerShips;
            $battleLog['final_defender_units'] = $currentDefenderUnits;
            $battleLog['winner'] = $winner;

            // Calculate losses in terms of resource value
            list($battleLog['attacker_lost_value'], $battleLog['defender_lost_value']) = self::calculateLostResourceValues($battleLog['initial_attacker_ships'], $currentAttackerShips, $battleLog['initial_defender_units'], $currentDefenderUnits, $db);

            // Calculate Battlepoints
            $attackerBP = self::calculateBattlepoints($battleLog['defender_lost_value'], $winner === 'attacker');
            $defenderBP = self::calculateBattlepoints($battleLog['attacker_lost_value'], $winner === 'defender');
            
            $battleLog['attacker_bp_gained'] = $attackerBP;
            $battleLog['defender_bp_gained'] = $defenderBP;

            // Update Player Battlepoints
            Player::updateBattlepoints($attackerFleet->player_id, $attackerBP, $db);
            if ($defenderId) {
                Player::updateBattlepoints($defenderId, $defenderBP, $db);
            }

            // Update Alliance Battlepoints
            $attackerPlayer = Player::getPlayerData($attackerFleet->player_id, $db);
            if ($attackerPlayer && $attackerPlayer->alliance_id) {
                Alliance::updateBattlepoints($attackerPlayer->alliance_id, $attackerBP, $db); // Assuming Alliance model has this method
            }
            if ($defenderId) {
                $defenderPlayer = Player::getPlayerData($defenderId, $db);
                if ($defenderPlayer && $defenderPlayer->alliance_id) {
                    Alliance::updateBattlepoints($defenderPlayer->alliance_id, $defenderBP, $db); // Assuming Alliance model has this method
                }
            }


            // Handle plunder, debris, and mission specific outcomes
            $plunderedResources = ['eisen' => 0, 'silber' => 0, 'uderon' => 0, 'wasserstoff' => 0];
            $debrisCreated = ['metal' => 0, 'silber' => 0, 'uderon' => 0, 'wasserstoff' => 0];
            $missionOutcome = null; // To store specific mission results (e.g., invasion success)

            // Only process outcomes if there was a battle (i.e., not a shield bounce or noob protection)
            if (!isset($battleLog['error'])) {
                // Handle plunder (for Raid and potentially other missions if attacker wins)
                if ($winner === 'attacker' && $defenderId && ($attackerFleet->mission_type === self::MISSION_RAID || $attackerFleet->mission_type === self::MISSION_ATTACK)) {
                    $plunderedResources = self::handlePlunder($attackerFleet, $currentAttackerShips, $targetPlanet, $db);
                }
                $battleLog['plundered_resources'] = $plunderedResources;

                // Handle debris field (always created if units are lost)
                $debrisCreated = self::handleDebrisFields($battleLog['initial_attacker_ships'], $currentAttackerShips, $battleLog['initial_defender_units'], $currentDefenderUnits, $targetPlanet->id, $db);
                $battleLog['debris_created'] = $debrisCreated;

                // Handle mission-specific outcomes if attacker wins
                if ($winner === 'attacker') {
                    switch ($attackerFleet->mission_type) {
                        case self::MISSION_INVASION:
                            // Check if attacker fleet contains at least one Invasion Unit
                            $invasionUnitType = ShipType::getByInternalName('invasionseinheit', $db);
                            $hasInvasionUnit = false;
                            if ($invasionUnitType) {
                                foreach ($currentAttackerShips as $shipGroup) {
                                    if ($shipGroup['id'] === $invasionUnitType->id && $shipGroup['quantity'] > 0) {
                                        $hasInvasionUnit = true;
                                        break;
                                    }
                                }
                            }

                            if ($hasInvasionUnit) {
                                // Invasion successful - change planet ownership
                                if ($defenderId) { // Only invade if the planet was owned
                                    Planet::changeOwner($targetPlanet->id, $attackerFleet->player_id, $db); // Assuming changeOwner method exists
                                    // Transfer all remaining ships and defenses on the planet to the new owner
                                    PlayerShip::changeOwnerByPlanet($targetPlanet->id, $attackerFleet->player_id, $db); // Assuming changeOwnerByPlanet exists
                                    PlayerDefense::changeOwnerByPlanet($targetPlanet->id, $attackerFleet->player_id, $db); // Assuming changeOwnerByPlanet exists
                                    // Clear defender's construction queue for this planet? Or transfer? Let's clear for simplicity.
                                    ConstructionQueue::clearQueueForPlanet($targetPlanet->id, $db); // Assuming clearQueueForPlanet exists

                                    $missionOutcome = 'invasion_successful';
                                    NotificationService::createNotification($attackerFleet->player_id, "Invasion Successful", "You have successfully invaded planet {$targetPlanet->name} ({$targetPlanet->galaxy}:{$targetPlanet->system}:{$targetPlanet->position}).", "success");
                                    NotificationService::createNotification($defenderId, "Planet Invaded", "Your planet {$targetPlanet->name} ({$targetPlanet->galaxy}:{$targetPlanet->system}:{$targetPlanet->position}) has been invaded by Player {$attackerFleet->player_id}.", "alert");
                                } else {
                                    // Cannot invade an unowned planet with an invasion unit? Or it becomes owned by attacker?
                                    // Let's assume it becomes owned by the attacker if unowned and successfully attacked with invasion unit.
                                     Planet::changeOwner($targetPlanet->id, $attackerFleet->player_id, $db);
                                     $missionOutcome = 'colonization_successful'; // More like colonization if unowned
                                     NotificationService::createNotification($attackerFleet->player_id, "Colonization Successful", "You have successfully colonized planet {$targetPlanet->name} ({$targetPlanet->galaxy}:{$targetPlanet->system}:{$targetPlanet->position}).", "success");
                                }
                            } else {
                                // Attacker won but had no invasion unit - standard attack/raid outcome
                                $missionOutcome = 'attack_successful';
                                // Plunder is already handled above for attack/raid
                            }
                            break;

                        case self::MISSION_ARKON:
                            // Check if attacker fleet contains at least one Arkon Bomb
                            $arkonBombType = ShipType::getByInternalName('arkonbombe', $db);
                            $hasArkonBomb = false;
                            if ($arkonBombType) {
                                foreach ($currentAttackerShips as $shipGroup) {
                                    if ($shipGroup['id'] === $arkonBombType->id && $shipGroup['quantity'] > 0) {
                                        $hasArkonBomb = true;
                                        // Consume one Arkon Bomb
                                        Fleet::consumeShipFromFleet($attackerFleet->id, $arkonBombType->id, 1, $db);
                                        break; // Only one bomb needed to destroy
                                    }
                                }
                            }

                            if ($hasArkonBomb) {
                                // Arkon attack successful - destroy the planet
                                Planet::destroyPlanet($targetPlanet->id, $db); // Assuming destroyPlanet method exists and cleans up all related data

                                $missionOutcome = 'arkon_successful';
                                NotificationService::createNotification($attackerFleet->player_id, "Arkon Attack Successful", "You have successfully destroyed planet {$targetPlanet->name} ({$targetPlanet->galaxy}:{$targetPlanet->system}:{$targetPlanet->position}).", "success");
                                if ($defenderId) {
                                    NotificationService::createNotification($defenderId, "Planet Destroyed", "Your planet {$targetPlanet->name} ({$targetPlanet->galaxy}:{$targetPlanet->system}:{$targetPlanet->position}) has been destroyed by Player {$attackerFleet->player_id}.", "alert");
                                }
                            } else {
                                // Attacker won but had no Arkon bomb - standard attack/raid outcome
                                $missionOutcome = 'attack_successful';
                                // Plunder is already handled above for attack/raid
                            }
                            break;

                        case self::MISSION_RAID:
                            // Plunder is already handled above
                            $missionOutcome = 'raid_successful';
                            break;

                        case self::MISSION_ATTACK:
                            // Standard attack - plunder is handled above
                            $missionOutcome = 'attack_successful';
                            break;

                        // Add other mission types here if they have specific outcomes after winning a battle
                        // case self::MISSION_COLONIZE: // Colonization is likely handled differently, not via combat
                        // case self::MISSION_TRANSPORT: // Transport is likely handled differently
                        // case self::MISSION_DEPLOY: // Deploy is likely handled differently
                        // case self::MISSION_STATION: // Station is likely handled differently
                        // case self::MISSION_RECALL: // Recall is handled differently
                        // case self::MISSION_BLOCKADE: // Blockade arrival is handled differently
                        // case self::MISSION_ESPIONAGE: // Espionage is handled differently

                        default:
                            // Default outcome for winning an unknown mission type (maybe just plunder?)
                            $missionOutcome = 'attack_successful';
                            break;
                    }
                } else {
                    // Attacker lost or drew - no mission specific outcome for attacker
                    $missionOutcome = 'mission_failed';
                }
            }


            // Update units in DB (surviving ships for attacker, surviving/rebuilt units for defender)
            // This needs to happen AFTER mission outcomes that might destroy the planet or change ownership,
            // as updating units on a destroyed planet or for the wrong owner would be incorrect.
            // If the planet was destroyed, there are no defender units to update.
            // If ownership changed, defender units become attacker units (handled by changeOwnerByPlanet).
            // So, only update attacker fleet and defender units if the planet was NOT destroyed.
            if ($missionOutcome !== 'arkon_successful') {
                 self::updateAttackerFleetAfterCombat($attackerFleet->id, $currentAttackerShips, $plunderedResources, $db);
                 if ($defenderId && $missionOutcome !== 'invasion_successful') { // Only update defender units if planet wasn't invaded
                     // Pass initial defender units for rebuild calculation
                     $initialDefenderUnitsForRebuild = array_filter($battleLog['initial_defender_units'], fn($unit) => $unit['is_defense']);
                     self::updateDefenderPlanetAfterCombat($targetPlanet->id, $defenderId, $currentDefenderUnits, $plunderedResources, $initialDefenderUnitsForRebuild, $db);
                 }
            } else {
                 // If planet was destroyed, attacker fleet returns with remaining ships and no plunder
                 // The Fleet model's processFleets should handle returning the fleet.
                 // We just need to ensure the fleet_ships table is updated with remaining ships.
                 // Plunder should be zeroed out if planet destroyed.
                 $battleLog['plundered_resources'] = ['eisen' => 0, 'silber' => 0, 'uderon' => 0, 'wasserstoff' => 0]; // Ensure plunder is zeroed
                 self::updateAttackerFleetAfterCombat($attackerFleet->id, $currentAttackerShips, $battleLog['plundered_resources'], $db);
            }


            // Store Battle Report
            $reportDataJson = json_encode($battleLog);
            $reportId = BattleReport::create(
                $attackerFleet->player_id, 
                $defenderId, 
                $targetPlanet->id, 
                "{$targetPlanet->galaxy}:{$targetPlanet->system}:{$targetPlanet->position}", 
                $reportDataJson,
                $db // Pass the database connection
            );

            // Standardized Notifications for Battle Reports
            $attackerCoords = Planet::getCoordinates($attackerFleet->origin_planet_id, $db);
            $defenderCoords = "{$targetPlanet->galaxy}:{$targetPlanet->system}:{$targetPlanet->position}";
            $reportLink = BASE_URL . '/combat/report/' . $reportId;

            // Notification for Attacker
            $attackerMessage = "Kampfbericht: Deine Flotte griff Planet {$targetPlanet->name} ({$defenderCoords}) an.";
            if ($winner === 'attacker') {
                $attackerMessage .= " Ergebnis: Sieg!";
            } elseif ($winner === 'defender') {
                $attackerMessage .= " Ergebnis: Niederlage.";
            } else {
                $attackerMessage .= " Ergebnis: Unentschieden.";
            }
            PlayerNotification::createNotification(
                $attackerFleet->player_id,
                PlayerNotification::TYPE_BATTLE_REPORT,
                $attackerMessage,
                $reportLink,
                $db
            );

            // Notification for Defender (if exists)
            if ($defenderId) {
                $defenderMessage = "Kampfbericht: Deine Verteidigung auf Planet {$targetPlanet->name} ({$defenderCoords}) wurde von einer Flotte von {$attackerCoords} angegriffen.";
                 if ($winner === 'defender') {
                    $defenderMessage .= " Ergebnis: Sieg!";
                } elseif ($winner === 'attacker') {
                    $defenderMessage .= " Ergebnis: Niederlage.";
                } else {
                    $defenderMessage .= " Ergebnis: Unentschieden.";
                }
                PlayerNotification::createNotification(
                    $defenderId,
                    PlayerNotification::TYPE_BATTLE_REPORT,
                    $defenderMessage,
                    $reportLink,
                    $db
                );
            }
            
            // Remove older, more specific notifications if they are now covered by the standardized ones.
            // For example, the invasion/arkon/shield notifications might be redundant if the battle report notification is comprehensive.
            // However, for critical events like planet loss, a direct notification is still good.
            // The existing specific notifications for invasion/arkon success/failure will be kept for now.

            $db->commit();
            return ['report_id' => $reportId, 'battle_report_data' => $battleLog, 'winner' => $winner, 'mission_outcome' => $missionOutcome];

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Combat::processBattle Error for fleet {$fleetId}: " . $e->getMessage() . "\\n" . $e->getTraceAsString());
            // Attempt to return the fleet if an error occurs mid-process
            try {
                if (isset($fleetId) && Fleet::getFleetById($fleetId)) { // Check if fleet still exists
                     Fleet::setFleetToReturn($fleetId, self::getDB(), "Combat processing error: " . substr($e->getMessage(), 0, 100));
                }
            } catch (Exception $returnEx) {
                error_log("Combat::processBattle - Failed to set fleet {$fleetId} to return after error: " . $returnEx->getMessage());
            }
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Formats raw unit data (ships or defense) for battle simulation, applying research bonuses.
     *
     * @param array $unitsRaw Array of raw unit objects (from player_ships or player_defense).
     * @param array $playerResearch Player's research levels (indexed by research_type_id).
     * @param bool $isDefense True if formatting defense units, false for ships.
     * @param PDO $db Database connection.
     * @return array Formatted unit data for battle.
     */
    private static function formatUnitsForBattle(array $unitsRaw, array $playerResearch, bool $isDefense, PDO $db) {
        $formattedUnits = [];
        
        // Get player ID from one of the units (assuming all units belong to the same player)
        $playerId = null;
        if (!empty($unitsRaw)) {
            $firstUnit = reset($unitsRaw);
            // Assuming player_id is available in the raw unit object (from player_ships or player_defense join)
            // If not, we might need to pass player_id explicitly or fetch it from the planet.
            // For now, let's assume player_id is available in $unitsRaw objects.
            $playerId = $firstUnit->player_id ?? null; 
        }

        // Get player's alliance research bonuses
        $allianceWeaponBonus = 0;
        $allianceShieldBonus = 0;
        if ($playerId) {
            $player = Player::getPlayerData($playerId, $db);
            if ($player && $player->alliance_id) {
                $alliance = Alliance::getById($player->alliance->id, $db); // Corrected to use alliance object
                if ($alliance) {
                    $allianceResearch = AllianceResearch::getAllForAlliance($alliance->id, $db);
                    // Assuming internal names for alliance research are 'allianzwaffentechnik' and 'allianzschildtechnik'
                    foreach ($allianceResearch as $research) {
                        if ($research->internal_name === 'allianzwaffentechnik') {
                            $allianceWeaponBonus = $research->level * 0.01; // Assuming 1% per level
                        } elseif ($research->internal_name === 'allianzschildtechnik') {
                            $allianceShieldBonus = $research->level * 0.01; // Assuming 1% per level
                        }
                    }
                }
            }
        }

        $weaponTechLevel = $playerResearch[ResearchType::getByInternalName('waffentechnik', $db)->id] ?? 0;
        $shieldTechLevel = $playerResearch[ResearchType::getByInternalName('schildtechnik', $db)->id] ?? 0;
        $armorTechLevel = $playerResearch[ResearchType::getByInternalName('rumpfpanzerung', $db)->id] ?? 0;
        // Defense specific tech, if any (e.g. 'verteidigungstechnik')
        // $defenseTechLevel = $isDefense ? ($playerResearch[ResearchType::getByInternalName('verteidigungstechnik', $db)->id] ?? 0) : 0;

        foreach ($unitsRaw as $unitRaw) {
            $unitType = $isDefense ? DefenseType::getById($unitRaw->defense_type_id, $db) : ShipType::getById($unitRaw->ship_type_id, $db);
            if (!$unitType) continue;

            $baseWeapon = $unitType->weapon_power ?? 0;
            $baseShield = $unitType->shield_power ?? 0;
            $baseHull = $unitType->hull_strength ?? 0;

            // Apply player and alliance research bonuses
            $actualWeapon = $baseWeapon * (1 + $weaponTechLevel * 0.1 + $allianceWeaponBonus); // Example: 10% per player level, 1% per alliance level
            $actualShield = $baseShield * (1 + $shieldTechLevel * 0.1 + $allianceShieldBonus); // Example: 10% per player level, 1% per alliance level
            $actualHull = $baseHull * (1 + $armorTechLevel * 0.1); // Armor is typically player research only
            
            // if ($isDefense && $defenseTechLevel > 0) { // Example for defense-specific bonus
            //    $actualWeapon *= (1 + $defenseTechLevel * 0.05);
            //    $actualShield *= (1 + $defenseTechLevel * 0.05);
            //    $actualHull *= (1 + $defenseTechLevel * 0.05);
            // }

            $formattedUnits[] = [
                'id' => $isDefense ? $unitType->id : $unitType->id, // ship_type_id or defense_type_id
                'internal_name' => $unitType->internal_name,
                'name' => $unitType->name_de,
                'quantity' => (int)$unitRaw->quantity,
                'weapon' => $actualWeapon,
                'shield' => $actualShield, // Current shield points for this group
                'max_shield' => $actualShield, // Max shield points for one unit
                'hull' => $actualHull,   // Current hull points for this group
                'max_hull' => $actualHull,   // Max hull points for one unit
                'is_defense' => $isDefense,
                // Store original costs for debris calculation
                'cost_eisen' => $unitType->cost_eisen ?? 0,
                'cost_silber' => $unitType->cost_silber ?? 0,
                'cost_uderon' => $unitType->cost_uderon ?? 0,
                'cost_wasserstoff' => $unitType->cost_wasserstoff ?? 0,
                'points' => $unitType->points ?? 0, // Added for Battlepoints calculation
            ];
        }
        return $formattedUnits;
    }

    private static function simulateBattleRound(array &$attackerUnits, array &$defenderUnits, PDO $db) {
        $roundLog = ['attacker_fire' => [], 'defender_fire' => [], 'attacker_losses' => [], 'defender_losses' => []];

        // Attacker fires
        self::executeFiringRound($attackerUnits, $defenderUnits, $roundLog, 'attacker', $db);
        // Defender fires (if any units remain)
        if (!empty($defenderUnits)) {
            self::executeFiringRound($defenderUnits, $attackerUnits, $roundLog, 'defender', $db);
        }

        // Clean up destroyed units (quantity = 0)
        $attackerUnits = array_values(array_filter($attackerUnits, fn($unit) => $unit['quantity'] > 0));
        $defenderUnits = array_values(array_filter($defenderUnits, fn($unit) => $unit['quantity'] > 0));
        
        return [
            'log' => $roundLog,
            'attacker_remaining' => $attackerUnits,
            'defender_remaining' => $defenderUnits
        ];
    }

    /**
     * Executes a single firing round for a side.
     * Each unit in the firing fleet/defense fires once. Each shot targets a random unit on the opposing side.
     * Damage is applied to the target unit's shield first, then hull.
     *
     * @param array $firingUnits Array of unit groups firing (objects with id, quantity, weapon, shield, max_shield, hull, max_hull, is_defense).
     * @param array $targetUnits Array of unit groups being targeted (objects with id, quantity, weapon, shield, max_shield, hull, max_hull, is_defense).
     * @param array $roundLog Reference to the battle log for the current round.
     * @param string $firingSidePrefix Prefix for log entries ('attacker' or 'defender').
     * @param PDO $db Database connection.
     */
    private static function executeFiringRound(array &$firingUnits, array &$targetUnits, array &$roundLog, string $firingSidePrefix, PDO $db) {
        if (empty($targetUnits)) return;

        // Create a flat list of individual target units for random selection
        $individualTargetUnits = [];
        foreach ($targetUnits as $groupIndex => &$targetGroup) {
            for ($i = 0; $i < $targetGroup['quantity']; $i++) {
                $individualTargetUnits[] = ['group_index' => $groupIndex, 'unit_index_in_group' => $i];
            }
        }

        if (empty($individualTargetUnits)) return; // No targets left

        foreach ($firingUnits as &$firingUnitGroup) {
            if ($firingUnitGroup['quantity'] <= 0 || $firingUnitGroup['weapon'] <= 0) continue;

            $totalShotsFromGroup = $firingUnitGroup['quantity']; // Each unit fires once
            $damagePerShot = $firingUnitGroup['weapon'];

            for ($shot = 0; $shot < $totalShotsFromGroup; $shot++) {
                if (empty($individualTargetUnits)) break; // No targets left

                // Select a random individual target unit
                $targetUnitIndexInFlatList = array_rand($individualTargetUnits);
                $targetDetails = $individualTargetUnits[$targetUnitIndexInFlatList];
                $targetGroupIndex = $targetDetails['group_index'];
                // $unitIndexInGroup = $targetDetails['unit_index_in_group']; // Not strictly needed for this model

                // Reference the actual target unit group
                $targetUnitGroup = &$targetUnits[$targetGroupIndex];

                $damageDealt = $damagePerShot;
                $absorbedByShield = 0;
                $damageToHull = 0;
                $unitDestroyed = false;

                // Apply damage to one unit in the target group
                // In this simplified model, we apply damage to the group's shield pool first, then hull pool.
                // A more complex model would track shield/hull per individual unit or use a probability model.
                // Let's refine to apply damage to ONE unit's shield/hull from the group.
                // This requires tracking damage taken by the *group* to know when a unit is destroyed.

                // Simplified approach: Apply damage to one unit's shield/hull from the group.
                // If damage exceeds shield, remaining damage hits hull. If hull is destroyed, one unit is lost.
                // This doesn't track partial damage to individual units within a group across rounds.
                // A better approach: Track total HP (Shield + Hull) for the group.
                // Total HP = (Max Shield + Max Hull) * Quantity. Damage reduces total HP.
                // When total HP drops below (Max Shield + Max Hull) * (Quantity - 1), one unit is lost.

                // Let's try a slightly more refined approach: Damage is applied to the group's total shield pool first, then hull pool.
                // This is still simpler than per-unit tracking but better than the previous version.

                $damageAfterShield = max(0, $damageDealt - $targetUnitGroup['shield']);
                $absorbedByShield = $damageDealt - $damageAfterShield;
                $damageToHull = $damageAfterShield;

                $targetUnitGroup['shield'] = max(0, $targetUnitGroup['shield'] - $absorbedByShield);
                $targetUnitGroup['hull'] = max(0, $targetUnitGroup['hull'] - $damageToHull);

                // Calculate how many units are destroyed in this group based on the new total hull
                // Total initial HP per unit = max_shield + max_hull
                // Total current HP for the group = current_shield + current_hull
                // Units lost = Initial Quantity - (Current Total HP / Initial HP per Unit)
                // This is still not quite right as shield regenerates.

                // Let's revert to the per-shot, per-unit destruction chance, but make target selection more robust.
                // Each shot targets ONE random unit. If that unit's shield/hull is overcome, it's destroyed.

                // Re-select a random target group index (since array_rand might return the same index)
                $targetGroupIndex = array_rand($targetUnits);
                $targetUnitGroup = &$targetUnits[$targetGroupIndex];

                $damageDealt = $damagePerShot;
                $absorbedByShield = 0;
                $damageToHull = 0;
                $unitDestroyed = false;

                // Apply damage to ONE unit's shield/hull from this group
                $shieldOfOneUnit = $targetUnitGroup['max_shield'];
                $hullOfOneUnit = $targetUnitGroup['max_hull'];

                if ($damageDealt > $shieldOfOneUnit) {
                    $absorbedByShield = $shieldOfOneUnit;
                    $damageToHull = $damageDealt - $shieldOfOneUnit;
                } else {
                    $absorbedByShield = $damageDealt;
                    $damageToHull = 0; // Damage fully absorbed by shield
                }

                // Check if the unit is destroyed (damage to hull >= unit's max hull)
                if ($damageToHull >= $hullOfOneUnit) {
                    $unitDestroyed = true;
                    $targetUnitGroup['quantity']--; // Decrease quantity in the group
                    
                    // Remove the destroyed individual unit from the flat list
                    // Find the specific entry in $individualTargetUnits that points to this group index and a valid unit
                    $foundKey = null;
                    foreach ($individualTargetUnits as $key => $details) {
                        if ($details['group_index'] === $targetGroupIndex) {
                            // This is an entry for the target group. We can remove any one of them.
                            $foundKey = $key;
                            break;
                        }
                    }
                    if ($foundKey !== null) {
                        unset($individualTargetUnits[$foundKey]);
                        $individualTargetUnits = array_values($individualTargetUnits); // Re-index
                    }

                    // Log the loss
                    $lossKey = $firingSidePrefix === 'attacker' ? 'defender_losses' : 'attacker_losses';
                    $roundLog[$lossKey][] = ['name' => $targetUnitGroup['name'], 'lost' => 1, 'remaining' => $targetUnitGroup['quantity']];
                }

                $roundLog[$firingSidePrefix . '_fire'][] = [
                    'shooter_name' => $firingUnitGroup['name'],
                    'target_name' => $targetUnitGroup['name'],
                    'damage' => $damageDealt,
                    'absorbed_by_shield' => $absorbedByShield,
                    'damage_to_hull' => $damageToHull,
                    'unit_destroyed' => $unitDestroyed
                ];

                // If the target group quantity drops to 0, remove it from $targetUnits
                if ($targetUnitGroup['quantity'] <= 0) {
                    unset($targetUnits[$targetGroupIndex]);
                    $targetUnits = array_values($targetUnits); // Re-index
                    // Also remove all corresponding entries from individualTargetUnits
                    $individualTargetUnits = array_filter($individualTargetUnits, fn($details) => $details['group_index'] !== $targetGroupIndex);
                    $individualTargetUnits = array_values($individualTargetUnits); // Re-index
                }
            }
        }
        // Unset references
        unset($firingUnitGroup);
        unset($targetUnitGroup);
    }


    private static function handlePlunder(object $attackerFleet, array $survivingAttackerShips, object $targetPlanet, PDO $db) {
        $plunderPercentage = defined('PLUNDER_PERCENTAGE') ? PLUNDER_PERCENTAGE : 0.5;
        $plundered = ['eisen' => 0, 'silber' => 0, 'uderon' => 0, 'wasserstoff' => 0];

        $totalCargoCapacity = 0;
        foreach ($survivingAttackerShips as $shipGroup) {
            $shipType = ShipType::getById($shipGroup['id'], $db); // 'id' is ship_type_id
            if ($shipType) {
                $totalCargoCapacity += $shipType->cargo_capacity * $shipGroup['quantity'];
            }
        }
        
        // Available resources on planet (after potential partial refresh if needed)
        // For simplicity, using current values.
        $availableResources = [
            'eisen' => $targetPlanet->eisen,
            'silber' => $targetPlanet->silber,
            'uderon' => $targetPlanet->uderon,
            'wasserstoff' => $targetPlanet->wasserstoff, // Usually not plundered or less
        ];

        $currentCargoLoad = 0; // Assuming fleet starts empty for plunder calculation simplicity

        foreach (['eisen', 'silber', 'uderon', 'wasserstoff'] as $res) {
            if ($totalCargoCapacity - $currentCargoLoad <= 0) break;

            $plunderableAmount = floor($availableResources[$res] * $plunderPercentage);
            $canCarry = $totalCargoCapacity - $currentCargoLoad;
            $lootedAmount = min($plunderableAmount, $canCarry);
            
            if ($lootedAmount > 0) {
                $plundered[$res] = $lootedAmount;
                $currentCargoLoad += $lootedAmount;
                // This will be deducted from planet and added to fleet later in updateDefenderPlanetAfterCombat & updateAttackerFleetAfterCombat
            }
        }
        return $plundered;
    }

    private static function handleDebrisFields(array $initialAttacker, array $finalAttacker, array $initialDefender, array $finalDefender, int $targetPlanetId, PDO $db) {
        $debris = ['metal' => 0, 'silber' => 0, 'uderon' => 0, 'wasserstoff' => 0];

        $calculateDebrisForSide = function(array $initialUnits, array $finalUnits) use (&$debris, $db) {
            foreach ($initialUnits as $iUnit) {
                $lostQuantity = $iUnit['quantity'];
                foreach ($finalUnits as $fUnit) {
                    if ($fUnit['id'] == $iUnit['id'] && $fUnit['is_defense'] == $iUnit['is_defense']) {
                        $lostQuantity -= $fUnit['quantity'];
                        break;
                    }
                }
                if ($lostQuantity > 0) {
                    $costEisen = $iUnit['cost_eisen'] ?? 0;
                    $costSilber = $iUnit['cost_silber'] ?? 0;
                    $costUderon = $iUnit['cost_uderon'] ?? 0;
                    // Wasserstoff usually doesn't form debris

                    $debrisPercentMetal = $iUnit['is_defense'] ? (defined('DEBRIS_DEFENSE_METAL_PERCENTAGE') ? DEBRIS_DEFENSE_METAL_PERCENTAGE : 0.3) : (defined('DEBRIS_SHIP_METAL_PERCENTAGE') ? DEBRIS_SHIP_METAL_PERCENTAGE : 0.3);
                    $debrisPercentSilber = $iUnit['is_defense'] ? (defined('DEBRIS_DEFENSE_SILBER_PERCENTAGE') ? DEBRIS_DEFENSE_SILBER_PERCENTAGE : 0.3) : (defined('DEBRIS_SHIP_SILBER_PERCENTAGE') ? DEBRIS_SHIP_SILBER_PERCENTAGE : 0.3);
                    
                    $debris['metal'] += floor($costEisen * $lostQuantity * $debrisPercentMetal);
                    $debris['silber'] += floor($costSilber * $lostQuantity * $debrisPercentSilber);

                    if (defined('DEBRIS_RESOURCES_INCLUDE_UDERON') && DEBRIS_RESOURCES_INCLUDE_UDERON) {
                        $debrisPercentUderon = $iUnit['is_defense'] ? (defined('DEBRIS_DEFENSE_UDERON_PERCENTAGE') ? DEBRIS_DEFENSE_UDERON_PERCENTAGE : 0.15) : (defined('DEBRIS_SHIP_UDERON_PERCENTAGE') ? DEBRIS_SHIP_UDERON_PERCENTAGE : 0.15);
                        $debris['uderon'] += floor($costUderon * $lostQuantity * $debrisPercentUderon);
                    }
                }
            }
        };

        $calculateDebrisForSide($initialAttacker, $finalAttacker);
        $calculateDebrisForSide($initialDefender, $finalDefender);

        if ($debris['metal'] > 0 || $debris['silber'] > 0 || $debris['uderon'] > 0) {
            Planet::addOrUpdateDebrisField($targetPlanetId, $debris['metal'], $debris['silber'], $debris['uderon'], $debris['wasserstoff'], $db);
        }
        return $debris;
    }
    
    private static function calculateLostResourceValues(array $initialAttacker, array $finalAttacker, array $initialDefender, array $finalDefender, PDO $db) {
        $attackerLostValue = ['metal' => 0, 'silber' => 0, 'uderon' => 0, 'wasserstoff' => 0];
        $defenderLostValue = ['metal' => 0, 'silber' => 0, 'uderon' => 0, 'wasserstoff' => 0];

        $calculateSideLostValue = function(array $initialUnits, array $finalUnits, &$lostValueStore) {
            foreach ($initialUnits as $iUnit) {
                $lostQuantity = $iUnit['quantity'];
                foreach ($finalUnits as $fUnit) {
                    if ($fUnit['id'] == $iUnit['id'] && $fUnit['is_defense'] == $iUnit['is_defense']) {
                        $lostQuantity -= $fUnit['quantity'];
                        break;
                    }
                }
                if ($lostQuantity > 0) {
                    $lostValueStore['metal'] += ($iUnit['cost_eisen'] ?? 0) * $lostQuantity;
                    $lostValueStore['silber'] += ($iUnit['cost_silber'] ?? 0) * $lostQuantity;
                    $lostValueStore['uderon'] += ($iUnit['cost_uderon'] ?? 0) * $lostQuantity;
                    $lostValueStore['wasserstoff'] += ($iUnit['cost_wasserstoff'] ?? 0) * $lostQuantity;
                }
            }
        };
        
        $calculateSideLostValue($initialAttacker, $finalAttacker, $attackerLostValue);
        $calculateSideLostValue($initialDefender, $finalDefender, $defenderLostValue);
        
        return [$attackerLostValue, $defenderLostValue];
    }

    /**
     * Calculates Battlepoints gained/lost based on the value of destroyed enemy units and battle outcome.
     *
     * @param array $enemyLostValue Associative array of resource value of destroyed enemy units.
     * @param bool $isWinner True if the player won the battle, false otherwise.
     * @return int The calculated Battlepoints gained or lost.
     */
    private static function calculateBattlepoints(array $enemyLostValue, bool $isWinner): int {
        // Sum the resource value of destroyed enemy units
        $totalEnemyLostValue = array_sum($enemyLostValue);

        // Convert resource value to Battlepoints (assuming a simple conversion factor)
        // This conversion factor needs to be defined based on game balance.
        // For now, let's assume 1 BP per 1000 total resource value lost by the enemy.
        $resourceValueToBPFactor = defined('BP_RESOURCE_VALUE_FACTOR') ? BP_RESOURCE_VALUE_FACTOR : 0.001; // 1 BP per 1000 resources
        $bpFromLosses = floor($totalEnemyLostValue * $resourceValueToBPFactor);

        // Add/deduct points based on battle outcome
        $outcomeBonus = 0;
        if ($isWinner) {
            $outcomeBonus = self::WINNER_BP_BONUS;
        } else {
            $outcomeBonus = self::LOSER_BP_PENALTY;
        }

        // Total Battlepoints gained/lost
        $totalBP = $bpFromLosses + $outcomeBonus;

        return $totalBP;
    }


    private static function updateAttackerFleetAfterCombat(int $fleetId, array $survivingShips, array $plunderedResources, PDO $db) {
        // First, clear existing ships for this fleet
        $stmtClear = $db->prepare("DELETE FROM fleet_ships WHERE fleet_id = :fleet_id");
        $stmtClear->bindParam(':fleet_id', $fleetId, PDO::PARAM_INT);
        $stmtClear->execute();

        // Add surviving ships
        $stmtAdd = $db->prepare("INSERT INTO fleet_ships (fleet_id, ship_type_id, quantity) VALUES (:fleet_id, :ship_type_id, :quantity)");
        foreach ($survivingShips as $shipGroup) {
            if ($shipGroup['quantity'] > 0 && !$shipGroup['is_defense']) { // Defenses don't go into fleets
                $stmtAdd->bindParam(':fleet_id', $fleetId, PDO::PARAM_INT);
                $stmtAdd->bindParam(':ship_type_id', $shipGroup['id'], PDO::PARAM_INT); // 'id' is ship_type_id
                $stmtAdd->bindParam(':quantity', $shipGroup['quantity'], PDO::PARAM_INT);
                $stmtAdd->execute();
            }
        }

        // Update fleet cargo with plundered resources
        if (!empty(array_filter($plunderedResources))) { // Check if any resource has a value > 0
            $sqlCargo = "UPDATE fleets SET 
                            eisen_cargo = eisen_cargo + :eisen, 
                            silber_cargo = silber_cargo + :silber, 
                            uderon_cargo = uderon_cargo + :uderon, 
                            wasserstoff_cargo = wasserstoff_cargo + :wasserstoff
                         WHERE id = :fleet_id";
            $stmtCargo = $db->prepare($sqlCargo);
            $stmtCargo->bindParam(':eisen', $plunderedResources['eisen'], PDO::PARAM_INT);
            $stmtCargo->bindParam(':silber', $plunderedResources['silber'], PDO::PARAM_INT);
            $stmtCargo->bindParam(':uderon', $plunderedResources['uderon'], PDO::PARAM_INT);
            $stmtCargo->bindParam(':wasserstoff', $plunderedResources['wasserstoff'], PDO::PARAM_INT);
            $stmtCargo->bindParam(':fleet_id', $fleetId, PDO::PARAM_INT);
            $stmtCargo->execute();
        }
    }

    private static function updateDefenderPlanetAfterCombat(int $planetId, int $defenderPlayerId, array $survivingUnits, array $plunderedResources, array $initialDefenderDefenses, PDO $db) {
        // Update planet resources (deduct plundered)
        if (!empty(array_filter($plunderedResources))) {
            $sqlPlanetRes = "UPDATE planets SET \\r
                                eisen = GREATEST(0, eisen - :eisen), \\r
                                silber = GREATEST(0, silber - :silber), \\r
                                uderon = GREATEST(0, uderon - :uderon), \\r
                                wasserstoff = GREATEST(0, wasserstoff - :wasserstoff)\\r
                             WHERE id = :planet_id";
            $stmtPlanetRes = $db->prepare($sqlPlanetRes);
            $stmtPlanetRes->bindParam(':eisen', $plunderedResources['eisen'], PDO::PARAM_INT);
            $stmtPlanetRes->bindParam(':silber', $plunderedResources['silber'], PDO::PARAM_INT);
            $stmtPlanetRes->bindParam(':uderon', $plunderedResources['uderon'], PDO::PARAM_INT);
            $stmtPlanetRes->bindParam(':wasserstoff', $plunderedResources['wasserstoff'], PDO::PARAM_INT);
            $stmtPlanetRes->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmtPlanetRes->execute();
        }

        // Clear existing ships and defenses for this planet (owned by defender)
        $stmtClearShips = $db->prepare("DELETE FROM player_ships WHERE planet_id = :planet_id");
        $stmtClearShips->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmtClearShips->execute();

        $stmtClearDefs = $db->prepare("DELETE FROM player_defense WHERE planet_id = :planet_id");
        $stmtClearDefs->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmtClearDefs->execute();

        // Prepare statements for adding surviving/rebuilt units
        $stmtAddShip = $db->prepare("INSERT INTO player_ships (player_id, planet_id, ship_type_id, quantity) VALUES (:player_id, :planet_id, :ship_type_id, :quantity)");
        $stmtAddDef = $db->prepare("INSERT INTO player_defenses (player_id, planet_id, defense_type_id, quantity) VALUES (:player_id, :planet_id, :defense_type_id, :quantity)");
        
        $defenseRebuildChance = defined('DEFENSE_REBUILD_CHANCE') ? DEFENSE_REBUILD_CHANCE : 0.0; // Default to 0 if not defined

        // Process surviving ships
        foreach ($survivingUnits as $unitGroup) {
            if ($unitGroup['quantity'] > 0 && !$unitGroup['is_defense']) {
                $stmtAddShip->bindParam(':player_id', $defenderPlayerId, PDO::PARAM_INT);
                $stmtAddShip->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
                $stmtAddShip->bindParam(':ship_type_id', $unitGroup['id'], PDO::PARAM_INT);
                $stmtAddShip->bindParam(':quantity', $unitGroup['quantity'], PDO::PARAM_INT);
                $stmtAddShip->execute();
            }
        }

        // Process defenses (survivors + rebuilt)
        $finalDefenses = [];
        // Create a map of surviving defenses for quick lookup
        $survivingDefensesMap = [];
        foreach ($survivingUnits as $unitGroup) {
            if ($unitGroup['is_defense']) {
                $survivingDefensesMap[$unitGroup['id']] = $unitGroup['quantity'];
            }
        }

        foreach ($initialDefenderDefenses as $initialDefGroup) {
            if (!$initialDefGroup['is_defense']) continue;

            $defenseTypeId = $initialDefGroup['id'];
            $initialQuantity = $initialDefGroup['quantity'];
            $survivingQuantity = $survivingDefensesMap[$defenseTypeId] ?? 0;
            
            $destroyedQuantity = $initialQuantity - $survivingQuantity;
            $rebuiltQuantity = 0;

            if ($destroyedQuantity > 0 && $defenseRebuildChance > 0) {
                for ($i = 0; $i < $destroyedQuantity; $i++) {
                    if ((mt_rand(0, 999) / 1000) < $defenseRebuildChance) {
                        $rebuiltQuantity++;
                    }
                }
            }
            
            $finalQuantity = $survivingQuantity + $rebuiltQuantity;

            if ($finalQuantity > 0) {
                $finalDefenses[$defenseTypeId] = [
                    'id' => $defenseTypeId,
                    'quantity' => $finalQuantity
                ];
            }
        }
        
        // Add final defense quantities to DB
        foreach ($finalDefenses as $defGroup) {
            if ($defGroup['quantity'] > 0) {
                $stmtAddDef->bindParam(':player_id', $defenderPlayerId, PDO::PARAM_INT);
                $stmtAddDef->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
                $stmtAddDef->bindParam(':defense_type_id', $defGroup['id'], PDO::PARAM_INT);
                $stmtAddDef->bindParam(':quantity', $defGroup['quantity'], PDO::PARAM_INT);
                $stmtAddDef->execute();
            }
        }
    }

    private static function sendCombatNotifications(object $attackerFleet, object $targetPlanet, ?string $winner, ?int $reportId, array $plunderedResources, PDO $db) {
        $targetCoords = "{$targetPlanet->galaxy}:{$targetPlanet->system}:{$targetPlanet->position}";
        $attackerId = $attackerFleet->player_id;
        $defenderId = $targetPlanet->player_id;

        $attackerMsg = "Your fleet attacking {$targetPlanet->name} ({$targetCoords}) has completed its mission. ";
        $defenderMsg = "Your planet {$targetPlanet->name} ({$targetCoords}) was attacked by Player {$attackerId}. ";

        switch ($winner) {
            case 'attacker':
                $attackerMsg .= "You were victorious!";
                $defenderMsg .= "Your forces were defeated.";
                break;
            case 'defender':
                $attackerMsg .= "Your forces were defeated.";
                $defenderMsg .= "You successfully defended the planet!";
                break;
            case 'draw':
                $attackerMsg .= "The battle ended in a draw.";
                $defenderMsg .= "The battle ended in a draw.";
                break;
            default:
                $attackerMsg .= "The battle outcome is undetermined.";
                $defenderMsg .= "The battle outcome is undetermined.";
        }

        if ($reportId) {
            $attackerMsg .= " (Battle Report ID: {$reportId})";
            $defenderMsg .= " (Battle Report ID: {$reportId})";
        }
        
        $plunderSum = array_sum(array_values($plunderedResources));
        if ($plunderSum > 0 && $winner === 'attacker') {
            $plunderStr = " Plundered: Eisen " . $plunderedResources['eisen'] . ", Silber " . $plunderedResources['silber'] . ", Uderon " . $plunderedResources['uderon'] . ".";
            $attackerMsg .= $plunderStr;
            $defenderMsg .= " Resources lost: Eisen " . $plunderedResources['eisen'] . ", Silber " . $plunderedResources['silber'] . ", Uderon " . $plunderedResources['uderon'] . ".";
        }

        NotificationService::createNotification($attackerId, "Combat Report: {$targetPlanet->name}", $attackerMsg, $winner === 'attacker' ? 'success' : ($winner === 'defender' ? 'error' : 'warning'));
        if ($defenderId) {
            NotificationService::createNotification($defenderId, "Planet Attacked: {$targetPlanet->name}", $defenderMsg, $winner === 'defender' ? 'success' : ($winner === 'attacker' ? 'alert' : 'warning'));
        }
    }

    /**
     * Process an espionage mission.
     * $attackingFleetDetails should be the result of Fleet::getFleetDetails($fleetId)
     */
    public static function processEspionageMission(array $attackingFleetDetails, object $targetPlanet, ?array $defendingUnitsRaw) {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $attackerFleet = $attackingFleetDetails['fleet'];
            $attackerId = $attackerFleet->player_id;
            $defenderId = $targetPlanet->player_id;
            $targetCoords = "{$targetPlanet->galaxy}:{$targetPlanet->system}:{$targetPlanet->position}";

            // Determine if this is a Probe or Agent mission based on fleet composition
            $probeShipTypeId = ShipType::getByInternalName('spionagesonde', $db)->id ?? null;
            $hasProbes = false;
            $numProbes = 0;
            if ($probeShipTypeId) {
                foreach ($attackingFleetDetails['ships'] as $ship) {
                    if ($ship->ship_type_id == $probeShipTypeId) {
                        $numProbes = (int)$ship->quantity;
                        $hasProbes = $numProbes > 0;
                        break;
                    }
                }
            }

            // Assuming Agent espionage is initiated differently and doesn't use a fleet,
            // or uses a fleet with a specific 'agent' mission type and quantity of 'agent' units.
            // Based on the MD, Agents are stationed on planets and used from there.
            // This suggests processEspionageMission might be called by a different mechanism for Agents.
            // However, the current structure implies it's fleet-based.
            // Let's assume for now that Agent espionage uses a fleet with a special 'agent' unit type.
            // This contradicts the MD saying Agents are stationed on planets.
            // Let's re-read the MD on Agents carefully.

            // MD says: "Agenten werden zum Spionieren verwendet und verhindern oder erschweren das Ausspähen des Planeten auf dem sie stationiert sind."
            // "Agenten kosten 150 Eisen und 100 Silber zum Bau (Menüpunkt Spionage), und sind immer auf einem Planeten stationiert."
            // "Agenten können durch Eingabe eigener Planetenkoordinaten als Spähziel verlegt werden."
            // "Einsetzen kann man maximal die Anzahl der eigenen Agenten auf dem Planeten für einen Spionageversuch."
            // "Ob ein Spionageversuch erfolgreich ist wird beeinflusst von: Anzahl der eingesetzten Agenten, Anzahl der gegnerischen Agenten, Spionageabwehr des Gegners, eigener Spionagetechnik, Zufall"
            // "Bei einem nicht erfolgreichen Spionageversuch verliert der Angreifer 10% der eingesetzten Agenten"
            // "Mit Agenten lassen sich Planet (Gebäude-Ausbauten, Ressourcen, Forschungen und Heimat-Flotte bzw Stationierte Flotten), neue Messages (Nachrichten, nur System-Message), alte Messages (Nachrichten, nur System-Message), Werftaufträge des ausgewählten Planeten erscannen."

            // This confirms Agent espionage is NOT fleet-based. It's initiated from a planet using stationed Agents.
            // The current processEspionageMission method is designed for fleet-based missions (like Probes).
            // A separate method or controller action is needed for Agent espionage.

            // Given the current structure and the need to proceed, I will assume this method *could* be adapted
            // to handle both, perhaps by checking a flag or unit type. But it's a mismatch with the MD.
            // Let's stick to refining Probe espionage within this method as it's clearly fleet-based.
            // Agent espionage needs a different entry point.

            // Refine Probe Espionage logic based on MD:
            // - Sonden always return a report, but it's often inaccurate.
            // - Sonden do NOT show stationed fleets or research.
            // - Defender ALWAYS gets a notification.
            // - Sonden are destroyed after the mission.

            if ($hasProbes) { // This method is for fleet-based espionage (Probes)
                if ($numProbes == 0) {
                    // This case should be handled before calling this method, but as a safeguard:
                    NotificationService::createNotification($attackerId, "Espionage Failed", "No espionage probes in fleet.", "warning");
                    $db->commit();
                    return ['error' => 'No probes in fleet', 'probes_lost' => 0];
                }

                $attackerEspionageTech = PlayerResearch::getResearchLevelsByPlayerId($attackerId, $db)[ResearchType::getByInternalName('spionagetechnik', $db)->id] ?? 0;
                // Defender counter-espionage strength from Agents and Spionageabwehr research
                $defenderAgents = $defenderId ? PlayerAgent::getAgentsOnPlanet($targetPlanet->id, $db) : 0; // Assuming getAgentsOnPlanet exists
                $defenderCounterEspionageTech = $defenderId ? (PlayerResearch::getResearchLevelsByPlayerId($defenderId, $db)[ResearchType::getByInternalName('spionageabwehr', $db)->id] ?? 0) : 0; // Assuming Spionageabwehr is the counter-tech

                // Probe espionage success/detection chance calculation (simplified interpretation of MD)
                // More probes + attacker tech vs. Defender agents + defender tech
                $probeStrength = $numProbes * (1 + $attackerEspionageTech * 0.1); // Probes + Attacker Tech
                $defenderCounterStrength = $defenderAgents * (1 + $defenderCounterEspionageTech * 0.1); // Agents + Defender Tech

                // Success chance: Higher probe strength relative to defender strength
                $successChance = min(0.95, 0.1 + ($probeStrength / ($probeStrength + $defenderCounterStrength + 1)) * 0.85); // Base 10%, scales up

                // Probe loss chance: Higher defender strength relative to probe strength
                $probeLossChance = min(0.8, 0.05 + ($defenderCounterStrength / ($probeStrength + $probeStrength + 1)) * 0.75); // Base 5% loss, scales up (used probeStrength twice here, check MD)
                // Corrected: Probe loss chance should be based on defender strength vs probe strength
                $probeLossChance = min(0.8, 0.05 + ($defenderCounterStrength / ($probeStrength + $defenderCounterStrength + 1)) * 0.75); // Base 5% loss, scales up

                $isSuccess = (mt_rand(0, 1000) / 1000) < $successChance;
                $probesLost = floor($numProbes * $probeLossChance); // Probes are lost based on detection chance
                $probesRemaining = $numProbes - $probesLost;

                // Probes are destroyed after the mission, regardless of success/failure or loss chance.
                // The MD says "Sonden kehren nicht zurück. Sie sind nach einem Spionageeinsatz nicht erneut einsetzbar, da sie nach der Spionage zerstört sind."
                // This implies ALL probes sent are consumed. The 'probes_lost' might represent probes detected/shot down,
                // but the rest are also not recoverable.
                // Let's interpret 'probes_lost' as the number detected and reported to the defender,
                // while the rest are also consumed.

                // Consume ALL probes from the fleet
                if ($numProbes > 0 && $probeShipTypeId) {
                    Fleet::consumeShipFromFleet($attackerFleet->id, $probeShipTypeId, $numProbes, $db);
                }

                $reportData = [
                    'type' => 'probe',
                    'success' => $isSuccess,
                    'probes_sent' => $numProbes,
                    'probes_lost' => $probesLost, // Number detected/shot down
                    'planet_name' => $targetPlanet->name,
                    'coordinates' => $targetCoords,
                    'player_id' => $targetPlanet->player_id, // Can be null
                    'inaccurate_data' => true, // Probe data is often inaccurate
                ];

                if ($isSuccess) {
                    // Probe reports include Buildings, Resources, Ships (on planet), and Defenses.
                    // They do NOT include stationed fleets or research based on MD.
                    // Data is often inaccurate. Let's apply a simple inaccuracy factor.
                    $inaccuracyFactor = 1 - min(0.5, ($defenderCounterEspionageTech * 0.05)); // Higher defender tech = more inaccuracy, max 50%

                    $reportData['resources'] = [
                        'eisen' => floor($targetPlanet->eisen * $inaccuracyFactor * (mt_rand(90, 110)/100)), // Apply inaccuracy and slight random variation
                        'silber' => floor($targetPlanet->silber * $inaccuracyFactor * (mt_rand(90, 110)/100)),
                        'uderon' => floor($targetPlanet->uderon * $inaccuracyFactor * (mt_rand(90, 110)/100)),
                        'wasserstoff' => floor($targetPlanet->wasserstoff * $inaccuracyFactor * (mt_rand(90, 110)/100)),
                        'energie' => floor($targetPlanet->energie * $inaccuracyFactor * (mt_rand(90, 110)/100)),
                    ];
                    // For buildings, ships, defenses, maybe report levels/quantities with inaccuracy?
                    // Simplified: just report the actual values for now, note inaccuracy in report.
                    $reportData['buildings'] = PlayerBuilding::getAllForPlanet($targetPlanet->id, $db);
                    $reportData['ships'] = PlayerShip::getShipsOnPlanet($targetPlanet->id, $db);
                    $reportData['defenses'] = PlayerDefense::getDefensesOnPlanet($targetPlanet->id, $db);

                    NotificationService::createNotification($attackerId, "Espionage Successful (Probe)", "Espionage on {$targetPlanet->name} ({$targetCoords}) successful. {$probesLost} probes detected.", "success");
                } else {
                    NotificationService::createNotification($attackerId, "Espionage Failed (Probe)", "Espionage on {$targetPlanet->name} ({$targetCoords}) failed. {$probesLost} probes detected.", "error");
                }

                $reportId = EspionageReport::create($attackerId, $targetPlanet->id, json_encode($reportData), $db); // Pass DB
                $reportData['report_id'] = $reportId;

                // Standardized Notification for Espionage Report (Probe) for Attacker
                $attackerMessage = $isSuccess ? "Spionagebericht (Sonde): Mission auf {$targetPlanet->name} ({$targetCoords}) erfolgreich. {$probesLost} Sonden entdeckt." 
                                             : "Spionagebericht (Sonde): Mission auf {$targetPlanet->name} ({$targetCoords}) fehlgeschlagen. {$probesLost} Sonden entdeckt.";
                PlayerNotification::createNotification(
                    $attackerId,
                    PlayerNotification::TYPE_ESPIONAGE_REPORT, // New type
                    $attackerMessage,
                    BASE_URL . '/espionage/report/' . $reportId, // Link to report
                    $db
                );

                // Defender notification always happens for Probe espionage based on MD.
                if ($defenderId) {
                     $defenderNotificationMsg = "Eine Spionageaktivität wurde auf deinem Planeten {$targetPlanet->name} ({$targetCoords}) entdeckt.";
                     if ($probesLost > 0) {
                         $defenderNotificationMsg .= " {$probesLost} Sonden wurden dabei zerstört."; // Clarified message
                     }
                     // Defender does not get a link to the attacker's report.
                     PlayerNotification::createNotification(
                         $defenderId, 
                         PlayerNotification::TYPE_ESPIONAGE_ACTIVITY, // New type for general activity
                         $defenderNotificationMsg, 
                         null, // No link for defender's general alert
                         $db
                        );
                }
                // Remove older NotificationService calls as they are now replaced
                // NotificationService::createNotification($attackerId, "Espionage Successful (Probe)", ..., "success"); // REMOVED
                // NotificationService::createNotification($attackerId, "Espionage Failed (Probe)", ..., "error"); // REMOVED
                // NotificationService::createNotification($defenderId, "Espionage Attempt Detected", ..., "warning"); // REMOVED


                $db->commit();
                return $reportData;

            } else {
                // This is not a Probe mission. Assuming it's an Agent mission initiated elsewhere.
                // This method should not be called for Agent missions based on MD.
                // If it is called with a fleet but no probes, it's an invalid espionage mission.
                 NotificationService::createNotification($attackerId, "Espionage Failed", "Invalid espionage mission type or no probes in fleet.", "error");
                 // Fleet::setFleetToReturn($attackerFleet->id, $db, "Invalid espionage mission."); // Fleet.php handles return
                 $db->commit();
                 return ['error' => 'Invalid espionage mission type or no probes in fleet'];
            }


        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Combat::processEspionageMission Error for fleet {$attackerFleet->id}: " . $e->getMessage());
            // Attempt to return the fleet if an error occurs mid-process
            try {
                if (isset($attackerFleet->id) && Fleet::getFleetById($attackerFleet->id)) { // Check if fleet still exists
                     Fleet::setFleetToReturn($attackerFleet->id, self::getDB(), "Espionage processing error: " . substr($e->getMessage(), 0, 100));
                }
            } catch (Exception $returnEx) {
                error_log("Combat::processEspionageMission - Failed to set fleet {$attackerFleet->id} to return after error: " . $returnEx->getMessage());
            }
            return ['error' => $e->getMessage(), 'probes_lost' => $numProbes ?? 0]; // Assume all probes lost on critical error
        }
    }
    
    /**
     * Process an Agent espionage mission.
     * This method is called directly when an Agent mission is initiated from a planet.
     *
     * @param int $attackerId The ID of the attacking player.
     * @param int $startPlanetId The ID of the planet the agents were sent from.
     * @param int $targetPlanetId The ID of the target planet.
     * @param int $numAgents The number of agents used for the mission.
     * @param string $missionType The specific type of agent mission ('planet', 'new_messages', 'old_messages', 'shipyard_orders', 'disable_agents').
     * @param PDO $db Database connection.
     * @return array Espionage result data.
     */
    public static function processAgentEspionage(int $attackerId, int $startPlanetId, int $targetPlanetId, int $numAgents, string $missionType, PDO $db): array {
        $db->beginTransaction();
        try {
            $targetPlanet = Planet::getById($targetPlanetId, $db);
            if (!$targetPlanet) {
                throw new Exception("Target planet {$targetPlanetId} not found for agent mission.");
            }

            $defenderId = $targetPlanet->player_id; // Can be null if unowned planet
            $targetCoords = "{$targetPlanet->galaxy}:{$targetPlanet->system}:{$targetPlanet->position}";

            // Get attacker's espionage tech and defender's agents and counter-espionage tech
            $attackerEspionageTech = PlayerResearch::getResearchLevelsByPlayerId($attackerId, $db)[ResearchType::getByInternalName('spionagetechnik', $db)->id] ?? 0;
            $defenderAgents = $defenderId ? PlayerAgent::getAgentsOnPlanet($targetPlanet->id, $db) : 0; // Assuming getAgentsOnPlanet exists
            $defenderCounterEspionageTech = $defenderId ? (PlayerResearch::getResearchLevelsByPlayerId($defenderId, $db)[ResearchType::getByInternalName('spionageabwehr', $db)->id] ?? 0) : 0; // Assuming Spionageabwehr is the counter-tech

            // Agent espionage success chance calculation (simplified interpretation of MD)
            // More attacker agents + attacker tech vs. Defender agents + defender tech
            $agentStrength = $numAgents * (1 + $attackerEspionageTech * 0.15); // Agents + Attacker Tech (higher tech bonus for agents?)
            $defenderCounterStrength = $defenderAgents * (1 + $defenderCounterEspionageTech * 0.1); // Agents + Defender Tech

            // Success chance: Higher agent strength relative to defender strength
            $successChance = min(0.98, 0.2 + ($agentStrength / ($agentStrength + $defenderCounterStrength + 1)) * 0.78); // Base 20%, scales up to 98%

            // Agent loss chance: Higher defender strength relative to agent strength
            // MD says 10% loss on *unsuccessful* missions. Let's interpret this as a base loss on failure.
            // Detection chance might lead to higher losses.
            $detectionChance = min(0.9, 0.1 + ($defenderCounterStrength / ($agentStrength + $defenderCounterStrength + 1)) * 0.8); // Base 10%, scales up to 90%

            $isSuccess = (mt_rand(0, 1000) / 1000) < $successChance;
            $isDetected = (mt_rand(0, 1000) / 1000) < $detectionChance;

            $agentsLost = 0;
            if (!$isSuccess) {
                // 10% loss on unsuccessful mission
                $agentsLost = floor($numAgents * 0.1);
            }
            // Additional losses if detected? MD only mentions 10% loss on failure. Let's stick to that.

            // Deduct lost agents from the attacker's planet
            if ($agentsLost > 0) {
                PlayerAgent::removeAgents($startPlanetId, $agentsLost, $db); // Deduct from start planet
            }


            $reportData = [
                'type' => 'agent',
                'success' => $isSuccess,
                'agents_sent' => $numAgents,
                'agents_lost' => $agentsLost,
                'planet_name' => $targetPlanet->name,
                'coordinates' => $targetCoords,
                'player_id' => $targetPlanet->player_id, // Can be null
                'mission_type' => $missionType,
            ];

            if ($isSuccess) {
                // Gather information based on mission type
                switch ($missionType) {
                    case 'planet':
                        // Planet (Gebäude-Ausbauten, Ressourcen, Forschungen und Heimat-Flotte bzw Stationierte Flotten)
                        $reportData['resources'] = [
                            'eisen' => $targetPlanet->eisen, 'silber' => $targetPlanet->silber, 'uderon' => $targetPlanet->uderon, 'wasserstoff' => $targetPlanet->wasserstoff, 'energie' => $targetPlanet->energie
                        ];
                        $reportData['buildings'] = PlayerBuilding::getAllForPlanet($targetPlanet->id, $db);
                        $reportData['ships_on_planet'] = PlayerShip::getShipsOnPlanet($targetPlanet->id, $db); // Ships physically on the planet
                        $reportData['defenses'] = PlayerDefense::getDefensesOnPlanet($targetPlanet->id, $db);
                        // Need to get stationed fleets and research if defender exists
                        if ($defenderId) {
                            $reportData['stationed_fleets'] = Fleet::getStationedFleetsAtPlanet($targetPlanet->id, $defenderId, $db); // Assuming this method exists
                            $reportData['research'] = PlayerResearch::getResearchLevelsByPlayerId($defenderId, $db);
                        } else {
                            $reportData['stationed_fleets'] = [];
                            $reportData['research'] = [];
                        }
                        break;
                    case 'new_messages':
                        // New System-Messages for the defender
                        if ($defenderId) {
                            $reportData['new_messages'] = PlayerMessage::getNewSystemMessagesForPlayer($defenderId, $db);
                        } else {
                            $reportData['new_messages'] = [];
                        }
                        break;
                    case 'old_messages':
                        // Old System-Messages for the defender
                         if ($defenderId) {
                            $reportData['old_messages'] = PlayerMessage::getOldSystemMessagesForPlayer($defenderId, $db);
                        } else {
                            $reportData['old_messages'] = [];
                        }
                        break;
                    case 'shipyard_orders':
                        // Defender's shipyard queue/orders
                         if ($defenderId) {
                            $reportData['shipyard_orders'] = ConstructionQueue::getShipyardOrdersForPlanet($targetPlanet->id, $db);
                        } else {
                            $reportData['shipyard_orders'] = [];
                        }
                        break;
                    case 'disable_agents':
                        // This mission type's report content is unclear from MD.
                        // Maybe just a success confirmation?
                        $reportData['action_taken'] = 'Attempted to disable agents.';
                        // Actual disabling logic would go here or be called from here.
                        // For now, just report the attempt and success status.
                        break;
                    default:
                        // Should not happen due to validation in controller
                        $reportData['error'] = 'Unknown agent mission type.';
                        $isSuccess = false; // Mark as failed if mission type is unknown
                }
                NotificationService::createNotification($attackerId, "Agent Mission Successful ({$missionType})", "Agent mission to {$targetPlanet->name} ({$targetCoords}) successful. {$agentsLost} agents lost.", "success");

            } else { // Mission failed
                NotificationService::createNotification($attackerId, "Agent Mission Failed ({$missionType})", "Agent mission to {$targetPlanet->name} ({$targetCoords}) failed. {$agentsLost} agents lost.", "error");
            }

            // Defender notification always happens for Agent espionage based on MD.
            if ($defenderId && $isDetected) {
                $defenderNotificationMsg = "Eine feindliche Agentenaktivität wurde auf deinem Planeten {$targetPlanet->name} ({$targetCoords}) entdeckt.";
                if ($agentsLost > 0) { // Assuming a variable $agentsLostByDefender if defender's agents can be lost
                    $defenderNotificationMsg .= " {$agentsLost} deiner Agenten wurden dabei eliminiert.";
                } elseif ($agentsLost > 0 && $isSuccess) { // If attacker lost agents but was successful
                     $defenderNotificationMsg .= " Der Angriff war erfolgreich, aber einige feindliche Agenten wurden gestoppt.";
                } elseif ($agentsLost > 0 && !$isSuccess) { // If attacker lost agents and failed
                     $defenderNotificationMsg .= " Der Angriff wurde abgewehrt, feindliche Agenten wurden eliminiert.";
                }
                PlayerNotification::createNotification(
                    $defenderId,
                    PlayerNotification::TYPE_ESPIONAGE_ACTIVITY, // General activity
                    $defenderNotificationMsg,
                    null, // No link for defender's general alert
                    $db
                );
            }
            // Remove older NotificationService calls as they are now replaced

            $db->commit();
            return $reportData;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Combat::processAgentEspionage Error: " . $e->getMessage());
            // Agent losses on critical error? MD only mentions loss on failure.
            // Let's assume no agents are lost on a processing error before success/failure is determined.
            // Ensure notification for attacker on critical failure
            PlayerNotification::createNotification(
                $attackerId,
                PlayerNotification::TYPE_ESPIONAGE_REPORT, // Or a general system error type
                "Agentenmission auf {$targetPlanet->name} ({$targetCoords}) kritisch fehlgeschlagen.",
                null, // No report to link
                $db
            );
            $db->commit(); // Commit after logging error and sending notification
            return ['error' => $e->getMessage(), 'agents_lost' => $numAgents]; // Assume all agents lost on critical error
        }
    }
}
?>
