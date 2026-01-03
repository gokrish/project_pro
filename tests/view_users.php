<?php
/**
 * USER CREDENTIALS VIEWER
 * Shows what users exist and their passwords
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Credentials Viewer</title>
    <style>
        body { 
            font-family: 'Courier New', monospace; 
            padding: 30px; 
            background: #0f172a; 
            color: #e2e8f0; 
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: #1e293b; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        h1 { 
            color: #60a5fa; 
            margin-top: 0; 
            border-bottom: 2px solid #60a5fa; 
            padding-bottom: 15px; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0; 
            background: #334155;
        }
        th, td { 
            padding: 15px; 
            text-align: left; 
            border: 1px solid #475569; 
        }
        th { 
            background: #475569; 
            font-weight: bold; 
            color: #60a5fa;
        }
        tr:hover { 
            background: #3f4f66; 
        }
        .password { 
            font-family: monospace; 
            background: #0f172a; 
            padding: 5px 10px; 
            border-radius: 4px; 
            color: #22c55e;
        }
        .hashed { 
            color: #f59e0b; 
        }
        .null { 
            color: #ef4444; 
        }
        .login-test { 
            background: #1e40af; 
            color: white; 
            padding: 8px 15px; 
            border-radius: 6px; 
            text-decoration: none; 
            display: inline-block;
            margin-top: 10px;
        }
        .info-box {
            background: #1e40af;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #60a5fa;
        }
        .warning-box {
            background: #78350f;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #f59e0b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>👥 User Credentials Viewer</h1>

<?php

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("<p style='color:#ef4444;'>❌ Connection failed: " . $conn->connect_error . "</p>");
    }
    
    echo "<p style='color:#22c55e;'>✅ Connected to database: <strong>" . DB_NAME . "</strong></p>";
    
    // Check what columns exist
    $result = $conn->query("DESCRIBE user");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    echo "<div class='info-box'>";
    echo "<strong>Available columns in user table:</strong><br>";
    echo implode(', ', $columns);
    echo "</div>";
    
    // Build query based on available columns
    $selectColumns = ['id'];
    $possibleColumns = ['user_code', 'user_email', 'user_password', 'user_level', 'user_name', 'email', 'password', 'level', 'name'];
    
    foreach ($possibleColumns as $col) {
        if (in_array($col, $columns)) {
            $selectColumns[] = $col;
        }
    }
    
    $sql = "SELECT " . implode(', ', $selectColumns) . " FROM user ORDER BY id";
    $result = $conn->query($sql);
    
    if (!$result) {
        die("<p style='color:#ef4444;'>❌ Query failed: " . $conn->error . "</p>");
    }
    
    $userCount = $result->num_rows;
    
    echo "<h2>Found $userCount users in database:</h2>";
    
    if ($userCount == 0) {
        echo "<div class='warning-box'>";
        echo "<strong>⚠️ NO USERS FOUND!</strong><br><br>";
        echo "You need to create a user first. Run this SQL:<br><br>";
        echo "<code style='background:#0f172a;padding:10px;display:block;border-radius:4px;'>";
        echo "INSERT INTO user (user_code, user_email, user_password, user_level, user_name)<br>";
        echo "VALUES ('admin', 'admin@test.com', 'admin123', 'admin', 'Admin User');";
        echo "</code>";
        echo "</div>";
    } else {
        echo "<table>";
        echo "<tr>";
        echo "<th>ID</th>";
        
        // Dynamic headers based on available columns
        if (in_array('user_code', $columns)) echo "<th>User Code</th>";
        if (in_array('user_email', $columns)) echo "<th>Email</th>";
        if (in_array('user_password', $columns)) echo "<th>Password</th>";
        if (in_array('user_level', $columns)) echo "<th>Level</th>";
        if (in_array('user_name', $columns)) echo "<th>Name</th>";
        
        // Fallback columns
        if (!in_array('user_code', $columns) && in_array('email', $columns)) echo "<th>Email (old column)</th>";
        if (!in_array('user_password', $columns) && in_array('password', $columns)) echo "<th>Password (old column)</th>";
        
        echo "<th>Login With</th>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            
            // Get the actual values
            $userCode = $row['user_code'] ?? ($row['email'] ?? 'N/A');
            $userEmail = $row['user_email'] ?? ($row['email'] ?? 'N/A');
            $userPassword = $row['user_password'] ?? ($row['password'] ?? null);
            $userLevel = $row['user_level'] ?? ($row['level'] ?? 'N/A');
            $userName = $row['user_name'] ?? ($row['name'] ?? 'N/A');
            
            if (in_array('user_code', $columns)) {
                echo "<td><strong>" . htmlspecialchars($userCode) . "</strong></td>";
            }
            
            if (in_array('user_email', $columns)) {
                echo "<td>" . htmlspecialchars($userEmail) . "</td>";
            }
            
            if (in_array('user_password', $columns)) {
                echo "<td>";
                if ($userPassword === null) {
                    echo "<span class='null'>NULL - No password set!</span>";
                } elseif (empty($userPassword)) {
                    echo "<span class='null'>EMPTY - No password!</span>";
                } elseif (substr($userPassword, 0, 4) === '$2y$') {
                    echo "<span class='hashed'>HASHED (bcrypt) - Need original password</span>";
                } else {
                    echo "<span class='password'>" . htmlspecialchars($userPassword) . "</span>";
                }
                echo "</td>";
            }
            
            if (in_array('user_level', $columns)) {
                echo "<td>" . htmlspecialchars($userLevel) . "</td>";
            }
            
            if (in_array('user_name', $columns)) {
                echo "<td>" . htmlspecialchars($userName) . "</td>";
            }
            
            // Login instructions
            echo "<td>";
            echo "<strong>User Code:</strong> " . htmlspecialchars($userCode) . "<br>";
            
            if ($userPassword === null || empty($userPassword)) {
                echo "<span class='null'>⚠️ Cannot login - no password!</span>";
            } elseif (substr($userPassword, 0, 4) === '$2y$') {
                echo "<span class='hashed'>Password is hashed - try common passwords:<br>";
                echo "admin123, password, test123</span>";
            } else {
                echo "<strong>Password:</strong> <span class='password'>" . htmlspecialchars($userPassword) . "</span>";
            }
            echo "</td>";
            
            echo "</tr>";
        }
        
        echo "</table>";
        
        echo "<div class='info-box'>";
        echo "<h3>🔐 How to Login:</h3>";
        echo "<ol>";
        echo "<li>Go to <a href='login.php' style='color:#60a5fa;'>login.php</a></li>";
        echo "<li>Use <strong>User Code</strong> from the table above</li>";
        echo "<li>Use the <strong>Password</strong> shown (if plain text)</li>";
        echo "<li>If password is hashed, you need to know the original password</li>";
        echo "</ol>";
        echo "</div>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color:#ef4444;'>❌ Error: " . $e->getMessage() . "</p>";
}

?>

        <div class="warning-box">
            <h3>⚠️ User Code: pro/901</h3>
            <p>You tried to login with: <strong>pro/901</strong></p>
            
            <?php
            // Check if this user exists
            try {
                $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                $testUserCode = 'pro/901';
                
                $stmt = $conn->prepare("SELECT * FROM user WHERE user_code = ? OR user_email = ?");
                $stmt->bind_param('ss', $testUserCode, $testUserCode);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    echo "<p style='color:#22c55e;'>✅ User <strong>pro/901</strong> EXISTS in database!</p>";
                    
                    $password = $user['user_password'] ?? ($user['password'] ?? null);
                    
                    if ($password === null || empty($password)) {
                        echo "<p style='color:#ef4444;'>❌ But this user has NO PASSWORD set!</p>";
                        echo "<p>Run this SQL to set a password:</p>";
                        echo "<code style='background:#0f172a;padding:10px;display:block;border-radius:4px;'>";
                        echo "UPDATE user SET user_password = 'password123' WHERE user_code = 'pro/901';";
                        echo "</code>";
                    } elseif (substr($password, 0, 4) === '$2y$') {
                        echo "<p style='color:#f59e0b;'>⚠️ Password is HASHED - you need the original password</p>";
                        echo "<p>Try common passwords: admin123, password, test123</p>";
                    } else {
                        echo "<p style='color:#22c55e;'>✅ Password is PLAIN TEXT: <strong class='password'>" . htmlspecialchars($password) . "</strong></p>";
                        echo "<p>Use this exact password to login!</p>";
                    }
                } else {
                    echo "<p style='color:#ef4444;'>❌ User <strong>pro/901</strong> does NOT exist in database!</p>";
                    echo "<p>Create this user with:</p>";
                    echo "<code style='background:#0f172a;padding:10px;display:block;border-radius:4px;'>";
                    echo "INSERT INTO user (user_code, user_email, user_password, user_level, user_name)<br>";
                    echo "VALUES ('pro/901', 'pro901@test.com', 'yourpassword', 'admin', 'Pro User 901');";
                    echo "</code>";
                }
                
                $conn->close();
            } catch (Exception $e) {
                echo "<p style='color:#ef4444;'>Error checking user: " . $e->getMessage() . "</p>";
            }
            ?>
        </div>

    </div>
</body>
</html>
