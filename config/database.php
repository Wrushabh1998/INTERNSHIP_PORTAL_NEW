<?php
/**
 * Database Connection Class (PDO)
 * Singleton pattern — single connection per request
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                self::$instance->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (PDOException $e) {
                // Check if error is 'Unknown database'
                if ($e->getCode() == 1049 || str_contains($e->getMessage(), 'Unknown database')) {
                    try {
                        $dsnNoDb = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
                        $tempPdo = new PDO($dsnNoDb, DB_USER, DB_PASS, $options);
                        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci");
                        
                        // Connect to newly created database
                        self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                        self::$instance->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                        
                        // Check if schema is loaded
                        $tableCheck = self::$instance->query("SHOW TABLES LIKE 'admin'");
                        if ($tableCheck->rowCount() === 0) {
                            $sqlFile = BASE_PATH . '/database/schema.sql';
                            if (file_exists($sqlFile)) {
                                $sql = file_get_contents($sqlFile);
                                // Strip UTF-8 BOM if present
                                $sql = ltrim($sql, "\xef\xbb\xbf");
                                self::$instance->exec($sql);
                            }
                        }
                    } catch (PDOException $ex) {
                        error_log('[DB AUTO-SETUP ERROR] ' . $ex->getMessage());
                        die(json_encode(['success' => false, 'message' => 'Database auto-creation failed: ' . $ex->getMessage()]));
                    }
                } else {
                    error_log('[DB ERROR] ' . $e->getMessage());
                    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
                }
            }
        }
        return self::$instance;
    }

    // Prevent cloning
    private function __clone() {}
}

/**
 * Convenience function — returns the PDO instance
 */
function db(): PDO {
    return Database::getConnection();
}
