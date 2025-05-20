<?php
namespace Core;

use PDO;
use PDOException;

abstract class Model {
    protected static $db;

    public function __construct() {
        if (self::$db === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$db = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // In a real app, log this error and show a user-friendly message
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }
    }

    protected static function getDB() {
        if (self::$db === null) {
            new static(); // Initialize DB connection if not already done
        }
        return self::$db;
    }
    
    // Basic query execution (example)
    // public static function query($sql, $params = []) {
    //     $stmt = self::getDB()->prepare($sql);
    //     $stmt->execute($params);
    //     return $stmt;
    // }
}
?>
