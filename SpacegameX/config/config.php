<?php
// SpacegameX Configuration File

// Database Configuration
define('DB_HOST', 'localhost'); // Or 127.0.0.1
define('DB_USER', 'root');      // Your local MySQL username
define('DB_PASS', '');          // Your local MySQL password
define('DB_NAME', 'spacegamex_local'); // Your local database name
define('DB_CHARSET', 'utf8mb4');

// Site Configuration
define('SITE_NAME', 'SpacegameX');
define('BASE_URL', 'http://localhost/'); // Adjusted for DocumentRoot pointing to public
// define('BASE_URL', 'http://localhost/SpacegameX/public/'); // Original - Adjust if needed

// Error Reporting for Local Development
error_reporting(E_ALL);
ini_set('display_errors', '1'); // Display errors in browser
ini_set('log_errors', '1');     // Log errors to a file
ini_set('error_log', 'F:/sdi/wog/SpacegameX/logs/php_errors.log'); // Ensure this path and directory are writable

// Game Settings (examples)
define('INITIAL_PLANETS', 1);
define('INITIAL_EISEN', 500);
define('INITIAL_SILBER', 500);
define('INITIAL_UDERON', 100);
define('INITIAL_WASSERSTOFF', 0);
define('INITIAL_ENERGIE', 0);
define('MAX_ACTIVE_FLEETS', 16); // Maximum number of fleets a player can have active
define('UNIVERSE_SPEED_FACTOR', 1.0); // Default universe speed factor. Higher is faster.

// Combat Settings
define('COMBAT_MAX_ROUNDS', 6);
define('PLUNDER_PERCENTAGE', 0.50); // 50% of available resources (metal, silber, uderon, wasserstoff)
define('DEBRIS_SHIP_METAL_PERCENTAGE', 0.30);    // 30% of ship's metal cost to debris
define('DEBRIS_SHIP_SILBER_PERCENTAGE', 0.30);   // 30% of ship's silber cost to debris
define('DEBRIS_DEFENSE_METAL_PERCENTAGE', 0.30); // 30% of defense's metal cost to debris
define('DEBRIS_DEFENSE_SILBER_PERCENTAGE', 0.30); // 30% of defense's silber cost to debris
// Uderon and Wasserstoff typically don't form debris fields from ships or defense. Set to true to include them based on their respective costs and percentages.
define('DEBRIS_RESOURCES_INCLUDE_UDERON', false);
define('DEBRIS_RESOURCES_INCLUDE_WASSERSTOFF', false);
define('DEBRIS_SHIP_UDERON_PERCENTAGE', 0.15);    // Example: 15% if uderon is included
define('DEBRIS_SHIP_WASSERSTOFF_PERCENTAGE', 0.0); // Example: 0% if wasserstoff is included
define('DEBRIS_DEFENSE_UDERON_PERCENTAGE', 0.15); // Example: 15% if uderon is included
define('DEBRIS_DEFENSE_WASSERSTOFF_PERCENTAGE', 0.0); // Example: 0% if wasserstoff is included

define('DEFENSE_REBUILD_CHANCE', 0.70); // 70% chance for destroyed defenses to rebuild

// Research effect percentages (per level) for debris modification
// These add to the base DEBRIS_*_PERCENTAGE values, capped at a reasonable maximum (e.g., 0.75 or 75%)
define('RESEARCH_ATT_RECYCLING_BONUS_PER_LEVEL', 0.01); // e.g., +1% to debris percentage from defender's units
define('RESEARCH_RECYCLING_BONUS_PER_LEVEL', 0.01);     // e.g., +1% to debris percentage from attacker's units
define('MAX_DEBRIS_RECOVERY_PERCENTAGE', 0.75); // Maximum total debris percentage including research bonuses

// Language
define('DEFAULT_LANGUAGE', 'de'); // 'de' for German, 'en' for English

// Error Reporting (Development vs Production)
// For development:
error_reporting(E_ALL);
ini_set('display_errors', 1);
// For production, you might want to log errors instead:
// error_reporting(0);
// ini_set('display_errors', 0);
// ini_set('log_errors', 1);
// ini_set('error_log', BASE_PATH . '/logs/php_error.log');

// Timezone
date_default_timezone_set('Europe/Berlin');

?>
