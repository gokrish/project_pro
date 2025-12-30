<?php
include("db_conn.php");

// Add CORS headers to allow localhost requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

// Check database connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

/**
 * Fetch all email addresses from the candidates table
 */
function getEmails($conn) {
    
    $toprobealldata = isset($_GET['toprobealldata']) ? (int)$_GET['toprobealldata'] : 0;
    $toprobemissingcv = isset($_GET['toprobemissingcv']) ? (int)$_GET['toprobemissingcv'] : 0;

    // SQL Query based on conditions
    if ($toprobealldata == 1) {
        $sql = "SELECT email_id FROM candidates";  // Fetch all candidates
    } elseif ($toprobemissingcv == 1) {
        $sql = "SELECT email_id FROM candidates where candidate_cv IS NULL OR candidate_cv = ''";  // Fetch candidates with missing CVs
    } else {
        echo json_encode(["message" => "No filter applied", "emails" => []]);
        return;
    }

    $result = $conn->query($sql);
    $emails = [];

    while ($row = $result->fetch_assoc()) {
        $emails[] = $row['email_id'];
    }

    if (count($emails) > 0) {
        echo json_encode(["emails" => $emails], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(["message" => "No matching records found", "emails" => []]);
    }
}

/**
 * Fetch job details by reference number
 */
function getJobDetails($conn, $job_refno) {
    $sql = "SELECT job_refno, heading, company_name, experience, annual_package, job_location, posted_date, details 
            FROM jobs WHERE job_refno = '$job_refno' AND job_status = '1'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        return json_encode($result->fetch_assoc(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        return json_encode(["error" => "No job found with the provided reference number"]);
    }
}

/**
 * Fetch a list of active job references
 */
function getJobList($conn) {
    $sql = "SELECT job_refno, heading, posted_date FROM jobs WHERE job_status = '1'";
    $result = $conn->query($sql);

    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }

    return json_encode(["jobs" => $jobs]);
}

// **Routing Logic**
if (isset($_GET['fetch']) && $_GET['fetch'] === 'emails') {
    echo getEmails($conn);
} elseif (isset($_GET['ref_no'])) {
    echo getJobDetails($conn, $conn->real_escape_string($_GET['ref_no']));
} else {
    echo getJobList($conn);
}

// Close the database connection
$conn->close();
?>
