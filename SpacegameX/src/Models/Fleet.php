<?php
namespace Models;

use Core\Model;
use PDO;
use Exception; // For throwing and catching exceptions
use PDOException; // For database specific exceptions

// It's good practice to add use statements for other models you'll be using
use Models\Planet;
use Models\PlayerShip;
use Models\ShipType;
use Models\ResearchType; // Added for dynamic research ID
use Models\Player; // Added for player data in blockade
use Services\NotificationService; // Added for notifications
use Models\Combat; // Added for attack and espionage missions
use Models\EspionageReport; // Added for espionage missions
use Models\BattleReport; // Added for attack missions (though Combat model might handle creation directly)
use Models\PlayerDefense; // Added for invasion mission
use Models\DefenseType; // Added for invasion mission
use Models\Alliance; // Added for alliance research
use Models\AllianceResearch; // Added for alliance research
use Models\PlayerBuilding; // Added for clearing buildings on invasion


class Fleet extends Model {
    // ... existing properties ...
    public $id;
    public $player_id;
    public $start_planet_id;
    public $target_planet_id;
    public $target_galaxy;
    public $target_system;
    public $target_position;
    public $mission_type;
    public $start_time;
    public $arrival_time;
    public $return_time;
    public $eisen_cargo;
    public $silber_cargo;
    public $uderon_cargo;
    public $wasserstoff_cargo;
    public $energie_cargo;
    public $is_returning;
    public $is_completed;
    public $notes; // Added field based on usage in setFleetToReturn

    // Constants for distance and fuel calculations
    private const INTER_GALAXY_BASE_DISTANCE_FACTOR = 20000; // Distance factor per galaxy difference
    private const INTER_SYSTEM_BASE_DISTANCE = 2700;      // Base distance for inter-system travel within the same galaxy
    private const INTER_SYSTEM_PER_SYSTEM_FACTOR = 95;    // Distance factor per system difference within the same galaxy
    private const INTRA_SYSTEM_BASE_DISTANCE = 1000;      // Base distance for intra-system travel
    private const INTRA_SYSTEM_PER_POSITION_FACTOR = 5;   // Distance factor per position difference within the same system
    private const FUEL_CONSUMPTION_DISTANCE_UNIT = 10000; // e.g., fuel is consumed per 10,000 distance units

    // Constants for Arkon Mission (Finding Uderon - this logic is being removed)
    // private const ARKON_SIGNATURE_FIND_CHANCE = 20; // Percentage
    // private const ARKON_SIGNATURE_UDERON_REWARD = 1500; // Amount of Uderon

    // Constants for Destroy Mission
    private const DESTROY_MISSION_MIN_FIELDS_DESTROYED = 1;
    private const DESTROY_MISSION_MAX_FIELDS_DESTROYED = 5;

    // Alliance Tax Constant (Example: 5% tax on allied transport)
    private const ALLIANCE_TRANSPORT_TAX_PERCENTAGE = 0.05; // 5%

    // Helper function to get basic fleet data by ID
    public static function getFleetById($fleetId, PDO $db = null) {
        if (!$db) $db = self::getDB();
        $sql = "SELECT * FROM fleets WHERE id = :fleet_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':fleet_id', $fleetId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    // Get all active fleets for a player
    public static function getActiveFleetsByPlayerId($playerId, PDO $db = null) {
        if (!$db) $db = self::getDB();
        $sql = "SELECT f.*, 
                       p_start.name as start_planet_name, 
                       p_target.name as target_planet_name,
                       p_start.galaxy as start_galaxy, p_start.system as start_system, p_start.position as start_position,
                       CASE 
                           WHEN f.target_planet_id IS NOT NULL THEN p_target.galaxy
                           ELSE f.target_galaxy 
                       END as target_galaxy_coord,
                       CASE 
                           WHEN f.target_planet_id IS NOT NULL THEN p_target.system
                           ELSE f.target_system
                       END as target_system_coord,
                       CASE 
                           WHEN f.target_planet_id IS NOT NULL THEN p_target.position
                           ELSE f.target_position
                       END as target_position_coord
                FROM fleets f
                LEFT JOIN planets p_start ON f.start_planet_id = p_start.id
                LEFT JOIN planets p_target ON f.target_planet_id = p_target.id
                WHERE f.player_id = :player_id AND f.is_completed = 0
                ORDER BY f.arrival_time ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // Get fleets stationed at a specific planet
    // For 'station' mission: only owner or allies. For 'blockade': any fleet.
    public static function getStationedFleetsAtPlanet(int $planetId, ?int $planetOwnerId, PDO $db) {
        $params = [':planet_id' => $planetId];
        $ownerAllianceSubQuery = '';
        if ($planetOwnerId !== null) {
            $ownerAllianceSubQuery = "AND (
                f.mission_type = 'blockade' OR 
                (
                    f.mission_type = 'station' AND 
                    (
                        f.player_id = :planet_owner_id OR 
                        (
                            p_fleet_owner.alliance_id IS NOT NULL AND 
                            p_fleet_owner.alliance_id = (
                                SELECT p_target_owner.alliance_id 
                                FROM players p_target_owner 
                                WHERE p_target_owner.id = :planet_owner_id_for_alliance AND p_target_owner.alliance_id IS NOT NULL
                            )
                        )
                    )
                )
            )";
            $params[':planet_owner_id'] = $planetOwnerId;
            $params[':planet_owner_id_for_alliance'] = $planetOwnerId;
        } else {
            // If no planet owner, only non-station missions (like blockades on unowned planets, if applicable)
            // or adjust logic if stationing on unowned is allowed for self.
            // For now, if no owner, we might only care about blockades.
            // If $planetOwnerId is provided, the logic above handles stationing for owner/allies and blockades.
            $ownerAllianceSubQuery = "AND f.mission_type = 'blockade'";
        }

        $sql = "SELECT f.*, p_fleet_owner.username as owner_username 
                FROM fleets f
                JOIN players p_fleet_owner ON f.player_id = p_fleet_owner.id
                WHERE f.target_planet_id = :planet_id 
                  AND f.is_completed = 0 
                  AND f.is_returning = 0 
                  AND UNIX_TIMESTAMP(f.arrival_time) <= UNIX_TIMESTAMP(NOW())
                  {$ownerAllianceSubQuery}
                ORDER BY f.arrival_time DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
    
    // Get fleet details including ships
    public static function getFleetDetails($fleetId, PDO $db = null) {
        if (!$db) $db = self::getDB();
        
        // Get fleet data
        $sql = "SELECT f.*, 
                       p_start.name as start_planet_name, p_start.galaxy as start_galaxy, p_start.system as start_system, p_start.position as start_position,
                       p_target.name as target_planet_name, 
                       CASE 
                           WHEN f.target_planet_id IS NOT NULL THEN p_target.galaxy
                           ELSE f.target_galaxy 
                       END as target_galaxy_coord,
                       CASE 
                           WHEN f.target_planet_id IS NOT NULL THEN p_target.system
                           ELSE f.target_system
                       END as target_system_coord,
                       CASE 
                           WHEN f.target_planet_id IS NOT NULL THEN p_target.position
                           ELSE f.target_position
                       END as target_position_coord
                FROM fleets f
                LEFT JOIN planets p_start ON f.start_planet_id = p_start.id
                LEFT JOIN planets p_target ON f.target_planet_id = p_target.id
                WHERE f.id = :fleet_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':fleet_id', $fleetId, PDO::PARAM_INT);
        $stmt->execute();
        $fleet = $stmt->fetch(PDO::FETCH_OBJ);
        
        if (!$fleet) {
            return null;
        }
        
        // Get ships in fleet
        $sql = "SELECT fs.ship_type_id, fs.quantity, 
                       st.name_de, st.internal_name, st.speed, st.cargo_capacity, 
                       st.fuel_consumption, st.weapon_power, st.shield_power, st.armor_power, st.requirements_json
                FROM fleet_ships fs 
                JOIN ship_types st ON fs.ship_type_id = st.id 
                WHERE fs.fleet_id = :fleet_id";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':fleet_id', $fleetId, PDO::PARAM_INT);
        $stmt->execute();
        $ships = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        // Return all data
        return [
            'fleet' => $fleet,
            'ships' => $ships
        ];
    }
    
    // Helper function to get total cargo capacity of a fleet
    public static function getFleetCargoCapacity($fleetId, $db = null) {
        if (!$db) $db = self::getDB();
        $sql = "SELECT SUM(st.cargo_capacity * fs.quantity) as total_capacity
                FROM fleet_ships fs
                JOIN static_ship_types st ON fs.ship_type_id = st.id
                WHERE fs.fleet_id = :fleet_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':fleet_id', $fleetId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    // Helper function to get current total material resources in fleet's cargo
    public static function getCurrentMaterialCargoLoad($fleet) {
        if (!is_object($fleet)) return 0;
        return ($fleet->eisen_cargo ?? 0) + 
               ($fleet->silber_cargo ?? 0) + 
               ($fleet->uderon_cargo ?? 0) + 
               ($fleet->wasserstoff_cargo ?? 0) +
               ($fleet->energie_cargo ?? 0); // Assuming energie can be cargo
    }

    /**
     * Calculates the distance between two sets of coordinates.
     *
     * @param int $startGalaxy
     * @param int $startSystem
     * @param int $startPosition
     * @param int $targetGalaxy
     * @param int $targetSystem
     * @param int $targetPosition
     * @return int Distance
     */
    public static function calculateDistance($startGalaxy, $startSystem, $startPosition, $targetGalaxy, $targetSystem, $targetPosition) {
        if ($startGalaxy == $targetGalaxy) {
            if ($startSystem == $targetSystem) {
                // Intra-system
                if ($startPosition == $targetPosition && $startGalaxy == $targetGalaxy && $startSystem == $targetSystem) return 0; // Same planet
                return self::INTRA_SYSTEM_BASE_DISTANCE + (abs($startPosition - $targetPosition) * self::INTRA_SYSTEM_PER_POSITION_FACTOR);
            } else {
                // Inter-system, same galaxy
                return self::INTER_SYSTEM_BASE_DISTANCE + (abs($startSystem - $targetSystem) * self::INTER_SYSTEM_PER_SYSTEM_FACTOR);
            }
        } else {
            // Inter-galaxy
            // Simplified: a base factor per galaxy difference.
            // A more complex calculation could consider distance to edge of galaxy, then jump, then to target system.
            return abs($startGalaxy - $targetGalaxy) * self::INTER_GALAXY_BASE_DISTANCE_FACTOR;
        }
    }

    /**
     * Determines the primary drive type for a given ship type based on its prerequisites.
     * This is a simplified mapping based on the highest drive research required.
     *
     * @param int $shipTypeId The ID of the ship type.
     * @param PDO $db Database connection.
     * @return string|null Internal name of the primary drive type, or null if none found.
     */
    private static function getShipPrimaryDriveType(int $shipTypeId, PDO $db): ?string {
        $shipType = ShipType::getById($shipTypeId, $db);
        if (!$shipType || empty($shipType->requirements_json)) {
            // Fallback to a basic drive if no requirements specified or ship type not found
            return 'verbrennungstriebwerk_tech'; 
        }

        $requirements = json_decode($shipType->requirements_json, true);
        if (!isset($requirements['research']) || !is_array($requirements['research'])) {
             // Fallback if research requirements are missing or malformed
            return 'verbrennungstriebwerk_tech';
        }

        // Order matters: from most advanced to least advanced
        $driveResearchTypes = [
            'hyperraumantrieb_tech',      // Hyperspace Drive
            'warpantrieb_tech',           // Warp Drive
            'impulstriebwerk_tech',       // Impulse Drive
            'verbrennungstriebwerk_tech'  // Combustion Drive
        ];

        foreach ($driveResearchTypes as $driveTechName) {
            if (isset($requirements['research'][$driveTechName]) && $requirements['research'][$driveTechName] > 0) {
                return $driveTechName; // This is the highest required drive tech for this ship
            }
        }
        
        // If no specific drive tech is listed in requirements, assume basic combustion drive
        return 'verbrennungstriebwerk_tech'; 
    }


    /**
     * Calculates the travel time for a fleet.
     *
     * @param array $shipsDataForFleet Array of ship data [['ship_type_id' => ..., 'quantity' => ...], ...].
     * @param int $distance
     * @param int $playerId The ID of the player owning the fleet.
     * @param string $missionType The mission type of the fleet.
     * @param int $targetGalaxy Target galaxy coordinate.
     * @param int $targetSystem Target system coordinate.
     * @param int $targetPosition Target position coordinate.
     * @param PDO $db Database connection.
     * @param float $universeSpeedFactor Global universe speed factor.
     * @return int Travel time in seconds.
     */
    public static function calculateTravelTime(array $shipsDataForFleet, int $distance, int $playerId, string $missionType, int $targetGalaxy, int $targetSystem, int $targetPosition, PDO $db, float $universeSpeedFactor = 1.0): int {
        if (empty($shipsDataForFleet) || $distance <= 0) return PHP_INT_MAX; // Or a very large number representing infinite time

        if ($universeSpeedFactor <= 0) $universeSpeedFactor = 1.0; // Ensure positive speed factor

        $playerResearchLevels = PlayerResearch::getResearchLevelsByPlayerId($playerId, $db);
        $allianceAntriebstechnikLevel = 0;
        $allianceSpeedFactor = 1.0; // Overall factor from alliance research

        $player = Player::findById($playerId, $db); // Assuming Player::findById exists
        if ($player && $player->alliance_id) {
            // Fetch alliance research for 'antriebstechnik'
            // Assuming an AllianceResearch model and method like getResearchLevelsByAllianceId
            // or a specific method getResearchLevel($allianceId, 'antriebstechnik', $db)
            $allianceResearch = AllianceResearch::getResearchLevelsByAllianceId($player->alliance_id, $db);
            if (isset($allianceResearch['antriebstechnik'])) {
                $allianceAntriebstechnikLevel = (int)$allianceResearch['antriebstechnik'];
            }
            // Example: 1% speed increase per level of alliance propulsion tech
            $allianceSpeedFactor = 1.0 + ($allianceAntriebstechnikLevel * 0.01); 
        }

        $minEffectiveShipSpeed = PHP_INT_MAX;

        // Define drive tech bonuses (percentage per level)
        $driveBonuses = [
            'verbrennungstriebwerk_tech' => 0.05, // 5%
            'impulstriebwerk_tech' => 0.10,       // 10%
            'warpantrieb_tech' => 0.20,           // 20%
            'hyperraumantrieb_tech' => 0.30,      // 30%
        ];

        foreach ($shipsDataForFleet as $shipData) {
            if ($shipData['quantity'] <= 0) continue;

            $shipType = ShipType::getById($shipData['ship_type_id'], $db);
            if (!$shipType || $shipType->speed <= 0) continue;

            $baseShipSpeed = $shipType->speed;
            $primaryDriveTech = self::getShipPrimaryDriveType($shipData['ship_type_id'], $db);
            
            $driveResearchBonusFactor = 0;
            if ($primaryDriveTech && isset($playerResearchLevels[$primaryDriveTech]) && isset($driveBonuses[$primaryDriveTech])) {
                $driveResearchLevel = (int)$playerResearchLevels[$primaryDriveTech];
                $driveResearchBonusFactor = $driveResearchLevel * $driveBonuses[$primaryDriveTech];
            }
            
            $effectiveShipSpeed = $baseShipSpeed * (1 + $driveResearchBonusFactor);
            $minEffectiveShipSpeed = min($minEffectiveShipSpeed, $effectiveShipSpeed);
        }

        if ($minEffectiveShipSpeed === PHP_INT_MAX || $minEffectiveShipSpeed <= 0) {
             return PHP_INT_MAX; // No ships with valid speed or all ships have zero/negative speed
        }

        $finalFleetSpeed = $minEffectiveShipSpeed;

        // Apply mission-specific speed reductions
        if ($missionType === 'invasion') {
            $finalFleetSpeed *= 0.30; // 30% of normal speed
        } elseif ($missionType === 'arkon') { // Assuming 'arkon' is a special slow mission
            $finalFleetSpeed *= 0.25; // 25% of normal speed
        } elseif ($missionType === 'destroy' && isset($shipsDataForFleet[0]['ship_type_id'])) {
            // Example: Moon destruction missions are slower if specific ships are involved
            // This is a placeholder for more complex mission-speed logic
            // $isMoonDestructionShipPresent = false; ... check ship types ...
            // if ($isMoonDestructionShipPresent) $finalFleetSpeed *= 0.5;
        }
        
        if ($finalFleetSpeed <= 0) return PHP_INT_MAX;

        // Apply alliance diplomacy speed modifiers
        $targetPlanet = Planet::getByCoordinates($targetGalaxy, $targetSystem, $targetPosition, $db);
        $targetPlayerId = $targetPlanet ? $targetPlanet->player_id : null;

        if ($player && $targetPlayerId && $player->id != $targetPlayerId) { // Don't apply to self
             $targetOwner = Player::findById($targetPlayerId, $db);
             if ($targetOwner && $player->alliance_id && $targetOwner->alliance_id && $player->alliance_id != $targetOwner->alliance_id) {
                 // Assuming Alliance::getDiplomacyStatus($alliance1_id, $alliance2_id, $db)
                 // returns 'WAR', 'PEACE', 'ALLY', 'NEUTRAL', etc.
                 $diplomacyStatus = Alliance::getDiplomacyStatus($player->alliance_id, $targetOwner->alliance_id, $db);
                 if ($diplomacyStatus === 'WAR') {
                     $finalFleetSpeed *= 0.5; // 50% speed if at war
                 } elseif ($diplomacyStatus === 'NEUTRAL' || $diplomacyStatus === null) { // Assuming null is neutral
                     $finalFleetSpeed *= 0.8; // 80% speed if neutral (and not same alliance)
                 }
                 // PEACE or ALLY implies no speed penalty from this check (already handled if same alliance earlier)
             } elseif ($targetOwner && !$player->alliance_id && !$targetOwner->alliance_id) {
                 // Both players no alliance - could be a default "neutral" penalty if desired
                 // $finalFleetSpeed *= 0.9; // Example: 90% speed if target owned by non-allied, non-war player
             }
        }
        if ($finalFleetSpeed <= 0) return PHP_INT_MAX;


        // OGAME-like formula: Time = (10 + (3500 * SQRT(Distance * 10 / EffectiveSpeed)) / UniverseSpeedFactor) / AllianceSpeedFactor
        // EffectiveSpeed here is $finalFleetSpeed which includes player research, mission, diplomacy.
        // UniverseSpeedFactor affects the "base" travel part.
        // AllianceSpeedFactor (from alliance propulsion tech) reduces total time.
        $time = (10 + (3500 * sqrt(($distance * 10 / $finalFleetSpeed) / $universeSpeedFactor)));
        $time = $time / $allianceSpeedFactor; // Alliance tech makes it faster

        return ceil($time); // Return time in seconds, rounded up
    }

    /**
     * Calculates the fuel consumption for a fleet for a given distance.
     *
     * @param array $shipsDataForFleet Array of ship data [['ship_type_id' => ..., 'quantity' => ...], ...].
     * @param int $distance
     * @param int $playerId The ID of the player owning the fleet.
     * @param PDO $db Database connection.
     * @return int Total fuel (Wasserstoff) consumed.
     */
    public static function calculateFuelConsumption(array $shipsDataForFleet, int $distance, int $playerId, PDO $db): int {
        $totalFuel = 0;
        if ($distance <= 0) return 0;

        // Player research might affect fuel consumption (e.g., drive tech levels)
        // For now, assume base fuel consumption from ShipType is used.
        // A more complex model could use PlayerResearch to apply fuel efficiency bonuses.
        // $playerResearchLevels = PlayerResearch::getResearchLevelsByPlayerId($playerId, $db);

        foreach ($shipsDataForFleet as $shipData) {
            if (!isset($shipData['ship_type_id']) || $shipData['quantity'] <= 0) continue;

            $shipType = ShipType::getById($shipData['ship_type_id'], $db);
            if ($shipType && $shipType->fuel_consumption > 0) {
                // Assuming $shipType->fuel_consumption is the amount of fuel needed
                // for this ship type to travel self::FUEL_CONSUMPTION_DISTANCE_UNIT distance.
                // Formula: quantity * base_fuel_for_unit_dist * (actual_distance / unit_dist)
                $fuelForThisShipType = $shipData['quantity'] * $shipType->fuel_consumption * ($distance / self::FUEL_CONSUMPTION_DISTANCE_UNIT);
                
                // Optional: Apply player research bonus for fuel efficiency
                // $primaryDriveTech = self::getShipPrimaryDriveType($shipData['ship_type_id'], $db);
                // if ($primaryDriveTech && isset($playerResearchLevels[$primaryDriveTech])) {
                //    $driveLevel = (int)$playerResearchLevels[$primaryDriveTech];
                //    $fuelEfficiencyBonus = $driveLevel * 0.01; // Example: 1% less fuel per level
                //    $fuelForThisShipType *= (1 - $fuelEfficiencyBonus);
                // }

                $totalFuel += $fuelForThisShipType;
            }
        }
        return ceil($totalFuel);
    }


    /**
     * Calculates the total combat strength of a fleet.
     *
     * @param array $shipsDataForFleet Array of ship data [['ship_type_id' => ..., 'quantity' => ...], ...].
     * @param PDO $db Optional database connection.
     * @return int Total combat strength.
     */
    public static function calculateFleetCombatStrength(array $shipsDataForFleet, PDO $db = null) {
        if (!$db) $db = self::getDB();
        $totalStrength = 0;
        // Player research (weapon tech, shield tech, armor tech) should ideally modify this.
        // For now, it's raw strength.
        // $playerResearchLevels = PlayerResearch::getResearchLevelsByPlayerId($playerId, $db); // $playerId would be needed

        foreach ($shipsDataForFleet as $shipData) {
            if (!isset($shipData['ship_type_id']) || !is_numeric($shipData['ship_type_id']) || $shipData['quantity'] <= 0) {
                continue; 
            }
            $shipType = ShipType::getById($shipData['ship_type_id'], $db);
            if ($shipType && isset($shipType->weapon_power) && $shipData['quantity'] > 0) {
                $shipStrength = $shipType->weapon_power;
                // Example: Apply weapon tech bonus
                // if (isset($playerResearchLevels['weapon_technology'])) {
                //    $weaponTechLevel = (int)$playerResearchLevels['weapon_technology'];
                //    $shipStrength *= (1 + ($weaponTechLevel * 0.10)); // 10% bonus per level
                // }
                $totalStrength += $shipStrength * $shipData['quantity'];
            }
        }
        return $totalStrength;
    }

    // Send a fleet on a mission
    public static function sendFleet($playerId, $startPlanetId, $targetGalaxy, $targetSystem, $targetPosition, $missionType, array $shipsToSend, $resources = [], $blockadeDurationHours = null) {
        $db = self::getDB();
        
        $db->beginTransaction();
        
        try {
            // 1. Validate input
            if (empty($shipsToSend)) {
                throw new Exception("No ships selected for the fleet.");
            }
            if ($startPlanetId <= 0 || $playerId <= 0) {
                throw new Exception("Invalid player or start planet ID.");
            }
            if ($targetGalaxy <= 0 || $targetSystem <= 0 || $targetPosition <= 0) {
                throw new Exception("Invalid target coordinates.");
            }

            // 2. Get start planet details
            $startPlanet = Planet::getById($startPlanetId);
            if (!$startPlanet || $startPlanet->player_id != $playerId) {
                throw new Exception("Start planet not found or does not belong to the player.");
            }

            // 3. Determine target planet ID (if exists)
            $targetPlanet = Planet::getByCoords($targetGalaxy, $targetSystem, $targetPosition);
            $targetPlanetId = $targetPlanet ? $targetPlanet->id : null;

            // 4. Check if player has enough ships and resources on the start planet
            $fleetSpeed = PHP_INT_MAX;
            $fleetCargoCapacity = 0;
            $shipsDataForFleet = []; // To store ship_type_id and quantity for fuel calc and fleet_ships table

            foreach ($shipsToSend as $shipTypeId => $quantity) {
                if ($quantity <= 0) continue;
                $playerShip = PlayerShip::getByPlanetAndType($startPlanetId, $shipTypeId);
                if (!$playerShip || $playerShip->quantity < $quantity) {
                    throw new Exception("Not enough ships of type ID {$shipTypeId} on the planet.");
                }
                $shipType = ShipType::getById($shipTypeId);
                if (!$shipType) {
                    throw new Exception("Invalid ship type ID {$shipTypeId}.");
                }
                $fleetSpeed = min($fleetSpeed, $shipType->speed);
                $fleetCargoCapacity += $shipType->cargo_capacity * $quantity;
                // Ensure all necessary ship data is collected for later use (e.g. fuel consumption, combat strength)
                $shipsDataForFleet[] = [
                    'ship_type_id' => $shipTypeId, 
                    'quantity' => $quantity, 
                    'fuel_consumption' => $shipType->fuel_consumption, // Already in existing code
                    'weapon_power' => $shipType->weapon_power ?? 0 // For combat strength calculation
                ];
            }
            if (empty($shipsDataForFleet)) {
                 throw new Exception("No valid ships to send in the fleet.");
            }


            // 5. Calculate distance, travel time, fuel
            $distance = self::calculateDistance($startPlanet->galaxy, $startPlanet->system, $startPlanet->position, $targetGalaxy, $targetSystem, $targetPosition);
            
            // Get universe speed (from config)
            $universeSpeed = defined('UNIVERSE_SPEED_FACTOR') ? UNIVERSE_SPEED_FACTOR : 1.0;
            if ($universeSpeed <= 0) $universeSpeed = 1.0; // Safety check

            // Calculate travel time using the refined method (passing target coordinates)
            // Corrected argument order: $db before $universeSpeed
            $travelTimeSeconds = self::calculateTravelTime($shipsDataForFleet, $distance, $playerId, $missionType, $targetGalaxy, $targetSystem, $targetPosition, $db, $universeSpeed);

            // Calculate fuel using the refined method
            $totalFuelNeeded = self::calculateFuelConsumption($shipsDataForFleet, $distance, $playerId, $db); 

            // Check and deduct resources (including fuel)
            $currentWasserstoff = $startPlanet->wasserstoff;
            if ($currentWasserstoff < $totalFuelNeeded) {
                throw new Exception("Not enough Wasserstoff for the journey. Required: {$totalFuelNeeded}, Available: {$currentWasserstoff}");
            }
            $startPlanet->wasserstoff -= $totalFuelNeeded;

            $totalCargoToSend = 0;
            if (!empty($resources)) {
                foreach ($resources as $resName => $resQuantity) {
                    if ($resQuantity <= 0) continue; // Skip if trying to send zero or negative resources

                    // Ensure the resource name is valid and exists on the planet object
                    if (!property_exists($startPlanet, $resName)) {
                        throw new Exception("Invalid resource name: {$resName}");
                    }
                    if ($startPlanet->{$resName} < $resQuantity) {
                        throw new Exception("Not enough {$resName} on the planet. Required: {$resQuantity}, Available: {$startPlanet->{$resName}}");
                    }
                    $startPlanet->{$resName} -= $resQuantity;
                    $totalCargoToSend += $resQuantity;
                }
            }
            if ($totalCargoToSend > $fleetCargoCapacity) {
                throw new Exception("Not enough cargo capacity in the fleet. Required: {$totalCargoToSend}, Available: {$fleetCargoCapacity}");
            }

            // Update start planet resources (including deducted fuel)
            $sql = "UPDATE planets SET eisen = :eisen, silber = :silber, uderon = :uderon, wasserstoff = :wasserstoff, energie = :energie 
                    WHERE id = :planet_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':eisen', $startPlanet->eisen);
            $stmt->bindParam(':silber', $startPlanet->silber);
            $stmt->bindParam(':uderon', $startPlanet->uderon);
            $stmt->bindParam(':wasserstoff', $startPlanet->wasserstoff);
            $stmt->bindParam(':energie', $startPlanet->energie); 
            $stmt->bindParam(':planet_id', $startPlanetId, PDO::PARAM_INT);
            if (!$stmt->execute()) {
                throw new PDOException("Failed to update planet resources: " . implode(" ", $stmt->errorInfo()));
            }

            // 6. Deduct ships from start planet
            foreach ($shipsToSend as $shipTypeId => $quantity) {
                if ($quantity <= 0) continue; // Should have been caught earlier, but good for safety
                // Assuming PlayerShip model has a method to deduct ships
                // If not, this would be direct SQL UPDATE on player_ships table
                $deducted = PlayerShip::deductShipsFromPlanet($startPlanetId, $shipTypeId, $quantity, $db);
                if (!$deducted) {
                    // This exception should ideally include which ship type failed
                    throw new Exception("Failed to deduct ships of type ID {$shipTypeId} from planet ID {$startPlanetId}. They might have been moved or destroyed.");
                }
            }
            
            // Calculate blockade strength if mission is blockade
            $calculatedBlockadeStrength = null;
            if ($missionType === 'blockade') {
                // Using the general combat strength as blockade strength.
                // This might need a more specific calculation if blockade mechanics differ.
                $calculatedBlockadeStrength = self::calculateFleetCombatStrength($shipsDataForFleet, $db);
                if ($blockadeDurationHours === null || $blockadeDurationHours <= 0) {
                    throw new Exception("Blockade mission requires a valid duration.");
                }
            }

            // 7. Create fleet entry
            $startTime = time();
            $arrivalTime = $startTime + $travelTimeSeconds;
            
            $sql = "INSERT INTO fleets (player_id, start_planet_id, target_planet_id, target_galaxy, target_system, target_position, mission_type, start_time, arrival_time, eisen_cargo, silber_cargo, uderon_cargo, wasserstoff_cargo, energie_cargo, blockade_duration_hours, blockade_strength)
                    VALUES (:player_id, :start_planet_id, :target_planet_id, :target_galaxy, :target_system, :target_position, :mission_type, FROM_UNIXTIME(:start_time), FROM_UNIXTIME(:arrival_time), :eisen, :silber, :uderon, :wasserstoff, :energie, :blockade_duration, :blockade_strength)";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->bindParam(':start_planet_id', $startPlanetId, PDO::PARAM_INT);
            $stmt->bindParam(':target_planet_id', $targetPlanetId, $targetPlanetId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindParam(':target_galaxy', $targetGalaxy, PDO::PARAM_INT);
            $stmt->bindParam(':target_system', $targetSystem, PDO::PARAM_INT);
            $stmt->bindParam(':target_position', $targetPosition, PDO::PARAM_INT);
            $stmt->bindParam(':mission_type', $missionType, PDO::PARAM_STR);
            $stmt->bindParam(':start_time', $startTime, PDO::PARAM_INT);
            $stmt->bindParam(':arrival_time', $arrivalTime, PDO::PARAM_INT);
            $stmt->bindParam(':eisen', $resources['eisen'] ?? 0);
            $stmt->bindParam(':silber', $resources['silber'] ?? 0);
            $stmt->bindParam(':uderon', $resources['uderon'] ?? 0);
            $stmt->bindParam(':wasserstoff', $resources['wasserstoff'] ?? 0); 
            $stmt->bindParam(':energie', $resources['energie'] ?? 0);
            $stmt->bindParam(':blockade_duration', $blockadeDurationHours, $blockadeDurationHours ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindParam(':blockade_strength', $calculatedBlockadeStrength, $calculatedBlockadeStrength !== null ? PDO::PARAM_INT : PDO::PARAM_NULL); // Use calculated strength
            
            if (!$stmt->execute()) {
                throw new PDOException("Failed to create fleet entry: " . implode(" ", $stmt->errorInfo()));
            }
            $fleetId = $db->lastInsertId();

            // 8. Add ships to fleet_ships table
            $sql = "INSERT INTO fleet_ships (fleet_id, ship_type_id, quantity) VALUES (:fleet_id, :ship_type_id, :quantity)";
            $stmtShip = $db->prepare($sql); // Use different var name to avoid conflict
            foreach ($shipsDataForFleet as $shipData) { // Use $shipsDataForFleet which contains validated ships
                if ($shipData['quantity'] <= 0) continue;
                $stmtShip->bindParam(':fleet_id', $fleetId, PDO::PARAM_INT);
                $stmtShip->bindParam(':ship_type_id', $shipData['ship_type_id'], PDO::PARAM_INT);
                $stmtShip->bindParam(':quantity', $shipData['quantity'], PDO::PARAM_INT);
                if (!$stmtShip->execute()) {
                    throw new PDOException("Failed to add ships to fleet_ships table: " . implode(" ", $stmtShip->errorInfo()));
                }
            }

            $db->commit();
            return $fleetId;

        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Database error in sendFleet: " . $e->getMessage() . " SQL State: " . $e->getCode() . " Driver Code: " . (isset($e->errorInfo[1]) ? $e->errorInfo[1] : 'N/A') . " Driver Message: " . (isset($e->errorInfo[2]) ? $e->errorInfo[2] : 'N/A'));
            throw $e; // Re-throw to inform caller
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error in sendFleet: " . $e->getMessage());
            throw $e; // Re-throw to inform caller
        }
    }
    
    // Process fleet arrivals (called by a cron job or on page load)
    public static function processFleets() {
        $db = self::getDB();
        $processedExpiredBlockades = 0;
        $processedExpiredStations = 0; // Initialize counter for expired stationing missions
        
        // Process expired blockades FIRST
        $sqlExpiredBlockades = "SELECT p.id as planet_id, p.name as planet_name, p.blockading_fleet_id, p.blockade_player_id, pl.username as blockade_player_name 
                                FROM planets p
                                LEFT JOIN players pl ON p.blockade_player_id = pl.id
                                WHERE p.blockade_until IS NOT NULL AND p.blockade_until <= NOW()";
        $stmtExpiredBlockades = $db->prepare($sqlExpiredBlockades);
        $stmtExpiredBlockades->execute();
        $expiredBlockadedPlanets = $stmtExpiredBlockades->fetchAll(PDO::FETCH_OBJ);

        foreach ($expiredBlockadedPlanets as $planet) {
            $db->beginTransaction();
            try {
                $blockadingFleetId = $planet->blockading_fleet_id;
                $blockadingPlayerId = $planet->blockade_player_id;
                $blockadingPlayerName = $planet->blockade_player_name ?? "Player {$blockadingPlayerId}";
                $planetName = $planet->planet_name ?? "ID {$planet->planet_id}";


                // Reset blockade on planet
                $sqlResetBlockade = "UPDATE planets 
                                     SET blockade_player_id = NULL, 
                                         blockade_fleet_id = NULL, 
                                         blockade_until = NULL,
                                         blockade_strength = NULL
                                     WHERE id = :planet_id";
                $stmtResetBlockade = $db->prepare($sqlResetBlockade);
                $stmtResetBlockade->bindParam(':planet_id', $planet->planet_id, PDO::PARAM_INT);
                $stmtResetBlockade->execute();

                if ($blockadingFleetId) {
                    $fleetToReturn = self::getFleetById($blockadingFleetId); 
                    if ($fleetToReturn && !$fleetToReturn->is_returning && !$fleetToReturn->is_completed) {
                         self::setFleetToReturn($blockadingFleetId, $db, "Blockade expired on planet {$planetName}.");
                         // Notification for the blockading player
                         NotificationService::createNotification(
                             $blockadingPlayerId, 
                             "Blockade Expired", 
                             "Your blockade on planet {$planetName} has expired. Fleet ID {$blockadingFleetId} is returning.", 
                             "info"
                         );
                         // Notification for the planet owner (if any)
                         $planetOwnerData = Planet::getById($planet->planet_id, $db);
                         if ($planetOwnerData && $planetOwnerData->player_id) {
                             NotificationService::createNotification(
                                 $planetOwnerData->player_id,
                                 "Blockade Lifted",
                                 "The blockade by {$blockadingPlayerName} on your planet {$planetName} has expired.",
                                 "info"
                             );
                         }
                    }
                }
                $db->commit();
                $processedExpiredBlockades++;
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error processing expired blockade for planet {$planet->planet_id}: " . $e->getMessage());
            }
        }
        if ($processedExpiredBlockades > 0) {
            // This log can be noisy, consider logging only if errors or specific conditions met.
            // error_log("Processed {$processedExpiredBlockades} expired blockades.");
        }

        // Process expired stationing missions
        $sqlExpiredStation = "SELECT * FROM fleets 
                              WHERE mission_type = 'station' 
                                AND is_completed = 0 AND is_returning = 0 
                                AND blockade_duration_hours IS NOT NULL 
                                AND TIMESTAMPADD(HOUR, blockade_duration_hours, arrival_time) <= NOW()";
        $stmtExpiredStation = $db->prepare($sqlExpiredStation);
        $stmtExpiredStation->execute();
        $expiredStationFleets = $stmtExpiredStation->fetchAll(PDO::FETCH_OBJ);

        foreach ($expiredStationFleets as $stationedFleet) {
            $db->beginTransaction();
            try {
                $targetPlanet = Planet::getById($stationedFleet->target_planet_id, $db);
                $planetName = $targetPlanet ? $targetPlanet->name : "target coordinates";
                $coords = "{$stationedFleet->target_galaxy}:{$stationedFleet->target_system}:{$stationedFleet->target_position}";

                self::setFleetToReturn($stationedFleet->id, $db, "Stationing period at {$planetName} ({$coords}) has ended.");
                NotificationService::createNotification(
                    $stationedFleet->player_id,
                    "Stationing Ended",
                    "Your stationing period at {$planetName} ({$coords}) has ended. Fleet ID {$stationedFleet->id} is returning.",
                    "info"
                );
                
                if ($targetPlanet && $targetPlanet->player_id && $targetPlanet->player_id != $stationedFleet->player_id) {
                     NotificationService::createNotification(
                        $targetPlanet->player_id,
                        "Allied Fleet Departing",
                        "The allied fleet (ID {$stationedFleet->id}) from player " . Player::getUsernameById($stationedFleet->player_id, $db) . " stationed at your planet {$planetName} ({$coords}) is now returning as its stationing period ended.",
                        "info"
                    );
                }
                $db->commit();
                $processedExpiredStations++;
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error processing expired stationing for fleet {$stationedFleet->id}: " . $e->getMessage());
            }
        }
        if ($processedExpiredStations > 0) {
            // error_log("Processed {$processedExpiredStations} expired stationing missions.");
        }


        // Find fleets that have arrived at their destination
        $sql = "SELECT * FROM fleets 
                WHERE arrival_time <= NOW() AND is_returning = 0 AND is_completed = 0";
                
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $arrivedFleets = $stmt->fetchAll(PDO::FETCH_OBJ);
        
        foreach ($arrivedFleets as $fleet) {
            $db->beginTransaction();
            $missionAbortedDueToBlockade = false; 
            
            if ($fleet->mission_type !== 'blockade' && $fleet->mission_type !== 'station') { // Stationing on own planet might bypass blockade
                $targetPlanetForCheck = Planet::getByCoordinates($fleet->target_galaxy, $fleet->target_system, $fleet->target_position, $db);
                if ($targetPlanetForCheck && $targetPlanetForCheck->id) { 
                    $blockadeDetails = Planet::getActiveBlockadeDetails($targetPlanetForCheck->id, $db);
                    if ($blockadeDetails) {
                        $fleetOwnerData = Player::getPlayerData($fleet->player_id, $db);
                        $fleetOwnerAllianceId = $fleetOwnerData->alliance_id ?? null;
                        
                        $blockaderIsSelfOrAlly = ($fleet->player_id == $blockadeDetails['blockade_player_id']) ||
                                         ($blockadeDetails['blockade_alliance_id'] !== null && 
                                          $fleetOwnerAllianceId !== null && 
                                          $fleetOwnerAllianceId == $blockadeDetails['blockade_alliance_id']);

                        if (!$blockaderIsSelfOrAlly) {
                            $missionAbortedDueToBlockade = true;
                            $coords = "{$fleet->target_galaxy}:{$fleet->target_system}:{$fleet->target_position}";
                            NotificationService::createNotification($fleet->player_id, "Mission Aborted", "Fleet to {$coords} aborted. Target is blockaded by a hostile force.", "error");
                            self::setFleetToReturn($fleet->id, $db, "Aborted due to hostile blockade at target.");
                        }
                    }
                }
            } else if ($fleet->mission_type === 'station' ) { // Special check for stationing
                 $targetPlanetForCheck = Planet::getByCoordinates($fleet->target_galaxy, $fleet->target_system, $fleet->target_position, $db);
                 if ($targetPlanetForCheck && $targetPlanetForCheck->id && $targetPlanetForCheck->player_id != $fleet->player_id) { // If stationing on NOT OWN planet
                    $blockadeDetails = Planet::getActiveBlockadeDetails($targetPlanetForCheck->id, $db);
                    if ($blockadeDetails) { // And it's blockaded
                        $fleetOwnerData = Player::getPlayerData($fleet->player_id, $db);
                        $fleetOwnerAllianceId = $fleetOwnerData->alliance_id ?? null;
                        
                        $blockaderIsSelfOrAlly = ($fleet->player_id == $blockadeDetails['blockade_player_id']) ||
                                         ($blockadeDetails['blockade_alliance_id'] !== null && 
                                          $fleetOwnerAllianceId !== null && 
                                          $fleetOwnerAllianceId == $blockadeDetails['blockade_alliance_id']);
                        if (!$blockaderIsSelfOrAlly) { // By a non-ally
                            $missionAbortedDueToBlockade = true;
                            $coords = "{$fleet->target_galaxy}:{$fleet->target_system}:{$fleet->target_position}";
                            NotificationService::createNotification($fleet->player_id, "Station Mission Aborted", "Fleet to {$coords} aborted. Target is blockaded by a hostile force.", "error");
                            self::setFleetToReturn($fleet->id, $db, "Station aborted due to hostile blockade at target.");
                        }
                    }
                 }
            }


            if ($missionAbortedDueToBlockade) {
                $db->commit();
                continue; 
            }

            try {
                $isCurrentlyBlockading = false;
                if ($fleet->mission_type === 'blockade') {
                    $targetPlanetData = Planet::getByCoordinates($fleet->target_galaxy, $fleet->target_system, $fleet->target_position, $db); // Corrected target_position
                    if ($targetPlanetData && $targetPlanetData->blockade_fleet_id == $fleet->id && strtotime($targetPlanetData->blockade_until) > time()) { // Corrected time comparison
                        $isCurrentlyBlockading = true; // This fleet is ALREADY the active blockader and blockade is ongoing
                    }
                }

                if (!$isCurrentlyBlockading) { // Only process if not already an active, ongoing blockade by this fleet
                    switch ($fleet->mission_type) {
                        case 'transport':
                            self::processTransportMission($fleet, $db); // Pass $db
                            break;
                        case 'deploy':
                            self::processDeployMission($fleet, $db);
                        break;
                        case 'attack':
                            self::processAttackMission($fleet, $db);
                            break;
                        case 'espionage':
                            self::processEspionageMission($fleet, $db); 
                            break;
                        case 'colonize':
                            self::processColonizeMission($fleet, $db); // Pass $db
                            break;
                        case 'arkon': // Arkon mission now calls processDestroyMission
                            self::processDestroyMission($fleet, $db);
                            break;
                        case 'invasion':
                            self::processInvasionMission($fleet, $db);
                            break;
                        case 'blockade': 
                            self::processBlockadeMission($fleet, $db);
                            break;
                        case 'station': 
                            self::processStationMission($fleet, $db);
                            break;
                        case 'destroy': 
                            self::processDestroyMission($fleet, $db);
                            break;
                        default:
                            error_log("Unknown mission type for fleet {$fleet->id}: {$fleet->mission_type}");
                            self::setFleetToReturn($fleet->id, $db, "Unknown mission type.");
                    }
                }
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error processing fleet {$fleet->id} (mission: {$fleet->mission_type}): " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
                try {
                    // Attempt to set fleet to return even if other operations failed
                    self::setFleetToReturn($fleet->id, $db, "Error during mission processing.");
                } catch (Exception $innerE) {
                    error_log("Critical error: Failed to set fleet {$fleet->id} to return after an exception: " . $innerE->getMessage() . " Trace: " . $innerE->getTraceAsString());
                    // Potentially add more robust error handling here, like marking the fleet as needing manual intervention
                }
            }
        } // This closes the foreach loop for $arrivedFleets (previously $fleetsToProcess)

        // Process fleets that have returned to their origin
        $sqlReturning = "SELECT * FROM fleets WHERE return_time <= NOW() AND is_returning = 1 AND is_completed = 0";
        $stmtReturning = $db->prepare($sqlReturning);
        $stmtReturning->execute();
        $returningFleets = $stmtReturning->fetchAll(PDO::FETCH_OBJ);

        foreach ($returningFleets as $rFleet) {
            $db->beginTransaction();
            try {
                $startPlanet = Planet::getById($rFleet->start_planet_id, $db);
                if (!$startPlanet) {
                    throw new Exception("Origin planet ID {$rFleet->start_planet_id} not found for returning fleet ID {$rFleet->id}. Cannot dock ships/resources.");
                }

                // 1. Add ships back to the start_planet_id
                $fleetDetails = self::getFleetDetails($rFleet->id); // Fetches fleet record and ships
                if ($fleetDetails && !empty($fleetDetails['ships'])) {
                    foreach ($fleetDetails['ships'] as $shipInFleet) {
                        if ($shipInFleet->quantity > 0) {
                            PlayerShip::addShipsToPlanet($rFleet->start_planet_id, $shipInFleet->ship_type_id, $shipInFleet->quantity, $db);
                        }
                    }
                }

                // 2. Add any carried resources back to the start_planet_id
                // Use the fleet object $rFleet for cargo, as it's the most up-to-date from the DB for this fleet instance
                if (($rFleet->eisen_cargo ?? 0) > 0 || ($rFleet->silber_cargo ?? 0) > 0 || ($rFleet->uderon_cargo ?? 0) > 0 || ($rFleet->wasserstoff_cargo ?? 0) > 0 || ($rFleet->energie_cargo ?? 0) > 0) {
                    $updatePlanetResSql = "UPDATE planets SET 
                                            eisen = eisen + :eisen, 
                                            silber = silber + :silber, 
                                            uderon = uderon + :uderon, 
                                            wasserstoff = wasserstoff + :wasserstoff, 
                                            energie = energie + :energie 
                                          WHERE id = :planet_id";
                    $stmtUpdateRes = $db->prepare($updatePlanetResSql);
                    $stmtUpdateRes->bindValue(':eisen', $rFleet->eisen_cargo ?? 0, PDO::PARAM_INT);
                    $stmtUpdateRes->bindValue(':silber', $rFleet->silber_cargo ?? 0, PDO::PARAM_INT);
                    $stmtUpdateRes->bindValue(':uderon', $rFleet->uderon_cargo ?? 0, PDO::PARAM_INT);
                    $stmtUpdateRes->bindValue(':wasserstoff', $rFleet->wasserstoff_cargo ?? 0, PDO::PARAM_INT);
                    $stmtUpdateRes->bindValue(':energie', $rFleet->energie_cargo ?? 0, PDO::PARAM_INT);
                    $stmtUpdateRes->bindParam(':planet_id', $rFleet->start_planet_id, PDO::PARAM_INT);
                    $stmtUpdateRes->execute();
                }

                // 3. Mark fleet as completed and clear cargo (as it's now on planet)
                $sqlComplete = "UPDATE fleets SET 
                                is_completed = 1, 
                                eisen_cargo = 0, silber_cargo = 0, uderon_cargo = 0, wasserstoff_cargo = 0, energie_cargo = 0,
                                notes = CONCAT(COALESCE(notes, ''), ' | Returned to origin and unloaded.') 
                                WHERE id = :fleet_id";
                $stmtComplete = $db->prepare($sqlComplete);
                $stmtComplete->bindParam(':fleet_id', $rFleet->id, PDO::PARAM_INT);
                $stmtComplete->execute();
                
                // 4. Notification
                $planetName = $startPlanet->name ?? "Origin ID {$rFleet->start_planet_id}";
                $coords = $startPlanet ? "({$startPlanet->galaxy}:{$startPlanet->system}:{$startPlanet->position})" : "";
                NotificationService::createNotification($rFleet->player_id, "Fleet Arrived Home", "Your fleet (ID: {$rFleet->id}) has returned to {$planetName} {$coords} and unloaded its ships and cargo.", "info");

                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error processing returning fleet {$rFleet->id}: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
                // Potentially mark fleet for attention if it can't be processed
                // For now, just log. If it fails, it will be picked up in the next cron run.
            }
        } // End foreach returningFleets


        return true;
    }

    /**
     * Sets a fleet to return to its origin planet.
     * This is a common fallback action when a mission cannot be completed or encounters an error.
     *
     * @param int $fleetId The ID of the fleet.
     * @param PDO $db The database connection.
     * @param string|null $reason Optional reason for the return, logged for debugging.
     * @return bool True on success, false on failure.
     */
    public static function setFleetToReturn(int $fleetId, PDO $db, ?string $reason = null): bool
    {
        $fleet = self::getFleetById($fleetId);
        if (!$fleet) {
            error_log("setFleetToReturn: Fleet ID {$fleetId} not found.");
            return false;
        }

        if ($fleet->is_returning || $fleet->is_completed) {
            if (strpos($fleet->notes ?? '', $reason ?? '') === false) {
                 $updateNoteSql = "UPDATE fleets SET notes = CONCAT(COALESCE(notes, ''), ' | Attempted return (already returning/done): ', :note) WHERE id = :fleet_id";
                 $stmtUpdateNote = $db->prepare($updateNoteSql);
                 $stmtUpdateNote->bindParam(':note', $reason, PDO::PARAM_STR);
                 $stmtUpdateNote->bindParam(':fleet_id', $fleetId, PDO::PARAM_INT);
                 $stmtUpdateNote->execute();
            }
            return true;
        }

        $originalStartTime = strtotime($fleet->start_time);
        $originalArrivalTimeAtTarget = strtotime($fleet->arrival_time);
        $currentTime = time(); 
        $timeElapsed = $currentTime - $originalStartTime;
        $originalTravelDuration = $originalArrivalTimeAtTarget - $originalStartTime;
        if ($originalTravelDuration <= 0) $originalTravelDuration = 1; // Avoid division by zero later

        $returnTravelDuration = 0;
        $returnDistance = 0;

        $originPlanetForReturn = Planet::getById($fleet->start_planet_id, $db);
        $fleetShipsData = self::getFleetDetails($fleetId)['ships'] ?? [];

        if (!$originPlanetForReturn) {
            error_log("setFleetToReturn: Origin planet {$fleet->start_planet_id} not found for fleet {$fleet->id}. Cannot calculate return details accurately.");
            // Fallback: make return duration same as original travel duration or time elapsed
            $returnTravelDuration = ($currentTime < $originalArrivalTimeAtTarget && $timeElapsed > 0) ? $timeElapsed : $originalTravelDuration;
            if ($returnTravelDuration <=0) $returnTravelDuration = 1;
            // $returnDistance remains 0, fuel calculation will be affected.
        } else {
            $originalDistance = self::calculateDistance(
                $originPlanetForReturn->galaxy, $originPlanetForReturn->system, $originPlanetForReturn->position,
                $fleet->target_galaxy, $fleet->target_system, $fleet->target_position
            );

            $universeSpeed = defined('UNIVERSE_SPEED_FACTOR') ? UNIVERSE_SPEED_FACTOR : 1.0;
            if ($universeSpeed <= 0) $universeSpeed = 1.0;

            if ($currentTime < $originalArrivalTimeAtTarget) { // Mid-flight recall
                $distanceTraveledFraction = ($originalTravelDuration > 0) ? ($timeElapsed / $originalTravelDuration) : 1;
                $returnDistance = floor($distanceTraveledFraction * $originalDistance);

                if (!empty($fleetShipsData) && $returnDistance > 0) {
                    $returnTravelDuration = self::calculateTravelTime(
                        $fleetShipsData, $returnDistance, $fleet->player_id, "return", // mission type "return"
                        $originPlanetForReturn->galaxy, $originPlanetForReturn->system, $originPlanetForReturn->position, // target coords for return
                        $db, $universeSpeed
                    );
                } else { // Fallback if no ships or zero distance
                    $returnTravelDuration = $timeElapsed > 0 ? $timeElapsed : 1; 
                }
            } else { // Recalled at or after target arrival (or mission completion leading to return)
                $returnDistance = $originalDistance; // Full distance back
                if (!empty($fleetShipsData) && $returnDistance > 0) {
                    $returnTravelDuration = self::calculateTravelTime(
                        $fleetShipsData, $returnDistance, $fleet->player_id, "return",
                        $originPlanetForReturn->galaxy, $originPlanetForReturn->system, $originPlanetForReturn->position,
                        $db, $universeSpeed
                    );
                } else { // Fallback
                    $returnTravelDuration = $originalTravelDuration > 0 ? $originalTravelDuration : 1;
                }
            }
            if ($returnTravelDuration <= 0) $returnTravelDuration = 1; // Ensure minimum 1 second
        }
        
        $returnArrivalTime = $currentTime + $returnTravelDuration;

        // Calculate fuel needed for the return trip using $returnDistance
        $fuelNeededForReturn = 0;
        if ($returnDistance > 0 && !empty($fleetShipsData)) {
            $fuelNeededForReturn = self::calculateFuelConsumption($fleetShipsData, $returnDistance, $fleet->player_id, $db);
        } elseif ($returnDistance == 0) {
             $fuelNeededForReturn = 0; // No distance, no fuel.
        } else {
            error_log("setFleetToReturn: Could not calculate return fuel for fleet ID {$fleetId} (distance: {$returnDistance}, ships empty: " . (empty($fleetShipsData) ? 'yes' : 'no') . "). Assuming 0 fuel needed.");
            $fuelNeededForReturn = 0;
        }
        

        $currentWasserstoffInCargo = $fleet->wasserstoff_cargo ?? 0;
        $note = $reason ?? "Returning to origin.";

        if ($currentWasserstoffInCargo < $fuelNeededForReturn) {
            error_log("setFleetToReturn: Not enough Wasserstoff for return journey for fleet ID {$fleetId}. Required: {$fuelNeededForReturn}, Available: {$currentWasserstoffInCargo}. Fleet may be lost or cargo becomes negative.");
            $note .= " | WARNING: Not enough fuel for return ({$fuelNeededForReturn} needed, {$currentWasserstoffInCargo} available).";
            // Option: Don't deduct fuel if not enough, to avoid negative cargo. Or let it go negative and handle elsewhere.
            // For now, we will deduct, potentially making it negative. Game logic might need to handle this.
            // Or, better: only deduct what's available, and log the shortfall.
            // $fleet->wasserstoff_cargo -= $fuelNeededForReturn; // This could make it negative.
            // Let's assume for now that fuel is consumed, even if it means the fleet arrives with negative fuel (which is odd)
            // or simply doesn't make it (game rule). The current structure deducts.
            // A safer approach:
            $fuelToDeduct = min($currentWasserstoffInCargo, $fuelNeededForReturn);
            $fleet->wasserstoff_cargo -= $fuelToDeduct;
            if ($fuelToDeduct < $fuelNeededForReturn) {
                 $note .= " Shortfall of " . ($fuelNeededForReturn - $fuelToDeduct) . " fuel.";
            }
             $note .= " Fuel consumed for return: {$fuelToDeduct}.";

        } else {
            $fleet->wasserstoff_cargo -= $fuelNeededForReturn;
            $note .= " Fuel consumed for return: {$fuelNeededForReturn}.";
        }
        // Update fleet cargo in DB (only if changed)
        if (($fleet->wasserstoff_cargo ?? 0) != $currentWasserstoffInCargo) { // Check if cargo actually changed
            $stmtUpdateCargo = $db->prepare("UPDATE fleets SET wasserstoff_cargo = :wasserstoff_cargo WHERE id = :fleet_id");
            $stmtUpdateCargo->bindParam(':wasserstoff_cargo', $fleet->wasserstoff_cargo, PDO::PARAM_INT);
            $stmtUpdateCargo->bindParam(':fleet_id', $fleetId, PDO::PARAM_INT);
            $stmtUpdateCargo->execute();
        }


        $sql = "UPDATE fleets 
                SET is_returning = 1,
                    arrival_time = FROM_UNIXTIME(:current_time), 
                    return_time = FROM_UNIXTIME(:return_arrival_time),
                    notes = CONCAT(COALESCE(notes, ''), ' | ', :note) 
                WHERE id = :fleet_id AND is_completed = 0 AND is_returning = 0";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':current_time', $currentTime, PDO::PARAM_INT);
        $stmt->bindParam(':return_arrival_time', $returnArrivalTime, PDO::PARAM_INT);
        $stmt->bindParam(':note', $note, PDO::PARAM_STR);
        $stmt->bindParam(':fleet_id', $fleetId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                 $originPlanetName = $originPlanetForReturn ? $originPlanetForReturn->name : "Origin ID {$fleet->start_planet_id}";
                 $originCoords = $originPlanetForReturn ? "({$originPlanetForReturn->galaxy}:{$originPlanetForReturn->system}:{$originPlanetForReturn->position})" : "";
                 NotificationService::createNotification($fleet->player_id, "Fleet Returning", "Your fleet (ID: {$fleetId}, Mission: {$fleet->mission_type}) is now returning to {$originPlanetName} {$originCoords}. Reason: {$note}", "info");
                return true;
            } else {
                error_log("setFleetToReturn: Fleet ID {$fleetId} could not be updated to return, possibly already changed state or matched no rows.");
                $currentState = self::getFleetById($fleetId); // Re-check
                if ($currentState && ($currentState->is_returning || $currentState->is_completed)) {
                    return true; 
                }
                return false;
            }
        } else {
            error_log("setFleetToReturn: Failed to update fleet ID {$fleetId}. Error: " . implode(" ", $stmt->errorInfo()));
            return false;
        }
    }

    /**
     * Processes a transport mission upon fleet arrival.
     *
     * @param object $fleet The fleet object.
     * @param PDO $db The database connection.
     */
    private static function processTransportMission(object $fleet, PDO $db): void
    {
        $targetPlanet = Planet::getByCoordinates($fleet->target_galaxy, $fleet->target_system, $fleet->target_position, $db);
        $startPlanet = Planet::getById($fleet->start_planet_id, $db); // For notification purposes
        $fleetOwner = Player::getPlayerData($fleet->player_id, $db);
        $coords = "{$fleet->target_galaxy}:{$fleet->target_system}:{$fleet->target_position}";
        $returnReason = "Transport mission to {$coords} completed.";

        if ($targetPlanet) {
            $targetOwner = $targetPlanet->player_id ? Player::getPlayerData($targetPlanet->player_id, $db) : null;
            $canDeliver = false;
            $isAllianceDelivery = false;

            if (!$targetPlanet->player_id) { // Unowned planet
                $canDeliver = true; // Assuming transport to unowned planets drops resources (they might decay or be claimable)
                NotificationService::createNotification($fleet->player_id, "Transport Arrived", "Your fleet delivered resources to unowned coordinates {$coords}.", "info");
                 // Resources are added to the planet, even if unowned.
            } elseif ($targetPlanet->player_id == $fleet->player_id) { // Own planet
                $canDeliver = true;
            } else { // Another player's planet
                if ($fleetOwner && $fleetOwner->alliance_id && $targetOwner && $targetOwner->alliance_id && $fleetOwner->alliance_id == $targetOwner->alliance_id) {
                    $diplomacyStatus = Alliance::getDiplomacyStatus($fleetOwner->alliance_id, $targetOwner->alliance_id);
                    if ($diplomacyStatus === 'bündnis' || $diplomacyStatus === 'nap') { // Allow transport for NAP and Alliance
                        $canDeliver = true;
                        $isAllianceDelivery = true;
                    } else {
                        $returnReason = "Transport to {$coords} failed: Target planet {$targetPlanet->name} owned by a player with no transport agreement (Alliance/NAP).";
                        NotificationService::createNotification($fleet->player_id, "Transport Failed", $returnReason, "warning");
                    }
                } else {
                    $returnReason = "Transport to {$coords} failed: Target planet {$targetPlanet->name} owned by non-allied player.";
                    NotificationService::createNotification($fleet->player_id, "Transport Failed", $returnReason, "warning");
                }
            }

            if ($canDeliver) {
                $deliveredResources = [
                    'eisen' => $fleet->eisen_cargo,
                    'silber' => $fleet->silber_cargo,
                    'uderon' => $fleet->uderon_cargo,
                    'wasserstoff' => $fleet->wasserstoff_cargo,
                    'energie' => $fleet->energie_cargo,
                ];
                $taxedResources = [];

                if ($isAllianceDelivery && defined('self::ALLIANCE_TRANSPORT_TAX_PERCENTAGE') && self::ALLIANCE_TRANSPORT_TAX_PERCENTAGE > 0) {
                    // Apply tax only if it's an alliance member, not self, and not NAP
                    if ($targetPlanet->player_id != $fleet->player_id && Alliance::getDiplomacyStatus($fleetOwner->alliance_id, $targetOwner->alliance_id) === 'bündnis') {
                        foreach ($deliveredResources as $key => $value) {
                            if ($value > 0) {
                                $taxAmount = floor($value * self::ALLIANCE_TRANSPORT_TAX_PERCENTAGE);
                                if ($taxAmount > 0) {
                                    $taxedResources[$key] = $taxAmount;
                                    $deliveredResources[$key] -= $taxAmount;
                                    // TODO: Implement Alliance::addResourcesToTreasury($fleetOwner->alliance_id, [$key => $taxAmount], $db);
                                }
                            }
                        }
                    }
                }
                
                $updateSql = "UPDATE planets SET 
                                eisen = eisen + :eisen, 
                                silber = silber + :silber, 
                                uderon = uderon + :uderon, 
                                wasserstoff = wasserstoff + :wasserstoff, 
                                energie = energie + :energie 
                              WHERE id = :planet_id";
                $stmt = $db->prepare($updateSql);
                $stmt->bindParam(':eisen', $deliveredResources['eisen'], PDO::PARAM_INT);
                $stmt->bindParam(':silber', $deliveredResources['silber'], PDO::PARAM_INT);
                $stmt->bindParam(':uderon', $deliveredResources['uderon'], PDO::PARAM_INT);
                $stmt->bindParam(':wasserstoff', $deliveredResources['wasserstoff'], PDO::PARAM_INT);
                $stmt->bindParam(':energie', $deliveredResources['energie'], PDO::PARAM_INT);
                $stmt->bindParam(':planet_id', $targetPlanet->id, PDO::PARAM_INT);
                $stmt->execute();

                // Clear fleet cargo as it's delivered
                $updateFleetCargoSql = "UPDATE fleets SET eisen_cargo=0, silber_cargo=0, uderon_cargo=0, wasserstoff_cargo=0, energie_cargo=0 WHERE id = :fleet_id";
                $stmtFleetCargo = $db->prepare($updateFleetCargoSql);
                $stmtFleetCargo->bindParam(':fleet_id', $fleet->id, PDO::PARAM_INT);
                $stmtFleetCargo->execute();

                $resSummary = [];
                foreach ($deliveredResources as $key => $value) if ($value > 0) $resSummary[] = "{$value} " . ucfirst($key);
                $notificationMsg = "Your fleet delivered " . implode(', ', $resSummary) . " to planet {$targetPlanet->name} ({$coords}).";
                if (count($taxedResources) > 0) {
                    $taxSummary = [];
                    foreach ($taxedResources as $key => $value) if ($value > 0) $taxSummary[] = "{$value} " . ucfirst($key);
                    $notificationMsg .= " Alliance tax collected: " . implode(', ', $taxSummary) . ".";
                }

                NotificationService::createNotification($fleet->player_id, "Transport Successful", $notificationMsg, "success");
                if ($targetOwner && $targetOwner->id != $fleet->player_id) {
                    $senderUsername = $fleetOwner->username ?? "Player {$fleet->player_id}";
                    NotificationService::createNotification($targetOwner->id, "Resources Received", "You received " . implode(', ', $resSummary) . " from player {$senderUsername} at planet {$targetPlanet->name}.", "info");
                }
                $returnReason = "Transport to {$targetPlanet->name} ({$coords}) successful.";
            }
        } else {
            // Target planet does not exist (e.g., was destroyed or never colonized)
            $returnReason = "Transport mission to {$coords} failed: Target location is empty space. Resources returning.";
            NotificationService::createNotification($fleet->player_id, "Transport Failed", $returnReason, "warning");
        }
        self::setFleetToReturn($fleet->id, $db, $returnReason);
    }

    /**
     * Processes a deploy mission upon fleet arrival.
     *
     * @param object $fleet The fleet object.
     * @param PDO $db The database connection.
     */
    private static function processDeployMission(object $fleet, PDO $db): void
    {
        $targetPlanet = Planet::getByCoordinates($fleet->target_galaxy, $fleet->target_system, $fleet->target_position, $db);
        $startPlanet = Planet::getById($fleet->start_planet_id, $db); // For notification
        $fleetOwner = Player::getPlayerData($fleet->player_id, $db);
        $coords = "{$fleet->target_galaxy}:{$fleet->target_system}:{$fleet->target_position}";
        $returnReason = "Deployment to {$coords} completed.";
        $shipsReturnWithFleet = true; // By default, ships return if deployment fails

        if ($targetPlanet) {
            if ($targetPlanet->player_id == $fleetOwnerId) { // Own planet
                $fleetShips = self::getFleetDetails($fleet->id)['ships'] ?? [];
                $deployedShipSummary = [];

                if (!empty($fleetShips)) {
                    foreach ($fleetShips as $shipInFleet) {
                        if ($shipInFleet->quantity > 0) {
                            PlayerShip::addShipsToPlanet($targetPlanet->id, $shipInFleet->ship_type_id, $shipInFleet->quantity, $db);
                            $deployedShipSummary[] = "{$shipInFleet->quantity} x {$shipInFleet->name_de}";
                        }
                    }
                    // Remove ships from fleet_ships table as they are now on the planet
                    $sqlDeleteFleetShips = "DELETE FROM fleet_ships WHERE fleet_id = :fleet_id";
                    $stmtDelete = $db->prepare($sqlDeleteFleetShips);
                    $stmtDelete->bindParam(':fleet_id', $fleet->id, PDO::PARAM_INT);
                    $stmtDelete->execute();
                    $shipsReturnWithFleet = false; // Ships successfully deployed
                } else {
                    // No ships in fleet to deploy, but resources might still be transferred.
                }
                
                // Resources are also deployed (similar to transport)
                $deliveredResources = [
                    'eisen' => $fleet->eisen_cargo,
                    'silber' => $fleet->silber_cargo,
                    'uderon' => $fleet->uderon_cargo,
                    'wasserstoff' => $fleet->wasserstoff_cargo,
                    'energie' => $fleet->energie_cargo,
                ];
                if (array_sum($deliveredResources) > 0) { // Only update if there are resources
                    $updateSql = "UPDATE planets SET 
                                    eisen = eisen + :eisen, 
                                    silber = silber + :silber, 
                                    uderon = uderon + :uderon, 
                                    wasserstoff = wasserstoff + :wasserstoff, 
                                    energie = energie + :energie 
                                  WHERE id = :planet_id";
                    $stmtRes = $db->prepare($updateSql);
                    $stmtRes->bindParam(':eisen', $deliveredResources['eisen'], PDO::PARAM_INT);
                    $stmtRes->bindParam(':silber', $deliveredResources['silber'], PDO::PARAM_INT);
                    $stmtRes->bindParam(':uderon', $deliveredResources['uderon'], PDO::PARAM_INT);
                    $stmtRes->bindParam(':wasserstoff', $deliveredResources['wasserstoff'], PDO::PARAM_INT);
                    $stmtRes->bindParam(':energie', $deliveredResources['energie'], PDO::PARAM_INT);
                    $stmtRes->bindParam(':planet_id', $targetPlanet->id, PDO::PARAM_INT);
                    $stmtRes->execute();

                    // Clear fleet cargo
                    $updateFleetCargoSql = "UPDATE fleets SET eisen_cargo=0, silber_cargo=0, uderon_cargo=0, wasserstoff_cargo=0, energie_cargo=0 WHERE id = :fleet_id";
                    $stmtFleetCargo = $db->prepare($updateFleetCargoSql);
                    $stmtFleetCargo->bindParam(':fleet_id', $fleet->id, PDO::PARAM_INT);
                    $stmtFleetCargo->execute();
                }

                $notificationMsg = "Your fleet deployed ";
                if (!empty($deployedShipSummary)) {
                    $notificationMsg .= implode(', ', $deployedShipSummary);
                } else {
                    $notificationMsg .= "(no ships)";
                }
                $resSummary = [];
                foreach ($deliveredResources as $key => $value) if ($value > 0) $resSummary[] = "{$value} " . ucfirst($key);
                if (!empty($resSummary)) {
                    $notificationMsg .= " and resources (" . implode(', ', $resSummary) . ")";
                }
                $notificationMsg .= " to your planet {$targetPlanet->name} ({$coords}).";
                NotificationService::createNotification($fleet->player_id, "Deploy Successful", $notificationMsg, "success");
                $returnReason = "Deployment to {$targetPlanet->name} ({$coords}) successful. Fleet returning empty.";

            } else { // Not own planet (either unowned or other player's)
                 $returnReason = "Deployment to {$coords} failed: Target planet {$targetPlanet->name} is not owned by you. Ships and cargo returning.";
                 if ($targetPlanet->player_id === null) {
                     $returnReason = "Deployment to {$coords} failed: Target location is unowned space. Ships and cargo returning.";
                 }
                 NotificationService::createNotification($fleet->player_id, "Deploy Failed", $returnReason, "warning");
            }
        } else {
            $returnReason = "Deployment to {$coords} failed: Target location is empty space. Ships and cargo returning.";
            NotificationService::createNotification($fleet->player_id, "Deploy Failed", $returnReason, "warning");
        }
        
        // If ships were not successfully deployed, they remain in fleet_ships and return with the fleet.
        // If deployment was successful, fleet_ships was cleared, and fleet returns empty.
        // Resources also return if deployment failed.
        self::setFleetToReturn($fleet->id, $db, $returnReason);
    }

    /**
     * Processes an attack mission upon fleet arrival.
     *
     * @param object $fleet The fleet object.
     * @param PDO $db The database connection.
     */
    private static function processAttackMission(object $fleet, PDO $db): void
    {
        $targetPlanet = Planet::getByCoordinates($fleet->target_galaxy, $fleet->target_system, $fleet->target_position, $db);
        $coords = "{$fleet->target_galaxy}:{$fleet->target_system}:{$fleet->target_position}";
        $attackerPlayerId = $fleet->player_id;

        if (!$targetPlanet || !$targetPlanet->player_id) {
            // No planet or unowned planet at target coordinates
            $returnReason = "Attack on {$coords} aborted: No owned planet at target.";
            NotificationService::createNotification($attackerPlayerId, "Attack Aborted", $returnReason, "warning");
            self::setFleetToReturn($fleet->id, $db, $returnReason);
            return;
        }

        $defenderPlayerId = $targetPlanet->player_id;

       

        if ($attackerPlayerId == $defenderPlayerId) {
            $returnReason = "Attack on {$coords} aborted: Cannot attack your own planet.";
            NotificationService::createNotification($attackerPlayerId, "Attack Aborted", $returnReason, "error");
            self::setFleetToReturn($fleet->id, $db, $returnReason);
            return;
        }

        // Check for peace treaty / NAP with target player's alliance
        $attackerPlayer = Player::getPlayerData($attackerPlayerId, $db);
        $defenderPlayer = Player::getPlayerData($defenderPlayerId, $db);

        if ($attackerPlayer && $attackerPlayer->alliance_id && $defenderPlayer && $defenderPlayer->alliance_id) {
            $diplomacyStatus = Alliance::getDiplomacyStatus($attackerPlayer->alliance_id, $defenderPlayer->alliance_id);
            if ($diplomacyStatus === 'bündnis' || $diplomacyStatus === 'nap') {
                $returnReason = "Attack on {$coords} ({$defenderPlayer->username}) aborted: Your alliances have a non-aggression pact or are allied.";
                NotificationService::createNotification($attackerPlayerId, "Attack Aborted", $returnReason, "warning");
                self::setFleetToReturn($fleet->id, $db, $returnReason);
                return;
            }
        }

        // Check for Noob Protection
        if (Planet::checkNoobProtection($attackerPlayerId, $defenderPlayerId)) {
             $returnReason = "Attack on {$coords} ({$defenderPlayer->username}) aborted: Noob protection is active for the defender.";
             NotificationService::createNotification($attackerPlayerId, "Attack Aborted", $returnReason, "warning");
             self::setFleetToReturn($fleet->id, $db, $returnReason);
             return;
        }


        // Initiate combat
        try {
            $combatResult = Combat::initiateCombat($fleet->id, $targetPlanet->id, $db);
            // $combatResult will contain details like 'battle_report_id', 'winner', 'losses', etc.

            $battleReportId = $combatResult['battle_report_id'] ?? null;
            $winner = $combatResult['winner'] ?? 'none'; // attacker, defender, draw, none

            $attackerSurvived = false;
            $survivingAttackerShips = $combatResult['surviving_attacker_ships'] ?? [];
            if (!empty($survivingAttackerShips)) {
                 $attackerSurvived = true;
                 // Update fleet_ships with survivors
                 self::updateFleetShipsAfterCombat($fleet->id, $survivingAttackerShips, $db);
            }

            // Handle plunder if attacker wins
            $plunderedResources = [];
            if ($winner === 'attacker' && $attackerSurvived) {
                $fleetDetails = self::getFleetDetails($fleet->id); // Get updated fleet details (e.g. cargo capacity of survivors)
                $remainingCargoCapacity = 0;
                if ($fleetDetails && !empty($fleetDetails['ships'])){
                    foreach($fleetDetails['ships'] as $ship){
                        $remainingCargoCapacity += ($ship->cargo_capacity * $ship->quantity);
                    }
                }
                // Subtract already carried resources by the fleet (if any, though attack fleets usually are empty)
                $currentCargoLoad = self::getCurrentMaterialCargoLoad($fleetDetails['fleet']);
                $remainingCargoCapacity -= $currentCargoLoad;
                
                if ($remainingCargoCapacity > 0) {
                    $plunderedResources = Planet::plunderResources($targetPlanet->id, $remainingCargoCapacity, $db);
                    if (!empty($plunderedResources)) {
                        // Add plundered resources to the returning fleet's cargo
                        $updateCargoSql = "UPDATE fleets SET 
                                            eisen_cargo = eisen_cargo + :eisen, 
                                            silber_cargo = silber_cargo + :silber, 
                                            uderon_cargo = uderon_cargo + :uderon, 
                                            wasserstoff_cargo = wasserstoff_cargo + :wasserstoff, 
                                            energie_cargo = energie_cargo + :energie
                                          WHERE id = :fleet_id";
                        $stmtCargo = $db->prepare($updateCargoSql);
                        $stmtCargo->bindValue(':eisen', $plunderedResources['eisen'] ?? 0, PDO::PARAM_INT);
                        $stmtCargo->bindValue(':silber', $plunderedResources['silber'] ?? 0, PDO::PARAM_INT);
                        $stmtCargo->bindValue(':uderon', $plunderedResources['uderon'] ?? 0, PDO::PARAM_INT);
                        $stmtCargo->bindValue(':wasserstoff', $plunderedResources['wasserstoff'] ?? 0, PDO::PARAM_INT);
                        $stmtCargo->bindValue(':energie', $plunderedResources['energie'] ?? 0, PDO::PARAM_INT);
                        $stmtCargo->bindParam(':fleet_id', $fleet->id, PDO::PARAM_INT);
                        $stmtCargo->execute();
                    }
                }
            }

            $attackerMsg = "Attack on {$targetPlanet->name} ({$coords}) by {$attackerPlayer->username} completed. Winner: {$winner}.";
            if ($battleReportId) $attackerMsg .= " Battle Report ID: {$battleReportId}.";
            if (!empty($plunderedResources)) {
                $plunderSummary = [];
                foreach($plunderedResources as $res => $qty) if ($qty > 0) $plunderSummary[] = "{$qty} {$res}";
                if(!empty($plunderSummary)) $attackerMsg .= " Plundered: " . implode(", ", $plunderSummary) . ".";
            }
            NotificationService::createNotification($attackerPlayerId, "Attack Report", $attackerMsg, $winner === 'attacker' ? "success" : ($winner === 'defender' ? "error" : "warning"));

            $defenderMsg = "Your planet {$targetPlanet->name} ({$coords}) was attacked by {$attackerPlayer->username}. Winner: {$winner}.";
            if ($battleReportId) $defenderMsg .= " Battle Report ID: {$battleReportId}.";
            NotificationService::createNotification($defenderPlayerId, "Defense Report", $defenderMsg, $winner === 'defender' ? "success" : ($winner === 'attacker' ? "error" : "warning"));

            if ($attackerSurvived) {
                $returnReason = "Attack on {$targetPlanet->name} completed. Returning with survivors.";
                self::setFleetToReturn($fleet->id, $db, $returnReason);
            } else {
                // Attacker fleet destroyed, mark as completed (no return)
                $sqlComplete = "UPDATE fleets SET is_completed = 1, notes = CONCAT(COALESCE(notes, ''), ' | Fleet destroyed in attack.') WHERE id = :fleet_id";
                $stmtComplete = $db->prepare($sqlComplete);
                $stmtComplete->bindParam(':fleet_id', $fleet->id, PDO::PARAM_INT);
                $stmtComplete->execute();
                NotificationService::createNotification($attackerPlayerId, "Fleet Destroyed", "Your attacking fleet (ID: {$fleet->id}) was completely destroyed at {$targetPlanet->name} ({$coords}).", "error");
            }

        } catch (Exception $e) {
            error_log("Error during attack mission processing for fleet {$fleet->id}: " . $e->getMessage());
            $returnReason = "Attack on {$coords} encountered an error: " . $e->getMessage();
            NotificationService::createNotification($attackerPlayerId, "Attack Error", $returnReason, "error");
            // Ensure fleet returns even if combat logic fails catastrophically
            self::setFleetToReturn($fleet->id, $db, "Error during attack processing, returning.");
        }
    }

    /**
     * Helper function to update fleet ships after combat based on survivor data.
     *
     * @param int $fleetId
     * @param array $survivingShipsData Array of ['ship_type_id' => id, 'quantity' => count]
     * @param PDO $db
     */
    private static function updateFleetShipsAfterCombat(int $fleetId, array $survivingShipsData, PDO $db): void
    {
        // First, clear existing ships for the fleet to avoid duplicates or old entries
        $sqlDelete = "DELETE FROM fleet_ships WHERE fleet_id = :fleet_id";
        $stmtDelete = $db->prepare($sqlDelete);
        $stmtDelete->bindParam(':fleet_id', $fleetId, PDO::PARAM_INT);
        $stmtDelete->execute();

        if (empty($survivingShipsData)) {
            return; // No survivors to add
        }

        // Add surviving ships
        $sqlInsert = "INSERT INTO fleet_ships (fleet_id, ship_type_id, quantity) VALUES (:fleet_id, :ship_type_id, :quantity)";
        $stmtInsert = $db->prepare($sqlInsert);
        foreach ($survivingShipsData as $shipData) {
            if (isset($shipData['ship_type_id']) && isset($shipData['quantity']) && $shipData['quantity'] > 0) {
                $stmtInsert->bindParam(':fleet_id', $fleetId, PDO::PARAM_INT);
                $stmtInsert->bindParam(':ship_type_id', $shipData['ship_type_id'], PDO::PARAM_INT);
                $stmtInsert->bindParam(':quantity', $shipData['quantity'], PDO::PARAM_INT);
                $stmtInsert->execute();
            }
        }
    }


    /**
     * Processes an espionage mission upon fleet arrival.
     *
     * @param object $fleet The fleet object.
     * @param PDO $db The database connection.
     */
    private static function processEspionageMission(object $fleet, PDO $db): void
    {
        $targetPlanet = Planet::getByCoordinates($fleet->target_galaxy, $fleet->target_system, $fleet->target_position, $db);
        $returnReason = "Espionage mission completed.";

        if ($targetPlanet) {
            // For now, let's assume espionage always succeeds in revealing some information
            $reportData = [
                'fleet_id' => $fleet->id,
                'planet_id' => $targetPlanet->id,
                'resources' => json_encode([
                    'eisen' => $targetPlanet->eisen,
                    'silber' => $targetPlanet->silber,
                    'uderon' => $targetPlanet->uderon,
                    'wasserstoff' => $targetPlanet->wasserstoff,
                    'energie' => $targetPlanet->energie,
                ]),
                'defenses' => json_encode([]), // No defense data available yet
                'ships' => json_encode([]), // No ship data available yet
                'buildings' => json_encode([]), // No building data available yet
                'timestamp' => time(),
            ];

            // Insert or update the espionage report
            $sqlReport = "INSERT INTO espionage_reports (fleet_id, planet_id, resources, defenses, ships, buildings, timestamp) 
                          VALUES (:fleet_id, :planet_id, :resources, :defenses, :ships, :buildings, FROM_UNIXTIME(:timestamp))
                          ON DUPLICATE KEY UPDATE 
                            resources = :resources, 
                            defenses = :defenses, 
                            ships = :ships, 
                            buildings = :buildings, 
                            timestamp = FROM_UNIXTIME(:timestamp)";
            $stmtReport = $db->prepare($sqlReport);
            $stmtReport->bindParam(':fleet_id', $reportData['fleet_id'], PDO::PARAM_INT);
            $stmtReport->bindParam(':planet_id', $reportData['planet_id'], PDO::PARAM_INT);
            $stmtReport->bindParam(':resources', $reportData['resources'], PDO::PARAM_STR);
            $stmtReport->bindParam(':defenses', $reportData['defenses'], PDO::PARAM_STR);
            $stmtReport->bindParam(':ships', $reportData['ships'], PDO::PARAM_STR);
            $stmtReport->bindParam(':buildings', $reportData['buildings'], PDO::PARAM_STR);
            $stmtReport->bindParam(':timestamp', $reportData['timestamp'], PDO::PARAM_INT);
            $stmtReport->execute();

            $returnReason = "Espionage report generated for planet {$targetPlanet->name}.";
            NotificationService::createNotification($fleet->player_id, "Espionage Successful", $returnReason, "success");
        } else {
            $returnReason = "Espionage mission failed: Target coordinates are empty.";
            NotificationService::createNotification($fleet->player_id, "Espionage Failed", $returnReason, "warning");
        }
        self::setFleetToReturn($fleet->id, $db, $returnReason);
    }

    /**
     * Processes a colonize mission upon fleet arrival.
     *
     * @param object $fleet The fleet object.
     * @param PDO $db The database connection.
     */
    private static function processColonizeMission(object $fleet, PDO $db): void
    {
        $targetPlanet = Planet::getByCoordinates($fleet->target_galaxy, $fleet->target_system, $fleet->target_position, $db);
        $returnReason = "Colonization mission completed.";

        if ($targetPlanet) {
            // Check if the target planet is already owned
            if ($targetPlanet->player_id) {
                $returnReason = "Colonization failed: Target planet is already owned by another player.";
                NotificationService::createNotification($fleet->player_id, "Colonization Failed", $returnReason, "error");
                self::setFleetToReturn($fleet->id, $db, $returnReason);
                return;
            }

            // For now, let's assume colonization always succeeds if the planet is unowned
            $sqlColonize = "UPDATE planets SET player_id = :player_id, 
                                            galaxy = :galaxy, 
                                            system = :system, 
                                            position = :position, 
                                            name = :planet_name, 
                                            type = :planet_type, 
                                            size = :planet_size, 
                                            temperature = :temperature, 
                                            resources = :resources, 
                                            defenses = :defenses, 
                                            buildings = :buildings, 
                                            ships = :ships, 
                                            espionage_reports = NULL -- Clear any existing reports as it's now owned
                                    WHERE id = :planet_id";
            $stmtColonize = $db->prepare($sqlColonize);
            $stmtColonize->bindParam(':player_id', $fleet->player_id, PDO::PARAM_INT);
            $stmtColonize->bindParam(':galaxy', $fleet->target_galaxy, PDO::PARAM_INT);
            $stmtColonize->bindParam(':system', $fleet->target_system, PDO::PARAM_INT);
            $stmtColonize->bindParam(':position', $fleet->target_position, PDO::PARAM_INT);
            $stmtColonize->bindParam(':planet_name', $fleet->mission_type, PDO::PARAM_STR); // Using mission_type as a placeholder for planet name
            $stmtColonize->bindParam(':planet_type', $fleet->eisen_cargo, PDO::PARAM_STR); // Using eisen_cargo as a placeholder for planet type
            $stmtColonize->bindParam(':planet_size', $fleet->silber_cargo, PDO::PARAM_INT); // Using silber_cargo as a placeholder for planet size
            $stmtColonize->bindParam(':temperature', $fleet->uderon_cargo, PDO::PARAM_INT); // Using uderon_cargo as a placeholder for temperature
            $stmtColonize->bindParam(':resources', $fleet->wasserstoff_cargo, PDO::PARAM_STR); // Using wasserstoff_cargo as a placeholder for resources
            $stmtColonize->bindParam(':defenses', $fleet->energie_cargo, PDO::PARAM_STR); // Using energie_cargo as a placeholder for defenses
            $stmtColonize->bindParam(':buildings', $fleet->notes, PDO::PARAM_STR); // Using notes as a placeholder for buildings
            $stmtColonize->bindParam(':ships', $fleet->id, PDO::PARAM_STR); // Using id as a placeholder for ships
            $stmtColonize->bindParam(':planet_id', $targetPlanet->id, PDO::PARAM_INT);
            $stmtColonize->execute();

            // Clear fleet cargo and ships as they are now on the planet
            $updateFleetCargoSql = "UPDATE fleets SET eisen_cargo=0, silber_cargo=0, uderon_cargo=0, wasserstoff_cargo=0, energie_cargo=0 WHERE id = :fleet_id";
            $stmtFleetCargo = $db->prepare($updateFleetCargoSql);
            $stmtFleetCargo->bindParam(':fleet_id', $fleet->id, PDO::PARAM_INT);
            $stmtFleetCargo->execute();

            $returnReason = "Colonization of planet {$targetPlanet->name} successful.";
            NotificationService::createNotification($fleet->player_id, "Colonization Successful", $returnReason, "success");
        } else {
            $returnReason = "Colonization mission failed: Target coordinates are empty.";
            NotificationService::createNotification($fleet->player_id, "Colonization Failed", $returnReason, "warning");
        }
        self::setFleetToReturn($fleet->id, $db, $returnReason);
    }

    /**
     * Processes an Arkon mission (now Destroy mission for Uderon finding - placeholder).
     * This should be replaced with actual Destroy mission logic if Arkon is different.
     * For now, it's a copy of a generic "mission complete, return fleet".
     *
     * @param object $fleet The fleet object.
     * @param PDO $db The database connection.
     */
    // private static function processArkonMission(object $fleet, PDO $db): void // This is now handled by processDestroyMission
    // {
    //     // Logic for Arkon mission - e.g., chance to find Uderon
    //     // This mission type now calls processDestroyMission as per the switch case.
    //     // If Arkon has unique logic, it needs its own method.
    //     // For now, assuming processDestroyMission handles it or it's a placeholder.
    //     $coords = "{$fleet->target_galaxy}:{$fleet->target_system}:{$fleet->target_position}";
    //     NotificationService::createNotification($fleet->player_id, "Arkon Mission Update", "Arkon mission at {$coords} processed (via Destroy logic).", "info");
    //     self::setFleetToReturn($fleet->id, $db, "Arkon mission at {$coords} completed (via Destroy logic).");
    // }


    /**
     * Processes an invasion mission upon fleet arrival.
     * Placeholder - needs full implementation.
     *
     * @param object $fleet The fleet object.
     * @param PDO $db The database connection.
     */
    private static function processInvasionMission(object $fleet, PDO $db): void
    {
        // TODO: Implement invasion logic
        // - Check target planet owner, defenses, stationed ships
        // - Perform ground combat simulation
        // - Determine outcome: success (planet captured), partial success (damage), failure
        // - Update planet ownership, clear buildings/defenses if captured
        // - Create battle report
        // - Send notifications
        $coords = "{$fleet->target_galaxy}:{$fleet->target_system}:{$fleet->target_position}";
        NotificationService::createNotification($fleet->player_id, "Invasion Mission", "Invasion mission at {$coords} reached target. Logic not yet implemented.", "warning");
        self::setFleetToReturn($fleet->id, $db, "Invasion mission at {$coords} - logic pending. Returning fleet.");
    }

    /**
     * Processes a blockade mission upon fleet arrival.
     *
     * @param object $fleet The fleet object.
     * @param PDO $db The database connection.
     */
    private static function processBlockadeMission(object $fleet, PDO $db): void
    {
        $targetPlanet = Planet::getByCoordinates($fleet->target_galaxy, $fleet->target_system, $fleet->target_position, $db);
        $coords = "{$fleet->target_galaxy}:{$fleet->target_system}:{$fleet->target_position}";
        $attackerPlayerId = $fleet->player_id;
        $attackerPlayer = Player::getPlayerData($attackerPlayerId, $db);

        if (!$targetPlanet || !$targetPlanet->player_id) {
            NotificationService::createNotification($attackerPlayerId, "Blockade Failed", "Cannot blockade {$coords}: No owned planet at target.", "warning");
            self::setFleetToReturn($fleet->id, $db, "Blockade failed: No owned planet at target.");
            return;
        }

        $defenderPlayerId = $targetPlanet->player_id;
        if ($attackerPlayerId == $defenderPlayerId) {
            NotificationService::createNotification($attackerPlayerId, "Blockade Canceled", "Cannot blockade your own planet at {$coords}.", "error");
            self::setFleetToReturn($fleet->id, $db, "Blockade canceled: Cannot blockade own planet.");
            return;
        }
        
        $defenderPlayer = Player::getPlayerData($defenderPlayerId, $db);

        // Check diplomacy
        if ($attackerPlayer && $attackerPlayer->alliance_id && $defenderPlayer && $defenderPlayer->alliance_id) {
            $diplomacyStatus = Alliance::getDiplomacyStatus($attackerPlayer->alliance_id, $defenderPlayer->alliance_id);
            if ($diplomacyStatus === 'bündnis' || $diplomacyStatus === 'nap') {
                $returnReason = "Cannot blockade {$targetPlanet->name} ({$coords}): Target is an ally or has NAP.";
                NotificationService::createNotification($attackerPlayerId, "Blockade Canceled", $returnReason, "warning");
                self::setFleetToReturn($fleet->id, $db, "Blockade canceled due to alliance/NAP.");
                return;
            }
        }

        // Check if planet is already blockaded by someone else (and if this fleet is stronger)
        $currentBlockade = Planet::getActiveBlockadeDetails($targetPlanet->id, $db);
        if ($currentBlockade && $currentBlockade['blockade_fleet_id'] != $fleet->id) {
            // If blockaded by someone else, check strength.
            // Fleet->blockade_strength was calculated and stored when fleet was sent.
            if (($fleet->blockade_strength ?? 0) <= ($currentBlockade['blockade_strength'] ?? 0)) {
                NotificationService::createNotification($attackerPlayerId, "Blockade Failed", "Cannot blockade {$targetPlanet->name} ({$coords}): Already blockaded by an equal or stronger force.", "warning");
                self::setFleetToReturn($fleet->id, $db, "Blockade failed: Target already under stronger/equal blockade.");
                return;
            }
            // If this fleet is stronger, the existing blockade will be overridden.
            // Notify previous blockader their blockade was broken/overridden.
            if ($currentBlockade['blockade_player_id']) {
                 NotificationService::createNotification(
                    $currentBlockade['blockade_player_id'], 
                    "Blockade Overridden", 
                    "Your blockade on {$targetPlanet->name} ({$coords}) has been overridden by a stronger fleet from {$attackerPlayer->username}.", 
                    "warning"
                );
                // Set previous blockading fleet to return (if it's still there and active)
                $prevBlockadingFleet = self::getFleetById($currentBlockade['blockade_fleet_id']);
                if ($prevBlockadingFleet && !$prevBlockadingFleet->is_returning && !$prevBlockadingFleet->is_completed) {
                    self::setFleetToReturn($prevBlockadingFleet->id, $db, "Blockade overridden by a stronger fleet.");
                }
            }
        }
        
        // Establish blockade
        $blockadeUntil = date('Y-m-d H:i:s', strtotime($fleet->arrival_time) + ($fleet->blockade_duration_hours * 3600));
        
        $sql = "UPDATE planets SET 
                    blockade_player_id = :player_id, 
                    blockade_fleet_id = :fleet_id, 
                    blockade_until = :blockade_until,
                    blockade_strength = :blockade_strength
                WHERE id = :planet_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $attackerPlayerId, PDO::PARAM_INT);
        $stmt->bindParam(':fleet_id', $fleet->id, PDO::PARAM_INT);
        $stmt->bindParam(':blockade_until', $blockadeUntil, PDO::PARAM_STR);
        $stmt->bindParam(':blockade_strength', $fleet->blockade_strength, PDO::PARAM_INT);
        $stmt->bindParam(':planet_id', $targetPlanet->id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Mark the fleet as "mission active" but not completed or returning yet.
            // No change to fleet status here, it stays until duration ends or recalled.
            // The arrival_time has passed, so it's "at target".
            // Update fleet notes
            $updateFleetSql = "UPDATE fleets SET notes = CONCAT(COALESCE(notes, ''), ' | Blockade active on {$targetPlanet->name}') WHERE id = :fleet_id";
            $stmtFleetUpdate = $db->prepare($updateFleetSql);
            $stmtFleetUpdate->bindParam(':fleet_id', $fleet->id, PDO::PARAM_INT);
            $stmtFleetUpdate->execute();

            NotificationService::createNotification($attackerPlayerId, "Blockade Established", "Your fleet is now blockading {$targetPlanet->name} ({$coords}) until {$blockadeUntil}.", "success");
            if ($defenderPlayerId) {
                NotificationService::createNotification($defenderPlayerId, "Planet Blockaded", "Your planet {$targetPlanet->name} ({$coords}) is now being blockaded by {$attackerPlayer->username} until {$blockadeUntil}.", "error");
            }
        } else {
            NotificationService::createNotification($attackerPlayerId, "Blockade Failed", "Failed to establish blockade on {$targetPlanet->name} ({$coords}) due to a database error.", "error");
            self::setFleetToReturn($fleet->id, $db, "Blockade failed: DB error during planet update.");
        }
        // Fleet does NOT return here. It stays for the duration.
        // processFleets will handle its return when blockade_until expires.
    }

    /**
     * Processes a station mission upon fleet arrival.
     *
     * @param object $fleet The fleet object.
     * @param PDO $db The database connection.
     */
    private static function processStationMission(object $fleet, PDO $db): void
    {
        $targetPlanet = Planet::getByCoordinates($fleet->target_galaxy, $fleet->target_system, $fleet->target_position, $db);
        $coords = "{$fleet->target_galaxy}:{$fleet->target_system}:{$fleet->target_position}";
        $fleetOwnerId = $fleet->player_id;
        $fleetOwner = Player::getPlayerData($fleetOwnerId, $db);

        if (!$targetPlanet) {
            NotificationService::createNotification($fleetOwnerId, "Stationing Failed", "Cannot station fleet at {$coords}: Location is empty space.", "warning");
            self::setFleetToReturn($fleet->id, $db, "Stationing failed: Target is empty space.");
            return;
        }

        $canStation = false;
        if ($targetPlanet->player_id == $fleetOwnerId) { // Own planet
            $canStation = true;
        } else if ($targetPlanet->player_id) { // Other player's planet
            $targetOwner = Player::getPlayerData($targetPlanet->player_id, $db);
            if ($fleetOwner && $fleetOwner->alliance_id && $targetOwner && $targetOwner->alliance_id && $fleetOwner->alliance_id == $targetOwner->alliance_id) {
                // Check for Bündnis (alliance) or NAP for stationing rights
                 $diplomacyStatus = Alliance::getDiplomacyStatus($fleetOwner->alliance_id, $targetOwner->alliance_id);
                 if ($diplomacyStatus === 'bündnis' || $diplomacyStatus === 'nap') { // Assuming NAP also allows stationing
                    $canStation = true;
                 } else {
                     NotificationService::createNotification($fleetOwnerId, "Stationing Failed", "Cannot station at {$targetPlanet->name} ({$coords}): No stationing agreement with owner's alliance.", "warning");
                     self::setFleetToReturn($fleet->id, $db, "Stationing failed: No permission from target owner's alliance.");
                     return;
                 }
            } else {
                NotificationService::createNotification($fleetOwnerId, "Stationing Failed", "Cannot station at {$targetPlanet->name} ({$coords}): Planet owned by non-allied player.", "warning");
                self::setFleetToReturn($fleet->id, $db, "Stationing failed: Target owned by non-ally.");
                return;
            }
        } else { // Unowned planet
             NotificationService::createNotification($fleetOwnerId, "Stationing Failed", "Cannot station fleet at unowned planet {$coords}.", "warning");
            self::setFleetToReturn($fleet->id, $db, "Stationing failed: Target is unowned.");
            return;
        }

        if ($canStation) {
            // Fleet is now stationed. No specific change to planet table for "stationing" itself.
            // The fleet remains at the target. is_completed = 0, is_returning = 0.
            // blockade_duration_hours in fleets table is used for stationing duration.
            // processFleets already has logic to return stationed fleets when their duration expires.
            $stationUntil = date('Y-m-d H:i:s', strtotime($fleet->arrival_time) + ($fleet->blockade_duration_hours * 3600));
            
            $note = "Fleet stationed at {$targetPlanet->name} ({$coords}) until {$stationUntil}.";
            $updateFleetSql = "UPDATE fleets SET notes = CONCAT(COALESCE(notes, ''), ' | ', :note) WHERE id = :fleet_id";
            $stmtFleetUpdate = $db->prepare($updateFleetSql);
            $stmtFleetUpdate->bindParam(':note', $note, PDO::PARAM_STR);
            $stmtFleetUpdate->bindParam(':fleet_id', $fleet->id, PDO::PARAM_INT);
            $stmtFleetUpdate->execute();

            NotificationService::createNotification($fleetOwnerId, "Stationing Commenced", $note, "success");
            if ($targetPlanet->player_id && $targetPlanet->player_id != $fleetOwnerId) {
                NotificationService::createNotification($targetPlanet->player_id, "Allied Fleet Stationed", "An allied fleet from {$fleetOwner->username} (ID: {$fleet->id}) is now stationed at your planet {$targetPlanet->name} ({$coords}) until {$stationUntil}.", "info");
            }
        }
        // Fleet does NOT return here. It stays for the duration.
    }

    /**
     * Processes a destroy mission upon fleet arrival (e.g., moonshot, Arkon).
     * Placeholder - needs specific implementation based on game rules.
     *
     * @param object $fleet The fleet object.
     * @param PDO $db The database connection.
     */
    private static function processDestroyMission(object $fleet, PDO $db): void
    {
        // TODO: Implement destroy logic (e.g., for moons or specific targets)
        // - Check target, what can be destroyed?
        // - Chance of success, consequences.
        // - For Arkon: chance to find Uderon (if this is still the logic)
        // - For Moonshot: chance to destroy moon, chance of fleet destruction.
        $coords = "{$fleet->target_galaxy}:{$fleet->target_system}:{$fleet->target_position}";
        $targetPlanet = Planet::getByCoordinates($fleet->target_galaxy, $fleet->target_system, $fleet->target_position, $db);
        $returnReason = "Destroy mission at {$coords} - ";

        if ($fleet->mission_type === 'arkon') { // Specific Arkon logic (placeholder)
            // This was previously for finding Uderon. If it's now a generic destroy, this needs to change.
            // For now, let's assume it's a placeholder and just returns.
            // A real Arkon mission might involve checking for specific conditions or items.
            $foundUderon = (rand(1, 100) <= 20); // Example: 20% chance
            if ($foundUderon && $targetPlanet) { // Arkon might only work on planets
                $uderonAmount = 1500; // Example amount
                $fleet->uderon_cargo = ($fleet->uderon_cargo ?? 0) + $uderonAmount;
                $updateCargoSql = "UPDATE fleets SET uderon_cargo = :uderon_cargo WHERE id = :fleet_id";
                $stmtCargo = $db->prepare($updateCargoSql);
                $stmtCargo->bindParam(':uderon_cargo', $fleet->uderon_cargo, PDO::PARAM_INT);
                $stmtCargo->bindParam(':fleet_id', $fleet->id, PDO::PARAM_INT);
                $stmtCargo->execute();
                $returnReason .= "found {$uderonAmount} Uderon.";
                NotificationService::createNotification($fleet->player_id, "Arkon Mission Successful", "Your Arkon fleet found {$uderonAmount} Uderon at {$coords}!", "success");
            } else {
                $returnReason .= "found nothing of significance.";
                 NotificationService::createNotification($fleet->player_id, "Arkon Mission Report", "Your Arkon fleet explored {$coords} but found nothing of significance this time.", "info");
            }
        } else { // Generic destroy mission
            // Example: Destroy random number of fields on an unowned/enemy planet
            // This is highly dependent on game rules for "destroy" missions.
            // For now, just a placeholder message.
            $returnReason .= "logic not yet fully implemented.";
            NotificationService::createNotification($fleet->player_id, "Destroy Mission", "Destroy mission at {$coords} reached target. Specific destroy logic pending.", "warning");
        }
        
        self::setFleetToReturn($fleet->id, $db, $returnReason);
    }
}
?>
