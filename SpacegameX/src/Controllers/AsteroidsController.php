<?php
namespace Controllers;

use Core\Controller;
use Models\Player;
use Models\Planet;
use Models\PlayerResearch;
use Services\NotificationService;

class AsteroidsController extends Controller {
    
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
        
        // Get asteroid mining research levels
        $miningResearch = PlayerResearch::getResearchLevel($playerId, 'asteroid_mining');
        $miningResearchLevel = $miningResearch ? $miningResearch->level : 0;
        
        $advancedMiningResearch = PlayerResearch::getResearchLevel($playerId, 'advanced_asteroid_mining');
        $advancedMiningLevel = $advancedMiningResearch ? $advancedMiningResearch->level : 0;
        
        // Calculate asteroid-related values based on research
        $maxAsteroids = 1 + floor($miningResearchLevel / 3); // 1 asteroid at start, +1 for every 3 research levels
        $bonusMultiplier = 1 + ($advancedMiningLevel * 0.1); // 10% increase in bonus per advanced mining level
        
        // Get current asteroids for this planet
        $currentAsteroids = [];
        if ($planet->asteroid_data) {
            $asteroidData = json_decode($planet->asteroid_data, true);
            
            if (is_array($asteroidData)) {
                foreach ($asteroidData as $id => $asteroid) {
                    $currentAsteroids[] = (object) [
                        'id' => $id,
                        'type' => $asteroid['type'],
                        'size' => $asteroid['size'],
                        'bonus' => round($asteroid['bonus'] * $bonusMultiplier)
                    ];
                }
            }
        }
        
        // Check if mining is in cooldown
        $canMineNewAsteroid = false;
        $cooldownRemaining = '';
        
        if ($planet->last_asteroid_time) {
            $lastMining = strtotime($planet->last_asteroid_time);
            $cooldownTime = 3600 * 2; // 2 hours cooldown
            $currentTime = time();
            
            if (($lastMining + $cooldownTime) > $currentTime) {
                $cooldownRemainingSeconds = ($lastMining + $cooldownTime) - $currentTime;
                $hours = floor($cooldownRemainingSeconds / 3600);
                $minutes = floor(($cooldownRemainingSeconds % 3600) / 60);
                $seconds = $cooldownRemainingSeconds % 60;
                $cooldownRemaining = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
            } else {
                $canMineNewAsteroid = true;
            }
        } else {
            $canMineNewAsteroid = true;
        }
        
        // Calculate mining costs
        $miningCosts = [
            'metal' => 3000 + ($miningResearchLevel * 500),   // Eisen
            'crystal' => 1500 + ($miningResearchLevel * 250), // Silber
            'h2' => 800 + ($miningResearchLevel * 100),       // Wasserstoff
        ];
        
        // Check if player has enough resources
        $notEnoughResources = 
            $planet->metal < $miningCosts['metal'] || 
            $planet->crystal < $miningCosts['crystal'] || 
            $planet->h2 < $miningCosts['h2'];
        
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
            'miningResearchLevel' => $miningResearchLevel,
            'advancedMiningResearch' => $advancedMiningLevel,
            'maxAsteroids' => $maxAsteroids,
            'bonusMultiplier' => round($bonusMultiplier, 1),
            'currentAsteroids' => $currentAsteroids,
            'canMineNewAsteroid' => $canMineNewAsteroid,
            'cooldownRemaining' => $cooldownRemaining,
            'miningCosts' => $miningCosts,
            'notEnoughResources' => $notEnoughResources
        ];
        
        $this->view('game.asteroids', $viewData);
    }
    
    public function mine() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['planet_id']) || !isset($_POST['type'])) {
            $this->redirect('/asteroids');
            return;
        }
        
        $playerId = $_SESSION['user_id'];
        $planetId = (int)$_POST['planet_id'];
        $asteroidType = $_POST['type'];
        
        // Validate asteroid type
        $validTypes = ['metal', 'crystal', 'uderon', 'mixed'];
        if (!in_array($asteroidType, $validTypes)) {
            $_SESSION['error'] = 'Ungültiger Asteroid-Typ.';
            $this->redirect('/asteroids?planet_id=' . $planetId);
            return;
        }
        
        $planet = Planet::getById($planetId);
        
        if (!$planet || $planet->player_id != $playerId) {
            $_SESSION['error'] = 'Ungültiger Planet oder nicht dein Planet.';
            $this->redirect('/asteroids');
            return;
        }
        
        // Get research levels
        $miningResearch = PlayerResearch::getResearchLevel($playerId, 'asteroid_mining');
        $miningResearchLevel = $miningResearch ? $miningResearch->level : 0;
        
        $advancedMiningResearch = PlayerResearch::getResearchLevel($playerId, 'advanced_asteroid_mining');
        $advancedMiningLevel = $advancedMiningResearch ? $advancedMiningResearch->level : 0;
        
        // Check if mixed asteroids are available
        if ($asteroidType == 'mixed' && $advancedMiningLevel < 5) {
            $_SESSION['error'] = 'Gemischte Asteroiden erfordern Stufe 5 in Fortgeschrittener Bergbau-Forschung.';
            $this->redirect('/asteroids?planet_id=' . $planetId);
            return;
        }
        
        // Calculate max asteroids
        $maxAsteroids = 1 + floor($miningResearchLevel / 3);
        
        // Check current asteroids
        $currentAsteroids = [];
        if ($planet->asteroid_data) {
            $asteroidData = json_decode($planet->asteroid_data, true);
            if (is_array($asteroidData)) {
                $currentAsteroids = $asteroidData;
            }
        }
        
        // Check if max asteroids reached
        if (count($currentAsteroids) >= $maxAsteroids) {
            $_SESSION['error'] = 'Maximale Anzahl an Asteroiden erreicht (' . $maxAsteroids . ').';
            $this->redirect('/asteroids?planet_id=' . $planetId);
            return;
        }
        
        // Check if mining is in cooldown
        if ($planet->last_asteroid_time) {
            $lastMining = strtotime($planet->last_asteroid_time);
            $cooldownTime = 3600 * 2; // 2 hours cooldown
            
            if (($lastMining + $cooldownTime) > time()) {
                $_SESSION['error'] = 'Bergungsausrüstung ist im Cooldown.';
                $this->redirect('/asteroids?planet_id=' . $planetId);
                return;
            }
        }
        
        // Calculate mining costs
        $miningCosts = [
            'metal' => 3000 + ($miningResearchLevel * 500),   // Eisen
            'crystal' => 1500 + ($miningResearchLevel * 250), // Silber
            'h2' => 800 + ($miningResearchLevel * 100),       // Wasserstoff
        ];
        
        // Check if player has enough resources
        if ($planet->metal < $miningCosts['metal'] || 
            $planet->crystal < $miningCosts['crystal'] || 
            $planet->h2 < $miningCosts['h2']) {
            
            $_SESSION['error'] = 'Nicht genügend Ressourcen zum Einfangen des Asteroiden.';
            $this->redirect('/asteroids?planet_id=' . $planetId);
            return;
        }
        
        // Deduct resources
        $planet->metal -= $miningCosts['metal'];
        $planet->crystal -= $miningCosts['crystal'];
        $planet->h2 -= $miningCosts['h2'];
        
        // Generate asteroid
        $asteroidId = uniqid('ast_');

        // Size and bonus depend on research and chance
        $baseSize = rand(1, 3);
        $researchBonus = floor($miningResearchLevel / 4);
        $size = $baseSize + $researchBonus;
        
        // Bonus percentage based on size and type
        $bonusPercentage = 5 * $size; // 5% per size level
        
        if ($asteroidType == 'mixed') {
            // Mixed asteroids have slightly lower bonus since they affect all resources
            $bonusPercentage *= 0.7;
        }
        
        // Create asteroid
        $asteroid = [
            'type' => $asteroidType,
            'size' => $size,
            'bonus' => $bonusPercentage,
            'created_time' => date('Y-m-d H:i:s')
        ];
        
        // Update asteroid data
        $currentAsteroids[$asteroidId] = $asteroid;
        $planet->asteroid_data = json_encode($currentAsteroids);
        $planet->last_asteroid_time = date('Y-m-d H:i:s');
        
        // Save changes
        $planet->save();
        
        // Update bonus production rates in Planet object
        $planet->updateResourceBonus();
          $resourceType = ($asteroidType == 'metal' ? 'Eisen' : 
            ($asteroidType == 'crystal' ? 'Silber' : 
            ($asteroidType == 'uderon' ? 'Uderon' : 'alle Ressourcen')));

        $_SESSION['success'] = 'Asteroid erfolgreich eingefangen! +' . round($bonusPercentage) . '% Bonus auf ' . 
            $resourceType . '.';
        
        // Send notification about the new asteroid
        NotificationService::asteroidDiscovered(
            $playerId,
            $asteroidType,
            round($bonusPercentage),
            $planet->name
        );
        
        $this->redirect('/asteroids?planet_id=' . $planetId);
    }
    
    public function discard() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['planet_id']) || !isset($_POST['asteroid_id'])) {
            $this->redirect('/asteroids');
            return;
        }
        
        $playerId = $_SESSION['user_id'];
        $planetId = (int)$_POST['planet_id'];
        $asteroidId = $_POST['asteroid_id'];
        
        $planet = Planet::getById($planetId);
        
        if (!$planet || $planet->player_id != $playerId) {
            $_SESSION['error'] = 'Ungültiger Planet oder nicht dein Planet.';
            $this->redirect('/asteroids');
            return;
        }
        
        // Get current asteroids
        $currentAsteroids = [];
        if ($planet->asteroid_data) {
            $asteroidData = json_decode($planet->asteroid_data, true);
            if (is_array($asteroidData)) {
                $currentAsteroids = $asteroidData;
            }
        }
        
        // Check if asteroid exists
        if (!isset($currentAsteroids[$asteroidId])) {
            $_SESSION['error'] = 'Asteroid nicht gefunden.';
            $this->redirect('/asteroids?planet_id=' . $planetId);
            return;
        }
          // Store asteroid info before removal for notification
        $discardedAsteroid = $currentAsteroids[$asteroidId];
        
        // Remove asteroid
        unset($currentAsteroids[$asteroidId]);
        $planet->asteroid_data = json_encode($currentAsteroids);
        
        // Save changes
        $planet->save();
        
        // Notify about asteroid discard
        NotificationService::asteroidDiscarded(
            $playerId,
            $discardedAsteroid['type'],
            $discardedAsteroid['bonus'],
            $planet->name
        );
        
        // Update bonus production rates
        $planet->updateResourceBonus();
        
        $_SESSION['success'] = 'Asteroid erfolgreich aus der Umlaufbahn entfernt.';
        $this->redirect('/asteroids?planet_id=' . $planetId);
    }
}
