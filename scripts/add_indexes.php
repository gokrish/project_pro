<?php

if (php_sapi_name() !== 'cli') {
    die("Access denied. CLI only.");
}

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Database;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

echo "Appying Search & Filter Indexes...\n";

$db = Database::getPDO();

$sql = file_get_contents(__DIR__ . '/../database/updates/02_search_indexes.sql');

try {
    $db->exec($sql);
    echo "Indexes applied successfully!\n";
} catch (\PDOException $e) {
    if ($e->getCode() == '42000' && strpos($e->getMessage(), 'Duplicate key name') !== false) {
        echo "Notice: Indexes already exist.\n";
    } else {
        echo "Error applying indexes: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
