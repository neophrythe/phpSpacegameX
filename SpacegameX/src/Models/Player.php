<?php
namespace Models;

use Core\Model;
use PDO;

class Player extends Model {
    public $id;
    public $username;
    public $password_hash;
    public $email;
    public $registration_date;
    public $last_login_date;
    public $points_research;
    public $points_building;
    public $points_fleet;
    public $points_defense;
    public $battle_points;
    public $is_admin;
    public $alliance_id;
    public $alliance_rank;
    public $savemail_address;
    public $last_capital_change; // Added property

    public static function findByUsername($username) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM players WHERE username = :username');
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    public static function findById($id) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM players WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    /**
     * Get player data by ID.
     * Alias for findById for consistency with getPlayerDataById naming convention if used elsewhere.
     *
     * @param int $id The ID of the player.
     * @return self|false Player object or false if not found.
     */
    public static function getPlayerDataById(int $id): self|false {
        return self::findById($id);
    }

    public static function create($username, $password_hash, $email) {
        $db = self::getDB();
        $sql = "INSERT INTO players (username, password_hash, email, registration_date, last_login_date) 
                VALUES (:username, :password, :email, NOW(), NOW())";
        $stmt = $db->prepare($sql);
        
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $password_hash);
        $stmt->bindParam(':email', $email);
        
        $stmt->execute();
        return $db->lastInsertId();
    }
    
    // Add other player-related database methods here
}
?>
