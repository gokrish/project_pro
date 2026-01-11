<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\AuthMiddleware;

class ClientController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
    }

    public function index()
    {
        $db = Database::getPDO();
        $clients = $db->query("SELECT * FROM clients WHERE deleted_at IS NULL ORDER BY company_name ASC")->fetchAll();
        $this->view('clients.index', ['clients' => $clients]);
    }

    public function create()
    {
        $this->view('clients.create', ['csrf_token' => Session::generateCsrfToken()]);
    }

    public function store()
    {
        $token = $this->input('csrf_token');
        if (!Session::verifyCsrfToken($token))
            die("Invalid CSRF");

        $name = $this->input('company_name');
        $contact = $this->input('contact_person');
        $email = $this->input('email');
        $phone = $this->input('phone');

        $db = Database::getPDO();
        $stmt = $db->prepare("INSERT INTO clients (company_name, contact_person, email, phone) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $contact, $email, $phone]);

        $this->redirect('/clients');
    }
}
