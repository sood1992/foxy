<?php
/**
 * Response utility class
 */

class Response {
    public static function json($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit();
    }

    public static function success($data = null, $message = 'Success') {
        self::json([
            'success' => true,
            'message' => $message,
            ...(is_array($data) ? $data : ['data' => $data])
        ]);
    }

    public static function error($message, $code = 400) {
        self::json([
            'success' => false,
            'error' => $message
        ], $code);
    }
}
