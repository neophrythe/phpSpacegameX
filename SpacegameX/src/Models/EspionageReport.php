<?php
namespace Models;

use Core\Model;
use PDO;

class EspionageReport extends Model {
    public $id;
    public $player_id;
    public $target_planet_id;
    public $report_time;
    public $report_data; // JSON data
    public $is_read;

    public static function getById($id) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM espionage_reports WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    public static function getReportsByPlayerId($playerId) {
        $db = self::getDB();
        $sql = 'SELECT * FROM espionage_reports WHERE player_id = :player_id ORDER BY report_time DESC';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }

    public static function create($playerId, $targetPlanetId, $reportDataJson) {
        $db = self::getDB();
        $sql = "INSERT INTO espionage_reports (player_id, target_planet_id, report_time, report_data, is_read) 
                VALUES (:player_id, :target_planet_id, NOW(), :report_data, FALSE)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':target_planet_id', $targetPlanetId, PDO::PARAM_INT);
        $stmt->bindParam(':report_data', $reportDataJson, PDO::PARAM_LOB); // Use PDO::PARAM_LOB for LONGTEXT
        
        if ($stmt->execute()) {
            $reportId = $db->lastInsertId();
            $targetPlanet = Planet::getById($targetPlanetId); // Fetch planet for coordinates in notification
            $targetCoords = $targetPlanet ? $targetPlanet->getCoordinates() : 'Unbekannt';

            PlayerNotification::createNotification(
                $playerId,
                PlayerNotification::TYPE_ESPIONAGE_REPORT, 
                "Spionagebericht: {$targetCoords}", // Standardized message
                "/espionage/reports/view/{$reportId}" 
            );
            return $reportId;
        }
        return false;
    }

    public function markAsRead(PDO $db = null) {
        if ($db === null) {
            $db = self::getDB();
        }
        $sql = "UPDATE espionage_reports SET is_read = TRUE WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public static function getUnreadReportsCountByPlayerId($playerId, PDO $db = null) {
        if ($db === null) {
            $db = self::getDB();
        }
        $sql = "SELECT COUNT(*) 
                FROM espionage_reports 
                WHERE player_id = :player_id AND is_read = FALSE";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}
?>
