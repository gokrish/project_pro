<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\AuthMiddleware;

class JobController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
    }

    public function index()
    {
        $db = Database::getPDO();

        $query = $_GET['q'] ?? '';
        $clientId = $_GET['client_id'] ?? '';
        $status = $_GET['status'] ?? '';

        $sql = "SELECT j.*, c.company_name, u.name as created_by_name 
            FROM jobs j
            LEFT JOIN clients c ON j.client_id = c.id
            LEFT JOIN users u ON j.created_by = u.id
            WHERE j.deleted_at IS NULL";

        $params = [];

        if (!empty($query)) {
            $sql .= " AND MATCH(j.title, j.description, j.location) AGAINST(:query IN BOOLEAN MODE)";
            $params[':query'] = $query . '*';
        }

        if (!empty($clientId)) {
            $sql .= " AND j.client_id = :client_id";
            $params[':client_id'] = $clientId;
        }

        if (!empty($status)) {
            $sql .= " AND j.status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY j.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $jobs = $stmt->fetchAll();

        // Fetch Clients for Filter
        $clients = $db->query("SELECT id, company_name FROM clients ORDER BY company_name")->fetchAll();

        $this->view('jobs.index', [
            'jobs' => $jobs,
            'clients' => $clients,
            'query' => $query,
            'client_id' => $clientId,
            'status' => $status
        ]);
    }

    public function create()
    {
        $db = Database::getPDO();
        $clients = $db->query("SELECT id, company_name FROM clients WHERE is_active = 1")->fetchAll();
        $recruiters = $db->query("SELECT u.id, u.name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.slug = 'recruiter' AND u.is_active = 1")->fetchAll();

        $this->view('jobs.create', [
            'clients' => $clients,
            'recruiters' => $recruiters,
            'csrf_token' => Session::generateCsrfToken()
        ]);
    }

    public function store()
    {
        $token = $this->input('csrf_token');
        if (!Session::verifyCsrfToken($token))
            die("Invalid CSRF");

        $title = $this->input('title');
        $clientId = $this->input('client_id');
        $description = $this->input('description');
        $location = $this->input('location');
        $salary = $this->input('salary_range');
        $recruiterId = $this->input('recruiter_id'); // Optional
        $userId = Session::get('user_id');

        $db = Database::getPDO();
        $stmt = $db->prepare("INSERT INTO jobs (title, client_id, description, location, salary_range, recruiter_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $clientId, $description, $location, $salary, $recruiterId ?: null, $userId]);

        $this->redirect('/jobs');
    }

    public function show($id)
    {
        $db = Database::getPDO();

        // Fetch Job
        $stmt = $db->prepare("
            SELECT j.*, c.company_name, u.name as created_by_name, r.name as recruiter_name
            FROM jobs j
            LEFT JOIN clients c ON j.client_id = c.id
            LEFT JOIN users u ON j.created_by = u.id
            LEFT JOIN users r ON j.recruiter_id = r.id
            WHERE j.id = ?
        ");
        $stmt->execute([$id]);
        $job = $stmt->fetch();

        if (!$job)
            die("Job not found");

        // Fetch Applications for this job
        $applications = $db->query("
            SELECT cj.*, c.first_name, c.last_name, c.email, c.id as candidate_id
            FROM candidate_jobs cj
            JOIN candidates c ON cj.candidate_id = c.id
            WHERE cj.job_id = $id
            ORDER BY cj.created_at DESC
        ")->fetchAll();

        // Update: Fetch Submissions (Enterprise)
        $submissions = $db->query("
            SELECT s.*, c.first_name, c.last_name, c.email, c.id as candidate_id
            FROM submissions s
            JOIN candidates c ON s.candidate_id = c.id
            WHERE s.job_id = $id
            ORDER BY s.created_at DESC
        ")->fetchAll();

        $this->view('jobs.show', [
            'job' => $job,
            'applications' => $applications, // old
            'submissions' => $submissions,    // new
            'csrf_token' => Session::generateCsrfToken()
        ]);
    }

    public function edit($id)
    {
        $db = Database::getPDO();
        $stmt = $db->prepare("SELECT * FROM jobs WHERE id = ?");
        $stmt->execute([$id]);
        $job = $stmt->fetch();

        if (!$job)
            die("Job not found");

        $clients = $db->query("SELECT id, company_name FROM clients WHERE is_active = 1")->fetchAll();
        $recruiters = $db->query("SELECT u.id, u.name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.slug = 'recruiter' AND u.is_active = 1")->fetchAll();

        $this->view('jobs.edit', [
            'job' => $job,
            'clients' => $clients,
            'recruiters' => $recruiters,
            'csrf_token' => Session::generateCsrfToken()
        ]);
    }

    public function update($id)
    {
        $token = $this->input('csrf_token');
        if (!Session::verifyCsrfToken($token))
            die("Invalid CSRF");

        $title = $this->input('title');
        $clientId = $this->input('client_id');
        $description = $this->input('description');
        $location = $this->input('location');
        $salary = $this->input('salary_range');
        $recruiterId = $this->input('recruiter_id');
        $status = $this->input('status');

        $db = Database::getPDO();
        $db->prepare("
            UPDATE jobs 
            SET title = ?, client_id = ?, description = ?, location = ?, salary_range = ?, recruiter_id = ?, status = ?
            WHERE id = ?
        ")->execute([$title, $clientId, $description, $location, $salary, $recruiterId ?: null, $status, $id]);

        $this->redirect("/jobs/$id");
    }
}
