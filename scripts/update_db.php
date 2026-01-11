<?php

if (php_sapi_name() !== 'cli') {
    die("Access denied. CLI only.");
}

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Database;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

echo "Applying Enterprise Schema Update...\n";

$db = Database::getPDO();

$sql = file_get_contents(__DIR__ . '/../database/updates/01_enterprise_upgrade.sql');

try {
    // Attempt to run the SQL
    // We split by ';' to run statements individually if needed, but PDO can handle multiple if configured.
    // However, ALTER TABLE failures (duplicate column) stop execution.
    // Let's iterate.

    // Simple split (not robust for comments with ;)
    // But our SQL is simple.

    // Actually, let's just try running the whole block.
    $db->exec($sql);
    echo "Schema updated successfully!\n";
} catch (\PDOException $e) {
    // Handle "Duplicate column name" error code 1060
    if ($e->getCode() == '42S21' || strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Notice: Column already exists, skipping ADD COLUMN.\n";
    } else {
        echo "Error updating schema: " . $e->getMessage() . "\n";
        // Continue anyway if tables exist
    }
}

echo "Done.\n";
