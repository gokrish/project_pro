<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\AuthMiddleware;

class SubmissionController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
    }

    // Show form to submit a candidate to a job (client)
    public function create()
    {
        $candidateId = $this->input('candidate_id');
        $jobId = $this->input('job_id');

        if (!$candidateId) {
            die("Error: Candidate ID is required.");
        }

        $db = Database::getPDO();

        // Fetch candidate
        $stmt = $db->prepare("SELECT * FROM candidates WHERE id = ?");
        $stmt->execute([$candidateId]);
        $candidate = $stmt->fetch();

        if (!$candidate)
            die("Candidate not found");

        // Fetch jobs (if job_id not provided, show all open jobs)
        $jobs = [];
        if ($jobId) {
            $stmt = $db->prepare("SELECT j.*, c.company_name FROM jobs j JOIN clients c ON j.client_id = c.id WHERE j.id = ?");
            $stmt->execute([$jobId]);
            $jobs = $stmt->fetchAll();
        } else {
            $jobs = $db->query("SELECT j.*, c.company_name FROM jobs j JOIN clients c ON j.client_id = c.id WHERE j.status = 'open' ORDER BY j.created_at DESC")->fetchAll();
        }

        $this->view('submissions.create', [
            'candidate' => $candidate,
            'jobs' => $jobs,
            'selected_job_id' => $jobId,
            'csrf_token' => Session::generateCsrfToken()
        ]);
    }

    public function store()
    {
        $token = $this->input('csrf_token');
        if (!Session::verifyCsrfToken($token))
            die("Invalid CSRF");

        $candidateId = $this->input('candidate_id');
        $jobId = $this->input('job_id');
        $salary = $this->input('salary_expectation');
        $notes = $this->input('notes');
        $recruiterId = Session::get('user_id');

        $db = Database::getPDO();

        // 1. Get Client ID from Job
        $job = $db->query("SELECT client_id FROM jobs WHERE id = $jobId")->fetch();
        if (!$job || !$job['client_id'])
            die("Invalid Job or Job has no Client");
        $clientId = $job['client_id'];

        // 2. Check for duplicate submission
        $check = $db->prepare("SELECT id FROM submissions WHERE candidate_id = ? AND job_id = ?");
        $check->execute([$candidateId, $jobId]);
        if ($check->fetch()) {
            // Flash error? For now simple die or redirect with error param
            die("Candidate already submitted to this job.");
        }

        // 3. Create Submission
        $stmt = $db->prepare("
            INSERT INTO submissions (candidate_id, job_id, client_id, recruiter_id, status, salary_expectation, notes)
            VALUES (?, ?, ?, ?, 'submitted', ?, ?)
        ");
        $stmt->execute([$candidateId, $jobId, $clientId, $recruiterId, $salary, $notes]);
        $submissionId = $db->lastInsertId();

        // 4. Log History
        $this->logHistory($submissionId, $recruiterId, null, 'submitted', 'Initial submission');

        $this->redirect("/submissions/$submissionId");
    }

    public function show($id)
    {
        $db = Database::getPDO();

        // Fetch Submission Details with relations
        $stmt = $db->prepare("
            SELECT s.*, 
                   c.first_name, c.last_name, c.email as candidate_email, c.id as candidate_id,
                   j.title as job_title, j.id as job_id,
                   cl.company_name, cl.id as client_id,
                   u.name as recruiter_name
            FROM submissions s
            JOIN candidates c ON s.candidate_id = c.id
            JOIN jobs j ON s.job_id = j.id
            JOIN clients cl ON s.client_id = cl.id
            JOIN users u ON s.recruiter_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $submission = $stmt->fetch();

        if (!$submission)
            die("Submission not found");

        // Fetch History
        $history = $db->query("
            SELECT h.*, u.name as user_name 
            FROM submission_history h 
            JOIN users u ON h.user_id = u.id 
            WHERE h.submission_id = $id 
            ORDER BY h.created_at DESC
        ")->fetchAll();

        $this->view('submissions.show', [
            'submission' => $submission,
            'history' => $history,
            'csrf_token' => Session::generateCsrfToken()
        ]);
    }

    public function updateStatus()
    {
        $token = $this->input('csrf_token');
        if (!Session::verifyCsrfToken($token))
            die("Invalid CSRF");

        $id = $this->input('submission_id');
        $newStatus = $this->input('status');
        $comment = $this->input('comment');
        $userId = Session::get('user_id');

        $validStatuses = ['draft', 'submitted', 'client_review', 'interview', 'rejected', 'offer', 'placed'];
        if (!in_array($newStatus, $validStatuses))
            die("Invalid Status");

        $db = Database::getPDO();

        // Get current status
        $current = $db->query("SELECT status FROM submissions WHERE id = $id")->fetch();
        if (!$current)
            die("Submission not found");
        $oldStatus = $current['status'];

        if ($oldStatus !== $newStatus) {
            // Update Status
            $db->prepare("UPDATE submissions SET status = ? WHERE id = ?")->execute([$newStatus, $id]);

            // Log History
            $this->logHistory($id, $userId, $oldStatus, $newStatus, $comment);
        }

        $this->redirect("/submissions/$id");
    }

    private function logHistory($submissionId, $userId, $from, $to, $comment)
    {
        $db = Database::getPDO();
        $stmt = $db->prepare("
            INSERT INTO submission_history (submission_id, user_id, from_status, to_status, comment)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$submissionId, $userId, $from, $to, $comment]);
    }
}
