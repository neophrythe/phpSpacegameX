<?php
namespace Controllers;

use Core\Controller;
use Models\Player;
use Models\Alliance;
use Models\AllianceBuilding;
use Models\AllianceResearch;
use Models\AllianceBuildingType;
use Models\AllianceResearchType;
use Models\SolarSystem; // Needed to link alliance buildings to systems
use Models\PlayerBuilding; // Needed for alliance building requirements (e.g., Raumstation level)
use Models\PlayerResearch; // Needed for alliance research requirements
use Models\Planet; // Added for planet checks
use Models\BuildingType; // Added for checking mine levels
use Models\NotificationService; // Added for notifications


class AllianceController extends Controller {

    public function index() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }

        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);

        if (!$player) {
            $this->redirect('/');
        }

        $alliance = null;
        $allianceMembers = [];
        $allianceBuildings = [];
        $allianceResearch = [];
        $staticAllianceBuildingTypes = [];
        $staticAllianceResearchTypes = [];
        $allianceTreasury = ['eisen' => 0, 'silber' => 0, 'uderon' => 0, 'wasserstoff' => 0, 'energie' => 0];

        if ($player->alliance_id) {
            $alliance = Alliance::findById($player->alliance_id);
            if ($alliance) {
                $allianceMembers = $alliance->getMembers();
                $allianceBuildings = AllianceBuilding::getAllForAlliance($alliance->id);
                $allianceResearch = AllianceResearch::getAllForAlliance($alliance->id);
                $staticAllianceBuildingTypes = AllianceBuildingType::getAll();
                $staticAllianceResearchTypes = AllianceResearchType::getAll();
                $allianceTreasury = [
                    'eisen' => $alliance->eisen,
                    'silber' => $alliance->silber,
                    'uderon' => $alliance->uderon,
                    'wasserstoff' => $alliance->wasserstoff,
                    'energie' => $alliance->energie
                ];
            }
        } else {
            // Player is not in an alliance, show list of alliances to join
            $alliances = Alliance::getAll();
        }

        $data = [
            'pageTitle' => 'Allianz',
            'player' => $player,
            'alliance' => $alliance,
            'allianceMembers' => $allianceMembers,
            'allianceBuildings' => $allianceBuildings,
            'allianceResearch' => $allianceResearch,
            'staticAllianceBuildingTypes' => $staticAllianceBuildingTypes,
            'staticAllianceResearchTypes' => $staticAllianceResearchTypes,
            'alliancesToJoin' => $alliances ?? [], // Pass list of alliances if player is not in one
            'allianceTreasury' => $allianceTreasury // Added alliance treasury
        ];

        $this->view('game.alliance', $data); // Need to create this view
    }

    public function create() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['name']) || !isset($_POST['tag'])) {
            $this->setFlashMessage('error', 'Ungültige Anfrage.');
            $this->redirect('/alliance');
        }

        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);

        if (!$player || $player->alliance_id) {
            // Player already in an alliance or invalid player
            $this->setFlashMessage('error', 'Du bist bereits in einer Allianz oder dein Spielerprofil ist ungültig.');
            $this->redirect('/alliance');
        }

        $name = trim($_POST['name']);
        $tag = trim($_POST['tag']);
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;

        // Basic validation
        if (empty($name) || empty($tag)) {
            $this->setFlashMessage('error', 'Allianzname und Tag dürfen nicht leer sein.');
            $this->redirect('/alliance');
        }

        // Check if alliance name or tag already exists
        if (Alliance::findByName($name)) {
            $this->setFlashMessage('error', "Eine Allianz mit dem Namen '{$name}' existiert bereits.");
            $this->redirect('/alliance');
        }
        if (Alliance::findByTag($tag)) {
            $this->setFlashMessage('error', "Eine Allianz mit dem Tag '{$tag}' existiert bereits.");
            $this->redirect('/alliance');
        }

        // Create the alliance
        $allianceId = Alliance::create($name, $tag, $description, $playerId);

        if ($allianceId) {
            // Add the creating player as an admin
            $alliance = Alliance::findById($allianceId);
            $alliance->addMember($playerId, 'admin'); // Assuming addMember handles updating player table

            $this->setFlashMessage('success', "Allianz '{$name}' [{$tag}] erfolgreich gegründet!");
        } else {
            $this->setFlashMessage('error', 'Fehler beim Erstellen der Allianz.');
        }

        $this->redirect('/alliance');
    }

    public function join() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['alliance_id'])) {
            $this->setFlashMessage('error', 'Ungültige Anfrage.');
            $this->redirect('/alliance');
        }

        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);

        if (!$player || $player->alliance_id) {
            $this->setFlashMessage('error', 'Du bist bereits in einer Allianz oder dein Spielerprofil ist ungültig.');
            $this->redirect('/alliance');
        }

        $allianceId = intval($_POST['alliance_id']);
        $alliance = Alliance::findById($allianceId);

        if (!$alliance) {
            $this->setFlashMessage('error', 'Ungültige Allianz-ID.');
            $this->redirect('/alliance');
        }

        // Add player to alliance as a test member
        $result = $alliance->addMember($playerId, 'test_member');

        if ($result) {
            $this->setFlashMessage('success', "Erfolgreich der Allianz '{$alliance->name}' beigetreten.");
        } else {
            $this->setFlashMessage('error', "Fehler beim Beitritt zur Allianz '{$alliance->name}'.");
        }

        $this->redirect('/alliance');
    }

    public function leave() {
        if (!isset($_SESSION['user_id'])) {
            $this->redirect('/login');
        }

        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);

        if (!$player || !$player->alliance_id) {
            $this->setFlashMessage('error', 'Du bist in keiner Allianz.');
            $this->redirect('/alliance');
        }

        $alliance = Alliance::findById($player->alliance_id);

        if (!$alliance) {
            // Alliance not found (should not happen if player has alliance_id)
            $this->setFlashMessage('error', 'Allianz nicht gefunden.');
            $this->redirect('/alliance');
        }

        // Check if the player is the last admin (prevent leaving if last admin)
        $members = $alliance->getMembers();
        $adminCount = 0;
        foreach ($members as $member) {
            if ($member->alliance_rank === 'admin') {
                $adminCount++;
            }
        }

        if ($player->alliance_rank === 'admin' && $adminCount === 1) {
            $this->setFlashMessage('error', 'Du kannst die Allianz nicht verlassen, da du der letzte Administrator bist. Übertrage zuerst die Leitung oder lösche die Allianz.');
            $this->redirect('/alliance');
        }

        // Remove player from alliance
        $result = $alliance->removeMember($playerId);

        if ($result) {
            $this->setFlashMessage('success', "Du hast die Allianz '{$alliance->name}' verlassen.");
        } else {
            $this->setFlashMessage('error', "Fehler beim Verlassen der Allianz '{$alliance->name}'.");
        }

        $this->redirect('/alliance');
    }

    public function changeRank() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['member_id']) || !isset($_POST['rank'])) {
            $this->setFlashMessage('error', 'Ungültige Anfrage.');
            $this->redirect('/alliance');
        }

        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);

        if (!$player || !$player->alliance_id || $player->alliance_rank !== 'admin') {
            $this->setFlashMessage('error', 'Du bist kein Administrator dieser Allianz oder nicht in einer Allianz.');
            $this->redirect('/alliance');
        }

        $memberId = intval($_POST['member_id']);
        $newRank = $_POST['rank']; // 'member', 'admin', 'test_member'
        $validRanks = ['member', 'admin', 'test_member'];

        if (!in_array($newRank, $validRanks)){
            $this->setFlashMessage('error', 'Ungültiger Rang.');
            $this->redirect('/alliance');
        }

        $alliance = Alliance::findById($player->alliance_id);
        $memberToChange = Player::findById($memberId);

        if (!$alliance || !$memberToChange || $memberToChange->alliance_id !== $alliance->id) {
            $this->setFlashMessage('error', 'Ungültige Allianz oder Mitglied.');
            $this->redirect('/alliance');
        }

        // Prevent changing own rank
        if ($playerId === $memberId) {
            $this->setFlashMessage('error', 'Du kannst deinen eigenen Rang nicht ändern.');
            $this->redirect('/alliance');
        }

        // Prevent demoting the last admin
        if ($memberToChange->alliance_rank === 'admin' && $newRank !== 'admin') {
             $members = $alliance->getMembers();
             $adminCount = 0;
             foreach ($members as $m) {
                 if ($m->alliance_rank === 'admin') {
                     $adminCount++;
                 }
             }
             if ($adminCount === 1) {
                 $this->setFlashMessage('error', 'Du kannst den letzten Administrator nicht degradieren.');
                 $this->redirect('/alliance');
             }
        }

        // Update member rank
        $result = $alliance->updateMemberRank($memberId, $newRank);

        if ($result) {
            $this->setFlashMessage('success', "Rang von Spieler {$memberToChange->username} erfolgreich zu {$newRank} geändert.");
        } else {
            $this->setFlashMessage('error', "Fehler beim Ändern des Rangs von Spieler {$memberToChange->username}.");
        }

        $this->redirect('/alliance');
    }

    public function kickMember() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['member_id'])) {
            $this->setFlashMessage('error', 'Ungültige Anfrage.');
            $this->redirect('/alliance');
        }

        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);

        if (!$player || !$player->alliance_id || $player->alliance_rank !== 'admin') {
            $this->setFlashMessage('error', 'Du bist kein Administrator dieser Allianz oder nicht in einer Allianz.');
            $this->redirect('/alliance');
        }

        $memberIdToKick = intval($_POST['member_id']);
        $alliance = Alliance::findById($player->alliance_id);
        $memberToKick = Player::findById($memberIdToKick);

        if (!$alliance || !$memberToKick || $memberToKick->alliance_id !== $alliance->id) {
            $this->setFlashMessage('error', 'Ungültige Allianz oder Mitglied.');
            $this->redirect('/alliance');
        }

        // Prevent kicking self
        if ($playerId === $memberIdToKick) {
            $this->setFlashMessage('error', 'Du kannst dich nicht selbst kicken.');
            $this->redirect('/alliance');
        }

        // Prevent kicking the last admin
        if ($memberToKick->alliance_rank === 'admin') {
             $members = $alliance->getMembers();
             $adminCount = 0;
             foreach ($members as $m) {
                 if ($m->alliance_rank === 'admin') {
                     $adminCount++;
                 }
             }
             if ($adminCount === 1) {
                 $this->setFlashMessage('error', 'Du kannst den letzten Administrator nicht kicken.');
                 $this->redirect('/alliance');
             }
        }

        // Remove member from alliance
        $result = $alliance->removeMember($memberIdToKick);

        if ($result) {
            $this->setFlashMessage('success', "Spieler {$memberToKick->username} wurde aus der Allianz entfernt.");
        } else {
            $this->setFlashMessage('error', "Fehler beim Entfernen von Spieler {$memberToKick->username} aus der Allianz.");
        }

        $this->redirect('/alliance');
    }

    public function updateDetails() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['alliance_id'])) {
            $this->setFlashMessage('error', 'Ungültige Anfrage.');
            $this->redirect('/alliance');
        }

        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);

        if (!$player || !$player->alliance_id || $player->alliance_rank !== 'admin') {
            $this->setFlashMessage('error', 'Du bist kein Administrator dieser Allianz oder nicht in einer Allianz.');
            $this->redirect('/alliance');
        }

        $allianceId = intval($_POST['alliance_id']);
        $alliance = Alliance::findById($allianceId);

        if (!$alliance || $alliance->id !== $player->alliance_id) {
            $this->setFlashMessage('error', 'Ungültige Allianz oder du bist kein Administrator dieser Allianz.');
            $this->redirect('/alliance');
        }

        $name = isset($_POST['name']) ? trim($_POST['name']) : null;
        $tag = isset($_POST['tag']) ? trim($_POST['tag']) : null;
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;

        // Basic validation
        if (($name !== null && empty($name)) || ($tag !== null && empty($tag))) {
             $this->setFlashMessage('error', 'Allianzname und Tag dürfen nicht leer sein, wenn sie geändert werden.');
             $this->redirect('/alliance');
        }

        // Check if new name or tag already exists (if changing)
        if ($name !== null && $name !== $alliance->name && Alliance::findByName($name)) {
             $this->setFlashMessage('error', "Eine Allianz mit dem Namen '{$name}' existiert bereits.");
             $this->redirect('/alliance');
        }
        if ($tag !== null && $tag !== $alliance->tag && Alliance::findByTag($tag)) {
             $this->setFlashMessage('error', "Eine Allianz mit dem Tag '{$tag}' existiert bereits.");
             $this->redirect('/alliance');
        }

        // Update alliance details
        $result = $alliance->updateDetails($name, $tag, $description);

        if ($result) {
            $this->setFlashMessage('success', 'Allianzdetails erfolgreich aktualisiert.');
        } else {
            $this->setFlashMessage('error', 'Fehler beim Aktualisieren der Allianzdetails.');
        }

        $this->redirect('/alliance');
    }

    public function delete() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['alliance_id'])) {
            $this->setFlashMessage('error', 'Ungültige Anfrage.');
            $this->redirect('/alliance');
        }

        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);

        if (!$player || !$player->alliance_id || $player->alliance_rank !== 'admin') {
            $this->setFlashMessage('error', 'Du bist kein Administrator dieser Allianz oder nicht in einer Allianz.');
            $this->redirect('/alliance');
        }

        $allianceId = intval($_POST['alliance_id']);
        $alliance = Alliance::findById($allianceId);

        if (!$alliance || $alliance->id !== $player->alliance_id) {
            $this->setFlashMessage('error', 'Ungültige Allianz oder du bist kein Administrator dieser Allianz.');
            $this->redirect('/alliance');
        }

        // Check if there are other admins (cannot delete if not the last admin)
        $members = $alliance->getMembers();
        $adminCount = 0;
        foreach ($members as $member) {
            if ($member->alliance_rank === 'admin') {
                $adminCount++;
            }
        }

        if ($adminCount > 1 && $player->alliance_rank === 'admin') {
            // Only allow deletion if current player is an admin AND is the only admin.
            // Or, if the alliance has no members (which implies the current player is the founder/admin).
            // This logic might need refinement based on exact game rules for alliance deletion.
            $isOnlyAdmin = true;
            foreach ($members as $member) {
                if ($member->id !== $playerId && $member->alliance_rank === 'admin') {
                    $isOnlyAdmin = false;
                    break;
                }
            }
            if (!$isOnlyAdmin) {
                 $this->setFlashMessage('error', 'Du kannst die Allianz nicht löschen, da es noch andere Administratoren gibt.');
                 $this->redirect('/alliance');
            }
        } else if ($adminCount === 1 && $player->alliance_rank !== 'admin') {
            // This case should ideally not be reachable if checks are correct, but as a safeguard:
            $this->setFlashMessage('error', 'Nur der Administrator kann die Allianz löschen.');
            $this->redirect('/alliance');
        }


        // Delete the alliance
        $allianceName = $alliance->name;
        $result = $alliance->delete(); // This method should handle removing all members first

        if ($result) {
            $this->setFlashMessage('success', "Allianz '{$allianceName}' erfolgreich gelöscht.");
        } else {
            $this->setFlashMessage('error', "Fehler beim Löschen der Allianz '{$allianceName}'. Stelle sicher, dass du der einzige Administrator bist.");
        }

        $this->redirect('/alliance');
    }

    public function buildAllianceBuilding() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['building_type_id']) || !isset($_POST['solar_system_galaxy']) || !isset($_POST['solar_system_system'])) {
            $this->setFlashMessage('error', 'Ungültige Anfrage.');
            $this->redirect('/alliance');
        }

        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);

        if (!$player || !$player->alliance_id || $player->alliance_rank !== 'admin') {
            $this->setFlashMessage('error', 'Nur Allianz-Administratoren können Gebäude bauen.');
            $this->redirect('/alliance');
        }

        $alliance = Alliance::findById($player->alliance_id);
        if (!$alliance) {
            $this->setFlashMessage('error', 'Allianz nicht gefunden.');
            $this->redirect('/alliance'); // Should not happen
        }

        $buildingTypeId = intval($_POST['building_type_id']);
        $solarSystemGalaxy = intval($_POST['solar_system_galaxy']);
        $solarSystemSystem = intval($_POST['solar_system_system']);

        $buildingType = AllianceBuildingType::getById($buildingTypeId);
        if (!$buildingType) {
            $this->setFlashMessage('error', 'Ungültiger Gebäudetyp.');
            $this->redirect('/alliance');
        }

        $solarSystem = SolarSystem::getByGalaxyAndSystem($solarSystemGalaxy, $solarSystemSystem);
        if (!$solarSystem) {
            $this->setFlashMessage('error', 'Ungültiges Sonnensystem.');
            $this->redirect('/alliance');
        }

        $existingBuilding = AllianceBuilding::getByAllianceAndSystemAndType($alliance->id, $solarSystem->id, $buildingTypeId);
        if ($existingBuilding) {
            $this->setFlashMessage('info', 'Ein Gebäude dieses Typs existiert bereits in diesem System. Du kannst es möglicherweise ausbauen.');
            $this->redirect('/alliance');
        }

        // Check requirements
        if ($buildingType->requirements_json) {
            $requirements = json_decode($buildingType->requirements_json, true);
            if (isset($requirements['alliance_research'])) {
                $allianceResearchLevels = AllianceResearch::getAllForAlliance($alliance->id);
                $allianceResearchMap = [];
                foreach ($allianceResearchLevels as $research) {
                    $allianceResearchMap[$research->research_type_id] = $research->level;
                }
                foreach ($requirements['alliance_research'] as $internalName => $level) {
                    $requiredResearchType = AllianceResearchType::getByInternalName($internalName);
                    if ($requiredResearchType) {
                        $currentLevel = $allianceResearchMap[$requiredResearchType->id] ?? 0;
                        if ($currentLevel < $level) {
                            $this->setFlashMessage('error', "Voraussetzung nicht erfüllt: Allianzforschung '{$requiredResearchType->name}' Level {$level} benötigt.");
                            $this->redirect('/alliance');
                        }
                    }
                }
            }
            // Player building requirements are not typically applicable for alliance buildings.
            // If there's a specific scenario, it needs to be defined.
        }

        $cost = $buildingType->getCostAtLevel(0); // Cost for level 1

        // Check resources and deduct
        if (!$alliance->hasEnoughResources($cost)) {
            $this->setFlashMessage('error', 'Nicht genügend Ressourcen in der Allianzkasse.');
            $this->redirect('/alliance');
        }

        // Start construction (simplified: directly create building record for now)
        // In a real scenario, this would go into a queue.
        $buildTimeSeconds = $buildingType->getBaseBuildTimeAtLevel(0);
        $completionTime = date('Y-m-d H:i:s', time() + $buildTimeSeconds);

        // For now, assume instant build for simplicity or direct creation if no queue system for alliance buildings yet
        $newBuildingId = AllianceBuilding::create($alliance->id, $buildingTypeId, $solarSystem->id, 1, $completionTime, 'building');

        if ($newBuildingId) {
            $alliance->deductResources($cost);
            $this->setFlashMessage('success', "Bau von '{$buildingType->name}' in System {$solarSystemGalaxy}:{$solarSystemSystem} in Auftrag gegeben.");
        } else {
            $this->setFlashMessage('error', 'Fehler beim Starten des Baus.');
        }

        $this->redirect('/alliance');
    }

    public function upgradeAllianceBuilding() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['alliance_building_id'])) {
            $this->setFlashMessage('error', 'Ungültige Anfrage.');
            $this->redirect('/alliance');
        }

        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);

        if (!$player || !$player->alliance_id || $player->alliance_rank !== 'admin') {
            $this->setFlashMessage('error', 'Nur Allianz-Administratoren können Gebäude ausbauen.');
            $this->redirect('/alliance');
        }

        $allianceBuildingId = intval($_POST['alliance_building_id']);
        $allianceBuilding = AllianceBuilding::getById($allianceBuildingId); // Assumes getById exists

        if (!$allianceBuilding || $allianceBuilding->alliance_id !== $player->alliance_id) {
            $this->setFlashMessage('error', 'Ungültiges Allianzgebäude oder gehört nicht zu deiner Allianz.');
            $this->redirect('/alliance');
        }

        $alliance = Alliance::findById($player->alliance_id);
        if (!$alliance) {
            $this->setFlashMessage('error', 'Allianz nicht gefunden.');
            $this->redirect('/alliance'); // Should not happen
        }

        $buildingType = AllianceBuildingType::getById($allianceBuilding->building_type_id);
        if (!$buildingType) {
            $this->setFlashMessage('error', 'Unbekannter Gebäudetyp.');
            $this->redirect('/alliance'); // Should not happen
        }

        $currentLevel = $allianceBuilding->level;
        $targetLevel = $currentLevel + 1;

        if ($buildingType->max_level !== null && $targetLevel > $buildingType->max_level) {
            $this->setFlashMessage('info', "'{$buildingType->name}' hat bereits die maximale Stufe erreicht.");
            $this->redirect('/alliance');
        }

        // Check requirements for the next level
        if ($buildingType->requirements_json) {
            $requirements = json_decode($buildingType->requirements_json, true);
            // Simplified: For upgrade, requirements usually apply to the *target* level
            // This might need adjustment based on how AllianceBuildingType stores requirements (per level or general)
            if (isset($requirements['alliance_research'])) {
                $allianceResearchLevels = AllianceResearch::getAllForAlliance($alliance->id);
                $allianceResearchMap = [];
                foreach ($allianceResearchLevels as $research) {
                    $allianceResearchMap[$research->research_type_id] = $research->level;
                }
                // Assuming requirements are for the level being built (targetLevel)
                // If requirements are for *current* level to allow upgrade, adjust logic
                foreach ($requirements['alliance_research'] as $internalName => $levelRequired) {
                    // This check might be for the *current* building level or *target* level
                    // For now, assume it's for the target level, meaning research must be met *before* upgrade starts.
                    // If it means "to unlock level X, you need research Y", then this is fine.
                    $requiredResearchType = AllianceResearchType::getByInternalName($internalName);
                    if ($requiredResearchType) {
                        $currentResearchLevel = $allianceResearchMap[$requiredResearchType->id] ?? 0;
                        // Example: To build level 5, need research X at level 3.
                        // If $buildingType->getRequirementForLevel($targetLevel) exists, use that.
                        // For now, assume $levelRequired is for the $targetLevel.
                        if ($currentResearchLevel < $levelRequired) {
                            $this->setFlashMessage('error', "Voraussetzung für Stufe {$targetLevel} nicht erfüllt: Allianzforschung '{$requiredResearchType->name}' Stufe {$levelRequired} benötigt.");
                            $this->redirect('/alliance');
                        }
                    }
                }
            }
            // Player building requirements are not typically applicable for alliance building upgrades.
            // If there's a specific scenario, it needs to be defined.
        }

        $cost = $buildingType->getCostAtLevel($currentLevel); // Cost to upgrade *from* currentLevel to targetLevel

        if (!$alliance->hasEnoughResources($cost)) {
            $this->setFlashMessage('error', 'Nicht genügend Ressourcen in der Allianzkasse für den Ausbau.');
            $this->redirect('/alliance');
        }

        $researchTimeSeconds = $buildingType->getBaseBuildTimeAtLevel($currentLevel);
        $completionTime = date('Y-m-d H:i:s', time() + $researchTimeSeconds);

        // This should create or update an AllianceResearch entry and potentially a queue entry.
        $updated = AllianceBuilding::startUpgrade($allianceBuilding->id, $targetLevel, $completionTime); // Assumes such a method exists

        if ($updated) { // This should reflect if the queue entry was made
            $alliance->deductResources($cost);
            $this->setFlashMessage('success', "Ausbau von '{$buildingType->name}' auf Stufe {$targetLevel} in Auftrag gegeben.");
        } else {
            $this->setFlashMessage('error', 'Fehler beim Starten des Ausbaus.');
        }

        $this->redirect('/alliance');
    }

    public function researchAllianceResearch() {
        if (!isset($_SESSION['user_id']) || !isset($_POST['research_type_id'])) {
            $this->setFlashMessage('error', 'Ungültige Anfrage.');
            $this->redirect('/alliance');
        }

        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);

        if (!$player || !$player->alliance_id || $player->alliance_rank !== 'admin') {
            $this->setFlashMessage('error', 'Nur Allianz-Administratoren können Forschungen starten.');
            $this->redirect('/alliance');
        }

        $alliance = Alliance::findById($player->alliance_id);
        if (!$alliance) {
            $this->setFlashMessage('error', 'Allianz nicht gefunden.');
            $this->redirect('/alliance'); // Should not happen
        }

        $researchTypeId = intval($_POST['research_type_id']);
        $researchType = AllianceResearchType::getById($researchTypeId); // Assumes getById exists

        if (!$researchType) {
            $this->setFlashMessage('error', 'Ungültiger Forschungstyp.');
            $this->redirect('/alliance');
        }

        $allianceResearch = AllianceResearch::getByAllianceAndType($alliance->id, $researchTypeId);
        $currentLevel = $allianceResearch ? $allianceResearch->level : 0;
        $targetLevel = $currentLevel + 1;

        // Check requirements for the next level
        if ($researchType->requirements_json) {
            $requirements = json_decode($researchType->requirements_json, true);

            if (isset($requirements['alliance_building'])) {
                $allianceBuildingLevels = AllianceBuilding::getBuildingLevelsForAlliance($alliance->id); // Needs this helper
                foreach ($requirements['alliance_building'] as $internalName => $levelRequired) {
                    $requiredBuildingType = AllianceBuildingType::getByInternalName($internalName);
                    if ($requiredBuildingType) {
                        $currentBuildingLevel = $allianceBuildingLevels[$requiredBuildingType->id] ?? 0;
                        if ($currentBuildingLevel < $levelRequired) {
                            $this->setFlashMessage('error', "Voraussetzung für '{$researchType->name}' Stufe {$targetLevel} nicht erfüllt: Allianzgebäude '{$requiredBuildingType->name}' Stufe {$levelRequired} benötigt.");
                            $this->redirect('/alliance');
                        }
                    }
                }
            }

            if (isset($requirements['alliance_research'])) {
                $allianceResearchLevels = AllianceResearch::getAllForAlliance($alliance->id);
                $allianceResearchMap = [];
                foreach ($allianceResearchLevels as $research) {
                    $allianceResearchMap[$research->research_type_id] = $research->level;
                }
                foreach ($requirements['alliance_research'] as $internalName => $levelRequired) {
                    $requiredResearchType = AllianceResearchType::getByInternalName($internalName);
                    if ($requiredResearchType) {
                        $currentResearchLevel = $allianceResearchMap[$requiredResearchType->id] ?? 0;
                        if ($currentResearchLevel < $levelRequired) {
                            $this->setFlashMessage('error', "Voraussetzung für '{$researchType->name}' Stufe {$targetLevel} nicht erfüllt: Allianzforschung '{$requiredResearchType->name}' Stufe {$levelRequired} benötigt.");
                            $this->redirect('/alliance');
                        }
                    }
                }
            }
        }

        $cost = $researchType->getCostAtLevel($currentLevel); // Cost to research *from* currentLevel to targetLevel

        if (!$alliance->hasEnoughResources($cost)) {
            $this->setFlashMessage('error', 'Nicht genügend Ressourcen in der Allianzkasse für diese Forschung.');
            $this->redirect('/alliance');
        }

        $researchTimeSeconds = $researchType->getResearchTimeAtLevel($currentLevel);
        $completionTime = date('Y-m-d H:i:s', time() + $researchTimeSeconds);

        // This should create or update an AllianceResearch entry and potentially a queue entry.
        $started = AllianceResearch::startResearch($alliance->id, $researchTypeId, $targetLevel, $completionTime); // Assumes such a method exists

        if ($started) { // This should reflect if the queue entry was made / research started
            $alliance->deductResources($cost);
            $this->setFlashMessage('success', "Forschung '{$researchType->name}' auf Stufe {$targetLevel} in Auftrag gegeben.");
        } else {
            $this->setFlashMessage('error', 'Fehler beim Starten der Forschung.');
        }

        $this->redirect('/alliance');
    }

    /**
     * Handles the payout of resources from the Alliance Treasury to a member's planet.
     */
    public function payoutTreasury() {
        if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->setFlashMessage('error', 'Ungültige Anfrage.');
            $this->redirect('/alliance');
        }

        $playerId = $_SESSION['user_id'];
        $player = Player::findById($playerId);

        // Check if player is an Alliance Admin
        if (!$player || !$player->alliance_id || $player->alliance_rank !== 'admin') {
            $this->setFlashMessage('error', 'Nur Allianz-Administratoren können Ressourcen auszahlen.');
            $this->redirect('/alliance');
        }

        $alliance = Alliance::findById($player->alliance_id);
        if (!$alliance) {
            $this->setFlashMessage('error', 'Allianz nicht gefunden.');
            $this->redirect('/alliance'); // Should not happen
        }

        $targetPlayerId = intval($_POST['target_player_id'] ?? 0);
        $targetPlanetId = intval($_POST['target_planet_id'] ?? 0);
        $resourcesToPayout = $_POST['resources'] ?? []; // Expected format: ['resource_type' => quantity]

        // Validate target player and planet
        $targetPlayer = Player::findById($targetPlayerId);
        $targetPlanet = Planet::getById($targetPlanetId);

        if (!$targetPlayer || $targetPlayer->alliance_id !== $alliance->id) {
            $this->setFlashMessage('error', 'Ungültiger Zielspieler oder Spieler ist kein Mitglied deiner Allianz.');
            $this->redirect('/alliance');
        }

        if (!$targetPlanet || $targetPlanet->player_id !== $targetPlayer->id) {
            $this->setFlashMessage('error', 'Ungültiger Zielplanet oder Planet gehört nicht dem Zielspieler.');
            $this->redirect('/alliance');
        }

        // Validate resources to payout
        $validResourceTypes = ['eisen', 'silber', 'uderon', 'wasserstoff', 'energie'];
        $totalPayoutAmount = 0;
        $payoutDetails = [];

        foreach ($resourcesToPayout as $resType => $quantity) {
            if (!in_array($resType, $validResourceTypes) || !is_numeric($quantity) || $quantity <= 0) {
                $this->setFlashMessage('error', "Ungültige Ressource oder Menge für Auszahlung: {$resType}.");
                $this->redirect('/alliance');
            }
            $resourcesToPayout[$resType] = floatval($quantity); // Ensure float
            $totalPayoutAmount += $resourcesToPayout[$resType];
            $payoutDetails[] = ucfirst($resType) . ": " . number_format($resourcesToPayout[$resType]);
        }

        if ($totalPayoutAmount <= 0) {
            $this->setFlashMessage('error', 'Keine gültigen Ressourcen zur Auszahlung angegeben.');
            $this->redirect('/alliance');
        }

        // Check if alliance treasury has enough resources
        $hasEnough = true;
        foreach ($resourcesToPayout as $resType => $quantity) {
            if ($alliance->$resType < $quantity) {
                $hasEnough = false;
                $this->setFlashMessage('error', "Nicht genügend {$resType} in der Allianzkasse.");
                $this->redirect('/alliance');
                return;
            }
        }

        // Check planet requirements for payout (Mines level 20)
        $mineTypes = ['eisenmine', 'silbermine', 'uderon_raffinerie', 'wasserstoff_raffinerie', 'fusionskraftwerk'];
        $allMinesLevel20 = true;
        $missingMines = [];

        foreach ($mineTypes as $mineInternalName) {
            $mineType = BuildingType::getByInternalName($mineInternalName);
            if ($mineType) {
                $playerBuilding = PlayerBuilding::getByPlanetAndType($targetPlanetId, $mineType->id);
                if (!$playerBuilding || $playerBuilding->level < 20) {
                    $allMinesLevel20 = false;
                    $missingMines[] = $mineType->name_de;
                }
            } else {
                 // Log error if building type not found, but don't block payout if it's a data issue
                 error_log("Building type not found for mine check: {$mineInternalName}");
            }
        }

        if (!$allMinesLevel20) {
            $this->setFlashMessage('error', "Auszahlung nicht möglich: Alle Minen auf dem Zielplaneten müssen mindestens Stufe 20 sein. Fehlend: " . implode(', ', $missingMines));
            $this->redirect('/alliance');
            return;
        }

        // Calculate maximum allowable payout percentage (1% or 3%)
        $maxPayoutPercentage = 0.01; // 1% everywhere

        // Check for Alliance Trade Center in the target galaxy for 3% payout
        $tradeCenterType = AllianceBuildingType::getByInternalName('allianz_handelszentrum'); // Assuming internal name
        if ($tradeCenterType) {
            // Find if there's an Alliance Trade Center owned by this alliance in the target planet's galaxy
            $solarSystem = SolarSystem::getByGalaxyAndSystem($targetPlanet->galaxy, $targetPlanet->system);
            if ($solarSystem) {
                 $tradeCenter = AllianceBuilding::getByAllianceAndSystemAndType($alliance->id, $solarSystem->id, $tradeCenterType->id);
                 if ($tradeCenter && $tradeCenter->level > 0) {
                     $maxPayoutPercentage = 0.03; // 3% if Trade Center exists in galaxy
                 }
            }
        }

        // Check if the requested payout amount exceeds the maximum allowable percentage
        $canPayout = true;
        $exceededResources = [];
        foreach ($resourcesToPayout as $resType => $quantity) {
            $maxAllowedAmount = floor($alliance->$resType * $maxPayoutPercentage);
            if ($quantity > $maxAllowedAmount) {
                $canPayout = false;
                $exceededResources[] = ucfirst($resType) . " (Max: " . number_format($maxAllowedAmount) . ")";
            }
        }

        if (!$canPayout) {
            $this->setFlashMessage('error', "Auszahlung übersteigt das maximale Limit von " . ($maxPayoutPercentage * 100) . "% der Allianzkasse. Betroffen: " . implode(', ', $exceededResources));
            $this->redirect('/alliance');
            return;
        }

        // Process payout
        $this->db->beginTransaction();
        try {
            // Deduct from alliance treasury
            foreach ($resourcesToPayout as $resType => $quantity) {
                if ($quantity > 0) {
                    $alliance->deductResourcesFromTreasury($resType, $quantity);
                }
            }

            // Add to target planet
            $sql = "UPDATE planets SET ";
            $updates = [];
            $params = [':planet_id' => $targetPlanetId];

            foreach ($resourcesToPayout as $resType => $quantity) {
                if ($quantity > 0) {
                    $updates[] = "{$resType} = {$resType} + :{$resType}_quantity";
                    $params[":{$resType}_quantity"] = $quantity;
                }
            }

            if (!empty($updates)) {
                $sql .= implode(', ', $updates) . " WHERE id = :planet_id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();

            // Send notifications
            $payoutSummary = implode(', ', $payoutDetails);
            $adminMessage = "Du hast erfolgreich {$payoutSummary} aus der Allianzkasse an Spieler {$targetPlayer->username} auf Planet {$targetPlanet->name} ausgezahlt.";
            NotificationService::createNotification($playerId, 'Allianz Auszahlung Erfolgreich', $adminMessage, 'success');

            $memberMessage = "Deine Allianz '{$alliance->name}' hat {$payoutSummary} auf deinen Planet {$targetPlanet->name} ausgezahlt.";
            NotificationService::createNotification($targetPlayerId, 'Allianz Auszahlung Erhalten', $memberMessage, 'info');


            $this->setFlashMessage('success', "Ressourcen erfolgreich ausgezahlt: {$payoutSummary}.");
            $this->redirect('/alliance');

        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Alliance treasury payout error: " . $e->getMessage());
            $this->setFlashMessage('error', 'Fehler bei der Auszahlung aus der Allianzkasse: ' . $e->getMessage());
            $this->redirect('/alliance');
        }
    }
}
?>
