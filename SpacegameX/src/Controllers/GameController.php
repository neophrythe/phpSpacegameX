<?php
namespace Controllers;

use Core\Controller;
use Models\Player;
use Models\Planet;
use Models\Fleet;
use Models\PlayerBuilding;
use Models\PlayerResearch;
use Models\PlayerShip;
use Models\PlayerDefense;
use Models\PlayerNotification;
use Services\NotificationService;

class GameController extends Controller {

    public function overview() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
        
        $playerId = $_SESSION['user_id'];
        $selectedPlanetId = isset($_GET['planet_id']) ? (int)$_GET['planet_id'] : null;

        // Check and complete any finished construction/research/fleet movements
        $this->runGameLoopUpdates($playerId); 
        
        $planet = null;
        if ($selectedPlanetId) {
            $candidatePlanet = Planet::getById($selectedPlanetId);
            if ($candidatePlanet && $candidatePlanet->player_id == $playerId) {
                $planet = $candidatePlanet;
            } else {
                // Invalid planet_id or not owned by player, redirect or show error
                // For now, let's fall back to home planet or show an error if even that fails
                $this->view('game.error', ['error' => 'Ungültige Planeten-ID oder Planet gehört dir nicht.']);
                // Optionally redirect to overview without planet_id to load home planet
                // $this->redirect('/game/overview');
                // return;
                // Fallback to home planet if specific one not found/valid
                 $planet = Planet::getHomePlanetByPlayerId($playerId);
            }
        } else {
            // No specific planet requested, load home planet
            $planet = Planet::getHomePlanetByPlayerId($playerId);
        }
        
        if (!$planet) {
            // This can happen if home planet isn't found and no valid specific planet was loaded
            $this->view('game.error', ['error' => 'Kein Planet zum Anzeigen gefunden. Stelle sicher, dass du einen Heimatplaneten hast.']);
            return;
        }
        
        // Get updated planet data (especially after runGameLoopUpdates)
        $planet = Planet::getById($planet->id); 
        
        // Get active fleets (player-wide)
        $fleets = Fleet::getActiveFleetsByPlayerId($playerId);
        
        // Get build queue
        // Player-specific items (research) and planet-specific items (buildings, ships, defense)
        $db = \Core\Model::getDB();
        $sql = "SELECT cq.*, 
                CASE 
                    WHEN cq.item_type = 'building' THEN bt.name_de
                    WHEN cq.item_type = 'research' THEN rt.name_de
                    WHEN cq.item_type = 'ship' THEN st.name_de
                    WHEN cq.item_type = 'defense' THEN dt.name_de
                    ELSE 'Unbekannt'
                END as name_de,
                p.name as queue_planet_name
                FROM construction_queue cq
                LEFT JOIN planets p ON cq.planet_id = p.id
                LEFT JOIN static_building_types bt ON cq.item_type = 'building' AND cq.item_id = bt.id
                LEFT JOIN static_research_types rt ON cq.item_type = 'research' AND cq.item_id = rt.id
                LEFT JOIN static_ship_types st ON cq.item_type = 'ship' AND cq.item_id = st.id
                LEFT JOIN static_defense_types dt ON cq.item_type = 'defense' AND cq.item_id = dt.id
                WHERE cq.player_id = :player_id 
                AND (cq.planet_id = :planet_id OR cq.planet_id IS NULL) -- Planet specific for buildings/ships/def, NULL for research
                ORDER BY cq.end_time ASC";
                
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, \PDO::PARAM_INT);
        $stmt->bindParam(':planet_id', $planet->id, \PDO::PARAM_INT); // Bind current planet ID
        $stmt->execute();
        $buildQueue = $stmt->fetchAll(\PDO::FETCH_OBJ);
            // Get detailed energy information
        $energyDetails = Planet::getEnergyDetails($planet->id);
        
        $data = [
            'pageTitle' => 'Spielübersicht - ' . htmlspecialchars($planet->name),
            'playerName' => Player::findById($playerId)->username,
            'currentPlanetId' => $planet->id, // Pass current planet ID to the view
            'planetName' => $planet->name,
            'coords' => $planet->galaxy . ':' . $planet->system . ':' . $planet->position,
            'eisen' => round($planet->eisen),
            'silber' => round($planet->silber),
            'uderon' => round($planet->uderon),
            'wasserstoff' => round($planet->wasserstoff),
            'energie' => round($planet->energie),
            'energyDetails' => $energyDetails,
            'activeFleets' => $fleets,
            'buildQueue' => $buildQueue,
            'allPlayerPlanets' => Planet::getPlanetsByPlayerId($playerId) // For a dropdown to switch planets
        ];
        
        $this->view('game.overview', $data);
    }    
    
    public function galaxy() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
        
        $playerId = $_SESSION['user_id'];
        
        // Check and complete any finished construction/research/fleet movements
        $this->runGameLoopUpdates($playerId); // Call a helper method for game loop updates

        // Get galaxy and system from request, or use defaults
        $currentGalaxy = isset($_GET['galaxy']) ? max(1, min(\Lib\GalaxyGenerator::GALAXY_COUNT, intval($_GET['galaxy']))) : 1;
        $currentSystem = isset($_GET['system']) ? max(1, min(\Lib\GalaxyGenerator::SYSTEMS_PER_GALAXY, intval($_GET['system']))) : 1;
        
        // Fetch all planets in the current system
        // We'll assume a method like getSystemPlanets exists in GalaxyGenerator
        // This method should return an array of planet objects/arrays, including owner info
        $systemPlanets = \Lib\GalaxyGenerator::getSystemPlanets($currentGalaxy, $currentSystem);
        
        $data = [
            'pageTitle' => "Galaxy {$currentGalaxy} - System {$currentSystem}",
            'playerName' => Player::findById($playerId)->username, // For context in the view
            'currentGalaxy' => $currentGalaxy,
            'currentSystem' => $currentSystem,
            'systemPlanets' => $systemPlanets,
            'maxGalaxies' => \Lib\GalaxyGenerator::GALAXY_COUNT,
            'maxSystems' => \Lib\GalaxyGenerator::SYSTEMS_PER_GALAXY,
            // Add any other necessary data for the view, e.g., player's own planets in this system
        ];
        
        $this->view('game.galaxy', $data); // Assumes a 'game.galaxy' view template exists
    }    public function runGameLoopUpdates($playerId) { // Changed from private to public
        // Fetch all planets for the player
        $planets = Planet::getPlanetsByPlayerId($playerId);

        // Update resources and check constructions for each planet
        foreach ($planets as $planet) {
            // Update resources for the current planet
            Planet::updateResources($planet->id);

            // Check and complete building constructions on this planet
            PlayerBuilding::checkAndCompleteConstructions($planet->id);

            // Check and complete ship production on this planet
            PlayerShip::checkAndCompleteProduction($planet->id); // Assuming a method like this exists or will be created

            // Check and complete defense production on this planet
            PlayerDefense::checkAndCompleteProduction($planet->id); // Assuming a method like this exists or will be created
            
            // REMOVED: Check for expired asteroids and send notifications
            // $this->checkAsteroidExpirations($planet, $playerId);
        }

        // Check and complete research for the player (player-wide, not per planet)
        PlayerResearch::checkAndCompleteResearch($playerId);

        // Check and complete fleet movements for the player
        Fleet::processFleets(); // Changed from checkAndCompleteMovements
        
        // Potentially, trigger combat checks if fleets have arrived and met targets
        // CombatController::resolvePendingCombatsForPlayer($playerId); // Example, actual implementation might vary

    }
    
    /**
     * Handles instant resource transfer between planets using the Transmitter.
     */
    public function transferResources() {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->setFlashMessage('error', 'Ungültige Anfrage für Ressourcentransfer.');
            $this->redirect('/game/overview'); // Redirect to overview or a specific transfer page
        }

        $playerId = (int)$_SESSION['user_id'];
        $sendingPlanetId = (int)($_POST['sending_planet_id'] ?? 0);
        $receivingPlanetId = (int)($_POST['receiving_planet_id'] ?? 0);
        $resourcesToTransfer = $_POST['resources'] ?? []; // Expected format: ['resource_type' => quantity]

        // Validate planet IDs are not the same
        if ($sendingPlanetId === $receivingPlanetId) {
            $this->setFlashMessage('error', 'Sende- und Empfangsplanet können nicht identisch sein.');
            $this->redirect('/game/overview?planet_id=' . $sendingPlanetId);
            return;
        }

        // Validate resources to transfer
        $validResourceTypes = ['eisen', 'silber', 'uderon', 'wasserstoff', 'energie'];
        $transferDetails = [];
        $validTransfer = false;

        foreach ($resourcesToTransfer as $resType => $quantity) {
            if (in_array($resType, $validResourceTypes) && is_numeric($quantity) && $quantity > 0) {
                $transferDetails[$resType] = floatval($quantity); // Ensure float
                $validTransfer = true;
            } else if (is_numeric($quantity) && $quantity < 0) {
                 $this->setFlashMessage('error', "Ungültige Menge für Ressource: {$resType}. Negative Mengen sind nicht erlaubt.");
                 $this->redirect('/game/overview?planet_id=' . $sendingPlanetId);
                 return;
            }
        }

        if (!$validTransfer) {
            $this->setFlashMessage('error', 'Keine gültigen Ressourcen oder Mengen für den Transfer angegeben.');
            $this->redirect('/game/overview?planet_id=' . $sendingPlanetId);
            return;
        }

        try {
            // Call the model method to perform the transfer
            $success = Planet::transferResourcesInstant($sendingPlanetId, $receivingPlanetId, $transferDetails);

            if ($success) {
                $sendingPlanet = Planet::getById($sendingPlanetId); // Get updated planet data for notification
                $receivingPlanet = Planet::getById($receivingPlanetId); // Get updated planet data for notification
                
                $transferSummary = [];
                foreach($transferDetails as $resType => $quantity) {
                    $transferSummary[] = ucfirst($resType) . ": " . number_format($quantity);
                }
                $transferSummaryStr = implode(', ', $transferSummary);

                $message = "Ressourcentransfer erfolgreich: {$transferSummaryStr} von {$sendingPlanet->name} nach {$receivingPlanet->name}.";
                NotificationService::createNotification($playerId, 'Ressourcentransfer Erfolgreich', $message, 'success');

                $this->setFlashMessage('success', $message);
            } else {
                // The model method should throw exceptions with specific error messages
                // If it returns false without throwing, use a generic error
                $this->setFlashMessage('error', 'Ressourcentransfer fehlgeschlagen. Überprüfe Transmitter-Level, Energie und verfügbare Ressourcen.');
            }

        } catch (\Exception $e) {
            // Catch exceptions thrown by the model method
            error_log("GameController::transferResources Error: " . $e->getMessage());
            $this->setFlashMessage('error', 'Ressourcentransfer fehlgeschlagen: ' . $e->getMessage());
        }

        // Redirect back to the sending planet's overview page
        header('Location: /game/overview?planet_id=' . $sendingPlanetId);
        exit;
    }


    // REMOVED: checkAsteroidExpirations method was here
    /*
     * Check for expired asteroids and send notifications for them
     *
     * @param Planet $planet The planet object to check for expired asteroids
     * @param int $playerId The ID of the player who owns the planet
     */
    // private function checkAsteroidExpirations($planet, $playerId) { ... }
}
