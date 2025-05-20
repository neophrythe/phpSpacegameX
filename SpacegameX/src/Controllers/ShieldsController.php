<?php
namespace Controllers;

use Core\Controller;
use Models\Player;
use Models\Planet;
use Models\PlayerResearch;

class ShieldsController extends Controller {
    
    public function index() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
        
        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);
        
        if (!$player) {
            $this->redirect('/login');
        }
        
        $selectedPlanetId = isset($_GET['planet_id']) ? (int)$_GET['planet_id'] : null;
        
        // Check and complete any finished construction/research/fleet movements
        $gameController = new GameController();
        $gameController->runGameLoopUpdates($playerId); 
        
        $planet = null;
        if ($selectedPlanetId) {
            $candidatePlanet = Planet::getById($selectedPlanetId);
            if ($candidatePlanet && $candidatePlanet->player_id == $playerId) {
                $planet = $candidatePlanet;
            } else {
                $planet = Planet::getHomePlanetByPlayerId($playerId);
            }
        } else {
            $planet = Planet::getHomePlanetByPlayerId($playerId);
        }
        
        if (!$planet) {
            $this->view('game.error', ['error' => 'Kein Planet zum Anzeigen gefunden.']);
            return;
        }
        
        // Get all player planets for the planet selector
        $allPlayerPlanets = Planet::getAllByPlayerId($playerId);
        
        // Get shield research level
        $shieldResearch = PlayerResearch::getResearchLevel($playerId, 'shield_tech');
        $shieldResearchLevel = $shieldResearch ? $shieldResearch->level : 0;
        $maxShieldResearchLevel = 20; // Maximum level for shield research
        
        // Calculate shield status
        $shieldActive = $planet->shield_active == 1;
        $shieldTimeRemaining = '';
        $shieldStrength = 0;
        $cooldownRemaining = '';
        $canActivateShield = false;
        $notEnoughResources = false;
        
        if ($shieldActive) {
            // Calculate remaining shield time
            $shieldEndTime = strtotime($planet->shield_end_time);
            $currentTime = time();
            
            if ($shieldEndTime > $currentTime) {
                $remainingSeconds = $shieldEndTime - $currentTime;
                $hours = floor($remainingSeconds / 3600);
                $minutes = floor(($remainingSeconds % 3600) / 60);
                $seconds = $remainingSeconds % 60;
                $shieldTimeRemaining = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
                
                // Calculate shield strength based on research level
                $baseShieldStrength = 10000; // Base shield strength
                $researchBonus = $shieldResearchLevel * 0.1; // 10% per research level
                $shieldStrength = $baseShieldStrength * (1 + $researchBonus);
            } else {
                // Shield has expired but not yet updated in the database
                $planet->shield_active = 0;
                $planet->shield_end_time = null;
                $planet->save();
                $shieldActive = false;
            }
        } else {
            // Check if shield is in cooldown
            if ($planet->last_shield_time) {
                $lastShieldDeactivation = strtotime($planet->last_shield_time);
                $cooldownTime = 3600 * 4; // 4 hours cooldown
                $currentTime = time();
                
                if (($lastShieldDeactivation + $cooldownTime) > $currentTime) {
                    $cooldownRemainingSeconds = ($lastShieldDeactivation + $cooldownTime) - $currentTime;
                    $hours = floor($cooldownRemainingSeconds / 3600);
                    $minutes = floor(($cooldownRemainingSeconds % 3600) / 60);
                    $seconds = $cooldownRemainingSeconds % 60;
                    $cooldownRemaining = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
                } else {
                    $canActivateShield = true;
                }
            } else {
                $canActivateShield = true;
            }
        }
        
        // Calculate shield activation costs
        $shieldCosts = [
            'metal' => 5000 * (1 + ($shieldResearchLevel * 0.5)),   // Eisen
            'crystal' => 2500 * (1 + ($shieldResearchLevel * 0.5)), // Silber
            'uderon' => 1000 * (1 + ($shieldResearchLevel * 0.3)),  // Uderon
            'energy' => 500 * (1 + ($shieldResearchLevel * 0.2))    // Energie
        ];
        
        // Check if player has enough resources
        if ($canActivateShield) {
            $notEnoughResources = 
                $planet->metal < $shieldCosts['metal'] || 
                $planet->crystal < $shieldCosts['crystal'] || 
                $planet->uderon < $shieldCosts['uderon'] || 
                $planet->nrg < $shieldCosts['energy'];
        }
        
        // Compile data for view
        $viewData = [
            'planetName' => $planet->name,
            'coords' => $planet->galaxy . ':' . $planet->system . ':' . $planet->position,
            'currentPlanetId' => $planet->id,
            'allPlayerPlanets' => $allPlayerPlanets,
            'eisen' => $planet->metal,
            'silber' => $planet->crystal,
            'uderon' => $planet->uderon,
            'wasserstoff' => $planet->h2,
            'energie' => $planet->nrg,
            'shieldActive' => $shieldActive,
            'shieldTimeRemaining' => $shieldTimeRemaining,
            'shieldStrength' => $shieldStrength,
            'cooldownRemaining' => $cooldownRemaining,
            'canActivateShield' => $canActivateShield,
            'notEnoughResources' => $notEnoughResources,
            'shieldCosts' => $shieldCosts,
            'shieldResearchLevel' => $shieldResearchLevel,
            'maxShieldResearchLevel' => $maxShieldResearchLevel
        ];
        
        $this->view('game.shields', $viewData);
    }
    
    public function activate() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['planet_id'])) {
            $this->redirect('/shields');
            return;
        }
        
        $playerId = $_SESSION['user_id'];
        $planetId = (int)$_POST['planet_id'];
        
        $planet = Planet::getById($planetId);
        
        if (!$planet || $planet->player_id != $playerId) {
            $_SESSION['error'] = 'Ungültiger Planet oder nicht dein Planet.';
            $this->redirect('/shields');
            return;
        }
        
        // Check if shield is already active
        if ($planet->shield_active == 1) {
            $_SESSION['error'] = 'Schild ist bereits aktiviert.';
            $this->redirect('/shields?planet_id=' . $planetId);
            return;
        }
        
        // Check if shield is in cooldown
        if ($planet->last_shield_time) {
            $lastShieldDeactivation = strtotime($planet->last_shield_time);
            $cooldownTime = 3600 * 4; // 4 hours cooldown
            $currentTime = time();
            
            if (($lastShieldDeactivation + $cooldownTime) > $currentTime) {
                $_SESSION['error'] = 'Schildgenerator ist noch im Cooldown.';
                $this->redirect('/shields?planet_id=' . $planetId);
                return;
            }
        }
        
        // Get shield research level
        $shieldResearch = PlayerResearch::getResearchLevel($playerId, 'shield_tech');
        $shieldResearchLevel = $shieldResearch ? $shieldResearch->level : 0;
        
        // Calculate shield activation costs
        $shieldCosts = [
            'metal' => 5000 * (1 + ($shieldResearchLevel * 0.5)),   // Eisen
            'crystal' => 2500 * (1 + ($shieldResearchLevel * 0.5)), // Silber
            'uderon' => 1000 * (1 + ($shieldResearchLevel * 0.3)),  // Uderon
            'energy' => 500 * (1 + ($shieldResearchLevel * 0.2))    // Energie
        ];
        
        // Check if player has enough resources
        if ($planet->metal < $shieldCosts['metal'] || 
            $planet->crystal < $shieldCosts['crystal'] || 
            $planet->uderon < $shieldCosts['uderon'] || 
            $planet->nrg < $shieldCosts['energy']) {
            
            $_SESSION['error'] = 'Nicht genügend Ressourcen zum Aktivieren des Schilds.';
            $this->redirect('/shields?planet_id=' . $planetId);
            return;
        }
        
        // Deduct resources
        $planet->metal -= $shieldCosts['metal'];
        $planet->crystal -= $shieldCosts['crystal'];
        $planet->uderon -= $shieldCosts['uderon'];
        $planet->nrg -= $shieldCosts['energy'];
        
        // Activate shield
        $planet->shield_active = 1;
        
        // Calculate shield duration based on research level
        $baseDuration = 24; // Base: 24 hours
        $researchBonus = $shieldResearchLevel * 0.5; // +0.5 hour per research level
        $durationHours = $baseDuration + $researchBonus;
        
        // Set shield end time
        $currentTime = time();
        $endTime = $currentTime + ($durationHours * 3600);
        $planet->shield_end_time = date('Y-m-d H:i:s', $endTime);
        
        // Save changes
        $planet->save();
        
        $_SESSION['success'] = 'Planetares Schild erfolgreich aktiviert für ' . $durationHours . ' Stunden.';
        $this->redirect('/shields?planet_id=' . $planetId);
    }
    
    public function deactivate() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['planet_id'])) {
            $this->redirect('/shields');
            return;
        }
        
        $playerId = $_SESSION['user_id'];
        $planetId = (int)$_POST['planet_id'];
        
        $planet = Planet::getById($planetId);
        
        if (!$planet || $planet->player_id != $playerId) {
            $_SESSION['error'] = 'Ungültiger Planet oder nicht dein Planet.';
            $this->redirect('/shields');
            return;
        }
        
        // Check if shield is active
        if ($planet->shield_active != 1) {
            $_SESSION['error'] = 'Schild ist nicht aktiviert.';
            $this->redirect('/shields?planet_id=' . $planetId);
            return;
        }
        
        // Deactivate shield
        $planet->shield_active = 0;
        $planet->shield_end_time = null;
        $planet->last_shield_time = date('Y-m-d H:i:s'); // Record deactivation time for cooldown
        
        // Save changes
        $planet->save();
        
        $_SESSION['success'] = 'Planetares Schild deaktiviert. Der Schildgenerator benötigt nun 4 Stunden zum Abkühlen.';
        $this->redirect('/shields?planet_id=' . $planetId);
    }
}
?>
