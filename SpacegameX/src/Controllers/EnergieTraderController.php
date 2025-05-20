<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Planet;
use App\Models\Player;
use App\Models\PlayerBuilding;
use App\Services\Logger;

class EnergieTraderController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!isset($_SESSION['player_id'])) {
            header('Location: /auth/login');
            exit;
        }
    }

    public function show()
    {
        $playerId = $_SESSION['player_id'];
        $player = new Player();
        $planets = $player->getPlanets($playerId);
        $currentPlanetId = $_SESSION['current_planet_id'] ?? ($planets[0]['id'] ?? null);

        $shipyardLevel = 0;
        $currentPlanetEnergy = 0;

        if ($currentPlanetId) {
            $playerBuildingModel = new PlayerBuilding();
            // Assuming 'werft' is the internal name for shipyard used in PlayerBuilding model
            $shipyardLevel = $playerBuildingModel->getBuildingLevel((int)$currentPlanetId, 'werft'); 
            
            $planetModel = new Planet();
            $currentP = $planetModel->getPlanetByIdAndPlayer((int)$currentPlanetId, $playerId);
            if ($currentP) {
                $currentPlanetEnergy = $currentP['energie'];
            }
        }

        $this->view('game/energie_trader', [
            'planets' => $planets,
            'pageTitle' => 'Energie-Trader',
            'currentPlanetId' => $currentPlanetId,
            'shipyardLevel' => $shipyardLevel,
            'currentPlanetEnergy' => $currentPlanetEnergy
        ]);
    }

    public function transferEnergy()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse(['error' => 'Invalid request method.'], 405);
            return;
        }

        $playerId = $_SESSION['player_id'];
        $sourcePlanetId = filter_input(INPUT_POST, 'source_planet_id', FILTER_VALIDATE_INT);
        $targetPlanetId = filter_input(INPUT_POST, 'target_planet_id', FILTER_VALIDATE_INT);
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_INT);

        if (!$sourcePlanetId || !$targetPlanetId || $amount === false || $amount <= 0) {
            $this->sendJsonResponse(['error' => 'Missing or invalid parameters.'], 400);
            return;
        }

        if ($sourcePlanetId == $targetPlanetId) {
            $this->sendJsonResponse(['error' => 'Source and target planet cannot be the same.'], 400);
            return;
        }

        $planetModel = new Planet();
        $sourcePlanet = $planetModel->getPlanetByIdAndPlayer($sourcePlanetId, $playerId);
        $targetPlanet = $planetModel->getPlanetByIdAndPlayer($targetPlanetId, $playerId);

        if (!$sourcePlanet || !$targetPlanet) {
            $this->sendJsonResponse(['error' => 'Invalid source or target planet.'], 403);
            return;
        }

        $playerBuildingModel = new PlayerBuilding();
        $shipyardLevel = $playerBuildingModel->getBuildingLevel($sourcePlanetId, 'werft');
        if (!$shipyardLevel || $shipyardLevel < 5) {
            $this->sendJsonResponse(['error' => 'Shipyard level 5 required on source planet for Energie-Trader.'], 403);
            return;
        }

        // Energie-Trader Cost: "very high energy costs (allerdings geringer als der Transmitter für Rohstoffe)"
        // Let's set a cost, e.g., 5% of the transferred amount, minimum 2500.
        // This is lower than the resource transmitter's 1 energy per 10 units (10%) and min 5000.
        $transferCost = ceil($amount * 0.05); 
        if ($transferCost < 2500) {
            $transferCost = 2500;
        }
        
        $totalDeduction = $amount + $transferCost;

        if ($sourcePlanet['energie'] < $totalDeduction) {
            $this->sendJsonResponse(['error' => "Not enough energy. Required: {$amount} (transfer) + {$transferCost} (cost) = {$totalDeduction}. Available: {$sourcePlanet['energie']}"], 400);
            return;
        }

        $db = $planetModel->getDb();
        try {
            $db->beginTransaction();

            // Deduct transferred amount + cost from source planet
            $sqlDeduct = "UPDATE planets SET energie = energie - :total_deduction WHERE id = :source_id";
            $stmtDeduct = $db->prepare($sqlDeduct);
            $stmtDeduct->execute(['total_deduction' => $totalDeduction, 'source_id' => $sourcePlanetId]);

            // Add transferred amount to target planet
            $sqlAdd = "UPDATE planets SET energie = energie + :amount WHERE id = :target_id";
            $stmtAdd = $db->prepare($sqlAdd);
            $stmtAdd->execute(['amount' => $amount, 'target_id' => $targetPlanetId]);

            $db->commit();

            Logger::log('energie_trader', "Player ID {$playerId} traded {$amount} energy from planet ID {$sourcePlanetId} to planet ID {$targetPlanetId}. Cost: {$transferCost} energy.");
            $this->sendJsonResponse(['success' => true, 'message' => "Successfully transferred {$amount} energy. Cost: {$transferCost} energy."], 200);

        } catch (\PDOException $e) {
            $db->rollBack();
            Logger::log('error', "Energie-Trader Error for Player ID {$playerId}: " . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Energy transfer failed due to a server error.'], 500);
        }
    }

    private function sendJsonResponse(array $data, int $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
