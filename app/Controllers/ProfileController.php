<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Middleware\AuthMiddleware;

class ProfileController extends Controller
{
    public function __construct()
    {
        (new AuthMiddleware())->handle();
    }

    public function edit()
    {
        $db = Database::getPDO();
        $stmt = $db->prepare("SELECT id, name, email, avatar_path, timezone FROM users WHERE id = ?");
        $stmt->execute([Session::get('user_id')]);
        $user = $stmt->fetch();

        $this->view('profile.edit', [
            'user' => $user,
            'csrf_token' => Session::generateCsrfToken()
        ]);
    }

    public function update()
    {
        $token = $this->input('csrf_token');
        if (!Session::verifyCsrfToken($token))
            die("Invalid CSRF");

        $name = $this->input('name');
        $email = $this->input('email'); // Ideally we should validate uniqueness if changed
        $timezone = $this->input('timezone');
        $password = $this->input('password');

        $userId = Session::get('user_id');
        $db = Database::getPDO();

        // 1. Update Basic Info
        $sql = "UPDATE users SET name = ?, email = ?, timezone = ? WHERE id = ?";
        $params = [$name, $email, $timezone, $userId];
        $db->prepare($sql)->execute($params);

        // 2. Update Password if provided
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
        }

        // 3. Update Avatar if provided
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = ROOT_PATH . '/public/uploads/avatars/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0755, true);

            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = 'params_' . $userId . '_' . time() . '.' . $ext;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $filename)) {
                $db->prepare("UPDATE users SET avatar_path = ? WHERE id = ?")->execute([$filename, $userId]);
                // Update Session avatar if we stored it there, or rely on DB fetch
            }
        }

        // Update Session Name in case it changed
        $_SESSION['user_name'] = $name;

        $this->redirect('/profile');
    }
}
