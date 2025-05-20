<?php
namespace Models;

use Core\\\\Model;
use PDO;

class StaticResearchType extends Model {
    // Properties corresponding to the database table columns (assuming a 'static_research_types' table)
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
    public $base_research_time; // Base time in seconds for level 1
    public $research_time_factor; // Factor by which research time increases per level
    public $requirements_json; // JSON string for research/building requirements
    public $effects_json; // JSON string for effects (e.g., combat bonuses, speed bonuses, production bonuses)
    public $max_level; // Maximum level for this research type
    public $is_alliance_research; // Boolean if it's an alliance research

    // Assuming a table structure like:
    // CREATE TABLE static_research_types (
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
    //     base_research_time INT DEFAULT 60, -- Example: 1 minute base research time
    //     research_time_factor FLOAT DEFAULT 1.5, -- Example factor
    //     requirements_json JSON,
    //     effects_json JSON,
    //     max_level INT DEFAULT 0, -- 0 means no explicit max level
    //     is_alliance_research BOOLEAN DEFAULT FALSE
    // );

    /**
     * Get a static research type by its internal name.
     *
     * @param string $internalName The internal name of the research type.
     * @param PDO $db Database connection.
     * @return StaticResearchType|false The research type object or false if not found.
     */
    public static function getByInternalName(string $internalName, PDO $db): StaticResearchType|false {
        $sql = "SELECT * FROM static_research_types WHERE internal_name = :internal_name";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':internal_name', $internalName, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchObject(self::class);
    }

    /**
     * Get a static research type by its ID.
     *
     * @param int $id The ID of the research type.
     * @param PDO $db Database connection.
     * @return StaticResearchType|false The research type object or false if not found.
     */
    public static function getById(int $id, PDO $db): StaticResearchType|false {
        $sql = "SELECT * FROM static_research_types WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(self::class);
    }

    // Add other static research type related methods here as needed
}
?>
