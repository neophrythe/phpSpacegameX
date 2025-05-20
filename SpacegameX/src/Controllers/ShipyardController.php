<?php
namespace Controllers;

use Core\Controller;
use Models\Planet;
use Models\ShipType;
use Models\PlayerShip;
use Models\DefenseType; // Added for defense units
use Models\PlayerDefense; // Added for defense units
use Models\Fleet;
use Models\BuildingType; // Added for requirements check
use Models\PlayerBuilding; // Added for requirements check
use Models\ResearchType; // Added for requirements check
use Models\PlayerResearch; // Added for requirements check

class ShipyardController extends Controller {
    
    public function index() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
        
        $playerId = $_SESSION['user_id'];
        
        // Check and complete any finished construction (ships and defense)
        PlayerShip::checkAndCompleteShipConstruction();
        PlayerDefense::checkAndCompleteDefenseConstruction(); // Need to implement this method
        
        // Check and process fleet movements
        Fleet::processFleets();
        
        // Get current planet (from request or default to home planet)
        $planetId = isset($_GET['planet']) ? intval($_GET['planet']) : null;
        
        if (!$planetId) {
            $planet = Planet::getHomePlanetByPlayerId($playerId);
        } else {
            $planet = Planet::getById($planetId);
            // Verify player owns this planet
            if (!$planet || $planet->player_id != $playerId) {
                $planet = Planet::getHomePlanetByPlayerId($playerId);
            }
        }
        
        if (!$planet) {
            $this->redirect('/');
        }
        
        // Update resources on planet
        Planet::updateResources($planet->id);
        
        // Get shipyard level
        $shipyardLevel = PlayerBuilding::getBuildingLevelForPlanet($planet->id, 'werft'); // Assuming 'werft' is internal name for Shipyard

        // Get Raumstation level (needed for defense construction time)
        $raumstationLevel = PlayerBuilding::getBuildingLevelForPlanet($planet->id, 'raumstation'); // Assuming 'raumstation' is internal name for Raumstation
        
        // If shipyard doesn't exist or is level 0, redirect to buildings
        if ($shipyardLevel == 0) {
            $this->setFlashMessage('error', 'Werft wird auf diesem Planeten benötigt, um Schiffe oder Verteidigungsanlagen zu bauen.');
            $this->redirect('/buildings?planet=' . $planet->id);
        }
        
        // Get available ship types
        $shipTypes = ShipType::getAll();
        
        // Get available defense types
        $defenseTypes = DefenseType::getAll(); // Need to implement this method
        
        // Get current ships on planet
        $currentShips = PlayerShip::getShipsOnPlanet($planet->id);

        // Get current defense on planet
        $currentDefense = PlayerDefense::getDefenseOnPlanet($planet->id); // Need to implement this method
        
        // Get build queue (ships and defense)
        $db = \Core\Model::getDB();
        $stmt = $db->prepare("SELECT cq.*, 
                                CASE 
                                    WHEN cq.item_type = 'ship' THEN st.name_de
                                    WHEN cq.item_type = 'defense' THEN dt.name_de
                                END as item_name
                             FROM construction_queue cq
                             LEFT JOIN static_ship_types st ON cq.item_id = st.id AND cq.item_type = 'ship'
                             LEFT JOIN static_defense_types dt ON cq.item_id = dt.id AND cq.item_type = 'defense'
                             WHERE cq.player_id = :player_id 
                               AND cq.planet_id = :planet_id 
                               AND (cq.item_type = 'ship' OR cq.item_type = 'defense')
                             ORDER BY cq.end_time ASC");
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':planet_id', $planet->id, PDO::PARAM_INT);
        $stmt->execute();
        $buildQueue = $stmt->fetchAll(\PDO::FETCH_OBJ);
        
        // Get player's other planets for navigation
        $stmt = $db->prepare("SELECT id, name, galaxy, system, position FROM planets 
                             WHERE player_id = :player_id ORDER BY id");
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        $planets = $stmt->fetchAll(\PDO::FETCH_OBJ);
        
        // Get active fleets
        $activeFleets = Fleet::getActiveFleetsByPlayerId($playerId);
        
        $data = [
            'pageTitle' => 'Werft',
            'playerName' => \Models\Player::findById($playerId)->username,
            'planet' => $planet,
            'shipyardLevel' => $shipyardLevel,
            'raumstationLevel' => $raumstationLevel, // Added Raumstation level
            'shipTypes' => $shipTypes,
            'defenseTypes' => $defenseTypes, // Added defense types
            'currentShips' => $currentShips,
            'currentDefense' => $currentDefense, // Added current defense
            'buildQueue' => $buildQueue,
            'planets' => $planets,
            'activeFleets' => $activeFleets
        ];
        
        $this->view('game.shipyard', $data);
    }
    
    public function build() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['item_type']) || !isset($_POST['item_id']) || !isset($_POST['quantity'])) {
            $this->redirect('/shipyard');
        }
        
        $playerId = $_SESSION['user_id'];
        $itemType = $_POST['item_type']; // 'ship' or 'defense'
        $itemId = intval($_POST['item_id']);
        $quantity = max(1, intval($_POST['quantity']));
        $planetId = isset($_POST['planet_id']) ? intval($_POST['planet_id']) : null;
        
        if (!$planetId) {
            $planet = Planet::getHomePlanetByPlayerId($playerId);
            $planetId = $planet->id;
        } else {
            $planet = Planet::getById($planetId);
            // Verify player owns this planet
            if (!$planet || $planet->player_id != $playerId) {
                $this->redirect('/shipyard');
            }
        }
        
        // Update resources
        Planet::updateResources($planetId);
        
        // Get current resources
        $planet = Planet::getById($planetId);
        
        $itemTypeModel = null;
        $playerItemModel = null;
        $startBuildingMethod = null;
        $buildTimeModifierLevel = 0; // Shipyard level for ships, Raumstation level for defense

        if ($itemType === 'ship') {
            $itemTypeModel = ShipType::getById($itemId);
            $playerItemModel = PlayerShip::getByPlanetAndType($planetId, $itemId); // Need getByPlanetAndType in PlayerShip
            $startBuildingMethod = '\Models\PlayerShip::startBuildingShips';
            $buildTimeModifierLevel = PlayerBuilding::getBuildingLevelForPlanet($planetId, 'werft'); // Shipyard level
        } elseif ($itemType === 'defense') {
            $itemTypeModel = DefenseType::getById($itemId); // Need DefenseType model
            $playerItemModel = PlayerDefense::getByPlanetAndType($planetId, $itemId); // Need getByPlanetAndType in PlayerDefense
            $startBuildingMethod = '\Models\PlayerDefense::startBuildingDefense'; // Need startBuildingDefense in PlayerDefense
            $buildTimeModifierLevel = PlayerBuilding::getBuildingLevelForPlanet($planetId, 'raumstation'); // Raumstation level
        } else {
            // Invalid item type
            $this->redirect('/shipyard?planet=' . $planetId);
        }

        if (!$itemTypeModel) {
            // Invalid ship or defense type
            $this->redirect('/shipyard?planet=' . $planetId);
        }

        // Check requirements (buildings and research)
        if ($itemTypeModel->requirements_json) {
            $requirements = json_decode($itemTypeModel->requirements_json, true);
            
            if (isset($requirements['building'])) {
                foreach ($requirements['building'] as $internalName => $level) {
                    $requiredBuildingType = BuildingType::getByInternalName($internalName);
                    if ($requiredBuildingType) {
                        $playerRequiredBuilding = PlayerBuilding::getByPlanetAndType($planetId, $requiredBuildingType->id);
                        if (!$playerRequiredBuilding || $playerRequiredBuilding->level < $level) {
                            // Requirement not met
                            $this->setFlashMessage('error', "Voraussetzung nicht erfüllt: Gebäude '{$requiredBuildingType->name_de}' Level {$level} benötigt.");
                            $this->redirect('/shipyard?planet=' . $planetId);
                        }
                    }
                }
            }

            if (isset($requirements['research'])) {
                // Get player's research levels
                $playerResearchLevels = PlayerResearch::getResearchLevelsByPlayerId($playerId);

                foreach ($requirements['research'] as $internalName => $level) {
                    $requiredResearchType = ResearchType::getByInternalName($internalName);
                    if ($requiredResearchType) {
                        $playerResearchLevel = $playerResearchLevels[$requiredResearchType->id] ?? 0;
                        if ($playerResearchLevel < $level) {
                            // Requirement not met
                            $this->setFlashMessage('error', "Voraussetzung nicht erfüllt: Forschung '{$requiredResearchType->name_de}' Level {$level} benötigt.");
                            $this->redirect('/shipyard?planet=' . $planetId);
                        }
                    }
                }
            }
        }

        // Calculate costs
        $cost = $itemTypeModel->getCostForQuantity($quantity); // Need getCostForQuantity in ShipType and DefenseType

        // Check if planet has sufficient resources
        if ($planet->eisen < $cost['eisen'] ||
            $planet->silber < $cost['silber'] ||
            $planet->uderon < $cost['uderon'] ||
            $planet->wasserstoff < $cost['wasserstoff'] ||
            $planet->energie < $cost['energie']) {
            // Not enough resources
            $this->setFlashMessage('error', 'Nicht genügend Ressourcen für den Bau vorhanden.');
            $this->redirect('/shipyard?planet=' . $planetId);
        }
        
        // Calculate build time
        $adjustedBuildTimeSeconds = $itemTypeModel->getBuildTime($quantity, $buildTimeModifierLevel); // Need getBuildTime in ShipType and DefenseType

        // Start building
        $result = call_user_func($startBuildingMethod, $planetId, $itemId, $quantity, $adjustedBuildTimeSeconds); // Call the appropriate static method

        if ($result) {
            // Deduct resources from the planet
            $db = \Core\Model::getDB();
            $sql = "UPDATE planets SET 
                    eisen = eisen - :eisen, 
                    silber = silber - :silber, 
                    uderon = uderon - :uderon,
                    wasserstoff = wasserstoff - :wasserstoff,
                    energie = energie - :energie
                    WHERE id = :planet_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':eisen', $cost['eisen'], \PDO::PARAM_STR);
            $stmt->bindParam(':silber', $cost['silber'], \PDO::PARAM_STR);
            $stmt->bindParam(':uderon', $cost['uderon'], \PDO::PARAM_STR);
            $stmt->bindParam(':wasserstoff', $cost['wasserstoff'], \PDO::PARAM_STR);
            $stmt->bindParam(':energie', $cost['energie'], \PDO::PARAM_STR);
            $stmt->bindParam(':planet_id', $planetId, \PDO::PARAM_INT);
            $stmt->execute();

            $this->setFlashMessage('success', $quantity . 'x ' . $itemTypeModel->name_de . ' erfolgreich zur Warteschlange hinzugefügt.');
        } else {
            $this->setFlashMessage('error', 'Fehler beim Hinzufügen zur Warteschlange (z.B. Warteschlange voll).');
        }
        
        // Redirect back to shipyard
        $this->redirect('/shipyard?planet=' . $planetId);
    }
    
    public function fleet() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
        
        $playerId = $_SESSION['user_id'];
        
        // Check and process fleet movements
        Fleet::processFleets();
        
        // Get current planet
        $planetId = isset($_GET['planet']) ? intval($_GET['planet']) : null;
        
        if (!$planetId) {
            $planet = Planet::getHomePlanetByPlayerId($playerId);
        } else {
            $planet = Planet::getById($planetId);
            // Verify player owns this planet
            if (!$planet || $planet->player_id != $playerId) {
                $planet = Planet::getHomePlanetByPlayerId($playerId);
            }
        }
        
        if (!$planet) {
            $this->redirect('/');
        }
        
        // Get ships on planet
        $shipsOnPlanet = PlayerShip::getShipsOnPlanet($planet->id);
        
        // Get mission type and target coordinates from request
        $missionType = isset($_GET['mission']) ? $_GET['mission'] : '';
        $targetGalaxy = isset($_GET['galaxy']) ? intval($_GET['galaxy']) : $planet->galaxy;
        $targetSystem = isset($_GET['system']) ? intval($_GET['system']) : $planet->system;
        $targetPosition = isset($_GET['position']) ? intval($_GET['position']) : $planet->position;
        
        // Get player's planets for quick selection
        $db = \Core\Model::getDB();
        $stmt = $db->prepare("SELECT id, name, galaxy, system, position FROM planets 
                             WHERE player_id = :player_id ORDER BY id");
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        $planets = $stmt->fetchAll(\PDO::FETCH_OBJ);
        
        // Get active fleets
        $activeFleets = Fleet::getActiveFleetsByPlayerId($playerId);
        
        $data = [
            'pageTitle' => 'Flotte',
            'playerName' => \Models\Player::findById($playerId)->username,
            'planet' => $planet,
            'shipsOnPlanet' => $shipsOnPlanet,
            'missionType' => $missionType,
            'targetGalaxy' => $targetGalaxy,
            'targetSystem' => $targetSystem,
            'targetPosition' => $targetPosition,
            'planets' => $planets,
            'activeFleets' => $activeFleets
        ];
        
        $this->view('game.fleet', $data);
    }
    
    public function send() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['planet_id']) || !isset($_POST['mission'])) {
            $this->redirect('/fleet');
        }
        
        $playerId = $_SESSION['user_id'];
        $startPlanetId = intval($_POST['planet_id']);
        $missionType = $_POST['mission'];
        $targetGalaxy = isset($_POST['target_galaxy']) ? intval($_POST['target_galaxy']) : 1;
        $targetSystem = isset($_POST['target_system']) ? intval($_POST['target_system']) : 1;
        $targetPosition = isset($_POST['target_position']) ? intval($_POST['target_position']) : 1;
        
        // Verify player owns the start planet
        $planet = Planet::getById($startPlanetId);
        if (!$planet || $planet->player_id != $playerId) {
            $this->redirect('/fleet');
        }
        
        // Find or create target planet
        $db = \Core\Model::getDB();
        $stmt = $db->prepare("SELECT id FROM planets 
                             WHERE galaxy = :galaxy AND system = :system AND position = :position");
        $stmt->bindParam(':galaxy', $targetGalaxy, \PDO::PARAM_INT);
        $stmt->bindParam(':system', $targetSystem, \PDO::PARAM_INT);
        $stmt->bindParam(':position', $targetPosition, \PDO::PARAM_INT);
        $stmt->execute();
        $targetPlanetId = $stmt->fetchColumn();
        
        if (!$targetPlanetId) {
            // Create a planet for colonization if it doesn't exist
            if ($missionType === 'colonize' && $targetPosition >= 1 && $targetPosition <= 15) { // Assuming positions 1-15 are colonizable
                $sql = "INSERT INTO planets (player_id, name, galaxy, system, position, diameter)
                        VALUES (0, 'Unbesiedelt', :galaxy, :system, :position, :diameter)";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':galaxy', $targetGalaxy, \PDO::PARAM_INT);
                $stmt->bindParam(':system', $targetSystem, \PDO::PARAM_INT);
                $stmt->bindParam(':position', $targetPosition, \PDO::PARAM_INT);
                $diameter = mt_rand(7000, 12000); // Example diameter
                $stmt->bindParam(':diameter', $diameter, \PDO::PARAM_INT);
                $stmt->execute();
                $targetPlanetId = $db->lastInsertId();
            } else {
                $_SESSION['fleet_error'] = "Ungültiges Ziel";
                $this->redirect('/fleet?planet=' . $startPlanetId);
            }
        }
        
        // Collect selected ships
        $ships = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'ship_') === 0 && intval($value) > 0) {
                $shipId = intval(substr($key, 5));
                $ships[$shipId] = intval($value);
            }
        }
        
        if (empty($ships)) {
            $_SESSION['fleet_error'] = "Keine Schiffe ausgewählt";
            $this->redirect('/fleet?planet=' . $startPlanetId);
        }
        
        // Collect resources if this is a transport mission
        $resources = [
            'eisen' => isset($_POST['eisen']) ? max(0, intval($_POST['eisen'])) : 0, // Updated resource names
            'silber' => isset($_POST['silber']) ? max(0, intval($_POST['silber'])) : 0, // Updated resource names
            'uderon' => isset($_POST['uderon']) ? max(0, intval($_POST['uderon'])) : 0, // Added Uderon
            'wasserstoff' => isset($_POST['wasserstoff']) ? max(0, intval($_POST['wasserstoff'])) : 0, // Updated resource names
            'energie' => isset($_POST['energie']) ? max(0, intval($_POST['energie'])) : 0 // Added Energie
        ];
        
        // Send fleet
        $fleetId = Fleet::sendFleet($playerId, $startPlanetId, $targetGalaxy, $targetSystem, $targetPosition, $missionType, $ships, $resources);
        
        if ($fleetId) {
            $_SESSION['fleet_success'] = "Flotte gesendet";
        } else {
            $_SESSION['fleet_error'] = "Fehler beim Senden der Flotte";
        }
        
        $this->redirect('/fleet?planet=' . $startPlanetId);
    }
}
?>
