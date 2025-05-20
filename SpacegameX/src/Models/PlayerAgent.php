<?php
namespace Models;

use Core\Model;
use PDO;

class PlayerAgent extends Model {
    // Assuming agents are stored per planet for a player
    // Table structure might be player_agents (id, player_id, planet_id, quantity)
    // Or agents are just a quantity field in the players table.
    // Based on "Agenten ... sind immer auf einem Planeten stationiert" and "Einsetzen kann man maximal die Anzahl der eigenen Agenten auf dem Planeten",
    // it seems agents are planet-specific.

    public $id;
    public $player_id;
    public $planet_id;
    public $quantity;

    /**
     * Get the number of agents stationed on a specific planet for a player.
     *
     * @param int $planetId The ID of the planet.
     * @param PDO $db Database connection.
     * @return int The number of agents on the planet, or 0 if none.
     */
    public static function getAgentsOnPlanet(int $planetId, PDO $db): int {
        // Assuming a table 'player_agents' with columns 'planet_id' and 'quantity'
        $stmt = $db->prepare('SELECT quantity FROM player_agents WHERE planet_id = :planet_id');
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->execute();
        $quantity = $stmt->fetchColumn();
        return $quantity !== false ? (int)$quantity : 0;
    }

    /**
     * Add agents to a specific planet for a player.
     * Assumes the player_id is linked via the planet_id or handled elsewhere.
     * This method should handle creating the entry if it doesn't exist.
     *
     * @param int $planetId The ID of the planet.
     * @param int $quantity The number of agents to add.
     * @param PDO $db Database connection.
     * @return bool True on success, false on failure.
     */
    public static function addAgents(int $planetId, int $quantity, PDO $db): bool {
        if ($quantity <= 0) return true;

        // Need player_id associated with the planet
        $planet = Planet::getById($planetId, $db);
        if (!$planet || !$planet->player_id) {
            error_log("PlayerAgent::addAgents: Planet {$planetId} not found or unowned.");
            return false;
        }
        $playerId = $planet->player_id;

        // Assuming ON DUPLICATE KEY UPDATE syntax for adding
        $sql = "INSERT INTO player_agents (player_id, planet_id, quantity)
                VALUES (:player_id, :planet_id, :quantity)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        
        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("PlayerAgent::addAgents Database error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove agents from a specific planet for a player.
     * Ensures quantity does not go below zero.
     *
     * @param int $planetId The ID of the planet.
     * @param int $quantity The number of agents to remove.
     * @param PDO $db Database connection.
     * @return bool True on success, false on failure (e.g., not enough agents).
     */
    public static function removeAgents(int $planetId, int $quantity, PDO $db): bool {
        if ($quantity <= 0) return true;

        // Check if sufficient agents are on the planet
        $currentQuantity = self::getAgentsOnPlanet($planetId, $db);
        if ($currentQuantity < $quantity) {
            error_log("PlayerAgent::removeAgents: Not enough agents on planet {$planetId}. Needed: {$quantity}, Available: {$currentQuantity}.");
            return false; // Not enough agents
        }

        $newQuantity = $currentQuantity - $quantity;

        if ($newQuantity <= 0) {
            // Delete the entry if quantity drops to zero or below
            $sql = "DELETE FROM player_agents WHERE planet_id = :planet_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        } else {
            // Update quantity
            $sql = "UPDATE player_agents SET quantity = :new_quantity WHERE planet_id = :planet_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':new_quantity', $newQuantity, PDO::PARAM_INT);
            $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        }

        try {
            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("PlayerAgent::removeAgents Database error: " . $e->getMessage());
            return false;
        }
    }
}
?>
