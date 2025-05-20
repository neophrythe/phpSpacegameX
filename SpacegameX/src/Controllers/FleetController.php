<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Fleet;
use App\Models\Planet;
use App\Models\PlayerShip; // Added for fetching ships on planet
use App\Services\Logger;
use PDO;

class FleetController extends Controller
{
    private $logger;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->logger = new Logger('fleet_controller');
    }

    /**
     * Shows the fleet movement and management page.
     * Potentially shows active fleets, options to send new fleets, etc.
     */
    public function index()
    {
        if (!isset($_SESSION['player_id'])) {
            // Redirect to login or show error
            header('Location: /login'); // Adjust as per your auth flow
            exit;
        }
        $playerId = (int)$_SESSION['player_id'];

        $fleetModel = new Fleet($this->db);
        $planetModel = new Planet($this->db);
        $playerShipModel = new PlayerShip($this->db);

        // Fetch current player's active fleets
        $activeFleets = $fleetModel->getActiveFleetsByPlayerId($playerId);

        // Fetch player's planets for fleet origin selection
        $stmt = $this->db->prepare("SELECT id, name, galaxy, system, position FROM planets WHERE player_id = :player_id ORDER BY id");
        $stmt->bindParam(':player_id', $playerId, \\PDO::PARAM_INT);
        $stmt->execute();
        $playerPlanets = $stmt->fetchAll(\\PDO::FETCH_OBJ);

        // Determine selected planet
        $currentPlanet = null;
        $selectedPlanetId = isset($_GET['planet_id']) ? (int)$_GET['planet_id'] : null;

        if ($selectedPlanetId) {
            $loadedPlanet = $planetModel->getById($selectedPlanetId);
            if ($loadedPlanet && $loadedPlanet->player_id == $playerId) {
                $currentPlanet = $loadedPlanet;
            } else {
                $selectedPlanetId = null; // Invalid or not owned
            }
        }

        if (!$currentPlanet) {
            $homePlanet = $planetModel->getHomePlanetByPlayerId($playerId);
            if ($homePlanet) {
                $currentPlanet = $homePlanet;
                $selectedPlanetId = $currentPlanet->id;
            } elseif (!empty($playerPlanets)) {
                // Default to the first planet in the list if no home planet somehow and no selection
                $currentPlanet = $planetModel->getById($playerPlanets[0]->id); // Assuming getById fetches full object
                $selectedPlanetId = $currentPlanet->id;
            }
        }
        
        // Fetch available ships on selected planet
        $shipsOnPlanet = [];
        if ($currentPlanet) {
            $shipsOnPlanet = $playerShipModel->getShipsOnPlanet($currentPlanet->id);
        }

        $data = [
            'pageTitle' => 'Fleet Management', // Example page title
            'activeFleets' => $activeFleets,
            'playerPlanets' => $playerPlanets,
            'currentPlanet' => $currentPlanet,
            'selectedPlanetId' => $selectedPlanetId,
            'shipsOnPlanet' => $shipsOnPlanet,
            // Add other necessary data for the view
        ];

        $this->view('game/fleet_management', $data); 
    }

    /**
     * Handles sending a new fleet on a mission.
     * This would be a generic method that then calls specific mission handlers.
     */
    public function sendFleet()
    {
        // Input validation: player_id, start_planet_id, target_coordinates, mission_type, ships, etc.
        // For now, focusing on blockade and arkon
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $playerId = $_SESSION['player_id']; // Assuming player ID is in session
            $startPlanetId = $_POST['start_planet_id'] ?? null;
            $targetGalaxy = $_POST['target_galaxy'] ?? null;
            $targetSystem = $_POST['target_system'] ?? null;
            $targetPosition = $_POST['target_position'] ?? null;
            $missionType = $_POST['mission_type'] ?? null;
            $ships = $_POST['ships'] ?? []; // Expected format: ['ship_type_internal_name' => quantity]
            $blockadeDurationHours = $_POST['blockade_duration_hours'] ?? null;

            // Convert ship internal names to IDs
            $shipsWithIds = [];
            if (!empty($ships)) {
                $shipTypeModel = new \App\Models\ShipType($this->db); // Corrected namespace
                foreach ($ships as $internalName => $quantity) {
                    if ($quantity > 0) {
                        $shipType = $shipTypeModel->getByInternalName($internalName);
                        if ($shipType) {
                            $shipsWithIds[$shipType->id] = (int)$quantity;
                        } else {
                            $this->logger->log("Invalid ship type internal name provided: {$internalName}. Player: {$playerId}", 'WARNING');
                            $_SESSION['error_message'] = "Invalid ship type selected: {$internalName}.";
                            header('Location: /fleet?error=InvalidShipType');
                            exit;
                        }
                    }
                }
            }

            if (empty($shipsWithIds)) {
                 $_SESSION['error_message'] = 'No ships selected for the mission.';
                 header('Location: /fleet?error=NoShipsSelected');
                 exit;
            }


            switch ($missionType) {
                case 'blockade':
                    $this->initiateBlockade($playerId, $startPlanetId, $targetGalaxy, $targetSystem, $targetPosition, $shipsWithIds, $blockadeDurationHours);
                    break;
                case 'arkon':
                    $this->initiateArkonAttack($playerId, $startPlanetId, $targetGalaxy, $targetSystem, $targetPosition, $shipsWithIds);
                    break;
                // Add other mission types here
                default:
                    $this->logger->log('Unsupported mission type: ' . $missionType, 'WARNING');
                    $_SESSION['error_message'] = 'Unsupported mission type selected.';
                    header('Location: /fleet?error=UnsupportedMission');
                    exit;
            }
        } else {
            // Likely redirect to fleet management page or show an error
            header('Location: /fleet?error=InvalidRequest');
            exit;
        }
    }

    /**
     * Initiates a blockade mission.
     */
    public function initiateBlockade(int $playerId, int $startPlanetId, int $targetGalaxy, int $targetSystem, int $targetPosition, array $shipsWithIds, ?int $blockadeDurationHours)
    {
        $fleetModel = new \App\Models\Fleet($this->db); // Corrected namespace
        $planetModel = new \App\Models\Planet($this->db); // Corrected namespace

        // Validate inputs
        if (empty($shipsWithIds) || $blockadeDurationHours === null || $blockadeDurationHours <= 0 || $blockadeDurationHours > 168) { // Max 1 week blockade
            $this->logger->log("Invalid parameters for blockade. Player: {$playerId}", 'ERROR');
            $_SESSION['error_message'] = 'Invalid parameters for blockade. Duration must be between 1 and 168 hours.';
            header('Location: /fleet?error=InvalidBlockadeParams');
            exit;
        }

        // 1. Get target planet ID from coordinates
        $targetPlanet = $planetModel->getPlanetByCoordinates($targetGalaxy, $targetSystem, $targetPosition);
        if (!$targetPlanet) {
            $this->logger->log("Target planet not found for blockade. Coords: G{$targetGalaxy}:S{$targetSystem}:P{$targetPosition}. Player: {$playerId}", 'ERROR');
            $_SESSION['error_message'] = 'Target planet not found.';
            header('Location: /fleet?error=TargetPlanetNotFound');
            exit;
        }
        $targetPlanetId = $targetPlanet['id'];

        // Pre-dispatch validation using Fleet::canBlockade()
        // Note: canBlockade is not implemented in the provided Fleet model.
        // If it were, it would be called here:
        /*
        $canBlockadeResult = $fleetModel->canBlockade($playerId, $targetPlanetId, $shipsWithIds);
        if (!$canBlockadeResult['can_blockade']) {
            $this->logger->log("Pre-dispatch blockade check failed: {$canBlockadeResult['message']}. Player: {$playerId}, Target: P{$targetPlanetId}", 'WARNING');
            $_SESSION['error_message'] = $canBlockadeResult['message'];
            header('Location: /fleet?error=CannotBlockade');
            exit;
        }
        */
        
        // Note: Some checks in canBlockade might overlap with checks below (e.g., blockading own planet).
        // It's fine to have them, canBlockade serves as a central ruleset for the model.

        // 2. Check if target planet is not owned by the attacker (already covered by canBlockade, but kept for clarity if canBlockade changes)
        if ($targetPlanet['player_id'] === $playerId) {
            $_SESSION['error_message'] = 'Cannot blockade your own planet.';
            header('Location: /fleet?error=CannotBlockadeOwnPlanet');
            exit;
        }

        // 3. Calculate blockade_strength.
        // Assuming Fleet::calculateBlockadeStrength exists and works with [ship_type_id => quantity]
        $blockadeStrength = $fleetModel->calculateFleetCombatStrength($shipsWithIds); // Using existing calculateFleetCombatStrength
        if ($blockadeStrength === 0) {
            $_SESSION['error_message'] = 'Blockade fleet must contain ships with combat strength.'; // Adjusted message
             header('Location: /fleet?error=NoBlockadeStrength');
            exit;
        }

        // 4. Create fleet entry in DB using the updated sendFleet method
        try {
            $fleetId = $fleetModel->sendFleet(
                $playerId,
                $startPlanetId,
                $targetGalaxy,
                $targetSystem,
                $targetPosition,
                'blockade', // mission_type
                $shipsWithIds, // ships [ship_type_id => quantity]
                [], // resources (empty for blockade)
                $blockadeDurationHours, // blockade_duration_hours
                $blockadeStrength // blockade_strength - calculated here, passed to sendFleet
            );

            if ($fleetId) {
                $this->logger->log("Blockade fleet dispatched. Fleet ID: {$fleetId}, Player: {$playerId}, Target: P{$targetPlanetId}, Duration: {$blockadeDurationHours}h, Strength: {$blockadeStrength}", 'INFO');
                $_SESSION['success_message'] = 'Blockade fleet dispatched successfully!';
                header('Location: /fleet'); // Redirect to fleet overview
                exit;
            } else {
                // sendFleet should throw exceptions on failure, so this else might not be reached
                 $this->logger->log("Failed to dispatch blockade fleet (unknown reason). Player: {$playerId}", 'ERROR');
                 $_SESSION['error_message'] = 'Failed to dispatch blockade fleet. Please try again.';
                 header('Location: /fleet?error=FleetCreationFail');
                 exit;
            }
        } catch (\\Exception $e) {
             $this->logger->log("Exception during blockade dispatch: " . $e->getMessage() . ". Player: {$playerId}", 'ERROR');
             $_SESSION['error_message'] = 'Error dispatching blockade fleet: ' . $e->getMessage();
             header('Location: /fleet?error=DispatchError');
             exit;
        }
    }

    /**
     * Initiates an Arkon attack mission.
     */
    public function initiateArkonAttack(int $playerId, int $startPlanetId, int $targetGalaxy, int $targetSystem, int $targetPosition, array $shipsWithIds)
    {
        $fleetModel = new \App\Models\Fleet($this->db);
        $planetModel = new \App\Models\Planet($this->db);

        // 1. Get target planet ID from coordinates
        $targetPlanet = $planetModel->getPlanetByCoordinates($targetGalaxy, $targetSystem, $targetPosition);
        if (!$targetPlanet) {
            $this->logger->log("Target planet not found for Arkon attack. Coords: G{$targetGalaxy}:S{$targetSystem}:P{$targetPosition}. Player: {$playerId}", 'ERROR');
            $_SESSION['error_message'] = 'Target planet not found.';
            header('Location: /fleet?error=TargetPlanetNotFound');
            exit;
        }
        $targetPlanetId = $targetPlanet['id'];
        $targetPlanetOwnerId = $targetPlanet['player_id'];

        // 2. Check if target planet is not owned by the attacker
        if ($targetPlanetOwnerId === $playerId) {
            $_SESSION['error_message'] = 'Cannot attack your own planet with an Arkon bomb.';
            header('Location: /fleet?error=CannotArkonOwnPlanet');
            exit;
        }

        // 3. Check for Arkonspamschutz
        $remainingAttacks = $planetModel->checkArkonSpamProtection($playerId, $targetPlanetOwnerId);

        if ($remainingAttacks === false) {
            $this->logger->log("Arkon spam protection triggered. Player: {$playerId}, Target: P{$targetPlanetId}", 'WARNING');
            $_SESSION['error_message'] = 'Arkon spam protection active for this target. You have launched too many Arkon attacks against similar targets recently.';
            header('Location: /fleet?error=ArkonSpamProtection');
            exit;
        } else {
             $this->logger->log("Arkon spam check passed. Player: {$playerId}, Target: P{$targetPlanetId}. Remaining attacks allowed: {$remainingAttacks}", 'INFO');
             // Optionally add a notification or message about remaining attacks allowed
        }

        // 4. Check if fleet contains at least one Arkon bomb
        $arkonBombType = \App\Models\ShipType::getByInternalName('arkonbombe'); // Corrected namespace
        $hasArkonBomb = false;
        if ($arkonBombType) {
            if (isset($shipsWithIds[$arkonBombType->id]) && $shipsWithIds[$arkonBombType->id] > 0) {
                $hasArkonBomb = true;
            }
        }

        if (!$hasArkonBomb) {
            $_SESSION['error_message'] = 'Arkon attack mission requires at least one Arkon bomb.';
            header('Location: /fleet?error=NoArkonBomb');
            exit;
        }

        // 5. Create fleet entry in DB using the updated sendFleet method
        try {
            $fleetId = $fleetModel->sendFleet(
                $playerId,
                $startPlanetId,
                $targetGalaxy,
                $targetSystem,
                $targetPosition,
                'arkon', // mission_type
                $shipsWithIds // ships [ship_type_id => quantity]
                // No resources or blockade duration for Arkon attack
            );

            if ($fleetId) {
                $this->logger->log("Arkon attack fleet dispatched. Fleet ID: {$fleetId}, Player: {$playerId}, Target: G{$targetGalaxy}:S{$targetSystem}:P{$targetPosition}", 'INFO');
                $_SESSION['success_message'] = 'Arkon attack fleet dispatched successfully!';
                header('Location: /fleet'); // Redirect to fleet overview
                exit;
            } else {
                 $this->logger->log("Failed to dispatch Arkon attack fleet (unknown reason). Player: {$playerId}", 'ERROR');
                 $_SESSION['error_message'] = 'Failed to dispatch Arkon attack fleet. Please try again.';
                 header('Location: /fleet?error=FleetCreationFail');
                 exit;
            }
        } catch (\Exception $e) {
             $this->logger->log("Exception during Arkon attack dispatch: " . $e->getMessage() . ". Player: {$playerId}", 'ERROR');
             $_SESSION['error_message'] = 'Error dispatching Arkon attack fleet: ' . $e->getMessage();
             header('Location: /fleet?error=DispatchError');
             exit;
        }
    }


    /**
     * Handles logic when a blockade fleet arrives at its target.
     * This would typically be called by a cron job or a game update script.
     */
    public function processBlockadeArrival(int $fleetId)
    {
        $fleetModel = new \App\Models\Fleet($this->db); // Corrected namespace
        // The Fleet model's static method `blockadePlanet` is called from `Fleet::processFleets`
        // This controller method is likely redundant if `processFleets` is the main entry point.
        
        $fleetData = $fleetModel->getFleetById($fleetId); 

        if (!$fleetData || $fleetData->mission_type !== 'blockade') {
            $this->logger->log("Invalid fleet or not a blockade mission for processing arrival via controller. Fleet ID: {$fleetId}", 'WARNING');
            return false;
        }
        
        // Explicitly calling blockadePlanet here if direct controller invocation is desired for some reason,
        // though processFleets should be the primary mechanism.
        // $fleetModel->blockadePlanet($fleetData); // This would be Models\Fleet::blockadePlanet($fleetData)

        $this->logger->log("Blockade arrival processing acknowledged for Fleet ID: {$fleetId} by controller. Main logic resides in Models\Fleet::processFleets.", 'INFO');
        return true; 
    }

    /**
     * Recalls/cancels an active blockade.
     */
    public function recallBlockade(int $fleetId)
    {
        $playerId = $_SESSION['player_id']; // Authenticate
        $fleetModel = new Fleet($this->db);
        $fleet = $fleetModel->getFleetById($fleetId);

        if (!$fleet || $fleet['player_id'] !== $playerId || $fleet['mission_type'] !== 'blockade') {
            $_SESSION['error_message'] = 'Invalid fleet or not authorized.';
            header('Location: /fleet?error=InvalidRecall');
            exit;
        }

        // If blockade is active, it needs to return. If not yet arrived, it can be cancelled.
        if ($fleet['is_active_blockade']) {
            // Initiate return trip
            if ($fleetModel->deactivateAndPrepareReturn($fleetId)) {
                 $_SESSION['success_message'] = 'Blockade recalled and fleet is returning.';
            } else {
                 $_SESSION['error_message'] = 'Failed to recall blockade.';
            }
        } else {
            // Cancel fleet if not yet arrived
            // $fleet variable already contains fleet data including origin_planet_id and ships_json (assumed)
            if ($fleetModel->cancelFleet($fleetId)) { // This should just delete the fleet record
                 
                 // Return ships to origin planet
                 if (!empty($fleet['ships_json']) && !empty($fleet['origin_planet_id'])) {
                     $shipsArray = json_decode($fleet['ships_json'], true);
                     if (is_array($shipsArray) && !empty($shipsArray)) {
                         foreach ($shipsArray as $shipTypeId => $quantity) {
                             if ($quantity > 0) {
                                 try {
                                     $stmt = $this->db->prepare("
                                         INSERT INTO player_ships (player_id, planet_id, ship_type_id, quantity)
                                         VALUES (:player_id, :planet_id, :ship_type_id, :quantity)
                                         ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                                     ");
                                     $stmt->execute([
                                         ':player_id' => $playerId,
                                         ':planet_id' => $fleet['origin_planet_id'],
                                         ':ship_type_id' => $shipTypeId,
                                         ':quantity' => $quantity
                                     ]);
                                 } catch (\\PDOException $e) {
                                     $this->logger->log("Error returning ships for fleet {$fleetId}, ship_type {$shipTypeId} to planet {$fleet['origin_planet_id']}: " . $e->getMessage(), 'ERROR');
                                     // Decide if this should set an error message for the user
                                 }
                             }
                         }
                         $_SESSION['success_message'] = 'Blockade mission cancelled and ships returned to origin planet.';
                     } else {
                         $_SESSION['success_message'] = 'Blockade mission cancelled. No ship data to return or invalid format.';
                         $this->logger->log("Blockade cancelled for fleet {$fleetId}, but ships_json was empty or invalid.", 'WARNING');
                     }
                 } else {
                     $_SESSION['success_message'] = 'Blockade mission cancelled. Origin planet or ship data missing for return.';
                     $this->logger->log("Blockade cancelled for fleet {$fleetId}, but origin_planet_id or ships_json was missing.", 'WARNING');
                 }
            } else {
                 $_SESSION['error_message'] = 'Failed to cancel blockade mission.';
            }
        }
        header('Location: /fleet');
        exit;
    }

    /**
     * Recalls a fleet from its mission.
     */
    public function recallFleet()
    {
        if (!isset($_SESSION['player_id']) || !isset($_POST['fleet_id'])) {
            $_SESSION['error_message'] = 'Invalid request to recall fleet.';
            header('Location: /fleet');
            exit;
        }

        $playerId = (int)$_SESSION['player_id'];
        $fleetId = (int)$_POST['fleet_id'];

        $fleetModel = new Fleet($this->db);
        $fleet = $fleetModel->getFleetById($fleetId);

        // Check if fleet exists, belongs to the player, and is not already returning or completed
        if (!$fleet || $fleet->player_id !== $playerId || $fleet->is_returning || $fleet->is_completed) {
            $_SESSION['error_message'] = 'Invalid fleet or cannot be recalled.';
            header('Location: /fleet');
            exit;
        }

        try {
            // If the fleet has not yet arrived at its target
            if (strtotime($fleet->arrival_time) > time()) {
                // Cancel the fleet mid-flight
                // This involves deleting the fleet record and returning ships/cargo to the origin planet.
                // Need to fetch ships and cargo before deleting the fleet record.
                $fleetDetails = $fleetModel->getFleetDetails($fleetId);
                $shipsToReturn = $fleetDetails['ships'] ?? [];
                $cargoToReturn = [
                    'eisen' => $fleet->eisen_cargo,
                    'silber' => $fleet->silber_cargo,
                    'uderon' => $fleet->uderon_cargo,
                    'wasserstoff' => $fleet->wasserstoff_cargo,
                    'energie' => $fleet->energie_cargo,
                ];
                $originPlanetId = $fleet->start_planet_id;

                $this->db->beginTransaction();
                try {
                    // Delete the fleet record
                    $stmtDeleteFleet = $this->db->prepare("DELETE FROM fleets WHERE id = :fleet_id");
                    $stmtDeleteFleet->bindParam(':fleet_id', $fleetId, PDO::PARAM_INT);
                    $stmtDeleteFleet->execute();

                    // Return ships to origin planet
                    $playerShipModel = new PlayerShip($this->db);
                    foreach ($shipsToReturn as $ship) {
                        if ($ship->quantity > 0) {
                            $playerShipModel->addShips($originPlanetId, $ship->ship_type_id, $ship->quantity, $this->db);
                        }
                    }

                    // Return cargo to origin planet
                    $planetModel = new Planet($this->db);
                    $originPlanet = $planetModel->getById($originPlanetId, $this->db);
                    if ($originPlanet) {
                        $sqlAddRes = "UPDATE planets SET ";
                        $updates = [];
                        $params = [':planet_id' => $originPlanetId];
                        foreach ($cargoToReturn as $resType => $quantity) {
                            if ($quantity > 0) {
                                $updates[] = "{$resType} = {$resType} + :{$resType}_quantity";
                                $params[":{$resType}_quantity"] = $quantity;
                            }
                        }
                        if (!empty($updates)) {
                            $sqlAddRes .= implode(', ', $updates) . " WHERE id = :planet_id";
                            $stmtAddRes = $this->db->prepare($sqlAddRes);
                            $stmtAddRes->execute($params);
                        }
                    } else {
                         error_log("recallFleet: Origin planet {$originPlanetId} not found for returning cargo after mid-flight cancellation of fleet {$fleetId}. Cargo lost.");
                    }

                    $this->db->commit();
                    $_SESSION['success_message'] = 'Fleet recalled successfully and ships/cargo returned to origin planet.';

                } catch (\Exception $e) {
                    $this->db->rollBack();
                    error_log("recallFleet: Error during mid-flight cancellation for fleet {$fleetId}: " . $e->getMessage());
                    $_SESSION['error_message'] = 'Error recalling fleet mid-flight: ' . $e->getMessage();
                }

            } else {
                // If the fleet has already arrived, set it to return
                if ($fleetModel->setFleetToReturn($fleetId, $this->db, "Recalled by player.")) {
                    $_SESSION['success_message'] = 'Fleet recalled successfully and is now returning.';
                } else {
                    $_SESSION['error_message'] = 'Failed to recall fleet.';
                }
            }

        } catch (\Exception $e) {
            error_log("recallFleet: Unexpected error for fleet {$fleetId}: " . $e->getMessage());
            $_SESSION['error_message'] = 'An unexpected error occurred while trying to recall the fleet.';
        }

        header('Location: /fleet'); // Redirect back to fleet overview
        exit;
    }


    // Other methods:
    // - breakBlockade(attackingFleetId, defendingBlockadeFleetId) - part of combat
    // - viewBlockade(planetId) - for the blockaded player to see fleet details
}
