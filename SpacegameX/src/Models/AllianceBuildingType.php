<?php
namespace Models;

use Core\Model;
use PDO;

class AllianceBuildingType extends Model {
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
    public $requirements_json;

    public static function getAll() {
        $db = self::getDB();
        $stmt = $db->query('SELECT * FROM static_alliance_building_types');
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }

    public static function getById($id) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM static_alliance_building_types WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    public static function getByInternalName($internalName) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM static_alliance_building_types WHERE internal_name = :internal_name');
        $stmt->bindParam(':internal_name', $internalName, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }
    
    // Add other methods as needed
}
?>
