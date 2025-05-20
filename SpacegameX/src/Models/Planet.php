<?php
namespace Models;

use Core\Model;
use PDO;

use Models\Player;
use Models\PlayerResearch;
use Models\AllianceResearch;
use Models\ResearchType;
use Models\AllianceResearchType;
use Models\PlayerBuilding;
use Models\BuildingType;
use Models\Alliance; // Added for diplomacy check
use Services\NotificationService; // Added for notifications


class Planet extends Model {
    public $id;
    public $player_id;
    public $name;
    public $galaxy;
    public $system;
    public $position;
    public $is_capital;
    public $diameter;
    public $temperature_min;
    public $temperature_max;
    public $last_resource_update;
    public $eisen;
    public $silber;
    public $uderon;
    public $wasserstoff;
    public $energie;
    public $eisen_bonus;
    public $silber_bonus;
    public $uderon_bonus;
    public $wasserstoff_bonus;
    public $relative_speed; // Relative speed factor (90% - 110%), affects building/research time
    public $asteroid_count; // Number of asteroids on the planet
    public $shield_active_until; // When the planetary shield is active until (timestamp)
    public $last_attacked; // When the planet was last attacked (timestamp)

    public function updateResourceBonus() {
        // Parse asteroid data
        $asteroidData = json_decode($this->asteroid_data, true);
        
        if (!is_array($asteroidData) || empty($asteroidData)) {
            // Invalid or empty data, reset bonuses
            $sql = "UPDATE planets SET
                    asteroid_metal_bonus = 0,
                    asteroid_crystal_bonus = 0, 
                    asteroid_uderon_bonus = 0
                    WHERE id = :planet_id";
                    
            $stmt = self::getDB()->prepare($sql); // Changed to self::getDB()
            $stmt->bindParam(':planet_id', $this->id, PDO::PARAM_INT);
            return $stmt->execute();
        }
        
        // Calculate bonuses from active asteroids
        $bonusPercentage = [
            'metal' => 0,
            'crystal' => 0,
            'h2' => 0
        ];
        
        // Calculate bonus from asteroids
        if (!empty($this->asteroid_data)) {
            $asteroids = json_decode($this->asteroid_data, true);
            if (is_array($asteroids)) {
                foreach ($asteroids as $asteroid) {
                    // Asteroids no longer expire, so remove end_time check
                    if (isset($asteroid['type'])) {
                        switch ($asteroid['type']) {
                            case 'iron': // Iron asteroids boost metal production
                                $bonusPercentage['metal'] += 0.30;
                                break;
                            case 'silver': // Silver asteroids boost crystal production
                                $bonusPercentage['crystal'] += 0.30;
                                break;
                            case 'uderon': // Uderon asteroids boost h2 production
                                $bonusPercentage['h2'] += 0.30;
                                break;
                            case 'crystal':
                                $bonusPercentage['crystal'] += 0.30;
                                break;
                        }
                    }
                }
            }
        }

        $this->metal_bonus = $bonusPercentage['metal'];
        $this->crystal_bonus = $bonusPercentage['crystal'];
        $this->uderon_bonus = $bonusPercentage['h2']; // Corrected to uderon_bonus and h2

        // Update the database
        $sql = "UPDATE planets SET 
                eisen_bonus = :metal_bonus, 
                silber_bonus = :crystal_bonus, 
                uderon_bonus = :uderon_bonus 
                WHERE id = :planet_id";
        $stmt = self::getDB()->prepare($sql); // Changed to self::getDB()
        $stmt->bindParam(':metal_bonus', $this->metal_bonus);
        $stmt->bindParam(':crystal_bonus', $this->crystal_bonus);
        $stmt->bindParam(':uderon_bonus', $this->uderon_bonus);
        $stmt->bindParam(':planet_id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Checks if the planet is currently under an active blockade and returns blockade details.
     *
     * @return array|false False if not blockaded, otherwise an array with [blockade_player_id, blockade_alliance_id, blockading_fleet_id, blockade_strength, blockade_until].
     */
    public function getActiveBlockadeDetails()
    {
        $db = self::getDB();
        $stmt = $db->prepare(
            'SELECT p.blockade_player_id, pl.alliance_id as blockade_alliance_id, p.blockading_fleet_id, p.blockade_strength, p.blockade_until '
            . 'FROM planets p '
            . 'LEFT JOIN players pl ON p.blockade_player_id = pl.id '
            . 'WHERE p.id = :planet_id AND p.blockade_until IS NOT NULL AND p.blockade_until > NOW()'
        );
        $stmt->bindParam(':planet_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();
        $blockadeDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($blockadeDetails && $blockadeDetails['blockade_player_id']) {
            return $blockadeDetails;
        }
        return false;
    }

    public static function createHomePlanetForPlayer($playerId) {
        // Find a free coordinate in the expanded galaxy system
        $galaxy = rand(1, \Lib\GalaxyGenerator::GALAXY_COUNT);
        $system = rand(1, \Lib\GalaxyGenerator::SYSTEMS_PER_GALAXY);
        $name = 'Heimatplanet';
        $eisen = INITIAL_EISEN;
        $silber = INITIAL_SILBER;
        $uderon = INITIAL_UDERON;
        $wasserstoff = INITIAL_WASSERSTOFF;
        $energie = INITIAL_ENERGIE;
        
        $db = self::getDB();
        
        // Make sure system exists
        $solarSystem = \Lib\GalaxyGenerator::getOrCreateSystem($galaxy, $system);
        $planetCount = $solarSystem['planet_count'];
        $systemType = $solarSystem['system_type'];
        
        // Find a free position in the system
        $availablePositions = [];
        for ($p = 1; $p <= $planetCount; $p++) {
            $stmt = $db->prepare('SELECT COUNT(*) FROM planets WHERE galaxy = :g AND system = :s AND position = :p');
            $stmt->execute([':g' => $galaxy, ':s' => $system, ':p' => $p]);
            if ($stmt->fetchColumn() == 0) {
                $availablePositions[] = $p;
            }
        }
                        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public static function getHomePlanetByPlayerId($playerId) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM planets WHERE player_id = :player_id AND is_capital = 1 LIMIT 1');
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    public static function getPlanetsByPlayerId($playerId) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM planets WHERE player_id = :player_id ORDER BY galaxy, system, position');
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }
    
    // Calculate and update resources for a planet
    public static function updateResources($planetId) {
        $db = self::getDB();
        
        // Get planet data
        $stmt = $db->prepare('SELECT * FROM planets WHERE id = :id');
        $stmt->bindParam(':id', $planetId, PDO::PARAM_INT);
        $stmt->execute();
        $planet = $stmt->fetchObject(get_called_class());
        
        if (!$planet) return false;

        // Get player data to check for alliance and war status
        $player = Player::findById($planet->player_id);
        $allianceId = $player ? $player->alliance_id : null;
        $isInWar = false;

        if ($allianceId) {
            $alliance = Alliance::findById($allianceId);
            if ($alliance) {
                $diplomacy = $alliance->getDiplomacy();
                foreach ($diplomacy as $rel) {
                    if ($rel['type'] === 'kriegserklärung' && (strtotime($rel['end_date']) > time() || $rel['end_date'] === null)) {
                        $isInWar = true;
                        break;
                    }
                }
            }
        }


        // Get all buildings on this planet
        $buildings = PlayerBuilding::getAllForPlanet($planetId);

        // Get player's individual research levels
        $playerResearchLevels = PlayerResearch::getResearchLevelsByPlayerId($planet->player_id);

        // Get alliance research levels (if in an alliance)
        $allianceResearchLevels = [];
        if ($allianceId) {
            $allianceResearch = AllianceResearch::getAllForAlliance($allianceId);
            foreach ($allianceResearch as $research) {
                $allianceResearchLevels[$research->internal_name] = $research->level;
            }
        }

        // Get total number of planets for the player to calculate Verwaltungskosten
        $allPlayerPlanets = self::getPlanetsByPlayerId($planet->player_id);
        $totalPlanets = count($allPlayerPlanets);

        // Calculate resource production per hour
        $production = [
            'eisen' => 0,
            'silber' => 0,
            'uderon' => 0,
            'wasserstoff' => 0,
            'energie_production' => 0,
            'energie_consumption' => 0
        ];

        // Get the number of Asteroids on this planet
        $asteroidCount = 0;
        foreach ($buildings as $building) {
            if ($building->internal_name === 'asteroid' ) { // Assuming 'asteroid' is the internal name for Asteroids
                $asteroidCount = $building->level; // Assuming level represents quantity for Asteroids
                break;
            }
        }
          // Get planet's resource bonuses (default to 1.0 if not set)
        $eisenBonus = isset($planet->eisen_bonus) ? $planet->eisen_bonus : 1.0;
        $silberBonus = isset($planet->silber_bonus) ? $planet->silber_bonus : 1.0;
        $uderonBonus = isset($planet->uderon_bonus) ? $planet->uderon_bonus : 1.0;
        $wasserstoffBonus = isset($planet->wasserstoff_bonus) ? $planet->wasserstoff_bonus : 1.0;
        
        // Get asteroid bonuses (if any)
        $asteroidMetalBonus = isset($planet->asteroid_metal_bonus) ? $planet->asteroid_metal_bonus / 100 : 0;
        $asteroidCrystalBonus = isset($planet->asteroid_crystal_bonus) ? $planet->asteroid_crystal_bonus / 100 : 0;
        $asteroidUderonBonus = isset($planet->asteroid_uderon_bonus) ? $planet->asteroid_uderon_bonus / 100 : 0;
        
        // Apply asteroid bonuses to base resource bonuses
        $eisenBonus *= (1 + $asteroidMetalBonus);
        $silberBonus *= (1 + $asteroidCrystalBonus);
        $uderonBonus *= (1 + $asteroidUderonBonus);
        
        // Base production (adjusted by planet bonuses)
        $production['eisen'] += 20 * $eisenBonus; // Base eisen production
        $production['silber'] += 10 * $silberBonus; // Base silber production
        
        // Calculate production from buildings on this planet
        foreach ($buildings as $building) {
            // Skip buildings that aren't built yet or are special types handled separately
            if ($building->level <= 0 || $building->internal_name === 'asteroid' || $building->internal_name === 'sonnenkollektor') continue;

            // Eisen Mine (with planet bonus and individual/alliance research)
            if ($building->internal_name === 'eisenmine') {
                $individualResearchLevel = $playerResearchLevels[ResearchType::getByInternalName('eisenfoerderung')->id] ?? 0;
                $allianceResearchLevel = $allianceResearchLevels['allianz_eisenfoerderung'] ?? 0; // Assuming internal name
                $researchBonusFactor = 1 + ($individualResearchLevel * 0.05) + ($allianceResearchLevel * 0.02);
                $production['eisen'] += (30 * $building->level * pow(1.1, $building->level)) * $eisenBonus * $researchBonusFactor;
                $production['energie_consumption'] += 10 * $building->level * pow(1.1, $building->level);
            }
            // Silber Mine (with planet bonus and individual/alliance research)
            else if ($building->internal_name === 'silbermine') {
                $individualResearchLevel = $playerResearchLevels[ResearchType::getByInternalName('silberfoerderung')->id] ?? 0;
                $allianceResearchLevel = $allianceResearchLevels['allianz_silberfoerderung'] ?? 0; // Assuming internal name
                $researchBonusFactor = 1 + ($individualResearchLevel * 0.05) + ($allianceResearchLevel * 0.02);
                $production['silber'] += (20 * $building->level * pow(1.1, $building->level)) * $silberBonus * $researchBonusFactor;
                $production['energie_consumption'] += 10 * $building->level * pow(1.1, $building->level);
            }
            // Uderon-Raffinerie (with planet bonus and individual/alliance research)
            else if ($building->internal_name === 'uderon_raffinerie') {
                $individualResearchLevel = $playerResearchLevels[ResearchType::getByInternalName('uderonproduktion')->id] ?? 0;
                $allianceResearchLevel = $allianceResearchLevels['allianz_uderonproduktion'] ?? 0; // Assuming internal name
                $researchBonusFactor = 1 + ($individualResearchLevel * 0.05) + ($allianceResearchLevel * 0.02);
                $production['uderon'] += (8 * $building->level * pow(1.1, $building->level)) * $uderonBonus * $researchBonusFactor;
                $production['energie_consumption'] += 15 * $building->level * pow(1.1, $building->level);
            }
            // Wasserstoff-Raffinerie (with planet bonus and individual/alliance research)
            else if ($building->internal_name === 'wasserstoff_raffinerie') {
                $individualResearchLevel = $playerResearchLevels[ResearchType::getByInternalName('h2_foerderung')->id] ?? 0;
                $allianceResearchLevel = $allianceResearchLevels['allianz_h2_foerderung'] ?? 0; // Assuming internal name
                $researchBonusFactor = 1 + ($individualResearchLevel * 0.05) + ($allianceResearchLevel * 0.02);
                $production['wasserstoff'] += (10 * $building->level * pow(1.1, $building->level)) * $wasserstoffBonus * $researchBonusFactor;
                $production['energie_consumption'] += 20 * $building->level * pow(1.1, $building->level);
            }
            // Fusionskraftwerk (with alliance energy research)
            else if ($building->internal_name === 'fusionskraftwerk') {
                $allianceResearchLevel = $allianceResearchLevels['allianz_energiefoerderung'] ?? 0; // Assuming internal name
                $researchBonusFactor = 1 + ($allianceResearchLevel * 0.01);
                $production['energie_production'] += (50 * $building->level * pow(1.1, $building->level)) * $researchBonusFactor;
                $production['wasserstoff'] -= 10 * $building->level; // Fusion uses wasserstoff
            }
        }

        // Apply Asteroid bonus (30% per Asteroid to Eisen, Silber, Uderon, Wasserstoff production)
        $asteroidBonusFactor = 1 + ($asteroidCount * 0.30);
        $production['eisen'] *= $asteroidBonusFactor;
        $production['silber'] *= $asteroidBonusFactor;
        $production['uderon'] *= $asteroidBonusFactor;
        $production['wasserstoff'] *= $asteroidBonusFactor;
        $production['energie_production'] *= $asteroidBonusFactor; // Added for NRG-Ertrag

        // Calculate system-wide Sonnenkollektor energy bonus
        $sonnenkollektorBonus = 0;
        $sonnenkollektorType = BuildingType::getByInternalName('sonnenkollektor'); // Assuming internal name
        if ($sonnenkollektorType) {
             // Get total Sonnenkollektor level in the system
             $stmt = $db->prepare("SELECT SUM(pb.level) FROM player_buildings pb
                                 JOIN planets p ON pb.planet_id = p.id
                                 WHERE p.galaxy = :galaxy AND p.system = :system
                                 AND pb.building_type_id = :sonnenkollektor_type_id");
             $stmt->bindParam(':galaxy', $planet->galaxy, PDO::PARAM_INT);
             $stmt->bindParam(':system', $planet->system, PDO::PARAM_INT);
             $stmt->bindParam(':sonnenkollektor_type_id', $sonnenkollektorType->id, PDO::PARAM_INT);
             $stmt->execute();
             $totalSystemSonnenkollektors = $stmt->fetchColumn() ?: 0;

             // Calculate bonus based on documentation: 2.319% per Sonnenkollektor level in the system
             $sonnenkollektorBonusPercentage = $totalSystemSonnenkollektors * 2.319; // Total percentage increase from SKs in the system

             // Apply Sonnenkollektor bonus to energy production
             $production['energie_production'] *= (1 + ($sonnenkollektorBonusPercentage / 100));
        }


        // Calculate energy balance and apply production penalties if negative
        $energieBalance = $production['energie_production'] - $production['energie_consumption'];
        $energieFactor = 1.0; // No penalty by default

        if ($energieBalance < 0) {
            // Production reduced based on energy shortage
            // The penalty should only apply to resource mines, not energy production itself.
            $energieFactor = max(0, $production['energie_production']) / $production['energie_consumption']; // Ensure factor is not negative
            // Reduce production by energy factor
            $production['eisen'] *= $energieFactor;
            $production['silber'] *= $energieFactor;
            $production['uderon'] *= $energieFactor;
            $production['wasserstoff'] *= $energieFactor;
        }

        // Calculate Verwaltungskosten penalty
        // Formula: (Number of Planets / 87) * 1%
        $verwaltungskostenPenalty = ($totalPlanets / 87) * 0.01;
        // Ensure penalty does not exceed 100%
        $verwaltungskostenPenalty = min(1.0, $verwaltungskostenPenalty);        // Apply Verwaltungskosten penalty to all resource production (Eisen, Silber, Uderon, Wasserstoff, but NOT Energy)
        $production['eisen'] *= (1 - $verwaltungskostenPenalty);
        $production['silber'] *= (1 - $verwaltungskostenPenalty);
        $production['uderon'] *= (1 - $verwaltungskostenPenalty);
        $production['wasserstoff'] *= (1 - $verwaltungskostenPenalty);
        
        // Apply capital planet bonus if this is the capital
        if ($planet->is_capital) {
            $capitalProductionBonus = 0.20; // 20% production bonus for capital
            $production['eisen'] *= (1 + $capitalProductionBonus);
            $production['silber'] *= (1 + $capitalProductionBonus);
            $production['uderon'] *= (1 + $capitalProductionBonus);
            $production['wasserstoff'] *= (1 + $capitalProductionBonus);
            $production['energie_production'] *= (1 + $capitalProductionBonus);
        }

        // Apply Kriegserklärung penalty (3% reduction on resource mines if in war)
        if ($isInWar) {
            $warPenalty = 0.97; // 3% reduction
            $production['eisen'] *= $warPenalty;
            $production['silber'] *= $warPenalty;
            $production['uderon'] *= $warPenalty;
            $production['wasserstoff'] *= $warPenalty;
            // Note: Energy production is NOT affected by this penalty based on the MD.
        }


        // Calculate time since last update
        $now = time();
        $lastUpdate = strtotime($planet->last_resource_update);
        $seconds = max(0, $now - $lastUpdate);
        $hours = $seconds / 3600; // Convert to hours
        
        // Calculate resources gained
        $eisenGained = $production['eisen'] * $hours;
        $silberGained = $production['silber'] * $hours;
        $uderonGained = $production['uderon'] * $hours;
        $wasserstoffGained = $production['wasserstoff'] * $hours;
        
        // Update resources in the database
        $sql = "UPDATE planets SET 
                eisen = eisen + :eisen,
                silber = silber + :silber, 
                uderon = uderon + :uderon,
                wasserstoff = wasserstoff + :wasserstoff,
                energie = :energie,
                last_resource_update = NOW()
                WHERE id = :id";
                
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':eisen', $eisenGained, PDO::PARAM_STR);
        $stmt->bindParam(':silber', $silberGained, PDO::PARAM_STR);
        $stmt->bindParam(':uderon', $uderonGained, PDO::PARAM_STR);
        $stmt->bindParam(':wasserstoff', $wasserstoffGained, PDO::PARAM_STR);
        $stmt->bindParam(':energie', $energieBalance, PDO::PARAM_STR);
        $stmt->bindParam(':id', $planetId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Return production rates (per hour)
        return [
            'eisen_rate' => $production['eisen'],
            'silber_rate' => $production['silber'],
            'uderon_rate' => $production['uderon'],
            'wasserstoff_rate' => $production['wasserstoff'],
            'energie_production' => $production['energie_production'],
            'energie_consumption' => $production['energie_consumption'],
            'energie_balance' => $energieBalance,
            'energie_factor' => $energieFactor,
            'verwaltungskosten_penalty' => $verwaltungskostenPenalty,
            'is_in_war' => $isInWar // Added to indicate if war penalty was applied
        ];
    }
    
    // Get full planet data with resources and production
    public static function getFullPlanetData($planetId) {
        $db = self::getDB();
        
        // Update resources first
        self::updateResources($planetId);
        
        // Get planet data
        $stmt = $db->prepare('SELECT * FROM planets WHERE id = :id');
        $stmt->bindParam(':id', $planetId, PDO::PARAM_INT);
        $stmt->execute();
        $planet = $stmt->fetchObject(get_called_class());
        
        if (!$planet) return false;
        
        // Get production rates
        $production = self::updateResources($planetId);
        
        // Get buildings
        $buildings = PlayerBuilding::getAllForPlanet($planetId);
        
        // Combine data
        $data = [
            'planet' => $planet,
            'production' => $production,
            'buildings' => $buildings
        ];
        
        return $data;
    }

    /**
     * Instantly transfer resources between two planets owned by the same player using a Transmitter.
     *
     * @param int $sendingPlanetId The ID of the planet sending resources.
     * @param int $receivingPlanetId The ID of the planet receiving resources.
     * @param array $resourcesToTransfer Associative array of resources and quantities to transfer (e.g., ['eisen' => 1000, 'silber' => 500]).
     * @return bool True on success, false on failure.
     */
    public static function transferResourcesInstant($sendingPlanetId, $receivingPlanetId, $resourcesToTransfer) {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            // Get sending and receiving planet data
            $sendingPlanet = self::getById($sendingPlanetId);
            $receivingPlanet = self::getById($receivingPlanetId);

            // Validate planets and ownership
            if (!$sendingPlanet || !$receivingPlanet || $sendingPlanet->player_id !== $receivingPlanet->player_id) {
                throw new \Exception("Invalid planets or planets not owned by the same player.");
            }

            // Check if sending planet has a Transmitter and its level
            $transmitterType = BuildingType::getByInternalName('transmitter'); // Assuming internal name
            $transmitterLevel = 0;
            if ($transmitterType) {
                 $transmitterBuilding = PlayerBuilding::getByPlanetAndType($sendingPlanetId, $transmitterType->id);
                 $transmitterLevel = $transmitterBuilding->level ?? 0;
            }

            if ($transmitterLevel <= 0) {
                throw new \Exception("Sending planet does not have a Transmitter or its level is too low.");
            }

            // Check if sending planet has sufficient resources to transfer
            foreach ($resourcesToTransfer as $resourceType => $quantity) {
                if ($sendingPlanet->$resourceType < $quantity) {
                    throw new \Exception("Not enough {$resourceType} on the sending planet.");
                }
            }

            // Calculate energy cost
            $totalResourceAmount = array_sum($resourcesToTransfer);
            $distance = \Models\Fleet::calculateDistance(
                $sendingPlanet->galaxy, $sendingPlanet->system, $sendingPlanet->position,
                $receivingPlanet->galaxy, $receivingPlanet->system, $receivingPlanet->position
            );

            // Calculate energy cost based on amount and distance, potentially influenced by research
            // Assuming a formula: (Total Resources / Transmitter Level) * Distance * Energy Research Factor
            $energyCost = ($totalResourceAmount / $transmitterLevel) * $distance; // Simplified formula

            $playerResearchLevels = PlayerResearch::getResearchLevelsByPlayerId($sendingPlanet->player_id);
            $energietechnikResearchType = ResearchType::getByInternalName('energietechnik'); // Assuming 'energietechnik' is the internal name
            $energyResearchLevel = 0;
            if ($energietechnikResearchType && isset($playerResearchLevels[$energietechnikResearchType->id])) {
                $energyResearchLevel = $playerResearchLevels[$energietechnikResearchType->id];
            }
            
            // Apply research bonus (e.g., 5% reduction per level)
            // Each level of Energietechnik reduces the energy consumption of the Transmitter by 5%, up to a maximum of 50% reduction.
            $researchBonusPercentage = $energyResearchLevel * 0.05;
            $researchBonusFactor = 1 - min($researchBonusPercentage, 0.50); // Cap reduction at 50%
            
            $energyCost *= $researchBonusFactor;


            // Check if sending planet has enough energy
            if ($sendingPlanet->energie < $energyCost) {
                throw new \Exception("Not enough energy on the sending planet to power the Transmitter.");
            }

            // Deduct resources and energy from the sending planet
            $sql = "UPDATE planets SET ";
            $updates = [];
            $params = [':sending_planet_id' => $sendingPlanetId, ':energy_cost' => $energyCost];

            foreach ($resourcesToTransfer as $resourceType => $quantity) {
                $updates[] = "{$resourceType} = {$resourceType} - :{$resourceType}_quantity";
                $params[":{$resourceType}_quantity"] = $quantity;
            }
            $updates[] = "energie = energie - :energy_cost";

            $sql .= implode(', ', $updates) . " WHERE id = :sending_planet_id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            // Add transferred resources to the receiving planet
            $sql = "UPDATE planets SET ";
            $updates = [];
            $params = [':receiving_planet_id' => $receivingPlanetId];

            foreach ($resourcesToTransfer as $resourceType => $quantity) {
                if ($quantity > 0) {
                    $updates[] = "{$resourceType} = {$resType} + :{$resType}_quantity";
                    $params[":{$resType}_quantity"] = $quantity;
                }
            }

            if (!empty($updates)) {
                $sql .= implode(', ', $updates) . " WHERE id = :receiving_planet_id";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Transmitter transfer error: " . $e->getMessage());
            return false;
        }
    }

    // Get full energy production and consumption details for a planet
    public static function getEnergyDetails($planetId) { // Added static keyword and $planetId parameter
        // First, update resources to ensure energy calculations are current
        $production = self::updateResources($planetId);
        
        if (!$production) {
            return false;
        }
        
        // Get planet object for additional information
        $planet = self::getById($planetId);
        
        // Prepare energy details array
        $energyDetails = [
            'production' => round($production['energie_production']),
            'consumption' => round($production['energie_consumption']),
            'balance' => round($production['energie_balance']),
            'factor' => $production['energie_factor'],
            'current_amount' => round($planet->energie)
        ];
        
        // Get individual building energy contributions
        $db = self::getDB();
        $buildingContributions = [];
        
        $buildings = PlayerBuilding::getAllForPlanet($planetId);
        foreach ($buildings as $building) {
            if ($building->level <= 0) continue;
            
            $contribution = [
                'name' => $building->name_de,
                'production' => 0,
                'consumption' => 0
            ];
            
            // Determine energy production/consumption based on building type
            switch ($building->internal_name) {
                case 'solar_plant':
                    $contribution['production'] = round(20 * $building->level * pow(1.1, $building->level));
                    break;
                case 'fusionskraftwerk':
                    $contribution['production'] = round(50 * $building->level * pow(1.1, $building->level));
                    // Consumption of Wasserstoff is handled separately in Planet::updateResources
                    break;
                case 'eisenmine':
                    $contribution['consumption'] = round(10 * $building->level * pow(1.1, $building->level));
                    break;
                case 'silbermine':
                    $contribution['consumption'] = round(10 * $building->level * pow(1.1, $building->level));
                    break;
                case 'uderon_raffinerie':
                    $contribution['consumption'] = round(15 * $building->level * pow(1.1, $building->level));
                    break;
                case 'wasserstoff_raffinerie':
                    $contribution['consumption'] = round(20 * $building->level * pow(1.1, $building->level));
                    break;
                // Add other buildings that produce/consume energy as needed
            }
            
            if ($contribution['production'] > 0 || $contribution['consumption'] > 0) {
                $buildingContributions[] = $contribution;
            }
        }
        
        $energyDetails['building_contributions'] = $buildingContributions;
        
        return $energyDetails;
    }

    /**
     * Create a new colony planet when a player uses a colonization ship
     *
     * @param int $playerId The ID of the player
     * @param int $galaxy Galaxy coordinate
     * @param int $system System coordinate
     * @param int $position Position coordinate
     * @param string $name Optional name for the planet
     * @return int|false The new planet ID or false on failure
     */
    public static function createPlanet($playerId, $galaxy, $system, $position, $name = 'Kolonie') {
        $db = self::getDB();
        
        // Check if player has enough colonial administration research level
        $kolonialverwaltungType = ResearchType::getByInternalName('kolonialverwaltung');
        if ($kolonialverwaltungType) {
            $kolonialverwaltungLevel = PlayerResearch::getByPlayerAndType($playerId, $kolonialverwaltungType->id);
            $maxAllowablePlanets = self::getMaxPlanetsForKVLevel($kolonialverwaltungLevel ? $kolonialverwaltungLevel->level : 0);
            
            // Count existing planets
            $stmt = $db->prepare('SELECT COUNT(*) FROM planets WHERE player_id = :player_id');
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->execute();
            $currentPlanetCount = $stmt->fetchColumn();
            
            if ($currentPlanetCount >= $maxAllowablePlanets + 1) { // +1 for home planet
                return false; // Cannot colonize more planets with current KV level
            }
        }
        
        // Check if position is already occupied
        $stmt = $db->prepare('SELECT COUNT(*) FROM planets WHERE galaxy = :galaxy AND system = :system AND position = :position');
        $stmt->bindParam(':galaxy', $galaxy, PDO::PARAM_INT);
        $stmt->bindParam(':system', $system, PDO::PARAM_INT);
        $stmt->bindParam(':position', $position, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            return false; // Position already occupied
        }
        
        // Make sure system exists
        $solarSystem = \Lib\GalaxyGenerator::getOrCreateSystem($galaxy, $system);
        $planetCount = $solarSystem['planet_count'];
        $systemType = $solarSystem['system_type'];
        
        // Check if the requested position is valid for this system
        if ($position <= 0 || $position > $planetCount) {
            return false;
        }
        
        // Generate planet characteristics
        $planetType = 'balanced'; // Default type for new colonies
        $bonuses = \Lib\GalaxyGenerator::PLANET_TYPES[$planetType];
        $diameter = rand(8000, 15000); // km
        
        // Determine temperature based on position
        if ($position <= 3) {
            $temperature_min = rand(10, 30);
            $temperature_max = $temperature_min + rand(30, 60);
            $wasserstoffBonus = $bonuses['h2'] * 1.2;
            $eisenBonus = $bonuses['metal'] * 0.9;
            $silberBonus = $bonuses['crystal'];
            $uderonBonus = $bonuses['uderon'] ?? 1.0; // Ensure uderon bonus is set
        } 
        elseif ($position >= \Lib\GalaxyGenerator::MAX_PLANETS_PER_SYSTEM - 2) {
            $temperature_min = rand(-50, -30);
            $temperature_max = $temperature_min + rand(30, 60);
            $eisenBonus = $bonuses['metal'] * 1.2;
            $wasserstoffBonus = $bonuses['h2'] * 0.9;
            $silberBonus = $bonuses['crystal'];
            $uderonBonus = $bonuses['uderon'] ?? 1.0; // Ensure uderon bonus is set
        } 
        else {
            $temperature_min = rand(-20, 10);
            $temperature_max = $temperature_min + rand(30, 60);
            $eisenBonus = $bonuses['metal'];
            $silberBonus = $bonuses['crystal'];
            $wasserstoffBonus = $bonuses['h2'];
            $uderonBonus = $bonuses['uderon'] ?? 1.0; // Ensure uderon bonus is set
        }
        
        // Generate relative speed (90% - 110%) - lower is better
        $relativeSpeed = rand(90, 110) / 100;
        
        // Apply system-wide bonus if applicable
        $uderonBonus = $bonuses['uderon'] ?? 1.0;
        if (isset(\Lib\GalaxyGenerator::SYSTEM_TYPES[$systemType]['resource_bonus'])) {
            $systemMod = \Lib\GalaxyGenerator::SYSTEM_TYPES[$systemType]['resource_bonus'];
            $eisenBonus *= $systemMod;
            $silberBonus *= $systemMod;
            $uderonBonus *= $systemMod;
            $wasserstoffBonus *= $systemMod;
        }
        
        // Initial resources for new colony
        $eisen = INITIAL_COLONY_EISEN ?? 1000;
        $silber = INITIAL_COLONY_SILBER ?? 500;
        $uderon = INITIAL_COLONY_UDERON ?? 300;
        $wasserstoff = INITIAL_COLONY_WASSERSTOFF ?? 300;
        $energie = INITIAL_COLONY_ENERGIE ?? 1000;
        
        $sql = "INSERT INTO planets (
                    player_id, name, galaxy, system, position, is_capital, 
                    diameter, temperature_min, temperature_max,
                    eisen, silber, uderon, wasserstoff, energie,
                    eisen_bonus, silber_bonus, uderon_bonus, wasserstoff_bonus,
                    relative_speed, asteroid_count, last_resource_update
                ) VALUES (
                    :player_id, :name, :galaxy, :system, :position, 0, 
                    :diameter, :tmin, :tmax, 
                    :eisen, :silber, :uderon, :wasserstoff, :energie,
                    :eisen_bonus, :silber_bonus, :uderon_bonus, :wasserstoff_bonus,
                    :relative_speed, 0, NOW()
                )";
                
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':player_id' => $playerId,
            ':name' => $name,
            ':galaxy' => $galaxy,
            ':system' => $system,
            ':position' => $position,
            ':diameter' => $diameter,
            ':tmin' => $temperature_min,
            ':tmax' => $temperature_max,
            ':eisen' => $eisen,
            ':silber' => $silber,
            ':uderon' => $uderon,
            ':wasserstoff' => $wasserstoff,
            ':energie' => $energie,
            ':eisen_bonus' => $eisenBonus,
            ':silber_bonus' => $silberBonus,
            ':uderon_bonus' => $uderonBonus,
            ':wasserstoff_bonus' => $wasserstoffBonus,
            ':relative_speed' => $relativeSpeed
        ]);
        
        $planetId = $db->lastInsertId();
        
        // Create initial buildings for the new colony (usually level 0 or 1)
        self::createInitialBuildingsForColony($planetId, $db);
        
        return $planetId;
    }
    
    /**
     * Get maximum number of planets allowed for a given Kolonialverwaltung level
     * Based on the MD files' table
     *
     * @param int $kvLevel Kolonialverwaltung research level
     * @return int Maximum number of planets allowed
     */
    public static function getMaxPlanetsForKVLevel($kvLevel) {
        $planetLimits = [
            0 => 12,
            1 => 18,
            2 => 37,
            3 => 65,
            4 => 108,
            5 => 172,
            6 => 195,
            7 => 219,
            8 => 246,
            9 => 274,
            10 => 305,
            11 => 338,
            12 => 373,
            13 => 411,
            14 => 452,
            15 => 496,
            16 => 543,
            17 => 594,
            18 => 649,
            19 => 708,
            20 => 771,
            21 => 839,
            22 => 911,
            23 => 990,
            24 => 1074,
            25 => 1165,
            26 => 1262,
            27 => 1367,
            28 => 1479,
            29 => 1600,
            30 => 1730
        ];
        
        // Return the limit for the given level, or for the highest defined level if higher
        if (isset($planetLimits[$kvLevel])) {
            return $planetLimits[$kvLevel];
        } else if ($kvLevel > 30) {
            // For levels above 30, apply some formula to calculate the limit
            // This is just an example formula, adjust based on actual game data
            return $planetLimits[30] + ($kvLevel - 30) * 150;
        } else {
            // Fallback for levels not defined (shouldn't happen)
            return 12; // Default limit
        }
    }

    /**
     * Deletes a planet and all its associated data.
     * Assumes the caller is managing database transactions.
     *
     * @param int $planetId The ID of the planet to delete.
     * @param PDO $db The database connection object.
     * @return bool True on success, throws Exception on failure.
     * @throws \Exception if any deletion step fails.
     */
    public static function deletePlanetById($planetId, PDO $db) {
        try {
            // 1. Delete associated player_buildings
            $stmt = $db->prepare("DELETE FROM player_buildings WHERE planet_id = :planet_id");
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();

            // 2. Delete associated player_ships (ships physically on the planet)
            $stmt = $db->prepare("DELETE FROM player_ships WHERE planet_id = :planet_id");
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();

            // 3. Delete from construction_queue for this planet
            $stmt = $db->prepare("DELETE FROM construction_queue WHERE planet_id = :planet_id");
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();
            
            // 4. Delete trade offers originating from this planet
            // Assuming 'trade_offers' is the correct table name from TradeOffer.php model
            $stmt = $db->prepare("DELETE FROM trade_offers WHERE planet_id = :planet_id");
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();

            // 5. Delete associated player_defense
            $stmt = $db->prepare("DELETE FROM player_defense WHERE planet_id = :planet_id");
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();

            // 6. Delete associated battle_reports 
            // This will delete reports where the planet was either attacker or defender.
            // If you prefer to keep the reports but disassociate them, you would NULLify the foreign keys instead.
            $stmt = $db->prepare("DELETE FROM battle_reports WHERE attacker_planet_id = :planet_id OR defender_planet_id = :planet_id");
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();

            // 7. Delete the planet itself
            $sql = "DELETE FROM planets WHERE id = :planet_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();

            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Error in deletePlanetById for planet {$planetId}: " . $e->getMessage());
            throw $e; // Re-throw to be caught by caller's transaction handler
        }
    }

    /**
     * Process a planet invasion - transfer ownership to a new player
     *
     * @param int $planetId ID of the planet to be invaded
     * @param int $newPlayerId ID of the conquering player
     * @return bool True on success, false on failure
     */
    public static function invasionProcess($planetId, $newPlayerId) {
        $db = self::getDB();
        $db->beginTransaction();
        
        try {
            // Get planet details
            $planet = self::getById($planetId);
            if (!$planet) {
                throw new Exception("Planet not found");
            }
            
            // Check if this is a main planet (prevent invasion of home planets)
            if ($planet->is_capital) {
                throw new Exception("Cannot invade a home planet");
            }
            
            $oldPlayerId = $planet->player_id;
            
            // Check if conquering player has enough colonial administration research level
            $kolonialverwaltungType = ResearchType::getByInternalName('kolonialverwaltung');
            if ($kolonialverwaltungType) {
                $kolonialverwaltungLevel = PlayerResearch::getByPlayerAndType($newPlayerId, $kolonialverwaltungType->id);
                $maxAllowablePlanets = self::getMaxPlanetsForKVLevel($kolonialverwaltungLevel ? $kolonialverwaltungLevel->level : 0);
                
                // Count existing planets for the new owner
                $stmt = $db->prepare('SELECT COUNT(*) FROM planets WHERE player_id = :player_id');
                $stmt->bindParam(':player_id', $newPlayerId, PDO::PARAM_INT);
                $stmt->execute();
                $currentPlanetCountNewOwner = $stmt->fetchColumn();
                
                if ($currentPlanetCountNewOwner >= $maxAllowablePlanets + 1) { // +1 for their own home planet if not included in count
                    throw new Exception("Conquering player does not have enough Kolonialverwaltung level to control this planet.");
                }
            }
            
            // Check for Noob Protection
            if (self::checkNoobProtection($newPlayerId, $oldPlayerId)) {
                throw new Exception("Noob protection is active for this target");
            }
            
            // Update planet ownership
            $sql = "UPDATE planets SET player_id = :new_player_id WHERE id = :planet_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':new_player_id', $newPlayerId, PDO::PARAM_INT);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();

            // Update player_id for ships stationed ON the planet (not in fleets) that belonged to the old owner
            $sqlShips = "UPDATE player_ships SET player_id = :new_player_id 
                         WHERE planet_id = :planet_id AND fleet_id IS NULL AND player_id = :old_player_id";
            $stmtShips = $db->prepare($sqlShips);
            $stmtShips->bindParam(':new_player_id', $newPlayerId, PDO::PARAM_INT);
            $stmtShips->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmtShips->bindParam(':old_player_id', $oldPlayerId, PDO::PARAM_INT);
            $stmtShips->execute();
            
            // Create notification for the old owner
            $coords = "{$planet->galaxy}:{$planet->system}:{$planet->position}";
            $message = "Dein Planet {$planet->name} ({$coords}) wurde von einem anderen Spieler invasiert!";
            PlayerNotification::create($oldPlayerId, 'invasion', $message, ['planet_id' => $planetId, 'coords' => $coords]);
            
            $db->commit();
            return true;
            
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Planet invasion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if noob protection applies between two players
     *
     * @param int $attackerId ID of the attacking player
     * @param int $defenderId ID of the defending player
     * @return bool True if noob protection applies, false otherwise
     */
    public static function checkNoobProtection($attackerId, $defenderId) {
        // If either player doesn't exist, no protection applies
        $attacker = Player::findById($attackerId);
        $defender = Player::findById($defenderId);
        
        if (!$attacker || !$defender) {
            return false;
        }
        
        // Check if defender has been inactive for more than 2 days
        $inactivityThreshold = time() - (2 * 24 * 3600); // 2 days in seconds
        if (strtotime($defender->last_activity) < $inactivityThreshold) {
            return false; // No protection for inactive players
        }
        
        // Calculate "strength" for both players based on MD formula
        // Formula: (Shield research levels + Fleet research * 3) / Werft Research
        $attackerResearch = PlayerResearch::getResearchLevelsByPlayerId($attackerId);
        $defenderResearch = PlayerResearch::getResearchLevelsByPlayerId($defenderId);
        
        $attackerShieldLevels = ($attackerResearch[ResearchType::getByInternalName('prallschirm')->id] ?? 0) + 
                              ($attackerResearch[ResearchType::getByInternalName('hochueberladungsschirm')->id] ?? 0) + 
                              ($attackerResearch[ResearchType::getByInternalName('paratronschild')->id] ?? 0);
                              
        $defenderShieldLevels = ($defenderResearch[ResearchType::getByInternalName('prallschirm')->id] ?? 0) + 
                              ($defenderResearch[ResearchType::getByInternalName('hochueberladungsschirm')->id] ?? 0) + 
                              ($defenderResearch[ResearchType::getByInternalName('paratronschild')->id] ?? 0);
        
        $attackerWerftLevel = $attackerResearch[ResearchType::getByInternalName('werftforschung')->id] ?? 1;
        $defenderWerftLevel = $defenderResearch[ResearchType::getByInternalName('werftforschung')->id] ?? 1;
        
        // Prevent division by zero
        if ($attackerWerftLevel <= 0) $attackerWerftLevel = 1;
        if ($defenderWerftLevel <= 0) $defenderWerftLevel = 1;
        
        $attackerStrength = ($attackerShieldLevels + ($attackerWerftLevel * 3)) / $attackerWerftLevel;
        $defenderStrength = ($defenderShieldLevels + ($defenderWerftLevel * 3)) / $defenderWerftLevel;
        
        // If attacker is 12+ times stronger than defender, noob protection applies
        return ($attackerStrength > ($defenderStrength * 12));
    }
    
    /**
     * Process planet destruction by Arkonbombe
     *
     * @param int $planetId ID of the planet to be destroyed
     * @return bool True on success, false on failure
     */
    public static function destroyPlanet($planetId) {
        $db = self::getDB();
        $db->beginTransaction();
        
        try {
            // Get planet details
            $planet = self::getById($planetId);
            if (!$planet) {
                throw new \Exception("Planet not found");
            }
            
            // Check if this is a main planet (prevent destruction of home planets)
            if ($planet->is_capital) {
                throw new \Exception("Cannot destroy a home planet");
            }
            
            $oldPlayerId = $planet->player_id;
            $coords = "{$planet->galaxy}:{$planet->system}:{$planet->position}";
            
            // Remove all buildings
            $sql = "DELETE FROM player_buildings WHERE planet_id = :planet_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Remove all ships
            $sql = "DELETE FROM player_ships WHERE planet_id = :planet_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Remove all defenses
            $sql = "DELETE FROM player_defenses WHERE planet_id = :planet_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Finally, remove the planet itself
            $sql = "DELETE FROM planets WHERE id = :planet_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Create notification for the owner
            $message = "Dein Planet {$planet->name} ({$coords}) wurde durch eine Arkonbombe zerstört!";
            PlayerNotification::create($oldPlayerId, 'planet_destroyed', $message, ['coords' => $coords]);
            
            $db->commit();
            return true;
            
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Planet destruction error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check Arkon spam protection - limit the number of arkon attacks a player can make
     *
     * @param int $attackerId ID of the attacking player
     * @param int $defenderId ID of the defending player
     * @return bool|int False if limit exceeded, otherwise returns remaining allowed attacks
     */
    public static function checkArkonSpamProtection($attackerId, $defenderId) {
        $db = self::getDB();
        
        // Get defender's KV level
        $kolonialverwaltungType = ResearchType::getByInternalName('kolonialverwaltung');
        $defenderKVLevel = 0;
        
        if ($kolonialverwaltungType) {
            $research = PlayerResearch::getByPlayerAndType($defenderId, $kolonialverwaltungType->id);
            $defenderKVLevel = $research ? $research->level : 0;
        }
        
        // Calculate max allowed attacks using formula from MD: SQRT(10 * KV-Level + 1)
        $maxAllowedAttacks = ceil(sqrt(10 * $defenderKVLevel + 1));
        
        // Count arkon attacks made by this player in the last 24 hours
        $oneDayAgo = date('Y-m-d H:i:s', time() - 86400); // 24 hours ago
        
        $sql = "SELECT COUNT(*) FROM fleets 
                WHERE player_id = :attacker_id 
                AND mission_type = 'arkon' 
                AND start_time >= :time_limit";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':attacker_id', $attackerId, PDO::PARAM_INT);
        $stmt->bindParam(':time_limit', $oneDayAgo);
        $stmt->execute();
        
        $attacksMade = $stmt->fetchColumn();
        
        if ($attacksMade >= $maxAllowedAttacks) {
            return false; // Limit exceeded
        }
        
        return $maxAllowedAttacks - $attacksMade; // Return remaining allowed attacks
    }

    /**
     * Add an asteroid to a planet
     *
     * @param int $planetId The ID of the planet
     * @return bool True on success, false on failure
     */
    public static function addAsteroid($planetId) {
        $db = self::getDB();
        $db->beginTransaction();
        
        try {
            // Get planet details
            $planet = self::getById($planetId);
            if (!$planet) {
                throw new \Exception("Planet not found");
            }
            
            // Get player's highest research center level
            $forschungszentrumTypeId = BuildingType::getByInternalName('forschungszentrum')->id ?? null;
            $highestFZLevel = 0;
            
            if ($forschungszentrumTypeId) {
                $allPlayerPlanets = self::getPlanetsByPlayerId($planet->player_id);
                foreach ($allPlayerPlanets as $pPlanet) {
                    $buildingOnPlanet = PlayerBuilding::getByPlanetAndType($pPlanet->id, $forschungszentrumTypeId);
                    if ($buildingOnPlanet && $buildingOnPlanet->level > $highestFZLevel) {
                        $highestFZLevel = $buildingOnPlanet->level;
                    }
                }
            }
            
            // Check if player can build another asteroid (FZ level / 2 = max asteroids)
            $maxAsteroids = floor($highestFZLevel / 2);
            
            if ($planet->asteroid_count >= $maxAsteroids) {
                throw new \Exception("Cannot build more asteroids with current research center level");
            }
            
            // Get planet's hourly resource production
            $production = self::updateResources($planetId);
            $hourlyProduction = $production['eisen_rate'] + $production['silber_rate'] + 
                              $production['uderon_rate'] + $production['wasserstoff_rate'];
            
            $planetCount = count(self::getPlanetsByPlayerId($planet->player_id));
            
            // Calculate asteroid cost - roughly 200 times hourly production, min 200k per resource
            $baseCost = max(200000, $hourlyProduction * 200);
            // Cost increases based on number of existing asteroids on the planet
            $costMultiplier = 1 + ($planet->asteroid_count * 0.5);
            // Additional multiplier based on total planet count
            $planetMultiplier = 1 + ($planetCount * 0.02);
            
            $finalCost = $baseCost * $costMultiplier * $planetMultiplier;
            
            // Check if planet has enough resources
            if ($planet->eisen < $finalCost || 
                $planet->silber < $finalCost || 
                $planet->uderon < $finalCost || 
                $planet->wasserstoff < $finalCost) {
                throw new \Exception("Not enough resources to build asteroid");
            }
            
            // Deduct resources
            $sql = "UPDATE planets SET 
                    eisen = eisen - :cost,
                    silber = silber - :cost, 
                    uderon = uderon - :cost,
                    wasserstoff = wasserstoff - :cost,
                    asteroid_count = asteroid_count + 1
                    WHERE id = :planet_id";
                    
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':cost', $finalCost, PDO::PARAM_STR);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Calculate build time - typically around 10 days
            $buildTimeHours = 240; // 10 days
            
            // Create a building task for the asteroid
            $sql = "INSERT INTO building_queue 
                    (planet_id, building_type, build_start_time, build_end_time) 
                    VALUES 
                    (:planet_id, 'asteroid', NOW(), DATE_ADD(NOW(), INTERVAL :hours HOUR))";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->bindParam(':hours', $buildTimeHours, PDO::PARAM_INT);
            $stmt->execute();
            
            $db->commit();
            return true;
            
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Asteroid building error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Activate planetary shield for a planet
     *
     * @param int $planetId The ID of the planet
     * @param int $duration Duration in hours (default 8)
     * @return bool True on success, false on failure
     */
    public static function activateShield($planetId, $duration = 8) {
        $db = self::getDB();
        $db->beginTransaction();
        
        try {
            // Get planet details
            $planet = self::getById($planetId);
            if (!$planet) {
                throw new \Exception("Planet not found");
            }
            
            // Check if shield is already active
            if ($planet->shield_active_until && strtotime($planet->shield_active_until) > time()) {
                throw new \Exception("Shield already active");
            }
            
            // Check if planet has the shield generator building
            $shieldGeneratorId = BuildingType::getByInternalName('planetarer_schirmfeldgenerator')->id ?? null;
            if ($shieldGeneratorId) {
                $shieldGenerator = PlayerBuilding::getByPlanetAndType($planetId, $shieldGeneratorId);
                if (!$shieldGenerator || $shieldGenerator->level < 1) {
                    throw new \Exception("No shield generator on this planet");
                }
            } else {
                throw new \Exception("Shield generator building type not found");
            }
            
            // Calculate energy cost based on shield generator level
            // Higher levels are more efficient
            $shieldLevel = $shieldGenerator->level;
            $energyCost = 100000 / $shieldLevel;
            
            // Check if planet has enough energy
            if ($planet->energie < $energyCost) {
                throw new \Exception("Not enough energy to activate shield");
            }
            
            // Calculate shield duration (base duration + bonus from shield level)
            $effectiveDuration = $duration * (1 + ($shieldLevel * 0.1));
            $shieldUntil = date('Y-m-d H:i:s', time() + ($effectiveDuration * 3600));
            
            // Deduct energy and activate shield
            $sql = "UPDATE planets SET 
                    energie = energie - :energy_cost,
                    shield_active_until = :shield_until
                    WHERE id = :planet_id";
                    
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':energy_cost', $energyCost, PDO::PARAM_STR);
            $stmt->bindParam(':shield_until', $shieldUntil);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();
            
            $db->commit();
            return true;
            
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Shield activation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set a planet as the player's capital (main planet)
     *
     * @param int $planetId The ID of the planet to set as capital
     * @param int $playerId The player's ID
     * @return bool True on success, false on failure
     */
    public static function setAsCapital($planetId, $playerId) {
        $db = self::getDB();
        $db->beginTransaction();
        
        try {
            // Get planet details
            $planet = self::getById($planetId);
            if (!$planet || $planet->player_id != $playerId) {
                throw new \Exception("Planet not found or does not belong to player");
            }
            
            // Check if this is a main planet (prevent invasion of home planets)
            if ($planet->is_capital) {
                return true; // Already the capital, nothing to do
            }
            
            // Get current capital
            $currentCapital = self::getHomePlanetByPlayerId($playerId);
            if (!$currentCapital) {
                throw new \Exception("Current capital not found");
            }
            
            // Check rule: 6-week cooldown between HP changes
            $lastChangeCooldown = 6 * 7 * 24 * 3600; // 6 weeks in seconds
            
            // We'd need to track the last HP change time for this to work
            // For now, let's assume we track it in a separate table
            
            // Check rule: At least 3 planets in the target system
            $stmt = $db->prepare('SELECT COUNT(*) FROM planets WHERE galaxy = :galaxy AND system = :system AND player_id = :player_id');
            $stmt->bindParam(':galaxy', $planet->galaxy, PDO::PARAM_INT);
            $stmt->bindParam(':system', $planet->system, PDO::PARAM_INT);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->execute();
            $planetsInSystem = $stmt->fetchColumn();
            
            if ($planetsInSystem < 3) {
                throw new \Exception("Need at least 3 planets in the target system");
            }
            
            // Check rule: Must be in same cluster (10 galaxies)
            $currentGalaxy = $currentCapital->galaxy;
            $targetGalaxy = $planet->galaxy;
            
            $currentClusterStart = floor($currentGalaxy / 10) * 10;
            $targetClusterStart = floor($targetGalaxy / 10) * 10;
            
            if ($currentClusterStart != $targetClusterStart) {
                throw new \Exception("Capital can only be moved within the same cluster");
            }
            
            // Start the 7-day capital change process
            // For now, we'll just do it immediately in this example
            
            // Update old capital
            $sql = "UPDATE planets SET is_capital = 0 WHERE id = :old_capital_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':old_capital_id', $currentCapital->id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Update new capital
            $sql = "UPDATE planets SET is_capital = 1 WHERE id = :new_capital_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':new_capital_id', $planetId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Update HP change timestamp (would need an appropriate table)
            
            $db->commit();
            return true;
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Set capital error: " . $e->getMessage());
            return false;
        }
    }
}
?>
