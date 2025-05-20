<?php
namespace Controllers;

use Core\Controller;
use Models\Player;
use Models\Planet;
use Models\BuildingType;
use Models\PlayerBuilding;
use Models\PlayerResearch; // Added for requirements check
use Core\Model; // For DB access to construction_queue

class BuildingController extends Controller {

    public function index() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
            return;
        }

        $playerId = $_SESSION['user_id'];
        
        // Ensure game state is updated
        $gameController = new GameController();
        $gameController->runGameLoopUpdates($playerId);

        $selectedPlanetId = isset($_GET['planet_id']) ? (int)$_GET['planet_id'] : null;

        $planet = null;
        if ($selectedPlanetId) {
            $candidatePlanet = Planet::getById($selectedPlanetId);
            if ($candidatePlanet && $candidatePlanet->player_id == $playerId) {
                $planet = $candidatePlanet;
            } else {
                $_SESSION['error_message'] = 'Ungültige Planeten-ID oder Planet gehört dir nicht. Wird auf Heimatplanet umgeleitet.';
                $planet = Planet::getHomePlanetByPlayerId($playerId);
                if (!$planet) {
                     $this->view('game.error', ['error' => 'Kein Heimatplanet gefunden und keine gültige Planeten-ID angegeben.']);
                     return;
                }
                $this->redirect('/game/buildings?planet_id=' . $planet->id);
                return;
            }
        } else {
            $planet = Planet::getHomePlanetByPlayerId($playerId);
            if (!$planet) {
                 $this->view('game.error', ['error' => 'Kein Heimatplanet gefunden. Bitte wähle einen Planeten.']);
                 return;
            }
            // Redirect to ensure planet_id is in URL for consistency and bookmarking
            $this->redirect('/game/buildings?planet_id=' . $planet->id);
            return;
        }
        
        // Refresh planet data, especially resources, as updates might have occurred.
        $planet = Planet::getById($planet->id); 

        $allBuildingTypes = BuildingType::getAllStaticData(); // Assuming this fetches from static_building_types
        $playerBuildingLevels = PlayerBuilding::getBuildingLevelsByPlanetId($planet->id); // Fetches current levels for the planet
        
        $db = Model::getDB();
        $sql = "SELECT cq.*, sbt.name_de 
                FROM construction_queue cq
                JOIN static_building_types sbt ON cq.item_id = sbt.id AND cq.item_type = 'building'
                WHERE cq.planet_id = :planet_id
                ORDER BY cq.end_time ASC";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $planet->id, \PDO::PARAM_INT);
        $stmt->execute();
        $buildingQueueOnPlanet = $stmt->fetchAll(\PDO::FETCH_OBJ);        // Get detailed energy information
        $energyDetails = Planet::getEnergyDetails($planet->id);
        
        $data = [
            'pageTitle' => 'Gebäude auf ' . htmlspecialchars($planet->name),
            'playerName' => Player::getPlayerDataById($playerId)->username, // Assuming method exists
            'currentPlanetId' => $planet->id,
            'planetName' => $planet->name,
            'coords' => $planet->galaxy . ':' . $planet->system . ':' . $planet->position,
            'eisen' => round($planet->eisen),
            'silber' => round($planet->silber),
            'uderon' => round($planet->uderon),
            'wasserstoff' => round($planet->wasserstoff),
            'energie' => round($planet->energie),
            'energyDetails' => $energyDetails,
            'allBuildingTypes' => $allBuildingTypes,
            'playerBuildingLevels' => $playerBuildingLevels,
            'buildingQueueOnPlanet' => $buildingQueueOnPlanet,
            'allPlayerPlanets' => Planet::getPlanetsByPlayerId($playerId),
            'error_message' => $_SESSION['error_message'] ?? null,
            'success_message' => $_SESSION['success_message'] ?? null
        ];
        
        unset($_SESSION['error_message']); // Clear messages after preparing them for view
        unset($_SESSION['success_message']);

        $this->view('game.buildings', $data);
    }

    public function build() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['building_type_id'])) { // Updated parameter name
            $this->redirect('/buildings');
        }
        
        $buildingTypeId = intval($_POST['building_type_id']); // Updated parameter name
        $playerId = $_SESSION['user_id'];

        // Get current planet (will need to implement planet selection later)
        $planet = Planet::getHomePlanetByPlayerId($playerId);
        
        if (!$planet) {
            $this->redirect('/');
        }
        
        // Check and complete any finished constructions
        PlayerBuilding::checkAndCompleteConstructions($planet->id);

        // Get the static building type information
        $buildingType = BuildingType::getById($buildingTypeId);

        if (!$buildingType) {
            // Invalid building type
            $this->redirect('/buildings');
        }

        // Get the player's current level for this building on this planet
        $playerBuilding = PlayerBuilding::getByPlanetAndType($planet->id, $buildingTypeId);
        $currentLevel = $playerBuilding ? $playerBuilding->level : 0;
        $targetLevel = $currentLevel + 1;

        // Check if max level is reached
        if ($buildingType->max_level !== null && $targetLevel > $buildingType->max_level) {
            // Max level reached
            $this->redirect('/buildings');
        }

        // Check requirements (buildings and research)
        if ($buildingType->requirements_json) {
            $requirements = json_decode($buildingType->requirements_json, true);
            
            if (isset($requirements['building'])) {
                foreach ($requirements['building'] as $internalName => $level) {
                    $requiredBuildingType = BuildingType::getByInternalName($internalName);
                    if ($requiredBuildingType) {
                        $playerRequiredBuilding = PlayerBuilding::getByPlanetAndType($planet->id, $requiredBuildingType->id);
                        if (!$playerRequiredBuilding || $playerRequiredBuilding->level < $level) {
                            // Requirement not met
                            $_SESSION['error_message'] = "Voraussetzung nicht erfüllt: {$requiredBuildingType->name_de} Stufe {$level} benötigt.";
                            $this->redirectBackToBuildings($planet->id);
                            return;
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
                            $_SESSION['error_message'] = "Forschungsvoraussetzung nicht erfüllt: {$requiredResearchType->name_de} Stufe {$level} benötigt.";
                            $this->redirectBackToBuildings($planet->id);
                            return;
                        }
                    }
                }
            }
        }

        // Calculate building costs for the next level
        $costEisen = $buildingType->base_cost_eisen * pow($buildingType->cost_factor, $currentLevel);
        $costSilber = $buildingType->base_cost_silber * pow($buildingType->cost_factor, $currentLevel);
        $costUderon = $buildingType->base_cost_uderon * pow($buildingType->cost_factor, $currentLevel);
        $costWasserstoff = $buildingType->base_cost_wasserstoff * pow($buildingType->cost_factor, $currentLevel);
        $costEnergie = $buildingType->base_cost_energie * pow($buildingType->cost_factor, $currentLevel);

        // Check if planet has sufficient resources
        if ($planet->eisen < $costEisen ||
            $planet->silber < $costSilber ||
            $planet->uderon < $costUderon ||
            $planet->wasserstoff < $costWasserstoff ||
            $planet->energie < $costEnergie) {
            // Not enough resources
            $_SESSION['error_message'] = "Nicht genügend Ressourcen für den Ausbau von {$buildingType->name_de}.";
            $this->redirectBackToBuildings($planet->id);
            return;
        }
        
        // Calculate build time
        // This needs to consider the planet's speed factor and the Zentrale level
        $planetSpeedFactor = $planet->relative_speed ?? 1.0; // Use planet's relative_speed
        // Need to get Zentrale level on this planet
        // $zentraleLevel = PlayerBuilding::getByPlanetAndType($planet->id, BuildingType::getByInternalName('zentrale')->id)->level ?? 0;
        // Build time formula based on manual.md: Ausbau senkt die Bauzeit aller Gebäude
        // Need a specific formula for how Zentrale level affects build time.
        $baseBuildTimeSeconds = $buildingType->base_build_time * pow($buildingType->build_time_factor, $currentLevel);
        $adjustedBuildTimeSeconds = $baseBuildTimeSeconds / $planetSpeedFactor; // Incorporate planet speed
        // Need to incorporate Zentrale level effect on build time here

        // Start building construction
        $result = PlayerBuilding::startConstruction($planet->id, $buildingTypeId, $targetLevel, $adjustedBuildTimeSeconds); // Updated parameters

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
            $stmt->bindParam(':eisen', $costEisen, \PDO::PARAM_STR);
            $stmt->bindParam(':silber', $costSilber, \PDO::PARAM_STR);
            $stmt->bindParam(':uderon', $costUderon, \PDO::PARAM_STR);
            $stmt->bindParam(':wasserstoff', $costWasserstoff, \PDO::PARAM_STR);
            $stmt->bindParam(':energie', $costEnergie, \PDO::PARAM_STR);
            $stmt->bindParam(':planet_id', $planet->id, \PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['success_message'] = "Ausbau von {$buildingType->name_de} auf Stufe {$targetLevel} gestartet.";
        } else {
            $_SESSION['error_message'] = "Fehler beim Starten des Ausbaus von {$buildingType->name_de}. Eventuell ist die Bauwarteschlange voll.";
        }
        
        // Redirect back to buildings page
        $this->redirectBackToBuildings($planet->id);
    }

    public function upgrade() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
            return;
        }
        $playerId = $_SESSION['user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $planetId = filter_input(INPUT_POST, 'planet_id', FILTER_VALIDATE_INT);
            $buildingTypeId = filter_input(INPUT_POST, 'building_type_id', FILTER_VALIDATE_INT);

            if (!$planetId || !$buildingTypeId) {
                $_SESSION['error_message'] = 'Ungültige Anfrageparameter.';
                $this->redirectBackToBuildings($planetId);
                return;
            }

            $planet = Planet::getById($planetId);
            if (!$planet || $planet->player_id != $playerId) {
                $_SESSION['error_message'] = 'Ungültiger Planet oder keine Berechtigung.';
                $this->redirectBackToBuildings($planetId, true); // True to redirect to overview if planetId is dubious
                return;
            }

            // Call the model method to handle the upgrade logic
            // This method should perform all checks (resources, prerequisites, queue status) and add to queue
            $result = PlayerBuilding::initiateUpgrade($playerId, $planetId, $buildingTypeId);

            if ($result['success']) {
                $_SESSION['success_message'] = $result['message'] ?? 'Gebäudeausbau gestartet!';
            } else {
                $_SESSION['error_message'] = $result['message'] ?? 'Fehler beim Starten des Gebäudeausbaus.';
            }
            
            $this->redirectBackToBuildings($planetId);

        } else {
            // If not POST, redirect to overview or buildings page of home planet
            $homePlanet = Planet::getHomePlanetByPlayerId($playerId);
            $redirectPlanetId = $homePlanet ? $homePlanet->id : null;
            $this->redirectBackToBuildings($redirectPlanetId, true);
        }
    }

    private function redirectBackToBuildings($planetId, $fallbackToOverview = false) {
        if ($planetId) {
            $this->redirect('/game/buildings?planet_id=' . $planetId);
        } elseif ($fallbackToOverview) {
            $this->redirect('/game/overview');
        } else {
            // Fallback to a generic buildings page if no planet ID, though ideally should always have one
            $this->redirect('/game/buildings'); 
        }
    }

    public function cancel() {
        // Logic for canceling a building construction (refund resources)
        // Not implemented in this version
        $this->redirect('/buildings');
    }
}
?>
