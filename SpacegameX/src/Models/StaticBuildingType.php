<?php
namespace Models;

use Core\\\\Model;
use PDO;

class StaticBuildingType extends Model {
    // Properties corresponding to the database table columns (assuming a 'static_building_types' table)
    public $id;
    public $internal_name;
    public $name_de; // German name
    public $description_de; // German description
    public $base_cost_eisen;
    public $base_cost_silber;
    public $base_cost_uderon;
    public $base_cost_wasserstoff;
    public $base_cost_energie;
    public $cost_factor; // Factor by which cost increases per level
    public $base_build_time; // Base time in seconds for level 1
    public $build_time_factor; // Factor by which build time increases per level
    public $requirements_json; // JSON string for research/building requirements
    public $effects_json; // JSON string for effects (e.g., resource production, research speed, ship build speed)
    public $max_level; // Maximum level for this building type
    public $is_alliance_building; // Boolean if it's an alliance building

    // Assuming a table structure like:
    // CREATE TABLE static_building_types (
    //     id INT AUTO_INCREMENT PRIMARY KEY,
    //     internal_name VARCHAR(50) UNIQUE NOT NULL,
    //     name_de VARCHAR(100) NOT NULL,
    //     description_de TEXT,
    //     base_cost_eisen INT DEFAULT 0,
    //     base_cost_silber INT DEFAULT 0,
    //     base_cost_uderon INT DEFAULT 0,
    //     base_cost_wasserstoff INT DEFAULT 0,
    //     base_cost_energie INT DEFAULT 0,
    //     cost_factor FLOAT DEFAULT 1.5, -- Example factor
    //     base_build_time INT DEFAULT 60, -- Example: 1 minute base build time
    //     build_time_factor FLOAT DEFAULT 1.5, -- Example factor
    //     requirements_json JSON,
    //     effects_json JSON,
    //     max_level INT DEFAULT 0, -- 0 means no explicit max level
    //     is_alliance_building BOOLEAN DEFAULT FALSE
    // );

    /**
     * Get a static building type by its internal name.
     *
     * @param string $internalName The internal name of the building type.
     * @param PDO $db Database connection.
     * @return StaticBuildingType|false The building type object or false if not found.
     */
    public static function getByInternalName(string $internalName, PDO $db): StaticBuildingType|false {
        $sql = "SELECT * FROM static_building_types WHERE internal_name = :internal_name";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':internal_name', $internalName, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchObject(self::class);
    }

    /**
     * Get a static building type by its ID.
     *
     * @param int $id The ID of the building type.
     * @param PDO $db Database connection.
     * @return StaticBuildingType|false The building type object or false if not found.
     */
    public static function getById(int $id, PDO $db): StaticBuildingType|false {
        $sql = "SELECT * FROM static_building_types WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(self::class);
    }

    // Add other static building type related methods here as needed
}
?>
