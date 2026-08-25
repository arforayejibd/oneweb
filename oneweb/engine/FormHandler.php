<?php

namespace OneWeb\Engine;

class FormHandler {
    public static function handlePostRequest(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $action = $_POST['_oneweb_action'] ?? null;
        $table  = $_POST['_oneweb_table'] ?? '';
        $where  = $_POST['_oneweb_where'] ?? null;
        $validate = $_POST['_oneweb_validate'] ?? null;
        $signature = $_POST['_oneweb_signature'] ?? null;
        $redirect = $_POST['_oneweb_redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '/');

        if (!$action || (strtolower($action) !== 'logout' && !$table)) {
            return;
        }

        // Security validation: check cryptographic signature to prevent form parameter tampering
        $expectedSignature = self::generateSignature($action, $table, $where ?? '');
        if (!$signature || !hash_equals($expectedSignature, $signature)) {
            http_response_code(403);
            echo "<div style='color:red; font-family:sans-serif; padding:2rem; border:1px solid red; background:#fff5f5; border-radius:5px;'>
                <h2>Security Violation</h2>
                <p>Form request signature validation failed. Parameter tampering or CSRF detected.</p>
            </div>";
            exit;
        }

        // Clean user payload (exclude oneweb control fields)
        $data = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, '_oneweb_') !== 0) {
                $data[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        // Run validation engine if validate attribute is present
        if (!empty($validate) && !in_array(strtolower($action), ['delete', 'logout'])) {
            $valError = self::validatePayload($data, $validate);
            if ($valError !== null) {
                if (session_status() === PHP_SESSION_NONE) {
                    @session_start();
                }
                $_SESSION['_oneweb_flash_error'] = $valError;
                $redirect = preg_replace('/\.one$/i', '', $redirect);
                if ($redirect === '/index') $redirect = '/';
                header("Location: " . $redirect);
                exit;
            }
        }

        switch (strtolower($action)) {
            case 'insert':
                Database::insert($table, $data);
                break;

            case 'update':
                if (!empty($where)) {
                    Database::update($table, $data, $where);
                }
                break;

            case 'delete':
                if (!empty($where)) {
                    Database::delete($table, $where);
                }
                break;

            case 'login':
                self::handleLoginAction($table, $data);
                break;

            case 'logout':
                self::handleLogoutAction();
                break;
        }

        // Normalize clean redirect URL
        $redirect = preg_replace('/\.one$/i', '', $redirect);
        if ($redirect === '/index') {
            $redirect = '/';
        }

        header("Location: " . $redirect);
        exit;
    }

    private static function validatePayload(array $data, string $rulesString): ?string {
        // Rules string format: "name|required|min:3;email|required|email;price|required|numeric"
        $fieldGroups = explode(';', $rulesString);
        foreach ($fieldGroups as $group) {
            $group = trim($group);
            if ($group === '') continue;

            $parts = explode('|', $group);
            $field = trim($parts[0]);
            $value = $data[$field] ?? null;

            for ($i = 1; $i < count($parts); $i++) {
                $rule = trim($parts[$i]);

                if ($rule === 'required') {
                    if ($value === null || $value === '') {
                        return "Field '" . ucfirst($field) . "' is required.";
                    }
                } elseif (strpos($rule, 'min:') === 0) {
                    $min = (int)substr($rule, 4);
                    if ($value !== null && strlen((string)$value) < $min) {
                        return "Field '" . ucfirst($field) . "' must be at least {$min} characters.";
                    }
                } elseif ($rule === 'numeric') {
                    if ($value !== null && $value !== '' && !is_numeric($value)) {
                        return "Field '" . ucfirst($field) . "' must be a valid number.";
                    }
                } elseif ($rule === 'email') {
                    if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return "Field '" . ucfirst($field) . "' must be a valid email address.";
                    }
                }
            }
        }

        return null;
    }

    public static function generateSignature(string $action, string $table, string $where): string {
        $secret = self::getSecret();
        return hash_hmac('sha256', $action . '|' . $table . '|' . $where, $secret);
    }

    private static function getSecret(): string {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        if (empty($_SESSION['_oneweb_secret'])) {
            $_SESSION['_oneweb_secret'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_oneweb_secret'];
    }

    private static function handleLoginAction(string $table, array $data): void {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $username = $data['username'] ?? $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$username || !$password) {
            $_SESSION['_oneweb_flash_error'] = "Username and password are required.";
            return;
        }

        $pdo = Database::getPdo();
        $tableClean = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

        try {
            $stmt = $pdo->prepare("SELECT * FROM `{$tableClean}` WHERE `username` = :u OR `email` = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();
        } catch (\PDOException $e) {
            $_SESSION['_oneweb_flash_error'] = "Authentication table or database schema error.";
            return;
        }

        if (!$user) {
            $_SESSION['_oneweb_flash_error'] = "Invalid credentials.";
            return;
        }

        $passwordField = $user['password'] ?? $user['password_hash'] ?? null;
        if (!$passwordField) {
            $_SESSION['_oneweb_flash_error'] = "Password configuration missing in users table.";
            return;
        }

        $verified = password_verify($password, $passwordField) || ($password === $passwordField);

        if ($verified) {
            $_SESSION['user'] = $user;
        } else {
            $_SESSION['_oneweb_flash_error'] = "Invalid credentials.";
        }
    }

    private static function handleLogoutAction(): void {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION['user']);
        @session_destroy();
    }
}
