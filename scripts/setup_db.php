<?php

// Only run from CLI
if (php_sapi_name() !== 'cli') {
    die("Access denied. CLI only.");
}

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Database;

// Load Env
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

define('ROOT_PATH', dirname(__DIR__));

echo "Migrating Database...\n";

try {
    $db = Database::getPDO();
    $sql = file_get_contents(ROOT_PATH . '/database/schema.sql');

    // Split by comma causes issues if triggers/procedures exist, but our schema is simple.
    // However, schema.sql has multiple statements delimited by ;
    // PDO::exec can run multiple, but better to be safe.

    $db->exec($sql);
    echo "Schema imported successfully.\n";

    // Seed Admin User
    echo "Seeding Admin User...\n";
    $password = password_hash('password123', PASSWORD_BCRYPT);
    $email = 'admin@proconsultancy.com';

    // Check if exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if (!$stmt->fetch()) {
        $stmt = $db->prepare("INSERT INTO users (role_id, name, email, password) VALUES (1, 'Super Admin', ?, ?)");
        $stmt->execute([$email, $password]);
        echo "Admin created: $email / password123\n";
    } else {
        echo "Admin already exists.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
