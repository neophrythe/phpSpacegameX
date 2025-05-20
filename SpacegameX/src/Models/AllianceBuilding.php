<?php
namespace Models;

use Core\Model;
use PDO;

class AllianceBuilding extends Model {
    public $id;
    public $alliance_id;
    public $solar_system_id;
    public $building_type_id;
    public $level;

    public static function getAllForAlliance($allianceId) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT ab.*, sabt.internal_name, sabt.name_de FROM alliance_buildings ab JOIN static_alliance_building_types sabt ON ab.building_type_id = sabt.id WHERE ab.alliance_id = :alliance_id');
        $stmt->bindParam(':alliance_id', $allianceId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public static function getByAllianceAndSystemAndType($allianceId, $solarSystemId, $buildingTypeId) {
        $db = self::getDB();
        $sql = 'SELECT ab.*, sabt.internal_name, sabt.name_de FROM alliance_buildings ab JOIN static_alliance_building_types sabt ON ab.building_type_id = sabt.id WHERE ab.alliance_id = :alliance_id AND ab.solar_system_id = :solar_system_id AND ab.building_type_id = :building_type_id';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':alliance_id', $allianceId, PDO::PARAM_INT);
        $stmt->bindParam(':solar_system_id', $solarSystemId, PDO::PARAM_INT);
        $stmt->bindParam(':building_type_id', $buildingTypeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    public static function create($allianceId, $solarSystemId, $buildingTypeId, $level = 1) {
        $db = self::getDB();
        $sql = "INSERT INTO alliance_buildings (alliance_id, solar_system_id, building_type_id, level) 
                VALUES (:alliance_id, :solar_system_id, :building_type_id, :level)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':alliance_id', $allianceId, PDO::PARAM_INT);
        $stmt->bindParam(':solar_system_id', $solarSystemId, PDO::PARAM_INT);
        $stmt->bindParam(':building_type_id', $buildingTypeId, PDO::PARAM_INT);
        $stmt->bindParam(':level', $level, PDO::PARAM_INT);
        $stmt->execute();
        return $db->lastInsertId();
    }

    public function upgrade() {
        $db = self::getDB();
        $sql = "UPDATE alliance_buildings SET level = level + 1 WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function delete() {
        $db = self::getDB();
        $stmt = $db->prepare('DELETE FROM alliance_buildings WHERE id = :id');
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Get the levels of all alliance buildings for a given alliance.
     *
     * @param int $allianceId The ID of the alliance.
     * @return array An associative array mapping building_type_id to level.
     */
    public static function getBuildingLevelsForAlliance(int $allianceId): array {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT building_type_id, level FROM alliance_buildings WHERE alliance_id = :alliance_id');
        $stmt->bindParam(':alliance_id', $allianceId, PDO::PARAM_INT);
        $stmt->execute();
        
        $levels = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $levels[$row['building_type_id']] = (int)$row['level'];
        }
        
        return $levels;
    }
}
?>
