<?php
namespace Controllers;

use Core\Controller;
use Models\Player;
use Models\Alliance;
use Models\AllianceResearch;
use Models\AllianceResearchType;

class AllianceResearchController extends Controller {
    
    public function index() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
        
        $playerId = $_SESSION['user_id'];
        $player = Player::getById($playerId);
        
        if (!$player) {
            $this->redirect('/login');
        }
        
        // Check if player is in an alliance
        if (!$player->alliance_id) {
            $this->view('game.alliance_research', [
                'alliance' => null
            ]);
            return;
        }
        
        // Get alliance information
        $alliance = Alliance::getById($player->alliance_id);
        
        if (!$alliance) {
            $this->view('game.alliance_research', [
                'alliance' => null
            ]);
            return;
        }
        
        // Get alliance members count
        $memberCount = Alliance::getMemberCount($alliance->id);
        $maxMembers = 30; // Maximum alliance members
        
        // Get player role in alliance
        $userRole = 'Mitglied';
        if ($alliance->leader_id == $player->id) {
            $userRole = 'Anführer';
        } elseif ($alliance->officer_ids && in_array($player->id, explode(',', $alliance->officer_ids))) {
            $userRole = 'Offizier';
        }
        
        // Check permissions
        $canStartResearch = ($userRole == 'Anführer' || $userRole == 'Offizier');
        $canCancelResearch = ($userRole == 'Anführer');
        $canManageResources = ($userRole == 'Anführer' || $userRole == 'Offizier');
        
        // Get alliance resources
        $allianceMetal = $alliance->metal ?? 0;
        $allianceCrystal = $alliance->crystal ?? 0;
        $allianceUderon = $alliance->uderon ?? 0;
        $alliancePoints = $alliance->points ?? 0;
        
        // Get alliance research queue
        $researchQueue = AllianceResearch::getCurrentResearch($alliance->id);
        
        // Get all research types
        $researchTypes = AllianceResearchType::getAll();
        
        // Get current alliance researches
        $currentResearches = AllianceResearch::getAllForAlliance($alliance->id);
        
        // Organize research by categories
        $researchCategories = [
            'resource' => [],
            'military' => [],
            'diplomatic' => [],
            'infrastructure' => []
        ];
        
        // Current bonuses
        $allianceBonuses = [
            'resource_production' => 0,
            'research_speed' => 0,
            'construction_speed' => 0,
            'attack_bonus' => 0,
            'defense_bonus' => 0,
            'espionage_defense' => 0,
            'flight_speed' => 0,
            'trade_bonus' => 0
        ];
        
        foreach ($researchTypes as $type) {
            // Find current level for this research
            $level = 0;
            $currentResearch = null;
            foreach ($currentResearches as $research) {
                if ($research->type_id == $type->id) {
                    $level = $research->level;
                    $currentResearch = $research;
                    break;
                }
            }
            
            // Calculate current and next level effects
            $currentEffects = [];
            $nextLevelEffects = [];
            
            switch ($type->key) {
                // Resource category
                case 'mineral_research':
                    $currentEffects['Eisen/Silber Produktion'] = '+' . ($level * 2) . '%';
                    $nextLevelEffects['Eisen/Silber Produktion'] = '+' . (($level + 1) * 2) . '%';
                    $allianceBonuses['resource_production'] += ($level * 2);
                    break;
                case 'gas_research':
                    $currentEffects['Wasserstoff Produktion'] = '+' . ($level * 2) . '%';
                    $nextLevelEffects['Wasserstoff Produktion'] = '+' . (($level + 1) * 2) . '%';
                    $allianceBonuses['resource_production'] += ($level * 1);
                    break;
                case 'energy_research':
                    $currentEffects['Energie Produktion'] = '+' . ($level * 3) . '%';
                    $nextLevelEffects['Energie Produktion'] = '+' . (($level + 1) * 3) . '%';
                    $allianceBonuses['resource_production'] += ($level * 1);
                    break;
                case 'uderon_research':
                    $currentEffects['Uderon Produktion'] = '+' . ($level * 2) . '%';
                    $nextLevelEffects['Uderon Produktion'] = '+' . (($level + 1) * 2) . '%';
                    $allianceBonuses['resource_production'] += ($level * 1);
                    break;
                
                // Military category
                case 'weapons_research':
                    $currentEffects['Schiffsangriff'] = '+' . ($level * 2) . '%';
                    $nextLevelEffects['Schiffsangriff'] = '+' . (($level + 1) * 2) . '%';
                    $allianceBonuses['attack_bonus'] += ($level * 2);
                    break;
                case 'armor_research':
                    $currentEffects['Schiffsverteidigung'] = '+' . ($level * 2) . '%';
                    $nextLevelEffects['Schiffsverteidigung'] = '+' . (($level + 1) * 2) . '%';
                    $allianceBonuses['defense_bonus'] += ($level * 2);
                    break;
                case 'drive_research':
                    $currentEffects['Fluggeschwindigkeit'] = '+' . ($level * 1) . '%';
                    $nextLevelEffects['Fluggeschwindigkeit'] = '+' . (($level + 1) * 1) . '%';
                    $allianceBonuses['flight_speed'] += ($level * 1);
                    break;
                
                // Diplomatic category
                case 'espionage_research':
                    $currentEffects['Spionageschutz'] = '+' . ($level * 5) . '%';
                    $nextLevelEffects['Spionageschutz'] = '+' . (($level + 1) * 5) . '%';
                    $allianceBonuses['espionage_defense'] += ($level * 5);
                    break;
                case 'diplomacy_research':
                    $currentEffects['Handelsbonus'] = '+' . ($level * 3) . '%';
                    $nextLevelEffects['Handelsbonus'] = '+' . (($level + 1) * 3) . '%';
                    $allianceBonuses['trade_bonus'] += ($level * 3);
                    break;
                
                // Infrastructure category
                case 'development_research':
                    $currentEffects['Forschungsgeschwindigkeit'] = '+' . ($level * 2) . '%';
                    $nextLevelEffects['Forschungsgeschwindigkeit'] = '+' . (($level + 1) * 2) . '%';
                    $allianceBonuses['research_speed'] += ($level * 2);
                    break;
                case 'construction_research':
                    $currentEffects['Schiffbaugeschwindigkeit'] = '+' . ($level * 2) . '%';
                    $nextLevelEffects['Schiffbaugeschwindigkeit'] = '+' . (($level + 1) * 2) . '%';
                    $allianceBonuses['construction_speed'] += ($level * 2);
                    break;
            }
            
            // Check requirements
            $requirements = [];
            $requirementsFulfilled = true;
            
            if ($type->required_research_ids) {
                $requiredResearchIds = explode(',', $type->required_research_ids);
                $requiredLevels = explode(',', $type->required_research_levels);
                
                for ($i = 0; $i < count($requiredResearchIds); $i++) {
                    $reqId = (int)$requiredResearchIds[$i];
                    $reqLevel = (int)$requiredLevels[$i];
                    
                    $reqType = null;
                    foreach ($researchTypes as $rt) {
                        if ($rt->id == $reqId) {
                            $reqType = $rt;
                            break;
                        }
                    }
                    
                    if ($reqType) {
                        $currentLevel = 0;
                        foreach ($currentResearches as $cr) {
                            if ($cr->type_id == $reqId) {
                                $currentLevel = $cr->level;
                                break;
                            }
                        }
                        
                        $fulfilled = $currentLevel >= $reqLevel;
                        $requirements[] = (object)[
                            'name' => $reqType->name,
                            'required_level' => $reqLevel,
                            'current_level' => $currentLevel,
                            'fulfilled' => $fulfilled
                        ];
                        
                        if (!$fulfilled) {
                            $requirementsFulfilled = false;
                        }
                    }
                }
            }
            
            // Calculate research costs
            $baseMetal = $type->base_cost_metal;
            $baseCrystal = $type->base_cost_crystal;
            $baseUderon = $type->base_cost_uderon;
            $baseTime = $type->base_time;
            
            $costFactor = pow(1.5, $level);
            $costs = [
                'metal' => round($baseMetal * $costFactor),
                'crystal' => round($baseCrystal * $costFactor),
                'uderon' => round($baseUderon * $costFactor),
                'time' => $this->formatTime(round($baseTime * $costFactor))
            ];
            
            // Check if resources are sufficient
            $canAfford = $allianceMetal >= $costs['metal'] && 
                         $allianceCrystal >= $costs['crystal'] && 
                         $allianceUderon >= $costs['uderon'];
            
            // Research object
            $research = (object)[
                'id' => $type->id,
                'key' => $type->key,
                'name' => $type->name,
                'description' => $type->description,
                'level' => $level,
                'max_level' => $type->max_level,
                'current_effects' => $currentEffects,
                'next_level_effects' => $nextLevelEffects,
                'requirements' => $requirements,
                'costs' => $costs,
                'can_research' => $requirementsFulfilled && $canAfford && $level < $type->max_level && empty($researchQueue)
            ];
            
            // Add to appropriate category
            $researchCategories[$type->category][] = $research;
        }
        
        // Compile data for view
        $viewData = [
            'alliance' => $alliance,
            'allianceName' => $alliance->name,
            'memberCount' => $memberCount,
            'maxMembers' => $maxMembers,
            'userRole' => $userRole,
            'canStartResearch' => $canStartResearch,
            'canCancelResearch' => $canCancelResearch,
            'canManageResources' => $canManageResources,
            'allianceMetal' => $allianceMetal,
            'allianceCrystal' => $allianceCrystal,
            'allianceUderon' => $allianceUderon,
            'alliancePoints' => $alliancePoints,
            'researchQueue' => $researchQueue,
            'researchCategories' => $researchCategories,
            'allianceBonuses' => $allianceBonuses
        ];
        
        // Check if there are success or error messages in session
        if (isset($_SESSION['success'])) {
            $viewData['successMessage'] = $_SESSION['success'];
            unset($_SESSION['success']);
        }
        
        if (isset($_SESSION['error'])) {
            $viewData['errorMessage'] = $_SESSION['error'];
            unset($_SESSION['error']);
        }
        
        $this->view('game.alliance_research', $viewData);
    }
    
    public function start() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['research_id'])) {
            $this->redirect('/alliance/research');
            return;
        }
        
        $playerId = $_SESSION['user_id'];
        $player = Player::getById($playerId);
        
        if (!$player || !$player->alliance_id) {
            $this->redirect('/alliance/research');
            return;
        }
        
        // Get alliance information
        $alliance = Alliance::getById($player->alliance_id);
        
        if (!$alliance) {
            $this->redirect('/alliance/research');
            return;
        }
        
        // Check permission
        $hasPermission = false;
        if ($alliance->leader_id == $player->id) {
            $hasPermission = true;
        } elseif ($alliance->officer_ids && in_array($player->id, explode(',', $alliance->officer_ids))) {
            $hasPermission = true;
        }
        
        if (!$hasPermission) {
            $_SESSION['error'] = 'Du hast keine Berechtigung, Allianz-Forschungen zu starten.';
            $this->redirect('/alliance/research');
            return;
        }
        
        // Check if research is already in progress
        $researchQueue = AllianceResearch::getCurrentResearch($alliance->id);
        if (!empty($researchQueue)) {
            $_SESSION['error'] = 'Es ist bereits eine Forschung in Bearbeitung.';
            $this->redirect('/alliance/research');
            return;
        }
        
        // Get research type
        $researchTypeId = (int)$_POST['research_id'];
        $researchType = AllianceResearchType::getById($researchTypeId);
        
        if (!$researchType) {
            $_SESSION['error'] = 'Ungültige Forschung.';
            $this->redirect('/alliance/research');
            return;
        }
        
        // Get current research level
        $currentResearch = AllianceResearch::getByTypeIdAndAllianceId($researchTypeId, $alliance->id);
        $currentLevel = $currentResearch ? $currentResearch->level : 0;
        
        // Check if max level reached
        if ($currentLevel >= $researchType->max_level) {
            $_SESSION['error'] = 'Diese Forschung hat bereits das maximale Level erreicht.';
            $this->redirect('/alliance/research');
            return;
        }
        
        // Check requirements
        if ($researchType->required_research_ids) {
            $requiredResearchIds = explode(',', $researchType->required_research_ids);
            $requiredLevels = explode(',', $researchType->required_research_levels);
            
            for ($i = 0; $i < count($requiredResearchIds); $i++) {
                $reqId = (int)$requiredResearchIds[$i];
                $reqLevel = (int)$requiredLevels[$i];
                
                $reqResearch = AllianceResearch::getByTypeIdAndAllianceId($reqId, $alliance->id);
                $reqCurrentLevel = $reqResearch ? $reqResearch->level : 0;
                
                if ($reqCurrentLevel < $reqLevel) {
                    $reqType = AllianceResearchType::getById($reqId);
                    $_SESSION['error'] = 'Voraussetzung nicht erfüllt: ' . $reqType->name . ' Level ' . $reqLevel . ' erforderlich.';
                    $this->redirect('/alliance/research');
                    return;
                }
            }
        }
        
        // Calculate costs
        $baseMetal = $researchType->base_cost_metal;
        $baseCrystal = $researchType->base_cost_crystal;
        $baseUderon = $researchType->base_cost_uderon;
        $baseTime = $researchType->base_time;
        
        $costFactor = pow(1.5, $currentLevel);
        $metal = round($baseMetal * $costFactor);
        $crystal = round($baseCrystal * $costFactor);
        $uderon = round($baseUderon * $costFactor);
        $time = round($baseTime * $costFactor); // Time in seconds
        
        // Check resources
        if ($alliance->metal < $metal || $alliance->crystal < $crystal || $alliance->uderon < $uderon) {
            $_SESSION['error'] = 'Nicht genügend Ressourcen für diese Forschung.';
            $this->redirect('/alliance/research');
            return;
        }
        
        // Deduct resources
        $alliance->metal -= $metal;
        $alliance->crystal -= $crystal;
        $alliance->uderon -= $uderon;
        $alliance->save();
        
        // Start research
        $nextLevel = $currentLevel + 1;
        $endTime = date('Y-m-d H:i:s', time() + $time);
        
        if ($currentResearch) {
            // Update existing research
            $currentResearch->researching = 1;
            $currentResearch->research_end_time = $endTime;
            $currentResearch->save();
        } else {
            // Create new research
            AllianceResearch::create([
                'alliance_id' => $alliance->id,
                'type_id' => $researchTypeId,
                'level' => 0, // Will be updated to 1 when complete
                'researching' => 1,
                'research_end_time' => $endTime
            ]);
        }
        
        $_SESSION['success'] = 'Forschung ' . $researchType->name . ' Level ' . $nextLevel . ' gestartet. Fertig in: ' . $this->formatTime($time);
        $this->redirect('/alliance/research');
    }
    
    public function cancel() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['research_id'])) {
            $this->redirect('/alliance/research');
            return;
        }
        
        $playerId = $_SESSION['user_id'];
        $player = Player::getById($playerId);
        
        if (!$player || !$player->alliance_id) {
            $this->redirect('/alliance/research');
            return;
        }
        
        // Get alliance information
        $alliance = Alliance::getById($player->alliance_id);
        
        if (!$alliance) {
            $this->redirect('/alliance/research');
            return;
        }
        
        // Check permission (only leader can cancel)
        if ($alliance->leader_id != $player->id) {
            $_SESSION['error'] = 'Nur der Allianzleiter kann Forschungen abbrechen.';
            $this->redirect('/alliance/research');
            return;
        }
        
        // Get research
        $researchId = (int)$_POST['research_id'];
        $research = AllianceResearch::getById($researchId);
        
        if (!$research || $research->alliance_id != $alliance->id || $research->researching != 1) {
            $_SESSION['error'] = 'Ungültige Forschung oder nicht in Bearbeitung.';
            $this->redirect('/alliance/research');
            return;
        }
        
        // Get research type
        $researchType = AllianceResearchType::getById($research->type_id);
        
        if (!$researchType) {
            $_SESSION['error'] = 'Ungültige Forschung.';
            $this->redirect('/alliance/research');
            return;
        }
        
        // Calculate refund (50% of resources)
        $baseMetal = $researchType->base_cost_metal;
        $baseCrystal = $researchType->base_cost_crystal;
        $baseUderon = $researchType->base_cost_uderon;
        
        $costFactor = pow(1.5, $research->level);
        $refundMetal = round(($baseMetal * $costFactor) * 0.5);
        $refundCrystal = round(($baseCrystal * $costFactor) * 0.5);
        $refundUderon = round(($baseUderon * $costFactor) * 0.5);
        
        // Refund resources
        $alliance->metal += $refundMetal;
        $alliance->crystal += $refundCrystal;
        $alliance->uderon += $refundUderon;
        $alliance->save();
        
        // Cancel research
        $research->researching = 0;
        $research->research_end_time = null;
        $research->save();
        
        $_SESSION['success'] = 'Forschung ' . $researchType->name . ' abgebrochen. 50% der Ressourcen wurden zurückerstattet.';
        $this->redirect('/alliance/research');
    }
    
    private function formatTime($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}
