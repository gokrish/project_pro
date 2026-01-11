<?php

if (php_sapi_name() !== 'cli') {
    die("Access denied. CLI only.");
}

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Database;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

echo "Applying Recruiter Schema Updates...\n";

$db = Database::getPDO();

$sql = file_get_contents(__DIR__ . '/../database/updates/03_recruiter_assignment.sql');

try {
    $db->exec($sql);
    echo "Updates applied successfully!\n";
} catch (\PDOException $e) {
    if ($e->getCode() == '42S21' || strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Notice: Column already exists.\n";
    } else {
        echo "Error applying updates: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
