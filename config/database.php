<?php
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'school_management');

function getDBConnection() {
    static $conn = null;
    
    if ($conn !== null) {
        if (!($conn instanceof mysqli)) {
            $conn = null;
        } else {
            if ($conn->connect_errno) {
                $conn = null;
            } else {
                try {
                    if (!$conn->ping()) {
                        $conn = null;
                    }
                } catch (Exception $e) {
                    $conn = null;
                }
            }
        }
    }
    
    if ($conn === null) {
        try {
            @set_error_handler(function($errno, $errstr) {
                error_log("Database connection error: " . $errstr);
            });
            
            $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            restore_error_handler();
            
            if ($conn->connect_error) {
                error_log("MySQL Connection failed: " . $conn->connect_error);
                die("Connection failed. Please try again later.");
            }
            
            $conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log("Database connection exception: " . $e->getMessage());
            die("Database connection error. Please try again later.");
        }
    }
    
    return $conn;
}

function tableExists($tableName) {
    try {
        $conn = getDBConnection();
        if (!$conn) return false;
        
        $tableName = $conn->real_escape_string($tableName);
        $result = $conn->query("SHOW TABLES LIKE '$tableName'");
        return $result && $result->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/school-management-system');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

$upload_dirs = ['profiles', 'notes', 'reports', 'clubs', 'news', 'personal_files', 'club_posts', 'comments', 'personal-files'];
foreach ($upload_dirs as $dir) {
    $path = UPLOAD_PATH . $dir;
    if (!file_exists($path)) {
        @mkdir($path, 0777, true);
    }
}
?>
