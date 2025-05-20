<?php
namespace Controllers;

use Core\Controller;
use Models\Player;
use Models\Planet;
use Models\EspionageReport;
use Models\Fleet; // Espionage missions are sent as fleets
use Models\ShipType; // To get ship type ID for Sonden/Agenten
use Models\PlayerShip; // To check available units
use Models\ResearchType; // To get research type ID for requirements
use Models\PlayerResearch; // To check player research levels
use Models\BuildingType; // To get building type ID for requirements
use Models\PlayerBuilding; // To check player building levels
use Models\PlayerAgent; // Added for agent espionage
use Models\Combat; // Added to call Combat methods
use Models\NotificationService; // Added for notifications
use Models\PlayerNotification; // Added for notifications

class EspionageController extends Controller {

    public function index() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }

        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId); // Or Player::getPlayerDataById($playerId, $this->db);

        if (!$player) {
            $this->redirect('/');
        }

        // Get player\'s espionage reports (maybe only recent ones for the main page?)
        // $reports = EspionageReport::getReportsByPlayerId($playerId, $this->db, 10); // Example: limit to 10

        // Get player\'s planets for sending missions
        $playerPlanets = Planet::getPlanetsByPlayerId($playerId, $this->db); 

        // Get player\'s available espionage units (Sonden, Agenten) on each planet
        $espionageUnits = [];
        $sondeType = ShipType::getByInternalName('spionagesonde'); 
        $agentType = ShipType::getByInternalName('agent'); 

        if ($playerPlanets) {
            foreach ($playerPlanets as $planet) {
                $espionageUnits[$planet->id] = [
                    'sonde' => $sondeType ? (PlayerShip::getByPlanetAndType($planet->id, $sondeType->id, $this->db)->quantity ?? 0) : 0,
                    'agent' => $agentType ? (PlayerAgent::getAgentsOnPlanet($planet->id, $this->db) ?? 0) : 0, 
                ];
            }
        }


        $data = [
            'pageTitle' => 'Spionagezentrum',
            'player' => $player,
            // 'reports' => $reports, // Decide if you want to show some reports here
            'playerPlanets' => $playerPlanets, 
            'espionageUnits' => $espionageUnits, 
            'sondeTypeId' => $sondeType->id ?? null, 
            'agentTypeId' => $agentType->id ?? null,
            'navigation' => $this->getNavigationContext(), // Added navigation context
        ];

        $this->view('game.espionage_index', $data); // Assuming you have/create espionage_index.php
    }

    public function sendMission() {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->setFlashMessage('error', 'Ungültige Anfrage.');
            $this->redirect('/game/espionage');
            return;
        }

        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);

        if (!$player) {
            $this->redirect('/');
        }

        $missionType = $_POST['mission_type'] ?? null; // 'planet', 'new_messages', 'old_messages', 'shipyard_orders', 'disable_agents'
        $targetGalaxy = intval($_POST['target_galaxy'] ?? 0);
        $targetSystem = intval($_POST['target_system'] ?? 0);
        $targetPosition = intval($_POST['target_position'] ?? 0);
        $startPlanetId = intval($_POST['start_planet_id'] ?? 0);
        $unitTypeId = intval($_POST['unit_type_id'] ?? 0); // Ship type ID of the espionage unit
        $quantity = max(1, intval($_POST['quantity'] ?? 0)); // Quantity of espionage units

        // Verify player owns the start planet
        $startPlanet = Planet::getById($startPlanetId);
        if (!$startPlanet || $startPlanet->player_id !== $playerId) {
            $this->setFlashMessage('error', "Ungültiger Startplanet oder keine Berechtigung.");
            $this->redirect('/game/espionage');
            return;
        }

        // Get the static type for the espionage unit (ShipType for Probes, maybe a different type for Agents?)
        // Assuming unitTypeId refers to ShipType for Probes and Agent type for Agents.
        // This needs clarification in the data structure.
        // For now, let's assume unitTypeId is ShipType ID for Probes and a special ID for Agents.
        // Let's get the ShipType first, and if not found, assume it's an Agent unit type ID.
        $espionageUnitType = ShipType::getById($unitTypeId);
        $isProbeMission = $espionageUnitType && $espionageUnitType->internal_name === 'spionagesonde';
        $isAgentMission = !$espionageUnitType; // Assuming if not a known ship type, it's an Agent unit type ID

        // Check if player has Spionage technology at least level 1 (required for all espionage)
        $spionageTechnikType = ResearchType::getByInternalName('spionagetechnik');
        $playerResearchLevels = PlayerResearch::getResearchLevelsByPlayerId($playerId);
        $espionageLevel = $spionageTechnikType ? ($playerResearchLevels[$spionageTechnikType->id] ?? 0) : 0;

        if ($espionageLevel < 1) {
            $this->setFlashMessage('error', 'Spionagetechnik Level 1 benötigt, um Spionagemissionen zu starten.');
            $this->redirect('/game/espionage');
            return;
        }

        // Validate target planet exists
        $targetPlanet = Planet::getByCoordinates($targetGalaxy, $targetSystem, $targetPosition);
        if (!$targetPlanet) {
            $this->setFlashMessage('error', "Zielplanet nicht gefunden.");
            $this->redirect('/game/espionage');
            return;
        }
        $targetPlanetId = $targetPlanet->id; // Use the actual target planet ID

        // Handle Probe Missions (Fleet-based)
        if ($isProbeMission) {
            // Check if player has sufficient Probes on the start planet
            $availableUnits = PlayerShip::getByPlanetAndType($startPlanetId, $unitTypeId);
            if (!$availableUnits || $availableUnits->quantity < $quantity) {
                $this->setFlashMessage('error', "Nicht genügend Spionageeinheiten ({$espionageUnitType->name_de}) auf dem Startplaneten vorhanden.");
                $this->redirect('/game/espionage');
                return;
            }

            // Check if the target planet is within the same galaxy or allowed range for probes (MD says within 3 galaxies)
            $distance = Fleet::calculateDistance($startPlanet->galaxy, $startPlanet->system, $startPlanet->position, $targetGalaxy, $targetSystem, $targetPosition);
            // Assuming a max probe distance check is needed, based on MD "Ausspionieren mit Sonden außerhalb der eigenen Galaxie ist auf 3 Galaxien beschränkt."
            // This implies a max distance or max galaxy difference check. Let's use galaxy difference for simplicity.
            if (abs($startPlanet->galaxy - $targetGalaxy) > 3) {
                 $this->setFlashMessage('error', "Spionage mit Sonden ist auf Ziele innerhalb von 3 Galaxien beschränkt.");
                 $this->redirect('/game/espionage');
                 return;
            }


            // Prepare the mission data (for EspionageReport)
            $missionData = [
                'player_id' => $playerId,
                'start_planet_id' => $startPlanetId,
                'target_planet_id' => $targetPlanetId, // Store target planet ID
                'target_galaxy' => $targetGalaxy,
                'target_system' => $targetSystem,
                'target_position' => $targetPosition,
                'unit_type_id' => $unitTypeId, // Ship type ID
                'quantity' => $quantity,
                'mission_type' => 'probe', // Explicitly 'probe'
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
            ];

            // Create EspionageReport entry (status 'pending')
            $reportId = EspionageReport::create($missionData);

            if ($reportId) {
                // Send the fleet (Fleet model will handle travel time and arrival processing)
                $shipsToSend = [$unitTypeId => $quantity];
                try {
                    $fleetId = Fleet::sendFleet($playerId, $startPlanetId, $targetGalaxy, $targetSystem, $targetPosition, 'espionage', $shipsToSend);

                    if ($fleetId) {
                        // Update EspionageReport with fleet_id if needed (optional)
                        // EspionageReport::update($reportId, ['fleet_id' => $fleetId]);
                        $this->setFlashMessage('success', "Spionagemission (Sonde) erfolgreich gestartet. Flotten-ID: {$fleetId}");
                    } else {
                        // Fleet sending failed, mark report as failed? Or rely on Fleet model to handle?
                        // For now, assume Fleet::sendFleet throws on critical failure.
                        // If it returns false, handle as error.
                        EspionageReport::update($reportId, ['status' => 'failed', 'report_data' => json_encode(['error' => 'Failed to send fleet'])]);
                        $this->setFlashMessage('error', "Fehler beim Senden der Spionagemission (Sonde).");
                    }
                } catch (\Exception $e) {
                     // Catch exceptions from sendFleet
                     EspionageReport::update($reportId, ['status' => 'failed', 'report_data' => json_encode(['error' => 'Fleet dispatch exception: ' . $e->getMessage()])]);
                     $this->setFlashMessage('error', "Fehler beim Senden der Spionagemission (Sonde): " . $e->getMessage());
                }

            } else {
                $this->setFlashMessage('error', "Fehler beim Erstellen des Spionageberichts (Sonde).");
            }

            $this->redirect('/game/espionage');
            return;

        }
        // Handle Agent Missions (Planet-based)
        else if ($isAgentMission) {
            // Assuming unitTypeId for Agent is a specific value, not a ShipType ID.
            // This needs to be defined in static data or config.
            // Let's assume for now that the 'agent' unit type has a specific ID, and we check PlayerAgent quantity.
            $agentType = ShipType::getByInternalName('agent'); // Assuming 'agent' is also a ShipType for cost/points, but stationed differently
            if (!$agentType || $unitTypeId !== $agentType->id) {
                 $this->setFlashMessage('error', "Ungültiger Spionageeinheitstyp für Agentenmission.");
                 $this->redirect('/game/espionage');
                 return;
            }

            // Check if player has sufficient Agents on the start planet
            $availableAgents = PlayerAgent::getAgentsOnPlanet($startPlanetId, $this->db); // Assuming this method exists
            if ($availableAgents < $quantity) {
                $this->setFlashMessage('error', "Nicht genügend Agenten auf dem Startplaneten vorhanden.");
                $this->redirect('/game/espionage');
                return;
            }

            // Calculate Energy Cost for Agent Mission
            // MD: 1 Einheit Energie pro verlegtem Agent. Auch dies kostet 1 Einheit Energie pro verlegtem Agent.
            // "Auch dies kostet 1 Einheit Energie pro verlegtem Agent. Jedoch können sich diese Kosten bei einer großen Enfternung erhöhen."
            // This implies a base cost per agent + distance cost.
            // Let's assume a base cost of 1 Energy per agent + 1 Energy per agent per galaxy difference.
            $distance = Fleet::calculateDistance($startPlanet->galaxy, $startPlanet->system, $startPlanet->position, $targetGalaxy, $targetSystem, $targetPosition);
            $galaxyDifference = abs($startPlanet->galaxy - $targetGalaxy);
            $energyCost = $quantity * (1 + $galaxyDifference); // Simplified cost formula

            // Check if start planet has enough energy
            if ($startPlanet->energie < $energyCost) {
                $this->setFlashMessage('error', "Nicht genügend Energie auf dem Startplaneten für die Agentenmission. Benötigt: {$energyCost}");
                $this->redirect('/game/espionage?planet_id=' . $startPlanetId);
                return;
            }

            // Deduct Energy from start planet
            $db = \Core\Model::getDB();
            $stmtDeductEnergy = $db->prepare("UPDATE planets SET energie = GREATEST(0, energie - :energy_cost) WHERE id = :planet_id");
            $stmtDeductEnergy->bindParam(':energy_cost', $energyCost, PDO::PARAM_STR);
            $stmtDeductEnergy->bindParam(':planet_id', $startPlanetId, PDO::PARAM_INT);
            $stmtDeductEnergy->execute();

            // Deduct Agents from start planet (Agents used are consumed/deployed)
            // MD says "Bei einem nicht erfolgreichen Spionageversuch verliert der Angreifer 10% der eingesetzten Agenten"
            // This implies Agents are NOT consumed on successful missions, only 10% on failure.
            // This contradicts the idea of "using" agents for a mission.
            // Let's assume the 10% loss is the *only* loss on failure, and on success, they return safely.
            // The quantity sent is the quantity *used* for the attempt.

            // Prepare the mission data (for EspionageReport)
            $missionData = [
                'player_id' => $playerId,
                'start_planet_id' => $startPlanetId,
                'target_planet_id' => $targetPlanetId, // Store target planet ID
                'target_galaxy' => $targetGalaxy,
                'target_system' => $targetSystem,
                'target_position' => $targetPosition,
                'unit_type_id' => $unitTypeId, // Agent unit type ID
                'quantity' => $quantity, // Quantity of agents used
                'mission_type' => $missionType, // e.g., 'planet', 'new_messages'
                'status' => 'completed', // Agent missions are instant
                'created_at' => date('Y-m-d H:i:s'),
                // Report data will be added by processAgentEspionage
            ];

            // Process the Agent espionage mission instantly
            try {
                // Corrected call to pass startPlanetId
                $espionageResult = Combat::processAgentEspionage($playerId, $startPlanetId, $targetPlanetId, $quantity, $missionType, $this->db);

                // Create EspionageReport entry with the result
                $reportDataJson = json_encode($espionageResult);
                $reportId = EspionageReport::create(array_merge($missionData, ['report_data' => $reportDataJson]));

                if (isset($espionageResult['error'])) {
                    $this->setFlashMessage('error', "Agentenmission fehlgeschlagen: " . $espionageResult['error']);
                } else if ($espionageResult['success']) {
                    $this->setFlashMessage('success', "Agentenmission erfolgreich. Bericht ID: {$reportId}");
                } else {
                    $agentsLost = $espionageResult['agents_lost'] ?? 0;
                    $this->setFlashMessage('error', "Agentenmission fehlgeschlagen. {$agentsLost} Agenten verloren. Bericht ID: {$reportId}");
                }

            } catch (\Exception $e) {
                error_log("EspionageController::sendMission (Agent) Error: " . $e->getMessage());
                $this->setFlashMessage('error', "Fehler bei der Agentenmission: " . $e->getMessage());
            }

            $this->redirect('/game/espionage');
            return;
        }
        // If not a recognized Probe or Agent unit type
        else {
            $this->setFlashMessage('error', "Ungültiger Spionageeinheitstyp.");
            $this->redirect('/game/espionage');
            return;
        }
    }

    public function reports() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
        $playerId = $_SESSION['user_id'];
        $player = Player::getPlayerDataById($playerId, $this->db);

        if (!$player) {
            $this->redirect('/'); 
        }

        $reports = EspionageReport::getReportsByPlayerId($playerId, $this->db);
        
        // Example of adding target player names if your EspionageReport model stores target_player_id
        // This is a placeholder, adjust based on your actual EspionageReport structure
        if ($reports) {
            foreach ($reports as $report) {
                if (isset($report->target_planet_id)) {
                    $targetPlanet = Planet::getById($report->target_planet_id, $this->db);
                    if ($targetPlanet && $targetPlanet->player_id) {
                        $targetPlayer = Player::getPlayerDataById($targetPlanet->player_id, $this->db);
                        $report->target_player_name = $targetPlayer ? $targetPlayer->username : 'Unbekannt';
                    } else {
                        $report->target_player_name = 'Niemand (Unbewohnt)';
                    }
                } else {
                     $report->target_player_name = 'N/A';
                }
            }
        }


        $data = [
            'pageTitle' => 'Spionageberichte',
            'player' => $player,
            'reports' => $reports,
            'navigation' => $this->getNavigationContext(),
        ];
        $this->view('game.espionage_reports_list', $data);
    }

    public function viewReport($params = []) {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
        $playerId = $_SESSION['user_id'];
        $player = Player::getPlayerDataById($playerId, $this->db);

        if (!$player) {
            $this->redirect('/');
        }

        if (!isset($params['id']) || !is_numeric($params['id'])) {
            $this->setFlashMessage('error', 'Ungültige Berichts-ID.');
            $this->redirect('/espionage/reports');
            return;
        }
        $reportId = (int)$params['id'];

        $report = EspionageReport::getById($reportId, $this->db);

        if (!$report || $report->player_id !== $playerId) {
            $this->setFlashMessage('error', 'Spionagebericht nicht gefunden oder keine Berechtigung.');
            $this->redirect('/espionage/reports');
            return;
        }

        // Mark report as read
        if (!$report->is_read) {
            EspionageReport::markAsRead($reportId, $this->db); 
            // Also mark the corresponding notification as read if it exists
            // Ensure PlayerNotification model and its methods accept $this->db
            PlayerNotification::markReadByLink('/espionage/reports/view/' . $reportId, $playerId, $this->db);
        }

        $reportData = json_decode($report->report_data, true);
        
        // Add target player name to the report object for the view
        if (isset($report->target_planet_id)) {
            $targetPlanet = Planet::getById($report->target_planet_id, $this->db);
            if ($targetPlanet && $targetPlanet->player_id) {
                $targetPlayer = Player::getPlayerDataById($targetPlanet->player_id, $this->db);
                $report->target_player_name = $targetPlayer ? $targetPlayer->username : 'Unbekannt';
                $report->target_planet_name = $targetPlanet->name;
            } else {
                $report->target_player_name = 'Niemand';
                $report->target_planet_name = $targetPlanet ? $targetPlanet->name : 'Unbekannter Planet';
            }
        } else {
             $report->target_player_name = 'N/A';
             $report->target_planet_name = 'N/A';
        }
        
        // Add attacker (own) player name and planet name
        if(isset($report->start_planet_id)) {
            $startPlanet = Planet::getById($report->start_planet_id, $this->db);
            $report->start_planet_name = $startPlanet ? $startPlanet->name : 'Unbekannter Startplanet';
        }
         $report->attacker_player_name = $player->username;


        // Get player\'s espionage level (current, as context)
        $spionageTechnikType = ResearchType::getByInternalName('spionagetechnik');
        $playerResearchLevels = PlayerResearch::getResearchLevelsByPlayerId($playerId, $this->db);
        $espionageLevel = $spionageTechnikType ? ($playerResearchLevels[$spionageTechnikType->id] ?? 0) : 0;

        // Get unit name
        $unitName = 'Unbekannte Einheit'; // Default
        if (isset($report->unit_type_id) && is_numeric($report->unit_type_id)) {
            $shipType = ShipType::getById($report->unit_type_id, $this->db);
            if ($shipType) {
                $unitName = $shipType->name_de;
            } elseif (strtolower($report->mission_type) === 'agent') { // Check mission_type for agent
                $unitName = 'Agent';
            }
        } elseif (strtolower($report->mission_type) === 'agent') { // Fallback for older reports or if unit_type_id is not set for agents
             $unitName = 'Agent';
        }


        $data = [
            'pageTitle' => 'Spionagebericht #\' . $report->id,
            'player' => Player::getPlayerDataById($playerId, $this->db),
            'report' => $report,
            'reportData' => $reportData,
            'espionageLevel' => $espionageLevel,
            'navigation' => $this->getNavigationContext(),
            'unitName' => $unitName, // Pass the unit name to the view
        ];

        $this->view('game.espionage_report_view', $data);
    }

    /**
     * Handles the movement of Agents between a player's planets.
     */
    public function moveAgents() {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->setFlashMessage('error', 'Ungültige Anfrage für Agentenverlegung.');
            $this->redirect('/game/espionage'); // Redirect to espionage page
            return;
        }

        $playerId = (int)$_SESSION['user_id'];
        $player = Player::findById($playerId);

        if (!$player) {
            $this->redirect('/');
        }

        $startPlanetId = (int)($_POST['start_planet_id'] ?? 0);
        $targetPlanetId = (int)($_POST['target_planet_id'] ?? 0);
        $quantity = max(1, intval($_POST['quantity'] ?? 0)); // Quantity of agents to move

        // Validate start and target planets
        $startPlanet = Planet::getById($startPlanetId);
        $targetPlanet = Planet::getById($targetPlanetId);

        if (!$startPlanet || $startPlanet->player_id !== $playerId) {
            $this->setFlashMessage('error', "Ungültiger Startplanet oder keine Berechtigung.");
            $this->redirect('/game/espionage');
            return;
        }

        if (!$targetPlanet || $targetPlanet->player_id !== $playerId) {
            $this->setFlashMessage('error', "Ungültiger Zielplanet oder Planet gehört dir nicht.");
            $this->redirect('/game/espionage?planet_id=' . $startPlanetId); // Redirect back to start planet
            return;
        }

        // Validate planets are not the same
        if ($startPlanetId === $targetPlanetId) {
            $this->setFlashMessage('error', 'Start- und Zielplanet für Agentenverlegung können nicht identisch sein.');
            $this->redirect('/game/espionage?planet_id=' . $startPlanetId);
            return;
        }

        // Check if player has sufficient Agents on the start planet
        $availableAgents = PlayerAgent::getAgentsOnPlanet($startPlanetId, $this->db); // Assuming this method exists
        if ($availableAgents < $quantity) {
            $this->setFlashMessage('error', "Nicht genügend Agenten auf dem Startplaneten vorhanden.");
            $this->redirect('/game/espionage?planet_id=' . $startPlanetId);
            return;
        }

        // Calculate Energy Cost for Agent Movement
        // MD: 1 Einheit Energie pro verlegtem Agent. Auch dies kostet 1 Einheit Energie pro verlegtem Agent.
        // "Auch dies kostet 1 Einheit Energie pro verlegtem Agent. Jedoch können sich diese Kosten bei einer großen Enfternung erhöhen."
        // This implies a base cost per agent + distance cost.
        // Let's assume a base cost of 1 Energy per agent + 1 Energy per agent per galaxy difference.
        $distance = Fleet::calculateDistance($startPlanet->galaxy, $startPlanet->system, $startPlanet->position, $targetPlanet->galaxy, $targetPlanet->system, $targetPlanet->position);
        $galaxyDifference = abs($startPlanet->galaxy - $targetPlanet->galaxy);
        $energyCost = $quantity * (1 + $galaxyDifference); // Simplified cost formula

        // Check if start planet has enough energy
        if ($startPlanet->energie < $energyCost) {
            $this->setFlashMessage('error', "Nicht genügend Energie auf dem Startplaneten für die Agentenverlegung. Benötigt: {$energyCost}");
            $this->redirect('/game/espionage?planet_id=' . $startPlanetId);
            return;
        }

        // Process Agent Movement
        $db = \Core\Model::getDB();
        $db->beginTransaction();
        try {
            // Deduct Energy from start planet
            $stmtDeductEnergy = $db->prepare("UPDATE planets SET energie = GREATEST(0, energie - :energy_cost) WHERE id = :planet_id");
            $stmtDeductEnergy->bindParam(':energy_cost', $energyCost, PDO::PARAM_STR);
            $stmtDeductEnergy->bindParam(':planet_id', $startPlanetId, PDO::PARAM_INT);
            $stmtDeductEnergy->execute();

            // Deduct Agents from start planet
            PlayerAgent::removeAgents($startPlanetId, $quantity, $db); // Assuming removeAgents exists and takes planet_id

            // Add Agents to target planet
            PlayerAgent::addAgents($targetPlanetId, $quantity, $db); // Assuming addAgents exists and takes planet_id

            $db->commit();

            $this->setFlashMessage('success', "{$quantity} Agent(en) erfolgreich von {$startPlanet->name} nach {$targetPlanet->name} verlegt. Energie Kosten: {$energyCost}.");

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("EspionageController::moveAgents Error: " . $e->getMessage());
            $this->setFlashMessage('error', "Fehler bei der Agentenverlegung: " . $e->getMessage());
        }

        // Redirect back to the start planet's espionage page
        header('Location: /game/espionage?planet_id=' . $startPlanetId);
        exit;
    }

    private function getNavigationContext()
    {
        if (!isset($_SESSION['user_id'])) {
            return [
                'unread_messages' => 0,
                'unread_combat_reports' => 0,
                'unread_espionage_reports' => 0,
            ];
        }
        $playerId = $_SESSION['user_id'];
        // Ensure all these static methods can accept $this->db if they need it and are not using a global DB instance.
        // If they use Model::getDB() internally, it might be fine.
        return [
            'unread_messages' => \\Models\\PlayerMessage::countUnreadMessages($playerId, $this->db),
            'unread_combat_reports' => \\Models\\BattleReport::getUnreadReportsCountByPlayerId($playerId, $this->db),
            'unread_espionage_reports' => \\Models\\EspionageReport::getUnreadReportsCountByPlayerId($playerId, $this->db),
        ];
    }
}
?>
