\
<?php

namespace App\\Services;

use App\\Core\\Database; // Assuming you have a Database class for DB access
use Monolog\\Logger as MonologLogger;
use Monolog\\Handler\\StreamHandler;
use Monolog\\Formatter\\LineFormatter;

class Logger {
    private static $loggers = [];
    private static $logDirectory = __DIR__ . '/../../logs/'; // Adjust path as needed

    private static function getLogger(string $channel = 'app'): MonologLogger {
        if (!isset(self::$loggers[$channel])) {
            if (!is_dir(self::$logDirectory)) {
                mkdir(self::$logDirectory, 0775, true);
            }

            $formatter = new LineFormatter(null, null, true, true);
            $handler = new StreamHandler(self::$logDirectory . $channel . '.log', MonologLogger::DEBUG);
            $handler->setFormatter($formatter);
            
            $logger = new MonologLogger($channel);
            $logger->pushHandler($handler);
            self::$loggers[$channel] = $logger;
        }
        return self::$loggers[$channel];
    }

    public static function log(string $channel, string $message, array $context = [], int $level = MonologLogger::INFO): void {
        self::getLogger($channel)->addRecord($level, $message, $context);
    }

    public static function info(string $channel, string $message, array $context = []): void {
        self::log($channel, $message, $context, MonologLogger::INFO);
    }

    public static function error(string $channel, string $message, array $context = []): void {
        self::log($channel, $message, $context, MonologLogger::ERROR);
    }

    public static function debug(string $channel, string $message, array $context = []): void {
        self::log($channel, $message, $context, MonologLogger::DEBUG);
    }

    // Method to log to database (optional, example)
    public static function logToDb(string $type, string $message, ?int $playerId = null): void {
        try {
            $db = Database::getInstance()->getConnection(); // Get PDO instance
            $sql = "INSERT INTO game_logs (log_type, message, player_id, created_at) VALUES (:type, :message, :player_id, NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':type' => $type,
                ':message' => $message,
                ':player_id' => $playerId
            ]);
        } catch (\\PDOException $e) {
            // Fallback to file logging if DB logging fails
            self::error('db_log_failure', 'Failed to write log to database: ' . $e->getMessage());
        }
    }
}
