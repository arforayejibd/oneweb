<?php

namespace OneScript\Engine;

class FormHandler {
    public static function handlePostRequest(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = $_POST['_onescript_action'] ?? null;
        $table  = $_POST['_onescript_table'] ?? null;
        $where  = $_POST['_onescript_where'] ?? null;
        $signature = $_POST['_onescript_signature'] ?? null;
        $redirect = $_POST['_onescript_redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '/');

        if (!$action || !$table) {
            return;
        }

        // Security validation: check cryptographic signature to prevent form parameter tampering (BOLA/IDOR)
        $expectedSignature = self::generateSignature($action, $table, $where ?? '');
        if (!$signature || !hash_equals($expectedSignature, $signature)) {
            http_response_code(403);
            echo "<div style='color:red; font-family:sans-serif; padding:2rem; border:1px solid red; background:#fff5f5; border-radius:5px;'>
                <h2>Security Violation</h2>
                <p>Form request signature validation failed. Parameter tampering or CSRF detected.</p>
            </div>";
            exit;
        }

        // Clean user payload (exclude onescript control fields)
        $data = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, '_onescript_') !== 0) {
                $data[$key] = is_string($value) ? trim($value) : $value;
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
        }

        // Normalize clean redirect URL (remove .one extension from redirect URL)
        $redirect = preg_replace('/\.one$/i', '', $redirect);
        if ($redirect === '/index') {
            $redirect = '/';
        }

        header("Location: " . $redirect);
        exit;
    }

    public static function generateSignature(string $action, string $table, string $where): string {
        $secret = self::getSecret();
        return hash_hmac('sha256', $action . '|' . $table . '|' . $where, $secret);
    }

    private static function getSecret(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['_onescript_secret'])) {
            $_SESSION['_onescript_secret'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_onescript_secret'];
    }
}
