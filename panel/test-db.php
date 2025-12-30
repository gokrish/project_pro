<?php
require_once __DIR__ . '/modules/_common.php';

try {
    $db = ProConsultancy\Core\Database::getInstance();
    $conn = $db->getConnection();
    echo "✅ Database connected!";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}