<?php
namespace Models;

use Core\Model;
use PDO;

class BattleReport extends Model {
    public $id;
    public $attacker_id;
    public $defender_id;
    public $battle_time;
    public $target_planet_id;
    public $target_coordinates;
    public $report_data; // JSON data
    public $is_read; // Added is_read field

    public static function getById($id) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM battle_reports WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    public static function getReportsByPlayerId($playerId) {
        $db = self::getDB();
        $sql = 'SELECT br.*, p_att.username as attacker_name, p_def.username as defender_name 
                FROM battle_reports br
                LEFT JOIN players p_att ON br.attacker_id = p_att.id
                LEFT JOIN players p_def ON br.defender_id = p_def.id
                WHERE br.attacker_id = :player_id_attacker OR br.defender_id = :player_id_defender 
                ORDER BY br.battle_time DESC';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id_attacker', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':player_id_defender', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }

    public static function create($attackerId, $defenderId, $targetPlanetId, $targetCoordinates, $reportDataJson) {
        $db = self::getDB();
        $sql = "INSERT INTO battle_reports (attacker_id, defender_id, battle_time, target_planet_id, target_coordinates, report_data, is_read) 
                VALUES (:attacker_id, :defender_id, NOW(), :target_planet_id, :target_coordinates, :report_data, FALSE)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':attacker_id', $attackerId, PDO::PARAM_INT);
        $stmt->bindParam(':defender_id', $defenderId, PDO::PARAM_INT);
        $stmt->bindParam(':target_planet_id', $targetPlanetId, PDO::PARAM_INT);
        $stmt->bindParam(':target_coordinates', $targetCoordinates, PDO::PARAM_STR);
        $stmt->bindParam(':report_data', $reportDataJson, PDO::PARAM_STR); // Changed PDO::PARAM_LOB to PDO::PARAM_STR
        
        if ($stmt->execute()) {
            $reportId = $db->lastInsertId();

            // Create notification for attacker
            PlayerNotification::createNotification(
                $attackerId,
                PlayerNotification::TYPE_BATTLE_REPORT,
                "Kampfbericht: Dein Angriff auf {$targetCoordinates}", // Standardized message
                "/combat/reports/view/{$reportId}"
            );

            // Create notification for defender
            if ($defenderId > 0) { // Assuming player IDs are positive integers
                 PlayerNotification::createNotification(
                    $defenderId,
                    PlayerNotification::TYPE_BATTLE_REPORT,
                    "Kampfbericht: Deine Verteidigung bei {$targetCoordinates}", // Standardized message
                    "/combat/reports/view/{$reportId}"
                );
            }
            return $reportId;
        }
        return false;
    }

    public function markAsRead(PDO $db = null) {
        if ($db === null) {
            $db = self::getDB();
        }
        $sql = "UPDATE battle_reports SET is_read = TRUE WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public static function getUnreadReportsCountByPlayerId($playerId, PDO $db = null) {
        if ($db === null) {
            $db = self::getDB();
        }
        $sql = "SELECT COUNT(*) 
                FROM battle_reports 
                WHERE (attacker_id = :player_id OR defender_id = :player_id) AND is_read = FALSE";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}
?>
