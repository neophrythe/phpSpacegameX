<?php
namespace Models;

use Core\\\\Model;
use PDO;

class ConstructionQueue extends Model {
    // Properties corresponding to the database table columns (assuming a 'construction_queue' table)
    public $id;
    public $planet_id;
    public $player_id; // Assuming queue is tied to a player via planet
    public $building_type_id; // For building construction
    public $ship_type_id; // For ship construction
    public $quantity; // For ship construction
    public $start_time;
    public $end_time;
    public $is_completed;
    public $queue_type; // e.g., 'building', 'ship'

    // Assuming a table structure like:
    // CREATE TABLE construction_queue (
    //     id INT AUTO_INCREMENT PRIMARY KEY,
    //     planet_id INT NOT NULL,
    //     player_id INT NOT NULL,
    //     building_type_id INT NULL,
    //     ship_type_id INT NULL,
    //     quantity INT DEFAULT 1,
    //     start_time DATETIME,
    //     end_time DATETIME,
    //     is_completed BOOLEAN DEFAULT FALSE,
    //     queue_type VARCHAR(50), -- 'building' or 'ship'
    //     FOREIGN KEY (planet_id) REFERENCES planets(id) ON DELETE CASCADE,
    //     FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    //     FOREIGN KEY (building_type_id) REFERENCES static_building_types(id) ON DELETE SET NULL,
    //     FOREIGN KEY (ship_type_id) REFERENCES static_ship_types(id) ON DELETE SET NULL
    // );

    /**
     * Get active shipyard orders for a planet.
     *
     * @param int $planetId The ID of the planet.
     * @param PDO $db Database connection.
     * @return array An array of ConstructionQueue objects (ship orders).
     */
    public static function getShipyardOrdersForPlanet(int $planetId, PDO $db): array {
        $sql = "SELECT cq.*, st.name_de as ship_name
                FROM construction_queue cq
                JOIN static_ship_types st ON cq.ship_type_id = st.id
                WHERE cq.planet_id = :planet_id 
                AND cq.queue_type = 'ship' 
                AND cq.is_completed = FALSE 
                ORDER BY cq.end_time ASC";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, self::class);
    }

    /**
     * Clear all construction queue entries for a planet.
     * Used during planet invasion.
     *
     * @param int $planetId The ID of the planet.
     * @param PDO $db Database connection.
     * @return bool True on success, false on failure.
     */
    public static function clearQueueForPlanet(int $planetId, PDO $db): bool {
        $sql = "DELETE FROM construction_queue WHERE planet_id = :planet_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Add other construction queue related methods here as needed (e.g., addOrder, completeOrder, etc.)
}
?>
