<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * Response.php — Unified JSON API Response Formatter
 * ─────────────────────────────────────────────────────────────────────────────
 */

class Response
{
    /** ส่ง JSON ดิบๆ พร้อมกำหนด HTTP status code แล้วจบการทำงานทันที */
    public static function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** กรณีสำเร็จ */
    public static function success($data = null, string $message = '', int $statusCode = 200, array $extra = []): void
    {
        $payload = array_merge([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ], $extra);

        self::json($payload, $statusCode);
    }

    /** กรณีผิดพลาด (ทั่วไป / server error) */
    public static function error(string $message, int $statusCode = 400, ?array $errors = null): void
    {
        $payload = [
            'success' => false,
            'message' => $message
        ];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        self::json($payload, $statusCode);
    }

    /** กรณี validation ไม่ผ่าน (HTTP 422) */
    public static function validationError(array $errors): void
    {
        self::error('ข้อมูลไม่ผ่านการตรวจสอบความถูกต้อง', 422, $errors);
    }

    /** กรณีไม่พบ route หรือ resource ที่ร้องขอ (HTTP 404) */
    public static function notFound(string $message = 'ไม่พบข้อมูลหรือ Endpoint ที่ร้องขอ'): void
    {
        self::error($message, 404);
    }

    /** กรณีไม่ผ่านการยืนยันตัวตน (HTTP 401 Unauthorized) */
    public static function unauthorized(string $message = 'กรุณาเข้าสู่ระบบก่อนดำเนินการ (Unauthorized)'): void
    {
        self::error($message, 401);
    }

    /** กรณีไม่มีสิทธิ์เข้าถึง (HTTP 403 Forbidden) */
    public static function forbidden(string $message = 'คุณไม่มีสิทธิ์ในการเข้าถึงส่วนนี้ (Forbidden)'): void
    {
        self::error($message, 403);
    }
}
