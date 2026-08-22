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
        $redirect = $_POST['_onescript_redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '/');

        if (!$action || !$table) {
            return;
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
}
