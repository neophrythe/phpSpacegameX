<?php
namespace Models;

use Core\Model;
use PDO;

class DefenseType extends Model {
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
    public $base_build_time;
    public $build_time_factor;
    public $weapon_power;
    public $shield_power;
    public $hull_strength;
    public $requirements_json;

    public static function getAll() {
        $db = self::getDB();
        $stmt = $db->query('SELECT * FROM static_defense_types');
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }

    public static function getById($id) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM static_defense_types WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    public static function getByInternalName($internalName) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM static_defense_types WHERE internal_name = :internal_name');
        $stmt->bindParam(':internal_name', $internalName, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }
    
    // Calculate the cost to build a given quantity of this defense type
    public function getCostForQuantity($quantity) {
        return [
            'eisen' => $this->base_cost_eisen * $quantity,
            'silber' => $this->base_cost_silber * $quantity,
            'uderon' => $this->base_cost_uderon * $quantity,
            'wasserstoff' => $this->base_cost_wasserstoff * $quantity,
            'energie' => $this->base_cost_energie * $quantity,
        ];
    }

    // Calculate the build time for a given quantity of this defense type, considering Raumstation level
    public function getBuildTime($quantity, $raumstationLevel) {
        // Base build time for one defense unit
        $baseTimePerUnit = $this->base_build_time;

        // Build time is affected by Raumstation level.
        // Assuming a simple inverse relationship: higher Raumstation level means faster building.
        // Needs refinement based on actual game mechanics if available.
        $adjustedTimePerUnit = $baseTimePerUnit / ($raumstationLevel > 0 ? $raumstationLevel : 1); // Avoid division by zero

        // Total build time for the quantity
        $totalAdjustedTime = $adjustedTimePerUnit * $quantity;

        return ceil($totalAdjustedTime); // Return time in seconds, rounded up
    }
}
?>
