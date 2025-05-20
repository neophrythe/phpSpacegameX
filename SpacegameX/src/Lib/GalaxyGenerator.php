<?php
// filepath: f:\sdi\wog\SpacegameX\src\Lib\GalaxyGenerator.php
namespace Lib;

use Core\Model;
use PDO;

/**
 * GalaxyGenerator - Utility class for generating the expanded galaxy system
 * Creates 150 galaxies with 355 systems per galaxy and 9-14 planets per system
 */
class GalaxyGenerator extends Model {
    // Constants for galaxy generation
    const GALAXY_COUNT = 150;
    const SYSTEMS_PER_GALAXY = 355;
    const MIN_PLANETS_PER_SYSTEM = 9;
    const MAX_PLANETS_PER_SYSTEM = 14;
    
    // Planet position types with their resource bonuses
    const PLANET_TYPES = [
        'metal-rich' => ['metal' => 1.5, 'crystal' => 1.0, 'h2' => 1.0, 'chance' => 20],
        'crystal-rich' => ['metal' => 1.0, 'crystal' => 1.5, 'h2' => 1.0, 'chance' => 15],
        'h2-rich' => ['metal' => 1.0, 'crystal' => 1.0, 'h2' => 1.5, 'chance' => 10],
        'balanced' => ['metal' => 1.2, 'crystal' => 1.2, 'h2' => 1.2, 'chance' => 5],
        'metal-poor' => ['metal' => 0.8, 'crystal' => 1.1, 'h2' => 1.1, 'chance' => 10],
        'crystal-poor' => ['metal' => 1.1, 'crystal' => 0.8, 'h2' => 1.1, 'chance' => 10],
        'h2-poor' => ['metal' => 1.1, 'crystal' => 1.1, 'h2' => 0.8, 'chance' => 15],
        'barren' => ['metal' => 0.9, 'crystal' => 0.9, 'h2' => 0.9, 'chance' => 10],
        'normal' => ['metal' => 1.0, 'crystal' => 1.0, 'h2' => 1.0, 'chance' => 100] // Default
    ];
    
    // System types with their characteristics
    const SYSTEM_TYPES = [
        'normal' => ['chance' => 70, 'planet_mod' => 0],
        'dense' => ['chance' => 10, 'planet_mod' => 2], // More planets
        'sparse' => ['chance' => 10, 'planet_mod' => -1], // Fewer planets
        'resource-rich' => ['chance' => 5, 'planet_mod' => 0, 'resource_bonus' => 1.2], // Better resources
        'resource-poor' => ['chance' => 5, 'planet_mod' => 0, 'resource_bonus' => 0.8], // Worse resources
    ];

    /**
     * Generate the entire expanded galaxy system
     * 
     * @return bool Success or failure
     */
    public static function generateExpandedGalaxySystem() {
        $db = self::getDB();
        $db->beginTransaction();
        
        try {
            // Create galaxies
            for ($g = 1; $g <= self::GALAXY_COUNT; $g++) {
                self::createGalaxy($g);
                
                // Create systems in this galaxy
                for ($s = 1; $s <= self::SYSTEMS_PER_GALAXY; $s++) {
                    $systemType = self::getRandomSystemType();
                    $planetCount = self::calculatePlanetCount($systemType);
                    
                    // Create system
                    self::createSolarSystem($g, $s, $planetCount, $systemType);
                }
            }
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Galaxy generation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a galaxy entry
     * 
     * @param int $galaxyNumber The galaxy number
     * @return int|bool The galaxy ID or false on failure
     */
    private static function createGalaxy($galaxyNumber) {
        $db = self::getDB();
        
        $sql = "INSERT INTO galaxies (galaxy_number, name, description) 
                VALUES (:galaxy_number, :name, :description)";
                
        $stmt = $db->prepare($sql);
        $name = "Galaxy " . $galaxyNumber;
        $description = "Galaxy number " . $galaxyNumber;
        
        $stmt->bindParam(':galaxy_number', $galaxyNumber, PDO::PARAM_INT);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            return $db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Create a solar system entry
     * 
     * @param int $galaxy The galaxy number
     * @param int $system The system number
     * @param int $planetCount Number of planets in the system
     * @param string $systemType Type of system
     * @return int|bool The system ID or false on failure
     */
    private static function createSolarSystem($galaxy, $system, $planetCount, $systemType) {
        $db = self::getDB();
        
        $sql = "INSERT INTO solar_systems (galaxy, system, planet_count, system_type, description) 
                VALUES (:galaxy, :system, :planet_count, :system_type, :description)";
                
        $stmt = $db->prepare($sql);
        $description = "Solar system in galaxy " . $galaxy;
        
        $stmt->bindParam(':galaxy', $galaxy, PDO::PARAM_INT);
        $stmt->bindParam(':system', $system, PDO::PARAM_INT);
        $stmt->bindParam(':planet_count', $planetCount, PDO::PARAM_INT);
        $stmt->bindParam(':system_type', $systemType, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            return $db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Calculate the random number of planets for a system
     * 
     * @param string $systemType Type of the system
     * @return int Number of planets
     */
    private static function calculatePlanetCount($systemType) {
        $baseCount = rand(self::MIN_PLANETS_PER_SYSTEM, self::MAX_PLANETS_PER_SYSTEM);
        
        if (isset(self::SYSTEM_TYPES[$systemType]['planet_mod'])) {
            $baseCount += self::SYSTEM_TYPES[$systemType]['planet_mod'];
        }
        
        // Make sure we stay within bounds
        return max(self::MIN_PLANETS_PER_SYSTEM, min(self::MAX_PLANETS_PER_SYSTEM, $baseCount));
    }
    
    /**
     * Get a random system type based on the defined chances
     * 
     * @return string System type
     */
    private static function getRandomSystemType() {
        $types = self::SYSTEM_TYPES;
        $totalChance = array_sum(array_column($types, 'chance'));
        $rand = rand(1, $totalChance);
        $currentSum = 0;
        
        foreach ($types as $type => $props) {
            $currentSum += $props['chance'];
            if ($rand <= $currentSum) {
                return $type;
            }
        }
        
        // Default to normal
        return 'normal';
    }
    
    /**
     * Get a random planet type based on the defined chances
     * 
     * @return string Planet type
     */
    private static function getRandomPlanetType() {
        $types = self::PLANET_TYPES;
        $totalChance = array_sum(array_column($types, 'chance'));
        $rand = rand(1, $totalChance);
        $currentSum = 0;
        
        foreach ($types as $type => $props) {
            $currentSum += $props['chance'];
            if ($rand <= $currentSum) {
                return $type;
            }
        }
        
        // Default to normal
        return 'normal';
    }
    
    /**
     * Create an uninhabited planet in a specific location
     * 
     * @param int $galaxy Galaxy number
     * @param int $system System number
     * @param int $position Position in the system
     * @param string $planetType Type of planet for resource bonuses
     * @param string $systemType Type of system
     * @return bool Success or failure
     */
    public static function createUninhabitedPlanet($galaxy, $system, $position, $planetType = null, $systemType = 'normal') {
        if ($planetType === null) {
            $planetType = self::getRandomPlanetType();
        }
        
        $bonuses = self::PLANET_TYPES[$planetType];
        $diameter = rand(8000, 15000); // km
        $temperature_min = rand(-40, 10);
        $temperature_max = $temperature_min + rand(30, 60);
        
        // Apply system-wide resource bonus if applicable
        $systemResourceMod = 1.0;
        if (isset(self::SYSTEM_TYPES[$systemType]['resource_bonus'])) {
            $systemResourceMod = self::SYSTEM_TYPES[$systemType]['resource_bonus'];
        }
        
        $metal_bonus = $bonuses['metal'] * $systemResourceMod;
        $crystal_bonus = $bonuses['crystal'] * $systemResourceMod;
        $h2_bonus = $bonuses['h2'] * $systemResourceMod;
        
        // Special adjustment for planets closer to the sun (higher temperature)
        // First 3 positions are hotter - better for h2, worse for metal
        // Last 3 positions are colder - better for metal, worse for h2
        if ($position <= 3) {
            $h2_bonus *= 1.2;
            $metal_bonus *= 0.9;
            $temperature_min += 30;
            $temperature_max += 30;
        } 
        elseif ($position >= self::MAX_PLANETS_PER_SYSTEM - 2) {
            $metal_bonus *= 1.2;
            $h2_bonus *= 0.9;
            $temperature_min -= 30;
            $temperature_max -= 30;
        }
        
        $db = self::getDB();
        
        // Check if this position is already occupied
        $stmt = $db->prepare('SELECT COUNT(*) FROM planets WHERE galaxy = :g AND system = :s AND position = :p');
        $stmt->execute([':g' => $galaxy, ':s' => $system, ':p' => $position]);
        if ($stmt->fetchColumn() > 0) {
            // Position already occupied
            return false;
        }
        
        $sql = "INSERT INTO planets (player_id, name, galaxy, system, position, 
                is_capital, diameter, temperature_min, temperature_max, 
                metal_bonus, crystal_bonus, h2_bonus)
                VALUES (:player_id, :name, :galaxy, :system, :position, 
                0, :diameter, :tmin, :tmax, 
                :metal_bonus, :crystal_bonus, :h2_bonus)";
                
        $stmt = $db->prepare($sql);
        $player_id = 0; // Uninhabited planet
        $name = "Uninhabited planet";
        
        $stmt->bindParam(':player_id', $player_id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':galaxy', $galaxy, PDO::PARAM_INT);
        $stmt->bindParam(':system', $system, PDO::PARAM_INT);
        $stmt->bindParam(':position', $position, PDO::PARAM_INT);
        $stmt->bindParam(':diameter', $diameter, PDO::PARAM_INT);
        $stmt->bindParam(':tmin', $temperature_min, PDO::PARAM_INT);
        $stmt->bindParam(':tmax', $temperature_max, PDO::PARAM_INT);
        $stmt->bindParam(':metal_bonus', $metal_bonus, PDO::PARAM_STR);
        $stmt->bindParam(':crystal_bonus', $crystal_bonus, PDO::PARAM_STR);
        $stmt->bindParam(':h2_bonus', $h2_bonus, PDO::PARAM_STR);
        
        return $stmt->execute();
    }
    
    /**
     * Initialize a specific solar system by creating uninhabited planets
     * 
     * @param int $galaxy Galaxy number
     * @param int $system System number
     * @return bool Success or failure
     */
    public static function initializeSolarSystem($galaxy, $system) {
        $db = self::getDB();
        
        // Fetch system info
        $stmt = $db->prepare('SELECT * FROM solar_systems WHERE galaxy = :g AND system = :s');
        $stmt->execute([':g' => $galaxy, ':s' => $system]);
        $solarSystem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$solarSystem) {
            // System not found, create it with default values
            $planetCount = rand(self::MIN_PLANETS_PER_SYSTEM, self::MAX_PLANETS_PER_SYSTEM);
            $systemType = 'normal';
            self::createSolarSystem($galaxy, $system, $planetCount, $systemType);
        } else {
            $planetCount = $solarSystem['planet_count'];
            $systemType = $solarSystem['system_type'];
        }
        
        // Create planets in the system
        for ($p = 1; $p <= $planetCount; $p++) {
            $planetType = self::getRandomPlanetType();
            self::createUninhabitedPlanet($galaxy, $system, $p, $planetType, $systemType);
        }
        
        return true;
    }
    
    /**
     * Utility method to get or create a specific system
     * 
     * @param int $galaxy Galaxy number
     * @param int $system System number
     * @return array System data
     */
    public static function getOrCreateSystem($galaxy, $system) {
        $db = self::getDB();
        
        // Check if system exists
        $stmt = $db->prepare('SELECT * FROM solar_systems WHERE galaxy = :g AND system = :s');
        $stmt->execute([':g' => $galaxy, ':s' => $system]);
        $solarSystem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$solarSystem) {
            // Create the system
            $systemType = self::getRandomSystemType();
            $planetCount = self::calculatePlanetCount($systemType);
            $systemId = self::createSolarSystem($galaxy, $system, $planetCount, $systemType);
            
            // Fetch the created system
            $stmt = $db->prepare('SELECT * FROM solar_systems WHERE id = :id');
            $stmt->execute([':id' => $systemId]);
            $solarSystem = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return $solarSystem;
    }

    /**
     * Get all planets within a specific system, including basic owner info.
     *
     * @param int $galaxyNumber The galaxy number.
     * @param int $systemNumber The system number.
     * @return array An array of planet data, including player_id and player_name if occupied.
     */
    public static function getSystemPlanets($galaxyNumber, $systemNumber) {
        $db = self::getDB();
        
        // First, ensure the system exists, or create it if it's part of the dynamic generation.
        // This step might be implicit if your game pre-generates all systems or creates them on-demand.
        // For this example, we assume the system entry in `solar_systems` should exist.
        // self::getOrCreateSystem($galaxyNumber, $systemNumber); // Uncomment if systems are created on-demand when viewed

        $sql = "SELECT p.*, pl.username as player_name 
                FROM planets p
                LEFT JOIN players pl ON p.player_id = pl.id
                WHERE p.galaxy = :galaxy AND p.system = :system
                ORDER BY p.position ASC";
                
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':galaxy', $galaxyNumber, \PDO::PARAM_INT);
        $stmt->bindParam(':system', $systemNumber, \PDO::PARAM_INT);
        $stmt->execute();
        
        $planetsData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // If you want to represent all potential planet slots (even empty ones)
        // you might need to fetch the system's planet_count and fill in the gaps.
        $systemInfo = self::getSystemInfo($galaxyNumber, $systemNumber);
        $expectedPlanetSlots = $systemInfo ? $systemInfo['planet_count'] : self::MAX_PLANETS_PER_SYSTEM; 

        $result = [];
        $occupiedPositions = array_column($planetsData, 'position');

        for ($i = 1; $i <= $expectedPlanetSlots; $i++) {
            $planetInSlot = null;
            foreach ($planetsData as $pd) {
                if ($pd['position'] == $i) {
                    $planetInSlot = $pd;
                    break;
                }
            }
            if ($planetInSlot) {
                $result[] = $planetInSlot;
            } else {
                // Represent an empty slot
                $result[] = [
                    'galaxy' => $galaxyNumber,
                    'system' => $systemNumber,
                    'position' => $i,
                    'name' => 'Uninhabited Planet',
                    'player_id' => null,
                    'player_name' => null,
                    'is_empty_slot' => true // Flag to help rendering
                ];
            }
        }
        return $result;
    }

    /**
     * Get information about a specific solar system.
     *
     * @param int $galaxyNumber
     * @param int $systemNumber
     * @return array|false System data or false if not found.
     */
    public static function getSystemInfo($galaxyNumber, $systemNumber) {
        $db = self::getDB();
        $stmt = $db->prepare("SELECT * FROM solar_systems WHERE galaxy = :g AND system = :s");
        $stmt->execute([':g' => $galaxyNumber, ':s' => $systemNumber]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get information about a specific galaxy.
     *
     * @param int $galaxyNumber
     * @return array|false Galaxy data or false if not found.
     */
    public static function getGalaxyInfo($galaxyNumber) {
        $db = self::getDB();
        $stmt = $db->prepare("SELECT * FROM galaxies WHERE galaxy_number = :gn");
        $stmt->execute([':gn' => $galaxyNumber]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
