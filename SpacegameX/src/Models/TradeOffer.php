<?php
namespace Models;

use Core\Model;
use PDO;

class TradeOffer extends Model {
    public $id;
    public $player_id;
    public $sell_resource_id; // Consider if we need a static resource type table
    public $sell_resource_type;
    public $sell_quantity;
    public $buy_resource_id; // Consider if we need a static resource type table
    public $buy_resource_type;
    public $buy_quantity;
    public $planet_id;
    public $is_active;
    public $created_at;

    public static function getAllActiveOffers() {
        $db = self::getDB();
        $stmt = $db->query('SELECT * FROM trade_offers WHERE is_active = TRUE');
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }

    public static function getActiveOffersByPlayerId($playerId) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM trade_offers WHERE player_id = :player_id AND is_active = TRUE');
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, get_called_class());
    }

    public static function getById($id) {
        $db = self::getDB();
        $stmt = $db->prepare('SELECT * FROM trade_offers WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchObject(get_called_class());
    }

    public static function create($playerId, $sellResourceType, $sellQuantity, $buyResourceType, $buyQuantity, $planetId) {
        $db = self::getDB();
        $sql = "INSERT INTO trade_offers (player_id, sell_resource_type, sell_quantity, buy_resource_type, buy_quantity, planet_id, is_active, created_at) 
                VALUES (:player_id, :sell_resource_type, :sell_quantity, :buy_resource_type, :buy_quantity, :planet_id, TRUE, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindParam(':sell_resource_type', $sellResourceType, PDO::PARAM_STR);
        $stmt->bindParam(':sell_quantity', $sellQuantity, PDO::PARAM_STR);
        $stmt->bindParam(':buy_resource_type', $buyResourceType, PDO::PARAM_STR);
        $stmt->bindParam(':buy_quantity', $buyQuantity, PDO::PARAM_STR);
        $stmt->bindParam(':planet_id', $planetId, PDO::PARAM_INT);
        $stmt->execute();
        return $db->lastInsertId();
    }

    public function markAsInactive() {
        $db = self::getDB();
        $sql = "UPDATE trade_offers SET is_active = FALSE WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
?>
