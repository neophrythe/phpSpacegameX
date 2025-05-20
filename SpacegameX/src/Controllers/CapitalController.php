<?php
namespace Controllers;

use Core\Controller;
use Models\Player;
use Models\Planet;
use Services\NotificationService;

class CapitalController extends Controller {
    
    public function index() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
        
        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);
        
        if (!$player) {
            $this->redirect('/login');
        }
        
        // Check and complete any finished construction/research/fleet movements
        $gameController = new GameController();
        $gameController->runGameLoopUpdates($playerId); 
        
        // Get all player planets
        $playerPlanets = Planet::getPlanetsByPlayerId($playerId);
        
        // Check if player has a capital planet
        $hasCapitalPlanet = false;
        $capitalPlanet = null;
        
        foreach ($playerPlanets as $planet) {
            if ($planet->is_capital == 1) {
                $hasCapitalPlanet = true;
                $capitalPlanet = $planet;
                break;
            }
        }
          // If no capital is set but player has planets, auto-set the first one
        if (!$hasCapitalPlanet && !empty($playerPlanets)) {
            $playerPlanets[0]->is_capital = 1;
            $playerPlanets[0]->save();
            $hasCapitalPlanet = true;
            $capitalPlanet = $playerPlanets[0];
            
            // Notify player of automatic capital assignment
            NotificationService::capitalChanged(
                $playerId, 
                $playerPlanets[0]->name,
                $playerPlanets[0]->galaxy,
                $playerPlanets[0]->system,
                $playerPlanets[0]->position
            );
        }
        
        // Get the date of last capital change
        $lastCapitalChangeDate = null;
        if ($player->last_capital_change) {
            $lastCapitalChangeDate = date('d.m.Y H:i', strtotime($player->last_capital_change));
        }
        
        // Capital change cooldown days
        $capitalChangeCooldownDays = 7;
        
        // Check if player can change capital
        $canChangeCapital = true;
        $nextPossibleChangeDate = null;
        
        if ($player->last_capital_change) {
            $lastChange = strtotime($player->last_capital_change);
            $cooldownTime = 86400 * $capitalChangeCooldownDays; // Days to seconds
            $currentTime = time();
            
            if (($lastChange + $cooldownTime) > $currentTime) {
                $canChangeCapital = false;
                $nextPossibleChangeDate = date('d.m.Y H:i', $lastChange + $cooldownTime);
            }
        }
        
        // Capital planet bonuses
        $capitalBonuses = [
            'production' => 20, // +20% resource production
            'research' => 15,   // +15% research speed
            'shipyard' => 10,   // +10% shipyard speed
            'defense' => 25     // +25% defense bonus
        ];
        
        // Compile data for view
        $viewData = [
            'playerPlanets' => $playerPlanets,
            'hasCapitalPlanet' => $hasCapitalPlanet,
            'capitalPlanet' => $capitalPlanet,
            'lastCapitalChangeDate' => $lastCapitalChangeDate,
            'canChangeCapital' => $canChangeCapital,
            'nextPossibleChangeDate' => $nextPossibleChangeDate,
            'capitalChangeCooldownDays' => $capitalChangeCooldownDays,
            'capitalBonuses' => $capitalBonuses
        ];
        
        $this->view('game.capital', $viewData);
    }
    
    public function change() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['planet_id']) || !isset($_POST['confirmation'])) {
            $this->redirect('/capital');
            return;
        }
        
        $playerId = $_SESSION['user_id'];
        $newCapitalPlanetId = (int)$_POST['planet_id'];
        
        $player = Player::findById($playerId);
        
        if (!$player) {
            $this->redirect('/login');
            return;
        }
        
        // Check if cooldown has passed
        $capitalChangeCooldownDays = 7;
        
        if ($player->last_capital_change) {
            $lastChange = strtotime($player->last_capital_change);
            $cooldownTime = 86400 * $capitalChangeCooldownDays; // Days to seconds
            $currentTime = time();
            
            if (($lastChange + $cooldownTime) > $currentTime) {
                $_SESSION['error'] = 'Hauptplanet kann erst am ' . date('d.m.Y H:i', $lastChange + $cooldownTime) . ' wieder gewechselt werden.';
                $this->redirect('/capital');
                return;
            }
        }
        
        // Verify the planet belongs to the player
        $newCapitalPlanet = Planet::getById($newCapitalPlanetId);
        
        if (!$newCapitalPlanet || $newCapitalPlanet->player_id != $playerId) {
            $_SESSION['error'] = 'Ungültiger Planet oder nicht dein Planet.';
            $this->redirect('/capital');
            return;
        }
        
        // Get all player planets
        $playerPlanets = Planet::getAllByPlayerId($playerId);
        
        // Remove capital status from all planets
        foreach ($playerPlanets as $planet) {
            if ($planet->is_capital == 1) {
                $planet->is_capital = 0;
                $planet->save();
                
                // If development is in progress, apply penalty or delay
                // (Optional implementation based on game design)
            }
        }
        
        // Set new capital planet
        $newCapitalPlanet->is_capital = 1;
        $newCapitalPlanet->save();
          // Update last capital change date
        $player->last_capital_change = date('Y-m-d H:i:s');
        $player->save();
        
        // Send notification about capital planet change
        NotificationService::capitalChanged(
            $playerId, 
            $newCapitalPlanet->name,
            $newCapitalPlanet->galaxy,
            $newCapitalPlanet->system,
            $newCapitalPlanet->position
        );
        
        $_SESSION['success'] = 'Hauptplanet erfolgreich zu ' . $newCapitalPlanet->name . ' [' . $newCapitalPlanet->galaxy . ':' . $newCapitalPlanet->system . ':' . $newCapitalPlanet->position . '] gewechselt.';
        $this->redirect('/capital');
    }
}
