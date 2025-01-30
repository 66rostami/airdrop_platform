<?php
// admin/includes/database.php

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'airdrop_platform');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');
define('DB_CHARSET', 'utf8mb4');

try {
    // Create PDO Connection
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci"
        ]
    );

    // Set Timezone
    $db->exec("SET time_zone = '+00:00'");

} catch (PDOException $e) {
    // Log Error
    error_log("Database Connection Failed: " . $e->getMessage());
    
    // Show Error Message
    die("Connection failed: Database is currently unavailable. Please try again later.");
}

// Database Helper Functions
function db_insert($table, $data) {
    global $db;
    
    $fields = array_keys($data);
    $values = array_values($data);
    $placeholders = str_repeat('?,', count($fields) - 1) . '?';
    
    $sql = "INSERT INTO $table (" . implode(',', $fields) . ") VALUES ($placeholders)";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Database Insert Error: " . $e->getMessage());
        return false;
    }
}

function db_update($table, $data, $where) {
    global $db;
    
    $set = [];
    $values = [];
    
    foreach ($data as $field => $value) {
        $set[] = "$field = ?";
        $values[] = $value;
    }
    
    $whereClause = [];
    foreach ($where as $field => $value) {
        $whereClause[] = "$field = ?";
        $values[] = $value;
    }
    
    $sql = "UPDATE $table SET " . implode(',', $set) . " WHERE " . implode(' AND ', $whereClause);
    
    try {
        $stmt = $db->prepare($sql);
        return $stmt->execute($values);
    } catch (PDOException $e) {
        error_log("Database Update Error: " . $e->getMessage());
        return false;
    }
}

function db_delete($table, $where) {
    global $db;
    
    $whereClause = [];
    $values = [];
    
    foreach ($where as $field => $value) {
        $whereClause[] = "$field = ?";
        $values[] = $value;
    }
    
    $sql = "DELETE FROM $table WHERE " . implode(' AND ', $whereClause);
    
    try {
        $stmt = $db->prepare($sql);
        return $stmt->execute($values);
    } catch (PDOException $e) {
        error_log("Database Delete Error: " . $e->getMessage());
        return false;
    }
}

function db_select($table, $where = [], $order = '', $limit = '') {
    global $db;
    
    $sql = "SELECT * FROM $table";
    $values = [];
    
    if (!empty($where)) {
        $whereClause = [];
        foreach ($where as $field => $value) {
            $whereClause[] = "$field = ?";
            $values[] = $value;
        }
        $sql .= " WHERE " . implode(' AND ', $whereClause);
    }
    
    if ($order) {
        $sql .= " ORDER BY $order";
    }
    
    if ($limit) {
        $sql .= " LIMIT $limit";
    }
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database Select Error: " . $e->getMessage());
        return false;
    }
}

// Transaction Helpers
function db_transaction() {
    global $db;
    $db->beginTransaction();
}

function db_commit() {
    global $db;
    $db->commit();
}

function db_rollback() {
    global $db;
    $db->rollBack();
}