<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\AuthMiddleware;

class CandidateController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
    }

    public function index()
    {
        $db = Database::getPDO();

        // Search & Filter Logic
        $query = $_GET['q'] ?? '';
        $status = $_GET['status'] ?? '';
        $source = $_GET['source'] ?? '';

        $sql = "SELECT * FROM candidates WHERE deleted_at IS NULL";
        $params = [];

        if (!empty($query)) {
            // Fulltext match against combined fields
            // Using boolean mode for flexible searching
            $sql .= " AND MATCH(first_name, last_name, email, skills_text, summary) AGAINST(:query IN BOOLEAN MODE)";
            $params[':query'] = $query . '*'; // Add wildcard
        }

        if (!empty($status)) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }

        if (!empty($source)) {
            $sql .= " AND source = :source";
            $params[':source'] = $source;
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $candidates = $stmt->fetchAll();

        $this->view('candidates.index', [
            'candidates' => $candidates,
            'query' => $query,
            'status' => $status,
            'source' => $source
        ]);
    }

    public function create()
    {
        $this->view('candidates.create', ['csrf_token' => Session::generateCsrfToken()]);
    }

    public function store()
    {
        $token = $this->input('csrf_token');
        if (!Session::verifyCsrfToken($token))
            die("Invalid CSRF");

        $firstName = $this->input('first_name');
        $lastName = $this->input('last_name');
        $email = $this->input('email'); // Should validate unique
        $phone = $this->input('phone');
        $skills = $this->input('skills_text');
        $summary = $this->input('summary');

        // Handle Resume Upload
        $resumePath = null;
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = ROOT_PATH . '/storage/uploads/resumes/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);

            $filename = time() . '_' . basename($_FILES['resume']['name']);
            if (move_uploaded_file($_FILES['resume']['tmp_name'], $uploadDir . $filename)) {
                $resumePath = $filename;
            }
        }

        $db = Database::getPDO();
        $stmt = $db->prepare("INSERT INTO candidates (first_name, last_name, email, phone, skills_text, summary, resume_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$firstName, $lastName, $email, $phone, $skills, $summary, $resumePath]);

        $this->redirect('/candidates');
    }

    public function show($id)
    {
        $db = Database::getPDO();
        $stmt = $db->prepare("SELECT * FROM candidates WHERE id = ?");
        $stmt->execute([$id]);
        $candidate = $stmt->fetch();

        if (!$candidate)
            die("Candidate not found");

        // Fetch Applications
        $apps = $db->query("
            SELECT aj.*, j.title as job_title 
            FROM candidate_jobs aj 
            JOIN jobs j ON aj.job_id = j.id 
            WHERE aj.candidate_id = $id
        ")->fetchAll();

        // Fetch Open Jobs for Modal
        $openJobs = $db->query("SELECT id, title FROM jobs WHERE status = 'open'")->fetchAll();

        // Fetch Submissions (Enterprise)
        $submissions = $db->query("
            SELECT s.*, j.title as job_title, c.company_name
            FROM submissions s
            JOIN jobs j ON s.job_id = j.id
            JOIN clients c ON s.client_id = c.id
            WHERE s.candidate_id = $id
            ORDER BY s.created_at DESC
        ")->fetchAll();

        $this->view('candidates.view', [
            'candidate' => $candidate,
            'applications' => $apps,
            'openJobs' => $openJobs,
            'submissions' => $submissions
        ]);
    }

    public function edit($id)
    {
        $db = Database::getPDO();
        $stmt = $db->prepare("SELECT * FROM candidates WHERE id = ?");
        $stmt->execute([$id]);
        $candidate = $stmt->fetch();

        if (!$candidate)
            die("Candidate not found");

        $this->view('candidates.edit', [
            'candidate' => $candidate,
            'csrf_token' => Session::generateCsrfToken()
        ]);
    }

    public function update($id)
    {
        $token = $this->input('csrf_token');
        if (!Session::verifyCsrfToken($token))
            die("Invalid CSRF");

        $firstName = $this->input('first_name');
        $lastName = $this->input('last_name');
        $email = $this->input('email');
        $phone = $this->input('phone');
        $skills = $this->input('skills_text');
        $summary = $this->input('summary');

        $db = Database::getPDO();

        // Handle optional resume replacement
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = ROOT_PATH . '/storage/uploads/resumes/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);
            $filename = time() . '_' . basename($_FILES['resume']['name']);
            if (move_uploaded_file($_FILES['resume']['tmp_name'], $uploadDir . $filename)) {
                $db->prepare("UPDATE candidates SET resume_path = ? WHERE id = ?")->execute([$filename, $id]);
            }
        }

        $stmt = $db->prepare("UPDATE candidates SET first_name=?, last_name=?, email=?, phone=?, skills_text=?, summary=? WHERE id=?");
        $stmt->execute([$firstName, $lastName, $email, $phone, $skills, $summary, $id]);

        $this->redirect("/candidates/$id");
    }
}
