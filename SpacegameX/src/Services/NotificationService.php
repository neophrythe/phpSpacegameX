<?php
namespace Services;

use Models\PlayerNotification;
use Models\Player;
use Models\Planet;

/**
 * Handles notification creation for various game events
 */
class NotificationService {
    
    // Notification types - These should align with PlayerNotification constants if they serve the same purpose
    // For now, keeping them as is, but consider consolidation if PlayerNotification types are sufficient.
    const TYPE_BUILDING = 'building_complete'; // Aligned with PlayerNotification
    const TYPE_RESEARCH = 'research_complete'; // Aligned with PlayerNotification
    const TYPE_SHIPYARD = 'shipyard_complete'; // Aligned with PlayerNotification
    // const TYPE_FLEET = 'fleet'; // Generic fleet, might need more specific types like arrival, return
    const TYPE_FLEET_ARRIVAL = 'fleet_arrival';
    const TYPE_FLEET_RETURN = 'fleet_return'; // Example for a new type
    const TYPE_ATTACK_INCOMING = 'attack_incoming'; // More specific than just 'attack'
    // const TYPE_DEFENSE = 'defense'; // This seems too generic, defense completion is covered by shipyard
    const TYPE_RESOURCE_TRANSFER = 'resource_transfer';
    const TYPE_ASTEROID_EVENT = 'asteroid_event'; // For discovered, discarded, expired
    const TYPE_CAPITAL_CHANGE = 'capital_change';
    const TYPE_ALLIANCE_EVENT = 'alliance_event'; // For research, member join/leave etc.
    const TYPE_SYSTEM_MESSAGE = 'system_message';
    

    /**
     * Create a notification when a building has been completed
     */
    public static function buildingCompleted($playerId, $buildingName, $level, $planetName, $planetId = null) {
        $message = "Gebäude <strong>{$buildingName}</strong> auf {$planetName} wurde auf Stufe {$level} fertiggestellt.";
        $link = $planetId ? (BASE_URL . '/overview/planet/' . $planetId) : null; // Link to planet overview
        return PlayerNotification::createNotification($playerId, PlayerNotification::TYPE_BUILDING_COMPLETE, $message, $link);
    }
    
    /**
     * Create a notification when research has been completed
     */
    public static function researchCompleted($playerId, $researchName, $level) {
        $message = "Forschung <strong>{$researchName}</strong> wurde auf Stufe {$level} fertiggestellt.";
        $link = BASE_URL . '/research'; // Link to research page
        return PlayerNotification::createNotification($playerId, PlayerNotification::TYPE_RESEARCH_COMPLETE, $message, $link);
    }
    
    /**
     * Create a notification when ships have been built
     */
    public static function shipsCompleted($playerId, $shipName, $quantity, $planetName, $planetId = null) {
        $message = "{$quantity}x <strong>{$shipName}</strong> auf {$planetName} wurde(n) fertiggestellt.";
        $link = $planetId ? (BASE_URL . '/shipyard/planet/' . $planetId) : (BASE_URL . '/shipyard'); // Link to shipyard
        return PlayerNotification::createNotification($playerId, PlayerNotification::TYPE_SHIPYARD_COMPLETE, $message, $link);
    }
    
    /**
     * Create a notification when defense systems have been built
     */
    public static function defenseCompleted($playerId, $defenseName, $quantity, $planetName, $planetId = null) {
        $message = "{$quantity}x <strong>{$defenseName}</strong> auf {$planetName} wurde(n) fertiggestellt.";
        // Assuming defense is also built in shipyard or a similar "defense" page
        $link = $planetId ? (BASE_URL . '/defense/planet/' . $planetId) : (BASE_URL . '/defense'); 
        return PlayerNotification::createNotification($playerId, PlayerNotification::TYPE_SHIPYARD_COMPLETE, $message, $link); // Using SHIPYARD_COMPLETE for now
    }
    
    /**
     * Create a notification when a fleet arrives at a destination
     */
    public static function fleetArrival($playerId, $sourcePlanet, $destPlanet, $missionType, $fleetId = null) {
        $missionTypes = [
            'attack' => 'Angriff',
            'transport' => 'Transport',
            'colonize' => 'Kolonialisierung',
            'harvest' => 'Ernten',
            'espionage' => 'Spionage',
            'deploy' => 'Stationierung'
        ];
        
        $mission = $missionTypes[$missionType] ?? ucfirst($missionType);
        $message = "Deine Flotte von <strong>{$sourcePlanet}</strong> ist bei <strong>{$destPlanet}</strong> mit der Mission '{$mission}' angekommen.";
        $link = $fleetId ? (BASE_URL . '/fleet/view/' . $fleetId) : (BASE_URL . '/fleet');
        return PlayerNotification::createNotification($playerId, self::TYPE_FLEET_ARRIVAL, $message, $link);
    }

    /**
     * Create a notification when a fleet returns to its origin
     */
    public static function fleetReturn($playerId, $destPlanet, $originPlanet, $missionType, $fleetId = null) {
        $missionTypes = [
            'attack' => 'Angriff',
            'transport' => 'Transport',
            'colonize' => 'Kolonialisierung',
            'harvest' => 'Ernten',
            'espionage' => 'Spionage',
            'deploy' => 'Stationierung'
        ];
        $mission = $missionTypes[$missionType] ?? ucfirst($missionType);
        $message = "Deine Flotte ist von ihrer Mission '{$mission}' bei <strong>{$destPlanet}</strong> nach <strong>{$originPlanet}</strong> zurückgekehrt.";
        $link = $fleetId ? (BASE_URL . '/fleet/view/' . $fleetId) : (BASE_URL . '/fleet');
        return PlayerNotification::createNotification($playerId, self::TYPE_FLEET_RETURN, $message, $link);
    }
    
    /**
     * Create a notification when a planet is under attack
     */
    public static function planetAttacked($playerId, $planetName, $planetId = null) {
        $message = "Dein Planet <strong>{$planetName}</strong> wird angegriffen!";
        $link = $planetId ? (BASE_URL . '/overview/planet/' . $planetId) : (BASE_URL . '/overview');
        // This might also link to a battle report if one is generated immediately, or just overview.
        return PlayerNotification::createNotification($playerId, self::TYPE_ATTACK_INCOMING, $message, $link);
    }

    /**
     * Create a notification for asteroid events (discovered, discarded, expired)
     */
    private static function asteroidEvent($playerId, $eventType, $asteroidType, $bonus, $planetName = null, $planetId = null) {
        $asteroidTypes = [
            'metal' => 'Eisen', 'crystal' => 'Silber', 'uderon' => 'Uderon', 'mixed' => 'gemischter'
        ];
        $type = $asteroidTypes[$asteroidType] ?? $asteroidType;
        $onPlanetStr = $planetName ? " auf <strong>{$planetName}</strong>" : "";

        switch ($eventType) {
            case 'discovered':
                $message = "Ein {$type} Asteroid wurde entdeckt{$onPlanetStr}! Er bietet einen Ressourcenbonus von {$bonus}%.";
                break;
            case 'discarded':
                $message = "Ein {$type} Asteroid{$onPlanetStr} mit einem Ressourcenbonus von {$bonus}% wurde abgebaut.";
                break;
            case 'expired':
                $message = "Ein {$type} Asteroid{$onPlanetStr} mit einem Ressourcenbonus von {$bonus}% ist abgelaufen.";
                break;
            default:
                $message = "Unbekanntes Asteroidenereignis.";
        }
        $link = $planetId ? (BASE_URL . '/overview/planet/' . $planetId) : (BASE_URL . '/overview');
        return PlayerNotification::createNotification($playerId, self::TYPE_ASTEROID_EVENT, $message, $link);
    }

    public static function asteroidDiscovered($playerId, $asteroidType, $bonus, $planetName = null, $planetId = null) {
        return self::asteroidEvent($playerId, 'discovered', $asteroidType, $bonus, $planetName, $planetId);
    }

    public static function asteroidDiscarded($playerId, $asteroidType, $bonus, $planetName = null, $planetId = null) {
        return self::asteroidEvent($playerId, 'discarded', $asteroidType, $bonus, $planetName, $planetId);
    }

    public static function asteroidExpired($playerId, $asteroidType, $bonus, $planetName = null, $planetId = null) {
        return self::asteroidEvent($playerId, 'expired', $asteroidType, $bonus, $planetName, $planetId);
    }
      
    /**
     * Create a notification when the capital planet changes
     */
    public static function capitalChanged($playerId, $planetName, $galaxy = null, $system = null, $position = null, $planetId = null) {
        $message = "Der Planet <strong>{$planetName}</strong>";
        if ($galaxy !== null && $system !== null && $position !== null) {
            $message .= " [{$galaxy}:{$system}:{$position}]";
        }
        $message .= " ist nun dein neuer Hauptplanet. Er erhält einen Produktionsbonus von 20%.";
        $link = $planetId ? (BASE_URL . '/overview/planet/' . $planetId) : (BASE_URL . '/overview');
        return PlayerNotification::createNotification($playerId, self::TYPE_CAPITAL_CHANGE, $message, $link);
    }
    
    /**
     * Create a notification when an alliance research is complete
     */
    public static function allianceResearchCompleted($playerId, $researchName, $level) {
        $message = "Allianz-Forschung <strong>{$researchName}</strong> wurde auf Stufe {$level} fertiggestellt.";
        $link = BASE_URL . '/alliance/research'; // Link to alliance research page
        return PlayerNotification::createNotification($playerId, self::TYPE_ALLIANCE_EVENT, $message, $link);
    }
    
    /**
     * Create a notification when resources are transported
     */
    public static function resourceTransferred($playerId, $sourcePlanet, $destPlanet, $resources, $sourcePlanetId = null, $destPlanetId = null) {
        $resourceList = [];
        foreach ($resources as $type => $amount) {
            if ($amount > 0) {
                $resourceNames = [
                    'eisen' => 'Eisen', 'silber' => 'Silber', 'uderon' => 'Uderon', 'wasserstoff' => 'Wasserstoff'
                ];
                $name = $resourceNames[$type] ?? ucfirst($type);
                $resourceList[] = number_format($amount, 0, ',', '.') . ' ' . $name;
            }
        }
        $resourcesText = implode(', ', $resourceList);
        $message = "Ressourcen ({$resourcesText}) wurden von <strong>{$sourcePlanet}</strong> nach <strong>{$destPlanet}</strong> transferiert.";
        // Link could go to the destination planet or a general overview
        $link = $destPlanetId ? (BASE_URL . '/overview/planet/' . $destPlanetId) : (BASE_URL . '/overview');
        return PlayerNotification::createNotification($playerId, self::TYPE_RESOURCE_TRANSFER, $message, $link);
    }
    
    /**
     * Create a notification for a system message
     */
    public static function systemMessage($playerId, $message, $link = null) {
        return PlayerNotification::createNotification($playerId, self::TYPE_SYSTEM_MESSAGE, $message, $link);
    }
    
    /**
     * Send notification to all alliance members
     */
    public static function notifyAllianceMembers($allianceId, $type, $message, $link = null) {
        $db = Player::getDB(); // Assuming Player model has getDB()
        $stmt = $db->prepare('SELECT id FROM players WHERE alliance_id = :alliance_id');
        $stmt->bindParam(':alliance_id', $allianceId, \PDO::PARAM_INT);
        $stmt->execute();
        
        $notificationIds = [];
        // Use PlayerNotification constants for type if applicable
        $notificationType = defined('Models\PlayerNotification::' . strtoupper($type)) ? constant('Models\PlayerNotification::' . strtoupper($type)) : PlayerNotification::TYPE_GENERIC;

        while ($player = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $notificationIds[] = PlayerNotification::createNotification($player['id'], $notificationType, $message, $link);
        }
        return $notificationIds;
    }
    
    /**
     * Delete old notifications (older than X days)
     * This is a general cleanup, might be better in a dedicated controller or cron job.
     * For now, it can be called periodically.
     */
    public static function cleanupOldNotificationsForPlayer($playerId, $days = 30) {
        $db = PlayerNotification::getDB();
        $sql = "DELETE FROM player_notifications 
                WHERE player_id = :player_id 
                AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                AND is_read = TRUE";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, \PDO::PARAM_INT);
        $stmt->bindParam(':days', $days, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    // It might be useful to have a global cleanup for all players, perhaps run by an admin or cron.
    public static function cleanupOldNotificationsGlobal($days = 30) {
        $db = PlayerNotification::getDB();
        $sql = "DELETE FROM player_notifications 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                AND is_read = TRUE";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':days', $days, \PDO::PARAM_INT);
        return $stmt->execute();
    }
}
?>
