<?php

namespace OneScript\Engine;

use PDO;
use PDOException;

class Database {
    private static ?PDO $pdo = null;
    private static string $driver = 'mysql';

    public static function init(array $config): PDO {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $driver = $config['driver'] ?? 'mysql';
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $dbname = $config['dbname'] ?? 'onescript_db';
        $user = $config['username'] ?? 'root';
        $pass = $config['password'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';

        if ($driver === 'mysql') {
            try {
                // First try connecting to MySQL server directly
                $dsnWithoutDb = "mysql:host={$host};port={$port};charset={$charset}";
                $pdoInit = new PDO($dsnWithoutDb, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);

                // Ensure database exists
                $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // Connect with Database selected
                $dsnWithDb = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
                self::$pdo = new PDO($dsnWithDb, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                self::$driver = 'mysql';
            } catch (PDOException $e) {
                // Fallback to SQLite file DB if MySQL is offline or unconfigured locally
                $sqliteFile = OneScript::getRootDir() . '/onescript.sqlite';
                self::$pdo = new PDO("sqlite:" . $sqliteFile, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                self::$driver = 'sqlite';
            }
        } else {
            $sqliteFile = OneScript::getRootDir() . '/onescript.sqlite';
            self::$pdo = new PDO("sqlite:" . $sqliteFile, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$driver = 'sqlite';
        }

        self::ensureDefaultTables();
        return self::$pdo;
    }

    public static function getDriver(): string {
        return self::$driver;
    }

    public static function loadConfigFromOneFile(): array {
        $configPath = OneScript::getRootDir() . '/config.one';
        $default = [
            'driver'      => 'mysql',
            'host'        => '127.0.0.1',
            'port'        => 3306,
            'dbname'      => 'onescript_db',
            'username'    => 'root',
            'password'    => '',
            'charset'     => 'utf8mb4',
            'name'        => 'OneScript Portal',
            'env'         => 'development',
            'debug'       => true,
            'url'         => 'http://onescript.test',
            'timezone'    => 'Asia/Dhaka',
            'enabled'     => true,
            'lifetime'    => 3600,
            'auto_escape' => true,
        ];

        if (file_exists($configPath)) {
            $content = file_get_contents($configPath);
            if (preg_match_all('/([a-zA-Z0-9_]+)\s*=\s*["\']?([^"\']*)["\']?/i', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $key = strtolower(trim($m[1]));
                    $val = trim($m[2]);
                    if (strtolower($val) === 'true') $val = true;
                    if (strtolower($val) === 'false') $val = false;
                    if ($key === 'port' || $key === 'lifetime') $val = (int)$val;
                    $default[$key] = $val;
                }
            }
        }

        return $default;
    }

    public static function getPdo(): PDO {
        if (self::$pdo === null) {
            $config = self::loadConfigFromOneFile();
            self::init($config);
        }
        return self::$pdo;
    }

    private static function ensureDefaultTables(): void {
        $pdo = self::$pdo;
        if (self::$driver === 'mysql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `products` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(255) NOT NULL,
                    `category` VARCHAR(100) DEFAULT 'General',
                    `price` DECIMAL(10,2) DEFAULT 0.00,
                    `description` TEXT,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `contacts` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(255) NOT NULL,
                    `email` VARCHAR(255) NOT NULL,
                    `message` TEXT,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS products (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    category TEXT DEFAULT 'General',
                    price REAL DEFAULT 0.00,
                    description TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS contacts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT NOT NULL,
                    message TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
            ");
        }

        // Insert sample products if empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM products");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO products (name, category, price, description) VALUES 
                ('OneScript Pro Theme', 'Template', 1500.00, 'Modern clean responsive UI template built with OneScript.'),
                ('E-commerce Suite', 'Software', 4500.00, 'Complete dynamic store management system.'),
                ('Hosting Package', 'Service', 1200.00, 'Super fast cPanel shared web hosting for OneScript sites.')
            ");
        }
    }

    public static function query(string $table, ?string $where = null, ?string $orderBy = null, ?int $limit = null, ?int $offset = null, bool $first = false): array|object|null {
        $pdo = self::getPdo();
        $tableClean = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $sql = "SELECT * FROM `{$tableClean}`";
        $params = [];

        if (!empty($where)) {
            $sql .= " WHERE " . self::parseWhereClause($where, $params);
        }

        if (!empty($orderBy)) {
            $sql .= " ORDER BY " . preg_replace('/[^a-zA-Z0-9_\s,ASCdesc]/', '', $orderBy);
        }

        if ($first) {
            $sql .= " LIMIT 1";
        } elseif ($limit !== null && $limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset !== null && $offset >= 0) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            if ($first) {
                return !empty($rows) ? (object)$rows[0] : null;
            }
            return $rows;
        } catch (PDOException $e) {
            return $first ? null : [];
        }
    }

    private static function parseWhereClause(string $where, array &$params): string {
        // Safe parameterization for `field = "val"` or `field LIKE "%val%"` or `field = 123`
        $pattern = '/([a-zA-Z0-9_]+)\s*(=|!=|>|<|>=|<=|LIKE)\s*(?:"([^"]*)"|\'([^\']*)\'|([0-9\.]+))/i';
        $result = preg_replace_callback($pattern, function($matches) use (&$params) {
            $field = $matches[1];
            $op = strtoupper($matches[2]);
            $val = $matches[3] !== '' ? $matches[3] : ($matches[4] !== '' ? $matches[4] : $matches[5]);
            $paramKey = ":p" . count($params);
            $params[$paramKey] = $val;
            return "`{$field}` {$op} {$paramKey}";
        }, $where);

        // Security validation: ensure no SQL injection payload remains
        if (!empty($result)) {
            $normalized = str_replace(['(', ')'], [' ( ', ' ) '], $result);
            $tokens = preg_split('/\s+/', trim($normalized));
            
            $allowedKeywords = ['AND', 'OR', 'LIKE', 'NOT', 'IS', 'NULL', 'IN', 'BETWEEN', '=', '!=', '>', '<', '>=', '<=', '<=', '<>', '(', ')'];
            
            foreach ($tokens as $token) {
                if ($token === '') continue;
                
                // 1. Check if placeholder
                if (preg_match('/^:[a-zA-Z0-9_]+$/', $token)) {
                    continue;
                }
                
                // 2. Check if backticked field name
                if (preg_match('/^`[a-zA-Z0-9_]+`$/', $token)) {
                    continue;
                }
                
                // 3. Check if number
                if (is_numeric($token)) {
                    continue;
                }
                
                // 4. Check if allowed keyword/operator
                if (in_array(strtoupper($token), $allowedKeywords)) {
                    continue;
                }
                
                // Reject query to prevent SQL Injection
                throw new \Exception("Security Violation: Unsafe SQL token detected in WHERE clause: " . htmlspecialchars($token));
            }
        }

        return $result ?: $where;
    }

    public static function insert(string $table, array $data): bool {
        $pdo = self::getPdo();
        $tableClean = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if (empty($data)) return false;

        $fields = array_keys($data);
        $cleanFields = array_map(fn($f) => "`" . preg_replace('/[^a-zA-Z0-9_]/', '', $f) . "`", $fields);
        $placeholders = array_map(fn($f) => ":" . preg_replace('/[^a-zA-Z0-9_]/', '', $f), $fields);

        $sql = "INSERT INTO `{$tableClean}` (" . implode(', ', $cleanFields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $params = [];
        foreach ($data as $key => $val) {
            $params[":" . preg_replace('/[^a-zA-Z0-9_]/', '', $key)] = $val;
        }

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function update(string $table, array $data, string $where, array $whereContext = []): bool {
        $pdo = self::getPdo();
        $tableClean = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if (empty($data)) return false;

        $setParts = [];
        $params = [];
        foreach ($data as $key => $val) {
            $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            $paramName = ":set_" . $cleanKey;
            $setParts[] = "`{$cleanKey}` = {$paramName}";
            $params[$paramName] = $val;
        }

        $parsedWhere = self::parseWhereClause($where, $params);
        $sql = "UPDATE `{$tableClean}` SET " . implode(', ', $setParts) . " WHERE " . $parsedWhere;

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function delete(string $table, string $where): bool {
        $pdo = self::getPdo();
        $tableClean = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $params = [];
        $parsedWhere = self::parseWhereClause($where, $params);

        if (empty($parsedWhere)) return false;

        $sql = "DELETE FROM `{$tableClean}` WHERE " . $parsedWhere;

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            return false;
        }
    }
}
