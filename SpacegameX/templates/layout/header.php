<!DOCTYPE html>
<html lang="<?php echo DEFAULT_LANGUAGE; ?>">
<head>
    <meta charset="UTF-8">    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' . SITE_NAME : SITE_NAME; ?></title>    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/energy.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/features.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/notifications.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/wog-2.0.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/wog30.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/messages.css"> <!-- Added Messages CSS -->
    <!-- Additional CSS can be linked here -->
</head>
<body>
    <header>
        <div class="container">
            <div id="branding">
                <h1><a href="<?php echo BASE_URL; ?>"><?php echo SITE_NAME; ?></a></h1>
            </div>
            <nav>
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>/">Übersicht</a></li>                    <li><a href="<?php echo BASE_URL; ?>/galaxy">Galaxie</a></li>                    <li><a href="<?php echo BASE_URL; ?>/buildings">Gebäude</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/research">Forschung</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/shipyard">Werft</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/fleet">Flotte</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/shields">Schilde</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/asteroids">Asteroiden</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/capital">Hauptplanet</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/alliance">Allianz</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/messages">Nachrichten</a></li> <!-- Added Messages Link -->
                    <li><a href="<?php echo BASE_URL; ?>/combat/reports">Kampfberichte</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/combat/simulator">Simulator</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/logout">Logout</a></li>
                    <!-- More links as needed -->
                </ul>
            </nav>
        </div>    </header>
    <div class="notification-center" id="notificationCenter">
        <div class="notification-header">
            <h3>Benachrichtigungen</h3>
            <span class="close-notifications" id="closeNotifications">&times;</span>
        </div>
        <div class="notification-list" id="notificationList">
            <!-- Notifications will be inserted here dynamically -->
        </div>
    </div>    <div class="notification-icon <?php echo isset($unreadNotificationCount) && $unreadNotificationCount > 0 ? 'has-notifications' : ''; ?>" id="notificationIcon">
        <span class="icon">🔔</span>
        <span class="notification-count" id="notificationCount"><?php echo isset($unreadNotificationCount) ? $unreadNotificationCount : '0'; ?></span>
    </div>

    <?php if (!empty($flash_messages)): ?>
        <div class="flash-messages">
            <?php foreach ($flash_messages as $message): ?>
                <div class="flash-message flash-<?php echo htmlspecialchars($message['type']); ?>">
                    <?php echo htmlspecialchars($message['message']); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <script>
        // Pass notification count to JavaScript
        var unreadNotificationCount = <?php echo isset($unreadNotificationCount) ? $unreadNotificationCount : '0'; ?>;
    </script>
    
    <div class="container main-content">
