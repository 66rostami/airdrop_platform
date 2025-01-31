<?php
/**
 * Database Management Class and Helper Functions
 * Author: 66rostami
 * Updated: 2025-01-31 22:42:51
 */

// Prevent direct access
if (!defined('ALLOW_ACCESS')) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
    exit('Direct access forbidden');
}

// Require configuration
require_once __DIR__ . '/../config.php';

class Database {
    private static $instance = null;
    private $connection;
    private $transactions = 0;
    private $queryLog = [];
    private $queryCount = 0;
    private $cached_queries = [];
    private $cache_enabled = true;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                sprintf(
                    "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                ),
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci",
                    PDO::ATTR_PERSISTENT => true
                ]
            );
            
            // Set timezone
            $this->connection->exec("SET time_zone = '+00:00'");
            
        } catch (PDOException $e) {
            $this->logError('Connection failed', $e);
            throw new Exception("Database connection failed");
        }
    }

    // Singleton pattern
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Basic Query Methods
    public function query($sql, $params = [], $cache_ttl = 0) {
        $cache_key = $this->generateCacheKey($sql, $params);
        
        // Check cache if enabled and TTL > 0
        if ($this->cache_enabled && $cache_ttl > 0 && isset($this->cached_queries[$cache_key])) {
            if (time() - $this->cached_queries[$cache_key]['time'] < $cache_ttl) {
                return $this->cached_queries[$cache_key]['result'];
            }
        }
        
        $start = microtime(true);
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            $this->logQuery($sql, $params, microtime(true) - $start);
            $this->queryCount++;
            
            // Cache result if needed
            if ($this->cache_enabled && $cache_ttl > 0) {
                $this->cached_queries[$cache_key] = [
                    'time' => time(),
                    'result' => $stmt
                ];
            }
            
            return $stmt;
        } catch (PDOException $e) {
            $this->logError('Query failed: ' . $sql, $e, $params);
            throw $e;
        }
    }

    // Advanced CRUD Operations
    public function insert($table, $data, $ignore = false) {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = sprintf(
            "INSERT %s INTO %s (%s) VALUES (%s)",
            $ignore ? 'IGNORE' : '',
            escape_identifier($table),
            implode(',', array_map('escape_identifier', $fields)),
            $placeholders
        );
        
        $this->query($sql, $values);
        return $this->connection->lastInsertId();
    }

    public function insertBatch($table, $data) {
        if (empty($data)) return false;
        
        $fields = array_keys($data[0]);
        $placeholders = '(' . str_repeat('?,', count($fields) - 1) . '?)';
        $values = [];
        
        foreach ($data as $row) {
            $values = array_merge($values, array_values($row));
        }
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES %s",
            escape_identifier($table),
            implode(',', array_map('escape_identifier', $fields)),
            str_repeat($placeholders . ',', count($data) - 1) . $placeholders
        );
        
        return $this->query($sql, $values)->rowCount();
    }

    public function select($table, $options = []) {
        $sql = "SELECT ";
        $params = [];
        
        // Fields with alias support
        if (isset($options['fields'])) {
            $fields = [];
            foreach ($options['fields'] as $key => $field) {
                if (is_string($key)) {
                    $fields[] = escape_identifier($field) . ' AS ' . escape_identifier($key);
                } else {
                    $fields[] = escape_identifier($field);
                }
            }
            $sql .= implode(',', $fields);
        } else {
            $sql .= '*';
        }
        
        $sql .= " FROM " . escape_identifier($table);
        
        // Complex Joins
        if (!empty($options['joins'])) {
            foreach ($options['joins'] as $join) {
                $sql .= " " . strtoupper($join['type']) . " JOIN " . escape_identifier($join['table']);
                if (isset($join['alias'])) {
                    $sql .= " AS " . escape_identifier($join['alias']);
                }
                $sql .= " ON " . $join['condition'];
            }
        }
        // Advanced Where Conditions
        if (!empty($options['where'])) {
            $sql .= " WHERE ";
            $whereConditions = [];
            
            foreach ($options['where'] as $field => $value) {
                if (is_array($value)) {
                    switch ($value[0]) {
                        case 'IN':
                            $in_placeholders = str_repeat('?,', count($value[1]) - 1) . '?';
                            $whereConditions[] = "$field IN ($in_placeholders)";
                            $params = array_merge($params, $value[1]);
                            break;
                        case 'BETWEEN':
                            $whereConditions[] = "$field BETWEEN ? AND ?";
                            $params[] = $value[1];
                            $params[] = $value[2];
                            break;
                        case 'LIKE':
                            $whereConditions[] = "$field LIKE ?";
                            $params[] = $value[1];
                            break;
                        case 'NULL':
                            $whereConditions[] = "$field IS NULL";
                            break;
                        case 'NOT NULL':
                            $whereConditions[] = "$field IS NOT NULL";
                            break;
                        default:
                            $whereConditions[] = "$field " . $value[0] . " ?";
                            $params[] = $value[1];
                    }
                } else {
                    $whereConditions[] = "$field = ?";
                    $params[] = $value;
                }
            }
            
            $sql .= implode(' AND ', $whereConditions);
        }
        
        // Group By with Having
        if (!empty($options['group'])) {
            $sql .= " GROUP BY " . implode(',', array_map('escape_identifier', $options['group']));
            
            if (!empty($options['having'])) {
                $sql .= " HAVING " . $options['having'];
            }
        }
        
        // Order By with multiple fields
        if (!empty($options['order'])) {
            $sql .= " ORDER BY ";
            if (is_array($options['order'])) {
                $orderParts = [];
                foreach ($options['order'] as $field => $direction) {
                    $orderParts[] = escape_identifier($field) . ' ' . strtoupper($direction);
                }
                $sql .= implode(', ', $orderParts);
            } else {
                $sql .= $options['order'];
            }
        }
        
        // Limit & Offset
        if (!empty($options['limit'])) {
            $sql .= " LIMIT " . (int)$options['limit'];
            if (!empty($options['offset'])) {
                $sql .= " OFFSET " . (int)$options['offset'];
            }
        }
        
        $cache_ttl = $options['cache_ttl'] ?? 0;
        $stmt = $this->query($sql, $params, $cache_ttl);
        return $options['single'] ?? false ? $stmt->fetch() : $stmt->fetchAll();
    }

    public function update($table, $data, $where, $limit = null) {
        $sets = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            if (is_array($value) && isset($value[0]) && $value[0] === 'RAW') {
                $sets[] = "$field = " . $value[1];
            } else {
                $sets[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        $whereConditions = [];
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                $whereConditions[] = "$field " . $value[0] . " ?";
                $params[] = $value[1];
            } else {
                $whereConditions[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            escape_identifier($table),
            implode(',', $sets),
            implode(' AND ', $whereConditions)
        );
        
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        return $this->query($sql, $params)->rowCount();
    }

    public function delete($table, $where, $limit = null) {
        $whereConditions = [];
        $params = [];
        
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                switch ($value[0]) {
                    case 'IN':
                        $in_placeholders = str_repeat('?,', count($value[1]) - 1) . '?';
                        $whereConditions[] = "$field IN ($in_placeholders)";
                        $params = array_merge($params, $value[1]);
                        break;
                    default:
                        $whereConditions[] = "$field " . $value[0] . " ?";
                        $params[] = $value[1];
                }
            } else {
                $whereConditions[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        $sql = sprintf(
            "DELETE FROM %s WHERE %s",
            escape_identifier($table),
            implode(' AND ', $whereConditions)
        );
        
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        return $this->query($sql, $params)->rowCount();
    }

    // Advanced Query Methods
    public function raw($sql, $params = []) {
        return $this->query($sql, $params);
    }

    public function count($table, $where = []) {
        $options = [
            'fields' => ['COUNT(*) as total'],
            'where' => $where,
            'single' => true
        ];
        return (int)$this->select($table, $options)['total'];
    }

    public function exists($table, $where) {
        $options = [
            'fields' => ['1'],
            'where' => $where,
            'limit' => 1
        ];
        return !empty($this->select($table, $options));
    }

    public function increment($table, $field, $where, $amount = 1) {
        return $this->update($table, [
            $field => ['RAW', "$field + $amount"]
        ], $where);
    }

    public function decrement($table, $field, $where, $amount = 1) {
        return $this->update($table, [
            $field => ['RAW', "$field - $amount"]
        ], $where);
    }

    // Transaction Management
    public function beginTransaction() {
        if ($this->transactions === 0) {
            $this->connection->beginTransaction();
        }
        $this->transactions++;
        return $this;
    }

    public function commit() {
        if ($this->transactions === 1) {
            $this->connection->commit();
        }
        $this->transactions = max(0, $this->transactions - 1);
        return $this;
    }

    public function rollback() {
        if ($this->transactions === 1) {
            $this->connection->rollBack();
        }
        $this->transactions = max(0, $this->transactions - 1);
        return $this;
    }

    public function transaction($callback) {
        try {
            $this->beginTransaction();
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    // Cache Management
    public function enableCache() {
        $this->cache_enabled = true;
        return $this;
    }

    public function disableCache() {
        $this->cache_enabled = false;
        return $this;
    }

    public function clearCache() {
        $this->cached_queries = [];
        return $this;
    }

    private function generateCacheKey($sql, $params) {
        return md5($sql . serialize($params));
    }

    // Debug & Logging Methods
    private function logError($message, $exception, $params = []) {
        $logEntry = sprintf(
            "[%s] Error: %s\nException: %s\nFile: %s:%d\nParameters: %s\nTrace:\n%s\n",
            date('Y-m-d H:i:s'),
            $message,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            print_r($params, true),
            $exception->getTraceAsString()
        );
        
        error_log($logEntry, 3, __DIR__ . '/../logs/database.log');
    }

    private function logQuery($sql, $params, $executionTime) {
        if (IS_DEVELOPMENT) {
            $this->queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'execution_time' => $executionTime,
                'timestamp' => date('Y-m-d H:i:s'),
                'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
            ];
        }
    }

    public function getQueryLog() {
        return $this->queryLog;
    }

    public function getQueryCount() {
        return $this->queryCount;
    }

    // Connection Management
    public function disconnect() {
        $this->connection = null;
        self::$instance = null;
    }

    public function reconnect() {
        $this->disconnect();
        return self::getInstance();
    }

    // Prevent cloning
    private function __clone() {}
    
    // Cleanup
    public function __destruct() {
        $this->disconnect();
    }
}

// Global Helper Functions
function db() {
    return Database::getInstance();
}

function escape_identifier($identifier) {
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function paginate($table, $page = 1, $perPage = 10, $where = [], $options = []) {
    $db = db();
    
    // Get total count
    $total = $db->count($table, $where);
    
    // Get page data
    $pageOptions = array_merge($options, [
        'where' => $where,
        'limit' => $perPage,
        'offset' => ($page - 1) * $perPage
    ]);
    
    $items = $db->select($table, $pageOptions);
    
    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($total / $perPage),
        'has_more' => ($page * $perPage) < $total
    ];
}