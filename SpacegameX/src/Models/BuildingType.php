<?php
namespace Models;

use Core\Model;
use PDO;

class BuildingType extends Model {
    public $id;
    public $internal_name;
    public $name_de;
    public $name_en;
    public $description_de;
    public $description_en;
    public $base_cost_eisen;
    public $base_cost_silber;
    public $base_cost_uderon;
    public $base_cost_wasserstoff;
    public $base_cost_energie;
    public $cost_factor;
    public $base_production_eisen;
    public $base_production_silber;
    public $base_production_uderon;
    public $base_production_wasserstoff;
    public $base_production_energie;
    public $production_formula;
    public $base_consumption_wasserstoff;
    public $base_consumption_energie;
    public $consumption_formula;
    public $max_level;
    public $base_build_time;
    public $build_time_factor;
    public $requirements_json;

    public static function getAll() {
        $db = self::getDB();
        $stmt = $db->query('SELECT * FROM static_building_types');
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }

    public static function getById($id) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM static_building_types WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    public static function getByInternalName($internalName) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM static_building_types WHERE internal_name = :internal_name');
        $stmt->bindParam(':internal_name', $internalName, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }
    
    // Calculate the cost to build this building at a specific level
    public function getCostAtLevel($level) {
        return [
            'eisen' => $this->base_cost_eisen * pow($this->cost_factor, $level),
            'silber' => $this->base_cost_silber * pow($this->cost_factor, $level),
            'uderon' => $this->base_cost_uderon * pow($this->cost_factor, $level),
            'wasserstoff' => $this->base_cost_wasserstoff * pow($this->cost_factor, $level),
            'energie' => $this->base_cost_energie * pow($this->cost_factor, $level),
        ];
    }

    // Calculate the base build time for this building at a specific level (before planet/zentrale modifiers)
    public function getBaseBuildTimeAtLevel($level) {
        return $this->base_build_time * pow($this->build_time_factor, $level);
    }
}
?>
