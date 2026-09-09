<?php
class Request
{
    private static ?array $jsonBody = null;

    public static function json(): array
    {
        if (self::$jsonBody === null) {
            $raw = file_get_contents('php://input');
            self::$jsonBody = json_decode($raw, true) ?? [];
        }
        return self::$jsonBody;
    }

    public static function input(string $key, $default = null)
    {
        $body = self::json();
        return $body[$key] ?? $_GET[$key] ?? $default;
    }

    public static function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }
}
