<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * JWT.php — Pure PHP JSON Web Token Helper (RFC 7519)
 * ─────────────────────────────────────────────────────────────────────────────
 * พัฒนาด้วย Pure PHP โดยไม่ใช้ External Library / Framework สำเร็จรูป
 * ตามข้อกำหนดและข้อควรระวังของโครงงาน
 * ─────────────────────────────────────────────────────────────────────────────
 */

class JWT
{
    private static string $defaultSecret = 'Northwind-BMS-Secret-Key-2569';

    /**
     * เข้ารหัสและออก JWT Token
     *
     * @param array $payload ข้อมูลที่ต้องการบรรจุใน Token (เช่น user id, username, role)
     * @param string|null $secret คีย์ลับสำหรับ Sign
     * @param int $expireSeconds อายุของ Token (วินาที) ค่าเริ่มต้น 24 ชั่วโมง
     * @return string
     */
    public static function encode(array $payload, ?string $secret = null, int $expireSeconds = 86400): string
    {
        $secret = $secret ?: (getenv('JWT_SECRET') ?: self::$defaultSecret);

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $payload['iat'] = time();
        $payload['exp'] = time() + $expireSeconds;

        $base64Header  = self::base64UrlEncode(json_encode($header));
        $base64Payload = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $secret, true);
        $base64Signature = self::base64UrlEncode($signature);

        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }

    /**
     * ตรวจสอบความถูกต้องและถอดรหัส JWT Token
     *
     * @param string $token
     * @param string|null $secret
     * @return array|null คืนค่า payload เมื่อ token ถูกต้อง หรือ null เมื่อไม่ถูกต้อง/หมดอายุ
     */
    public static function decode(string $token, ?string $secret = null): ?array
    {
        $secret = $secret ?: (getenv('JWT_SECRET') ?: self::$defaultSecret);

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64Header, $base64Payload, $base64Signature] = $parts;

        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $secret, true)
        );

        if (!hash_equals($expectedSig, $base64Signature)) {
            return null; // ลายเซ็นไม่ตรง
        }

        $payload = json_decode(self::base64UrlDecode($base64Payload), true);
        if (!$payload || !is_array($payload)) {
            return null;
        }

        // ตรวจสอบวันหมดอายุ (exp)
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null; // Token หมดอายุแล้ว
        }

        return $payload;
    }

    /**
     * ดึง Bearer Token จาก HTTP Authorization Header
     */
    public static function getBearerToken(): ?string
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
