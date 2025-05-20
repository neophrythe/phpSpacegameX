<?php
session_start(); // Start the session
// SpacegameX - Main Entry Point

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function ($class_name) {
    $file = BASE_PATH . '/src/' . str_replace('\\', '/', $class_name) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

require_once BASE_PATH . '/config/config.php';

// Simple Router
$request_uri = explode('?', $_SERVER['REQUEST_URI'], 2)[0];
$script_name = dirname($_SERVER['SCRIPT_NAME']);
$base_path = rtrim($script_name, '/\\');
$path = '/' . ltrim(substr($request_uri, strlen($base_path)), '/');

// Default route: / => GameController@overview
$routes = [
    '/' => ['controller' => 'Controllers\\GameController', 'action' => 'overview'],
    '/login' => ['controller' => 'Controllers\\AuthController', 'action' => 'login'],
    '/register' => ['controller' => 'Controllers\\AuthController', 'action' => 'register'],
    '/logout' => ['controller' => 'Controllers\\AuthController', 'action' => 'logout'],
    '/buildings' => ['controller' => 'Controllers\\BuildingController', 'action' => 'index'],
    '/buildings/build' => ['controller' => 'Controllers\\BuildingController', 'action' => 'build'],
    '/buildings/cancel' => ['controller' => 'Controllers\\BuildingController', 'action' => 'cancel'],
    '/research' => ['controller' => 'Controllers\\ResearchController', 'action' => 'index'],
    '/research/start' => ['controller' => 'Controllers\\ResearchController', 'action' => 'start'],
    '/research/cancel' => ['controller' => 'Controllers\\ResearchController', 'action' => 'cancel'],
    '/galaxy' => ['controller' => 'Controllers\\GameController', 'action' => 'galaxy'],
    '/shipyard' => ['controller' => 'Controllers\\ShipyardController', 'action' => 'index'],
    '/shipyard/build' => ['controller' => 'Controllers\\ShipyardController', 'action' => 'build'],
    '/fleet' => ['controller' => 'Controllers\\ShipyardController', 'action' => 'fleet'],
    '/shipyard/send' => ['controller' => 'Controllers\\ShipyardController', 'action' => 'send'],
    // Transmitter routes
    '/transmitter' => ['controller' => 'Controllers\\TransmitterController', 'action' => 'show'],
    '/transmitter/send' => ['controller' => 'Controllers\\TransmitterController', 'action' => 'transmit'], // Corrected method name
    // EnergieTrader routes
    '/energie-trader' => ['controller' => 'Controllers\\EnergieTraderController', 'action' => 'show'],
    '/energie-trader/transfer' => ['controller' => 'Controllers\\EnergieTraderController', 'action' => 'transferEnergy'],
    // Combat system routes
    '/combat/reports' => ['controller' => 'Controllers\\CombatController', 'action' => 'reports'],
    '/combat/report' => ['controller' => 'Controllers\\CombatController', 'action' => 'report'],
    '/combat/simulator' => ['controller' => 'Controllers\\CombatController', 'action' => 'simulator'],
    '/combat/simulate' => ['controller' => 'Controllers\\CombatController', 'action' => 'runSimulation'],
    '/combat/reports/view' => ['controller' => 'Controllers\\CombatController', 'action' => 'viewReport'], // New route for viewing combat reports
    
    // Shield system routes
    '/shields' => ['controller' => 'Controllers\\ShieldsController', 'action' => 'index'],
    '/shields/activate' => ['controller' => 'Controllers\\ShieldsController', 'action' => 'activate'],
    '/shields/deactivate' => ['controller' => 'Controllers\\ShieldsController', 'action' => 'deactivate'],
    
    // Asteroid system routes
    '/asteroids' => ['controller' => 'Controllers\\AsteroidsController', 'action' => 'index'],
    '/asteroids/mine' => ['controller' => 'Controllers\\AsteroidsController', 'action' => 'mine'],
    '/asteroids/discard' => ['controller' => 'Controllers\\AsteroidsController', 'action' => 'discard'],
    
    // Capital planet management routes
    '/capital' => ['controller' => 'Controllers\\CapitalController', 'action' => 'index'],
    '/capital/change' => ['controller' => 'Controllers\\CapitalController', 'action' => 'change'],
      // Alliance research routes
    '/alliance/research' => ['controller' => 'Controllers\\AllianceResearchController', 'action' => 'index'],
    '/alliance/research/start' => ['controller' => 'Controllers\\AllianceResearchController', 'action' => 'start'],
    '/alliance/research/cancel' => ['controller' => 'Controllers\\AllianceResearchController', 'action' => 'cancel'],
      // Notification system routes
    '/notifications' => ['controller' => 'Controllers\\NotificationController', 'action' => 'index'],
    '/notifications/unread' => ['controller' => 'Controllers\\NotificationController', 'action' => 'unread'],
    '/notifications/mark-read' => ['controller' => 'Controllers\\NotificationController', 'action' => 'markAsRead'],
    '/notifications/mark-all-read' => ['controller' => 'Controllers\\NotificationController', 'action' => 'markAllAsRead'],
    '/notifications/delete' => ['controller' => 'Controllers\\NotificationController', 'action' => 'delete'], // POST expected
    '/notifications/delete-all' => ['controller' => 'Controllers\\NotificationController', 'action' => 'deleteAll'], // POST expected
    '/notifications/cleanup' => ['controller' => 'Controllers\\NotificationController', 'action' => 'cleanup'], // POST expected, or GET if preferred for manual trigger

    // Player Message System routes
    '/messages' => ['controller' => 'Controllers\\MessageController', 'action' => 'index'], // Inbox
    '/messages/sent' => ['controller' => 'Controllers\\MessageController', 'action' => 'sent'],
    '/messages/compose' => ['controller' => 'Controllers\\MessageController', 'action' => 'compose'],
    '/messages/send' => ['controller' => 'Controllers\\MessageController', 'action' => 'send'], // POST
    '/messages/delete' => ['controller' => 'Controllers\\MessageController', 'action' => 'delete'], // POST
    '/messages/mark-read-bulk' => ['controller' => 'Controllers\\MessageController', 'action' => 'markReadBulk'], // POST, AJAX

    // Espionage System routes
    '/espionage' => ['controller' => 'Controllers\\EspionageController', 'action' => 'index'],
    '/espionage/send' => ['controller' => 'Controllers\\EspionageController', 'action' => 'sendMission'], // POST
    '/espionage/reports' => ['controller' => 'Controllers\\EspionageController', 'action' => 'reports'],
    '/espionage/move-agents' => ['controller' => 'Controllers\\EspionageController', 'action' => 'moveAgents'], // POST

    // Alliance Message System routes
    '/alliance/messages' => ['controller' => 'Controllers\\AllianceMessageController', 'action' => 'index'],
    '/alliance/messages/compose' => ['controller' => 'Controllers\\AllianceMessageController', 'action' => 'compose'],
    '/alliance/messages/send' => ['controller' => 'Controllers\\AllianceMessageController', 'action' => 'send'], // POST

    // Add more routes here as needed
];

$route = $routes['/']; // Default route
$matched = false;

// First, check static routes for an exact match
if (isset($routes[$path])) {
    $route = $routes[$path];
    $matched = true;
} else {
    // Check for dynamic routes with parameters using regex
    // Order matters here: more specific routes should come before more general ones if they share a prefix.

    // Combat Report View: /combat/reports/view/{id}
    if (preg_match('#^/combat/reports/view/(\d+)$#', $path, $matches)) {
        $route = [
            'controller' => 'Controllers\\CombatController',
            'action' => 'viewReport',
            'params' => ['id' => $matches[1]]
        ];
        $matched = true;
    }
    // Espionage Report View: /espionage/reports/view/{id}
    elseif (preg_match('#^/espionage/reports/view/(\d+)$#', $path, $matches)) {
        $route = [
            'controller' => 'Controllers\\EspionageController',
            'action' => 'viewReport',
            'params' => ['id' => $matches[1]]
        ];
        $matched = true;
    }
    // Message View: /messages/view/{id}
    elseif (preg_match('#^/messages/view/(\d+)$#', $path, $matches)) {
        $route = [
            'controller' => 'Controllers\\MessageController',
            'action' => 'view',
            'params' => ['id' => $matches[1]]
        ];
        $matched = true;
    }
    // Message Inbox Pagination: /messages/inbox/page/{page_number}
    // Or simply /messages/page/{page_number} if 'inbox' is the default for /messages
    elseif (preg_match('#^/messages/page/(\d+)$#', $path, $matches)) {
        $route = [
            'controller' => 'Controllers\\MessageController',
            'action' => 'index', // Assuming index handles pagination
            'params' => ['page' => $matches[1]]
        ];
        $matched = true;
    }
    elseif (preg_match('#^/messages/inbox/page/(\d+)$#', $path, $matches)) { // More specific inbox pagination
        $route = [
            'controller' => 'Controllers\\MessageController',
            'action' => 'index',
            'params' => ['page' => $matches[1]]
        ];
        $matched = true;
    }
    // Message Sent Pagination: /messages/sent/page/{page_number}
    elseif (preg_match('#^/messages/sent/page/(\d+)$#', $path, $matches)) {
        $route = [
            'controller' => 'Controllers\\MessageController',
            'action' => 'sent',
            'params' => ['page' => $matches[1]]
        ];
        $matched = true;
    }
    // Alliance Message View: /alliance/messages/view/{id}
    elseif (preg_match('#^/alliance/messages/view/(\d+)$#', $path, $matches)) {
        $route = [
            'controller' => 'Controllers\\AllianceMessageController',
            'action' => 'view',
            'params' => ['id' => $matches[1]]
        ];
        $matched = true;
    }
    // Alliance Message Delete: /alliance/messages/delete/{id}
    elseif (preg_match('#^/alliance/messages/delete/(\d+)$#', $path, $matches)) {
        $route = [
            'controller' => 'Controllers\\AllianceMessageController',
            'action' => 'delete',
            'params' => ['id' => $matches[1]]
        ];
        $matched = true;
    }
    // Alliance Message List Pagination: /alliance/messages/page/{page_number}
    elseif (preg_match('#^/alliance/messages/page/(\d+)$#', $path, $matches)) {
        $route = [
            'controller' => 'Controllers\\AllianceMessageController',
            'action' => 'index',
            'params' => ['page' => $matches[1]]
        ];
        $matched = true;
    }
    // Add other dynamic routes here

}

// If no route was matched, $route remains the default route ('/') or could be set to a 404 handler
if (!$matched && !isset($routes[$path])) { // If no static or dynamic match, explicitly set to 404 or handle as default
    // You might want to set a specific 404 route here if $routes['/'] is not desired for all unmatched paths
    // For now, it will fall through to the default controller/action or the 404 logic at the end.
}


$controllerName = $route['controller'];
$action = $route['action'];
$params = isset($route['params']) ? $route['params'] : [];

$controller = new $controllerName();
if (method_exists($controller, $action)) {
    call_user_func_array([$controller, $action], $params);
} else {
    http_response_code(404);
    echo '404 Not Found';
}
?>
