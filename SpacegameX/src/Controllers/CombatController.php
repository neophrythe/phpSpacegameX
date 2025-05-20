<?php
namespace Controllers;

use Core\Controller;
use Models\Fleet;
use Models\Planet;
use Models\Player;
use Models\BattleReport;
use Models\PlayerShip;
use Models\PlayerDefense;
use Models\ShipType;
use Models\DefenseType;
use Models\PlayerResearch; // For research bonuses
use Models\AllianceResearch; // For alliance research bonuses
use Models\Alliance; // To get alliance research
use Models\PlayerNotification; // Added for report notifications

class CombatController extends Controller {

    public function reports() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
            return;
        }
        $playerId = $_SESSION['user_id'];

        $reports = BattleReport::getReportsByPlayerId($playerId);

        $this->view('game.combat_reports_list', [
            'pageTitle' => 'Kampfberichte',
            'reports' => $reports,
            'playerName' => Player::getPlayerDataById($playerId)->username
        ]);
    }

    public function viewReport($params) {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
            return;
        }
        $playerId = $_SESSION['user_id'];
        $reportId = $params['id'] ?? null;

        if (!$reportId) {
            $_SESSION['error_message'] = 'Ungültige Berichts-ID.';
            $this->redirect('/combat/reports');
            return;
        }

        $report = BattleReport::getById($reportId);

        if (!$report || ($report->attacker_id != $playerId && $report->defender_id != $playerId)) {
            $_SESSION['error_message'] = 'Kampfbericht nicht gefunden oder Zugriff verweigert.';
            $this->redirect('/combat/reports');
            return;
        }

        // Mark as read if the current player is either attacker or defender and it's unread
        if (!$report->is_read && ($report->attacker_id == $playerId || $report->defender_id == $playerId)) {
            $report->markAsRead();
            // Mark the corresponding notification as read
            PlayerNotification::markReadByLink('/combat/reports/view/' . $reportId, $playerId);
        }
        
        // Decode JSON report data for display
        $reportData = json_decode($report->report_data, true);

        $this->view('game.combat_report_view', [
            'pageTitle' => 'Kampfbericht Details',
            'report' => $report,
            'reportData' => $reportData,
            'playerName' => Player::getPlayerDataById($playerId)->username
        ]);
    }

    /**
     * Process a battle between an attacking fleet and a defending planet.
     *
     * @param int $fleetId The ID of the attacking fleet.
     * @return array|false Battle result data or false on failure.
     */
    public function processBattle($fleetId) {
        $db = \Core\Model::getDB();
        $db->beginTransaction();

        try {
            // Get attacking fleet details
            $fleet = Fleet::getFleetDetails($fleetId);
            if (!$fleet || $fleet['fleet']->mission_type !== 'attack') {
                throw new \Exception("Invalid fleet or not an attack mission.");
            }

            $attackingFleet = $fleet['fleet'];
            $attackingShips = $fleet['ships'];
            $attackerPlayerId = $attackingFleet->player_id;
            $targetPlanetId = $attackingFleet->target_planet_id;

            // Get defending planet and player details
            $defendingPlanet = Planet::getById($targetPlanetId);
            if (!$defendingPlanet) {
                throw new \Exception("Target planet not found.");
            }
            $defenderPlayerId = $defendingPlanet->player_id;
            $defenderPlayer = $defenderPlayerId ? Player::findById($defenderPlayerId) : null;

            // Get defending ships and defense units on the planet owned by the defender
            $defendingShipsRaw = $defenderPlayerId ? PlayerShip::getShipsOnPlanet($targetPlanetId, $db) : [];
            $defendingDefenseRaw = $defenderPlayerId ? PlayerDefense::getDefenseOnPlanet($targetPlanetId, $db) : [];

            // Get ships from fleets stationed at this planet (owned by defender or allies)
            // Assuming Fleet::getStationedFleetsAtPlanet fetches fleets stationed at the target planet
            // and includes player_id for research lookup.
            $stationedFleets = Fleet::getStationedFleetsAtPlanet($targetPlanetId, $defenderPlayerId, $db); 
            $stationedShipsFormatted = [];
            foreach ($stationedFleets as $stationedFleet) {
                // Ensure the stationed fleet is not the attacking fleet itself
                if ($stationedFleet->id === $fleetId) {
                    continue;
                }
                $fleetDetails = Fleet::getFleetDetails($stationedFleet->id); // Get ships for each stationed fleet
                if ($fleetDetails && !empty($fleetDetails['ships'])) {
                    // Add these ships to the defending ships list
                    // Need to ensure they are formatted correctly for combat
                    $stationedFleetOwnerResearch = PlayerResearch::getResearchLevelsByPlayerId($stationedFleet->player_id, $db);
                    $formattedStationedShips = \Models\Combat::formatUnitsForBattle($fleetDetails['ships'], $stationedFleetOwnerResearch, false, $db);
                    $stationedShipsFormatted = array_merge($stationedShipsFormatted, $formattedStationedShips);
                }
            }

            // Get attacker's research bonuses (Waffen, Schilde, Att-Recycling)
            $attackerResearch = PlayerResearch::getResearchLevelsByPlayerId($attackerPlayerId, $db);
            $waffenResearchType = \Models\ResearchType::getByInternalName('waffen', $db); // Use fully qualified name
            $schildeResearchType = \Models\ResearchType::getByInternalName('schilde', $db);
            $attRecyclingResearchType = \Models\ResearchType::getByInternalName('att_recycling', $db);

            $attackerWeaponBonus = isset($waffenResearchType->id, $attackerResearch[$waffenResearchType->id]) ? $attackerResearch[$waffenResearchType->id] * 0.01 : 0;
            $attackerShieldBonus = isset($schildeResearchType->id, $attackerResearch[$schildeResearchType->id]) ? $attackerResearch[$schildeResearchType->id] * 0.01 : 0;
            $attackerAttRecyclingLevel = isset($attRecyclingResearchType->id, $attackerResearch[$attRecyclingResearchType->id]) ? $attackerResearch[$attRecyclingResearchType->id] : 0;

            // Get defender's research bonuses (Waffen, Schilde, Recycling)
            $defenderWeaponBonus = 0;
            $defenderShieldBonus = 0;
            $defenderRecyclingLevel = 0;
            if ($defenderPlayerId) {
                 $defenderResearch = PlayerResearch::getResearchLevelsByPlayerId($defenderPlayerId, $db);
                 $defenderWeaponBonus = isset($waffenResearchType->id, $defenderResearch[$waffenResearchType->id]) ? $defenderResearch[$waffenResearchType->id] * 0.01 : 0;
                 $defenderShieldBonus = isset($schildeResearchType->id, $defenderResearch[$schildeResearchType->id]) ? $defenderResearch[$schildeResearchType->id] * 0.01 : 0;
                 $recyclingResearchType = \Models\ResearchType::getByInternalName('recycling', $db);
                 $defenderRecyclingLevel = isset($recyclingResearchType->id, $defenderResearch[$recyclingResearchType->id]) ? $defenderResearch[$recyclingResearchType->id] : 0;
            }

            // Get attacker's alliance research bonuses (Waffen, Schilde)
            $attackerAllianceWeaponBonus = 0;
            $attackerAllianceShieldBonus = 0;
            $attackerPlayer = Player::getPlayerData($attackerPlayerId, $db);
            if ($attackerPlayer && $attackerPlayer->alliance_id) {
                $attackerAlliance = Alliance::getById($attackerPlayer->alliance_id, $db);
                if ($attackerAlliance) {
                    $attackerAllianceResearch = AllianceResearch::getAllForAlliance($attackerAlliance->id, $db);
                    // Assuming internal names for alliance research are 'allianzwaffentechnik' and 'allianzschildtechnik'
                    foreach ($attackerAllianceResearch as $research) {
                        if ($research->internal_name === 'allianzwaffentechnik') {
                            $attackerAllianceWeaponBonus = $research->level * 0.01; // Assuming 1% per level
                        } elseif ($research->internal_name === 'allianzschildtechnik') {
                            $attackerAllianceShieldBonus = $research->level * 0.01; // Assuming 1% per level
                        }
                    }
                }
            }
            
            // Get defender's alliance research bonuses (Waffen, Schilde)
            $defenderAllianceWeaponBonus = 0;
            $defenderAllianceShieldBonus = 0;
            if ($defenderPlayerId) {
                $defenderPlayer = Player::getPlayerData($defenderPlayerId, $db);
                 if ($defenderPlayer && $defenderPlayer->alliance_id) {
                    $defenderAlliance = Alliance::getById($defenderPlayer->alliance_id, $db);
                    if ($defenderAlliance) {
                        $defenderAllianceResearch = AllianceResearch::getAllForAlliance($defenderAlliance->id, $db);
                        foreach ($defenderAllianceResearch as $research) {
                            if ($research->internal_name === 'allianzwaffentechnik') {
                                $defenderAllianceWeaponBonus = $research->level * 0.01;
                            } elseif ($research->internal_name === 'allianzschildtechnik') {
                                $defenderAllianceShieldBonus = $research->level * 0.01;
                            }
                        }
                    }
                }
            }

            // Combine player and alliance research bonuses
            $attackerTotalWeaponBonus = $attackerWeaponBonus + $attackerAllianceWeaponBonus;
            $attackerTotalShieldBonus = $attackerShieldBonus + $attackerAllianceShieldBonus;
            $defenderTotalWeaponBonus = $defenderWeaponBonus + $defenderAllianceWeaponBonus;
            $defenderTotalShieldBonus = $defenderShieldBonus + $defenderAllianceShieldBonus;


            // Simulate combat rounds
            $battleLog = [];
            $maxRounds = defined('COMBAT_MAX_ROUNDS') ? COMBAT_MAX_ROUNDS : 6; // Use constant

            // Pass formatted units to resolveCombat
            $combatResult = \Models\Combat::resolveCombat(
                $attackingFleet,
                $attackingShips, // Attacking ships are already formatted by Fleet::getFleetDetails
                $defendingPlanet,
                $allDefendingShipsFormatted, // Pass combined and formatted defending ships
                $defendingDefenseFormatted, // Pass formatted defending defense
                $db
            );

            if (isset($combatResult['error'])) {
                // Error during combat (e.g., target shielded, noob protection already handled by processBattle, or other critical error)
                // Notification and fleet return are usually handled within processBattle or by the error handler there.
                // If fleet is not set to return by processBattle due to an error it caught, ensure it here.
                $fleetCheck = Fleet::getFleetById($fleetId);
                if ($fleetCheck && !$fleetCheck->is_returning && !$fleetCheck->is_completed) {
                     NotificationService::createNotification($attackerFleet->player_id, "Attack Mission Problem", "A problem occurred during the attack on {$defendingPlanet->galaxy}:{$defendingPlanet->system}:{$defendingPlanet->position}: {$combatResult['error']}. Fleet returning.", "error");
                     Fleet::setFleetToReturn($fleetId, $db, "Combat processing error: " . substr($combatResult['error'], 0, 50));
                }
                $db->commit(); // Commit the transaction started at the beginning
                return ['error' => $combatResult['error']];
            }

            // Combat::resolveCombat is expected to handle detailed notifications to both players,
            // update ship counts, resources, and create a battle report.
            // CombatController just needs to set the attacking fleet to return if it wasn't destroyed.
            
            // Check if the attacking fleet still exists after combat (it might have been destroyed)
            $attackingFleetAfterCombat = Fleet::getFleetDetails($fleetId);

            if ($attackingFleetAfterCombat && $attackingFleetAfterCombat['fleet'] && !$attackingFleetAfterCombat['fleet']->is_returning && !$attackingFleetAfterCombat['fleet']->is_completed) {
                 // Set the attacking fleet to return
                 Fleet::setFleetToReturn($fleetId, $db, "Attack mission completed.");
            } else if (!$attackingFleetAfterCombat) {
                 // Attacking fleet was destroyed in combat. No need to set to return.
                 error_log("Attacking fleet {$fleetId} was destroyed in combat.");
            }


            $db->commit(); // Commit the transaction started at the beginning
            return $combatResult;

        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("CombatController::processBattle Error for fleet {$fleetId}: " . $e->getMessage() . "\\n" . $e->getTraceAsString());
            // Attempt to return the fleet if an error occurs mid-process
            try {
                if (isset($fleetId) && Fleet::getFleetById($fleetId)) { // Check if fleet still exists
                     Fleet::setFleetToReturn($fleetId, self::getDB(), "Combat processing error: " . substr($e->getMessage(), 0, 100));
                }
            } catch (Exception $returnEx) {
                error_log("CombatController::processBattle - Failed to set fleet {$fleetId} to return after error: " . $returnEx->getMessage());
            }
            return false;
        }
    }
}
?>
