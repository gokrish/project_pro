<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Services\OpenAIService;
use App\Services\ResumeParserService;

class InboxController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
    }

    public function index()
    {
        $db = Database::getPDO();
        $items = $db->query("SELECT * FROM cv_inbox ORDER BY created_at DESC")->fetchAll();

        $this->view('inbox.index', [
            'items' => $items,
            'csrf_token' => Session::generateCsrfToken()
        ]);
    }

    public function upload()
    {
        $token = $this->input('csrf_token');
        if (!Session::verifyCsrfToken($token))
            die("Invalid CSRF");

        if (isset($_FILES['resumes'])) {
            $files = $_FILES['resumes']; // Multi-file upload
            $uploadDir = ROOT_PATH . '/storage/resumes/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);

            $db = Database::getPDO();
            $parser = new ResumeParserService();
            $ai = new OpenAIService();

            $total = count($files['name']);

            for ($i = 0; $i < $total; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $filename = uniqid() . '_' . basename($files['name'][$i]);
                    $path = $uploadDir . $filename;

                    if (move_uploaded_file($files['tmp_name'][$i], $path)) {
                        // 1. Extract Text
                        $text = $parser->extractText($path);

                        // 2. AI Parse (Optional: could be async queue)
                        $data = $ai->parseResumeText($text);
                        $json = json_encode($data);

                        // 3. Save to DB
                        $stmt = $db->prepare("
                            INSERT INTO cv_inbox (file_name, file_path, parsed_data, status)
                            VALUES (?, ?, ?, 'parsed')
                        ");
                        $stmt->execute([$files['name'][$i], $filename, $json]);
                    }
                }
            }
        }

        $this->redirect('/inbox');
    }

    public function convert($id)
    {
        $db = Database::getPDO();
        $stmt = $db->prepare("SELECT * FROM cv_inbox WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();

        if ($item && $item['parsed_data']) {
            $data = json_decode($item['parsed_data'], true);

            // Auto-create candidate
            $stmt = $db->prepare("
                INSERT INTO candidates (first_name, last_name, email, phone, linkedin_url, summary, skills_text, resume_path, source, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'inbox_import', ?)
            ");

            try {
                $stmt->execute([
                    $data['first_name'] ?? 'Unknown',
                    $data['last_name'] ?? '',
                    $data['email'] ?? 'unknown@email.com',
                    $data['phone'] ?? '',
                    $data['linkedin_url'] ?? '',
                    $data['summary'] ?? '',
                    json_encode($data['skills'] ?? []),
                    $item['file_path'],
                    Session::get('user_id')
                ]);

                // Mark Inbox as converted
                $db->prepare("UPDATE cv_inbox SET status = 'converted' WHERE id = ?")->execute([$id]);

            } catch (\Exception $e) {
                die("Error creating candidate: " . $e->getMessage());
            }
        }

        $this->redirect('/candidates');
    }
}
