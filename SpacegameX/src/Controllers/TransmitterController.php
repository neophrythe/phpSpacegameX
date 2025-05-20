<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Planet;
use App\Models\Player;
use App\Models\PlayerBuilding; // Added for prerequisite check
use App\Services\Logger;         // Added for logging
// use App\Services\ResourceService; // Assuming a ResourceService might be useful later
// use App\Services\ValidationService; // For input validation

class TransmitterController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // Ensure user is authenticated to access transmitter functions
        if (!isset($_SESSION['player_id'])) {
            // Or handle via a middleware/router check
            header('Location: /auth/login');
            exit;
        }
    }

    /**
     * Display the transmitter interface.
     * Shows options to select source planet (implicitly current), target planet, resource, and amount.
     */
    public function show()
    {
        $playerId = $_SESSION['player_id'];
        $player = new Player();
        $planets = $player->getPlanets($playerId); // Method to get all planets of a player
        $currentPlanetId = $_SESSION['current_planet_id'] ?? ($planets[0]['id'] ?? null); // Assuming current_planet_id is in session or default to first

        $shipyardLevel = 0;
        $currentPlanetEnergy = 0;

        if ($currentPlanetId) {
            $playerBuildingModel = new PlayerBuilding();
            $shipyardLevel = $playerBuildingModel->getBuildingLevel($currentPlanetId, 'werft'); // Assuming 'werft' is the internal name for shipyard

            $planetModel = new Planet();
            $currentP = $planetModel->getPlanetByIdAndPlayer($currentPlanetId, $playerId);
            if ($currentP) {
                $currentPlanetEnergy = $currentP['energie'];
            }
        }

        $this->view('game/transmitter', [
            'planets' => $planets,
            'pageTitle' => 'Transmitter',
            'currentPlanetId' => $currentPlanetId,
            'shipyardLevel' => $shipyardLevel,
            'currentPlanetEnergy' => $currentPlanetEnergy
            // Pass other necessary data like current planet's resources
        ]);
    }

    /**
     * Handle the resource transmission logic.
     */
    public function transmit()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse(['error' => 'Invalid request method.'], 405);
            return;
        }

        $playerId = $_SESSION['player_id'];
        // Source planet should be the one where the transmitter is, typically the current planet
        $sourcePlanetId = $_POST['source_planet_id'] ?? null; 
        $targetPlanetId = $_POST['target_planet_id'] ?? null;
        $resourceType = $_POST['resource_type'] ?? null; // e.g., 'eisen', 'silber', 'uderon', 'wasserstoff'
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_INT);

        // --- Basic Validation ---
        if (!$sourcePlanetId || !$targetPlanetId || !$resourceType || $amount === false || $amount <= 0) {
            $this->sendJsonResponse(['error' => 'Missing or invalid parameters.'], 400);
            return;
        }
        if ($sourcePlanetId == $targetPlanetId) {
            $this->sendJsonResponse(['error' => 'Source and target planet cannot be the same.'], 400);
            return;
        }

        // --- Authorization and Prerequisite Checks ---
        $planetModel = new Planet();
        $sourcePlanet = $planetModel->getPlanetByIdAndPlayer($sourcePlanetId, $playerId);
        $targetPlanet = $planetModel->getPlanetByIdAndPlayer($targetPlanetId, $playerId);

        if (!$sourcePlanet || !$targetPlanet) {
            $this->sendJsonResponse(['error' => 'Invalid source or target planet.'], 403);
            return;
        }

        // Check Shipyard (Werft) level 5 prerequisite on source planet
        $playerBuildingModel = new PlayerBuilding();
        $shipyardLevel = $playerBuildingModel->getBuildingLevel((int)$sourcePlanetId, 'werft'); // Assuming 'werft' is internal name
        if (!$shipyardLevel || $shipyardLevel < 5) {
            $this->sendJsonResponse(['error' => 'Shipyard level 5 required on source planet to use the transmitter.'], 403);
            return;
        }
        
        // --- Define Energy Cost ---
        // "Very high energy costs" - manual.md
        // Example: 1 energy per 10 units of resource, minimum 5000.
        // This can be adjusted to be "very high" as per game balance.
        $energyCost = ceil($amount / 10); 
        if ($energyCost < 5000) {
            $energyCost = 5000; 
        }
        if (isset($sourcePlanet['energie_verbrauch_faktor']) && $sourcePlanet['energie_verbrauch_faktor'] > 0) { // Example for planet specific factors
            $energyCost *= $sourcePlanet['energie_verbrauch_faktor'];
        }


        // --- Resource and Cost Handling ---
        // Check if source planet has enough energy
        if ($sourcePlanet['energie'] < $energyCost) { 
             $this->sendJsonResponse(['error' => 'Not enough energy for transmission. Required: ' . $energyCost . ' Available: ' . $sourcePlanet['energie']], 400);
             return;
        }

        // Check if source planet has enough of the resource to send
        // Ensure $resourceType is a valid column name to prevent SQL injection if not using prepared statements for column names
        $allowedResourceTypes = ['eisen', 'silber', 'gold', 'uderon', 'wasserstoff', 'antimaterie']; // Define allowed types
        if (!in_array($resourceType, $allowedResourceTypes)) {
            $this->sendJsonResponse(['error' => 'Invalid resource type specified.'], 400);
            return;
        }

        if (!isset($sourcePlanet[$resourceType]) || $sourcePlanet[$resourceType] < $amount) {
            $this->sendJsonResponse(['error' => 'Not enough ' . htmlspecialchars($resourceType) . ' to send. Required: ' . $amount . ' Available: ' . ($sourcePlanet[$resourceType] ?? 0)], 400);
            return;
        }

        // --- Perform Transaction (Database updates) ---
        $db = $planetModel->getDb(); 
        try {
            $db->beginTransaction();

            // Deduct energy from source
            $sqlDeductEnergy = "UPDATE planets SET energie = energie - :energy_cost WHERE id = :planet_id";
            $stmtDeductEnergy = $db->prepare($sqlDeductEnergy);
            $stmtDeductEnergy->execute(['energy_cost' => $energyCost, 'planet_id' => $sourcePlanetId]);

            // Deduct resource from source
            // Using backticks for column name is safe if $resourceType is validated against a whitelist
            $sqlDeductResource = "UPDATE planets SET `{$resourceType}` = `{$resourceType}` - :amount WHERE id = :planet_id";
            $stmtDeductResource = $db->prepare($sqlDeductResource);
            $stmtDeductResource->execute(['amount' => $amount, 'planet_id' => $sourcePlanetId]);
            
            // Add resource to target
            $sqlAddResource = "UPDATE planets SET `{$resourceType}` = `{$resourceType}` + :amount WHERE id = :planet_id";
            $stmtAddResource = $db->prepare($sqlAddResource);
            $stmtAddResource->execute(['amount' => $amount, 'planet_id' => $targetPlanetId]);

            $db->commit();

            Logger::log('transmitter', "Player ID {$playerId} transmitted {$amount} of {$resourceType} from planet ID {$sourcePlanetId} to planet ID {$targetPlanetId}. Energy cost: {$energyCost}.");

            $this->sendJsonResponse(['success' => true, 'message' => 'Transmission successful! ' . htmlspecialchars($amount) . ' ' . htmlspecialchars($resourceType) . ' sent.'], 200);

        } catch (\PDOException $e) {
            $db->rollBack();
            Logger::log('error', "Transmitter Error for Player ID {$playerId}: " . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Transmission failed due to a server error. Please try again.'], 500);
        }
    }

    private function sendJsonResponse(array $data, int $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
