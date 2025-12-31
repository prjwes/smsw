<?php

/**
 * Execute a SELECT query with parameters
 */
function dbSelect($conn, $query, $types = "", $params = []) {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare error: " . $conn->error);
        return [];
    }
    
    if ($types && count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Execute error: " . $stmt->error);
        return [];
    }
    
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Execute an INSERT/UPDATE/DELETE query with parameters
 */
function dbExecute($conn, $query, $types = "", $params = []) {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare error: " . $conn->error);
        return false;
    }
    
    if ($types && count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Execute error: " . $stmt->error);
        return false;
    }
    
    return true;
}

/**
 * Get single row from SELECT query
 */
function dbSelectOne($conn, $query, $types = "", $params = []) {
    $result = dbSelect($conn, $query, $types, $params);
    return count($result) > 0 ? $result[0] : null;
}

/**
 * Count rows from a query
 */
function dbCount($conn, $query, $types = "", $params = []) {
    $result = dbSelectOne($conn, $query, $types, $params);
    return $result ? (int)$result['count'] : 0;
}

/**
 * Get last inserted ID
 */
function dbLastInsertId($conn) {
    return $conn->insert_id;
}
?>
