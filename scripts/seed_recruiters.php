<?php

if (php_sapi_name() !== 'cli') {
    die("Access denied. CLI only.");
}

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Database;

// Load Env
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

echo "Seeding Recruiters...\n";

$db = Database::getPDO();

// 1. Get Recruiter Role ID
$stmt = $db->prepare("SELECT id FROM roles WHERE slug = 'recruiter'");
$stmt->execute();
$roleId = $stmt->fetchColumn();

if (!$roleId) {
    die("Error: 'recruiter' role not found in 'roles' table.\n");
}

// 2. Define Dummy Recruiters
$recruiters = [
    [
        'name' => 'Sarah Connor',
        'email' => 'sarah.connor@example.com',
        'password' => 'password123'
    ],
    [
        'name' => 'Kyle Reese',
        'email' => 'kyle.reese@example.com',
        'password' => 'password123'
    ]
];

// 3. Insert if not exist
foreach ($recruiters as $r) {
    // Check dupe
    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$r['email']]);

    if ($check->fetch()) {
        echo "Skipping: User {$r['email']} already exists.\n";
        continue;
    }

    $hash = password_hash($r['password'], PASSWORD_DEFAULT);

    // Attempt insert
    try {
        $stmt = $db->prepare("INSERT INTO users (role_id, name, email, password, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$roleId, $r['name'], $r['email'], $hash]);
        echo "Created Recruiter: {$r['name']} ({$r['email']})\n";
    } catch (\PDOException $e) {
        echo "Error creating {$r['name']}: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
