<?php
namespace Controllers;

use Core\Controller;
use Models\PlayerNotification;
use Models\Player;
use Services\NotificationService; // Added for cleanup

class NotificationController extends Controller {
    
    /**
     * Retrieve all notifications for the current player
     */
    public function index() {
        // Authentication check
        if (!isset($_SESSION['user_id'])) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Nicht eingeloggt.']);
                exit;
            }
            $this->redirect('/login');
        }
        
        $playerId = $_SESSION['user_id'];
        
        if ($this->isAjaxRequest()) {
            // Return JSON for AJAX requests
            $notifications = PlayerNotification::getNotificationsByPlayerId($playerId);
            header('Content-Type: application/json');
            echo json_encode($notifications);
            exit;
        } else {
            // Render the notifications view
            $notifications = PlayerNotification::getNotificationsByPlayerId($playerId);
            $this->view('notifications.index', [
                'pageTitle' => 'Benachrichtigungen',
                'notifications' => $notifications
            ]);
        }
    }
    
    /**
     * Retrieve only unread notifications for the current player
     */
    public function unread() {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Nicht eingeloggt.']);
            exit;
        }
        
        $playerId = $_SESSION['user_id'];
        $notifications = PlayerNotification::getNotificationsByPlayerId($playerId, true);
        
        header('Content-Type: application/json');
        echo json_encode($notifications);
    }
    
    /**
     * Mark one or more notifications as read
     */
    public function markAsRead() {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Nicht eingeloggt.']);
            exit;
        }
        
        if (!isset($_POST['notification_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Keine Benachrichtigungs-ID angegeben.']);
            exit;
        }
        
        $notificationId = (int)$_POST['notification_id'];
        $playerId = $_SESSION['user_id'];
        
        // Ensure the notification belongs to the current player
        $notification = PlayerNotification::getById($notificationId);
        if (!$notification || $notification->player_id != $playerId) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Ungültige Benachrichtigung.']);
            exit;
        }
        
        $success = $notification->markAsRead();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
    }
    
    /**
     * Mark all notifications for the current player as read
     */
    public function markAllAsRead() {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Nicht eingeloggt.']);
            exit;
        }
          $playerId = $_SESSION['user_id'];
        
        // Mark all as read
        $db = \Models\PlayerNotification::getDB();
        $sql = "UPDATE player_notifications SET is_read = TRUE WHERE player_id = :player_id";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':player_id', $playerId, \PDO::PARAM_INT);
        $success = $stmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
    }
    
    /**
     * Count unread notifications for the player
     */
    public function count() {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Nicht eingeloggt.']);
            exit;
        }
          $playerId = $_SESSION['user_id'];
        
        // Get count of unread notifications using the model method
        $count = PlayerNotification::countUnreadByPlayerId($playerId);
          header('Content-Type: application/json');
        echo json_encode(['count' => $count]);
    }
    
    /**
     * Delete a notification
     */
    public function delete() {
        if (!isset($_SESSION['user_id'])) {
            return $this->jsonError('Nicht eingeloggt.');
        }

        if (!isset($_POST['notification_id'])) {
            return $this->jsonError('Keine Benachrichtigungs-ID angegeben.');
        }

        $notificationId = (int)$_POST['notification_id'];
        $playerId = $_SESSION['user_id'];

        $notification = PlayerNotification::getById($notificationId);
        if (!$notification || $notification->player_id != $playerId) {
            return $this->jsonError('Ungültige Benachrichtigung oder keine Berechtigung.');
        }

        $success = $notification->delete();
        return $this->jsonSuccess(['success' => $success]);
    }

    /**
     * Delete all notifications for the current player
     */
    public function deleteAll() {
        if (!isset($_SESSION['user_id'])) {
            return $this->jsonError('Nicht eingeloggt.');
        }

        $playerId = $_SESSION['user_id'];
        $success = PlayerNotification::deleteAllByPlayerId($playerId); // Assuming this method exists or will be added
        
        return $this->jsonSuccess(['success' => $success]);
    }

    /**
     * Cleanup old, read notifications for the current player.
     * Defaults to notifications older than 30 days.
     */
    public function cleanup() {
        if (!isset($_SESSION['user_id'])) {
            return $this->jsonError('Nicht eingeloggt.');
        }

        $playerId = $_SESSION['user_id'];
        $daysToKeep = 30; // Default, could be made configurable later

        try {
            $cleanedCount = NotificationService::cleanupOldNotificationsForPlayer($playerId, $daysToKeep);
            if ($cleanedCount === false) { // Check for explicit false, as 0 is a valid count
                 return $this->jsonError('Fehler beim Aufräumen der Benachrichtigungen.');
            }
            return $this->jsonSuccess(['message' => "{$cleanedCount} alte, gelesene Benachrichtigungen wurden entfernt.", 'cleaned_count' => $cleanedCount]);
        } catch (\Exception $e) {
            // Log error $e->getMessage();
            return $this->jsonError('Ein serverseitiger Fehler ist aufgetreten.');
        }
    }

    /**
     * Helper method to check if the request is an AJAX request
     */
    private function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    // Helper methods for JSON responses to reduce repetition
    private function jsonError($message, $statusCode = 400) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }

    private function jsonSuccess($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
?>
