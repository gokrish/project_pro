<?php

if (php_sapi_name() !== 'cli') {
    die("Access denied. CLI only.");
}

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Database;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

echo "Seeding Data...\n";

$db = Database::getPDO();

// 1. Seed Clients (4)
echo "Seeding Clients...\n";
$clients = [
    ['TechCorp Solutions', 'John Smith', 'john@techcorp.com', '555-0101'],
    ['InnovateX Systems', 'Sarah Johnson', 'sarah@innovatex.com', '555-0102'],
    ['BlueSky Digital', 'Mike Brown', 'mike@bluesky.com', '555-0103'],
    ['Quantum Dynamics', 'Emma Wilson', 'emma@quantum.com', '555-0104']
];

$clientIds = [];
$stmt = $db->prepare("INSERT INTO clients (company_name, contact_person, email, phone) VALUES (?, ?, ?, ?)");
foreach ($clients as $c) {
    try {
        $stmt->execute($c);
        $clientIds[] = $db->lastInsertId();
    } catch (\Exception $e) { /* Ignore dups */
    }
}
// Refetch IDs if inserts failed (already exist)
if (empty($clientIds)) {
    $clientIds = $db->query("SELECT id FROM clients LIMIT 4")->fetchAll(PDO::FETCH_COLUMN);
}


// 2. Seed Jobs (6)
echo "Seeding Jobs...\n";
$titles = ['Senior PHP Developer', 'Frontend Engineer', 'DevOps Specialist', 'Project Manager', 'QA Automation Engineer', 'Product Owner'];
$statuses = ['open', 'open', 'draft', 'filled', 'open', 'closed'];
$locs = ['Remote', 'New York, NY', 'Austin, TX', 'London, UK', 'Remote', 'San Francisco, CA'];

$jobStmt = $db->prepare("INSERT INTO jobs (client_id, title, description, status, location, salary_range, created_by) VALUES (?, ?, 'Great opportunity...', ?, ?, '$100k - $120k', 1)");

for ($i = 0; $i < 6; $i++) {
    $cid = $clientIds[$i % count($clientIds)] ?? null;
    $jobStmt->execute([$cid, $titles[$i], $statuses[$i], $locs[$i]]);
}


// 3. Seed Candidates (10)
echo "Seeding Candidates...\n";
$fakerNames = [
    ['Alex', 'Taylor'],
    ['Jordan', 'Lee'],
    ['Casey', 'Smith'],
    ['Morgan', 'Davis'],
    ['Riley', 'Miller'],
    ['Quinn', 'Wilson'],
    ['Avery', 'Moore'],
    ['Jamie', 'Anderson'],
    ['Dakota', 'Thomas'],
    ['Cameron', 'Jackson']
];

$candStmt = $db->prepare("INSERT INTO candidates (first_name, last_name, email, phone, status, source, created_by) VALUES (?, ?, ?, ?, ?, 'seeded', 1)");

$statuses = ['new', 'screening', 'interview', 'offer', 'hired', 'rejected'];

foreach ($fakerNames as $k => $name) {
    $email = strtolower($name[0] . '.' . $name[1] . rand(10, 99) . '@example.com');
    $status = $statuses[$k % count($statuses)];
    try {
        $candStmt->execute([$name[0], $name[1], $email, '555-09' . sprintf('%02d', $k), $status]);
    } catch (\Exception $e) {
    }
}

echo "Done! Seeded 4 Clients, 6 Jobs, 10 Candidates.\n";
