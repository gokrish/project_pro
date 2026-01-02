<?php
require_once __DIR__ . '/includes/_init.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Error Handler Testing</title>
</head>
<body>
    <h1>Error Handler Test Page</h1>
    
    <h2>Test 1: PHP Warning</h2>
    <?php
    // This will trigger a warning
    echo $undefined_variable;
    ?>
    
    <h2>Test 2: User Error</h2>
    <?php
    trigger_error("This is a test user error", E_USER_ERROR);
    ?>
    
    <h2>Test 3: Exception</h2>
    <?php
    throw new Exception("This is a test exception");
    ?>
    
    <p>If you see this, error handling failed!</p>
</body>
</html>