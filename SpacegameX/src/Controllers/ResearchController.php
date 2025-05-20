<?php
namespace Controllers;

use Core\Controller;
use Models\Planet;
use Models\ResearchType;
use Models\PlayerResearch;
use Models\BuildingType; // Added for requirements check
use Models\PlayerBuilding; // Added for requirements check

class ResearchController extends Controller {
    
    public function index() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
            return;
        }
        
        $playerId = $_SESSION['user_id'];
        
        // Ensure game loop updates are processed
        $gameController = new GameController();
        $gameController->runGameLoopUpdates($playerId);
        
        // Get player's home planet for resources display
        $planet = Planet::getHomePlanetByPlayerId($playerId);
        
        if (!$planet) {
            $this->view('game.error', ['error' => 'Kein Heimatplanet gefunden.']);
            return;
        }
        
        // Refresh planet data after updates
        $planet = Planet::getById($planet->id);
        
        // Get player's research lab level - use the highest across all planets
        // This should ideally match the logic in PlayerResearch::initiateResearch
        $forschungszentrumTypeId = BuildingType::getByInternalName('forschungszentrum')->id ?? null;
        $labLevel = 0;
        
        if ($forschungszentrumTypeId) {
            $playerPlanets = Planet::getPlanetsByPlayerId($playerId);
            foreach ($playerPlanets as $playerPlanet) {
                $labOnPlanet = PlayerBuilding::getByPlanetAndType($playerPlanet->id, $forschungszentrumTypeId);
                if ($labOnPlanet && $labOnPlanet->level > $labLevel) {
                    $labLevel = $labOnPlanet->level;
                }
            }
        }
        
        // Get all research for the player - this includes current levels and if any are under research
        $playerResearch = PlayerResearch::getAllForPlayer($playerId);
        
        // Get research queue from construction_queue
        $db = \Core\Model::getDB();
        $stmt = $db->prepare('SELECT cq.*, rt.name_de, rt.internal_name 
                             FROM construction_queue cq
                             JOIN static_research_types rt ON cq.item_id = rt.id
                             WHERE cq.player_id = :player_id AND cq.item_type = "research"
                             ORDER BY cq.end_time ASC');
        $stmt->bindParam(':player_id', $playerId, \PDO::PARAM_INT);
        $stmt->execute();
        $researchQueue = $stmt->fetchAll(\PDO::FETCH_OBJ);
        
        // Get all static research types for displaying available research and calculating costs
        $allResearchTypes = ResearchType::getAll();
          
        // Prepare data for the view
        $data = [
            'pageTitle' => 'Forschung',
            'playerName' => \Models\Player::findById($playerId)->username,
            'planetName' => $planet->name,
            'coords' => $planet->galaxy . ':' . $planet->system . ':' . $planet->position,
            'eisen' => round($planet->eisen),
            'silber' => round($planet->silber),
            'uderon' => round($planet->uderon),
            'wasserstoff' => round($planet->wasserstoff),
            'energie' => round($planet->energie),
            'labLevel' => $labLevel,
            'playerResearch' => $playerResearch,
            'researchQueue' => $researchQueue,
            'allResearchTypes' => $allResearchTypes,
            'success_message' => $_SESSION['success_message'] ?? null,
            'error_message' => $_SESSION['error_message'] ?? null
        ];
        
        // Clear session messages after preparing them for view
        unset($_SESSION['success_message']);
        unset($_SESSION['error_message']);
        
        $this->view('game.research', $data);
    }
    
    public function start() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['research_type_id'])) {
            $this->redirect('/game/research');
            return;
        }
        
        $playerId = $_SESSION['user_id'];
        $researchTypeId = filter_input(INPUT_POST, 'research_type_id', FILTER_VALIDATE_INT);
        
        if (!$researchTypeId) {
            $_SESSION['error_message'] = 'Ungültige Anfrageparameter.';
            $this->redirect('/game/research');
            return;
        }

        // Call the model method to handle the research logic
        // This method will perform all checks (resources, prerequisites, queue status) and add to queue
        $result = PlayerResearch::initiateResearch($playerId, $researchTypeId);

        if ($result['success']) {
            $_SESSION['success_message'] = $result['message'] ?? 'Forschung gestartet!';
        } else {
            $_SESSION['error_message'] = $result['message'] ?? 'Fehler beim Starten der Forschung.';
        }
        
        // Redirect back to research page
        $this->redirect('/game/research');
    }
    
    public function cancel() {
        // Logic for canceling research (would refund resources)
        // Not implemented in this version
        $this->redirect('/research');
    }
}
?>
