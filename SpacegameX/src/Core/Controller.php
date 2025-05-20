<?php
namespace Core;

abstract class Controller {
    protected function view($viewName, $data = []) {
        // Add notification count for logged in users
        if (isset($_SESSION['user_id'])) {
            if (!isset($data['unreadNotificationCount'])) {
                // Only load if not already provided
                $data['unreadNotificationCount'] = \Models\PlayerNotification::countUnreadByPlayerId($_SESSION['user_id']);
            }
        }

        // Add flash messages to data and clear them
        if (isset($_SESSION['flash_messages'])) {
            $data['flash_messages'] = $_SESSION['flash_messages'];
            unset($_SESSION['flash_messages']);
        } else {
            $data['flash_messages'] = [];
        }
        
        extract($data); // Make data available as variables in the view
        
        // Construct path to view
        $viewPath = BASE_PATH . '/templates/' . str_replace('.', '/', $viewName) . '.php';

        if (file_exists($viewPath)) {
            require_once $viewPath;
        } else {
            // Handle view not found
            echo "Error: View '$viewName' not found at " . htmlspecialchars($viewPath);
        }
    }

    protected function redirect($url) {
        error_log("Redirecting to: " . $url . " from " . debug_backtrace()[1]['class'] . "::" . debug_backtrace()[1]['function'] . ". Session ID: " . session_id() . " Session Data: " . print_r($_SESSION, true));
        header('Location: ' . $url);
        exit;
    }

    protected function setFlashMessage($type, $message) {
        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }
        $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
    }
}
?>
