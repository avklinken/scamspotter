<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Env;
use PDO;
use RuntimeException;

final class MicrosoftIdentityService
{
    public function __construct(private PDO $db)
    {
    }

    public static function enabled(): bool
    {
        return Env::bool('MICROSOFT_ENTRA_ENABLED', false)
            && self::isClientId((string) Env::get('MICROSOFT_ENTRA_CLIENT_ID', ''))
            && trim((string) Env::get('MICROSOFT_ENTRA_RESOURCE', '')) !== '';
    }

    /** @return array<string, mixed>|null */
    public function authenticate(string $token): ?array
    {
        if (!self::enabled() || $token === '' || strlen($token) > 20000) {
            return null;
        }

        $tenantId = $this->unverifiedTenantId($token);
        if ($tenantId === null) {
            return null;
        }
        $tenant = $this->findTenant($tenantId);
        if ($tenant === null) {
            return null;
        }
        $claims = $this->validateToken($token);
        if ($claims === null || strcasecmp((string) ($claims['tid'] ?? ''), $tenantId) !== 0) {
            return null;
        }

        $oid = trim((string) ($claims['oid'] ?? $claims['sub'] ?? ''));
        if ($oid === '' || strlen($oid) > 180) {
            return null;
        }
        $providerSubject = $tenantId . ':' . $oid;
        $user = $this->findByProviderSubject((int) $tenant['organization_id'], $providerSubject);
        if ($user === null) {
            $email = $this->claimEmail($claims);
            if ($email === null) {
                return null;
            }
            $user = $this->findByEmail((int) $tenant['organization_id'], $email);
            if ($user === null || $this->cannotBind($user, $providerSubject)) {
                return null;
            }
            $this->db->prepare("UPDATE business_users
                SET identity_provider = 'entra', provider_subject = :provider_subject, last_login_at = NOW()
                WHERE id = :id")->execute(['provider_subject' => $providerSubject, 'id' => $user['id']]);
        } else {
            $this->db->prepare('UPDATE business_users SET last_login_at = NOW() WHERE id = :id')
                ->execute(['id' => $user['id']]);
        }

        unset($user['password_hash'], $user['identity_provider'], $user['provider_subject']);
        return $user;
    }

    /** @return array<string, mixed>|null */
    private function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $header = $this->decodePart($parts[0]);
        $claims = $this->decodePart($parts[1]);
        $signature = $this->base64UrlDecode($parts[2]);
        if ($header === null || $claims === null || $signature === null || ($header['alg'] ?? '') !== 'RS256') {
            return null;
        }

        $tenantId = (string) ($claims['tid'] ?? '');
        if (!self::isTenantId($tenantId)) {
            return null;
        }
        $configuredTenant = trim((string) Env::get('MICROSOFT_ENTRA_TENANT_ID', ''));
        if ($configuredTenant !== '' && strcasecmp($configuredTenant, $tenantId) !== 0) {
            return null;
        }
        $issuer = (string) ($claims['iss'] ?? '');
        $allowedIssuers = [
            'https://login.microsoftonline.com/' . $tenantId . '/v2.0',
            'https://sts.windows.net/' . $tenantId . '/',
        ];
        if (!in_array($issuer, $allowedIssuers, true)) {
            return null;
        }
        $now = time();
        $skew = max(30, Env::int('MICROSOFT_ENTRA_CLOCK_SKEW', 120));
        if (!is_numeric($claims['exp'] ?? null) || (int) $claims['exp'] < $now - $skew) {
            return null;
        }
        if (isset($claims['nbf']) && (!is_numeric($claims['nbf']) || (int) $claims['nbf'] > $now + $skew)) {
            return null;
        }
        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        $allowedAudiences = array_values(array_filter([
            trim((string) Env::get('MICROSOFT_ENTRA_CLIENT_ID', '')),
            trim((string) Env::get('MICROSOFT_ENTRA_RESOURCE', '')),
        ]));
        if ($allowedAudiences === [] || !array_intersect($allowedAudiences, $audiences)) {
            return null;
        }

        $kid = (string) ($header['kid'] ?? '');
        if ($kid === '') {
            return null;
        }
        $key = $this->keyForKid($tenantId, $kid);
        if ($key === null || openssl_verify($parts[0] . '.' . $parts[1], $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            return null;
        }
        return $claims;
    }

    /** @return array<string, mixed>|null */
    private function findTenant(string $tenantId): ?array
    {
        $statement = $this->db->prepare("SELECT mt.organization_id, o.name AS organization_name
            FROM microsoft_tenants mt INNER JOIN organizations o ON o.id = mt.organization_id
            WHERE mt.tenant_id = :tenant_id AND mt.consent_status = 'active'
              AND mt.revoked_at IS NULL AND o.status IN ('trial', 'active') LIMIT 1");
        $statement->execute(['tenant_id' => $tenantId]);
        $tenant = $statement->fetch();
        return is_array($tenant) ? $tenant : null;
    }

    /** @return array<string, mixed>|null */
    private function findByProviderSubject(int $organizationId, string $providerSubject): ?array
    {
        $statement = $this->db->prepare("SELECT bu.id, bu.email, bu.name, bu.password_hash,
                bu.identity_provider, bu.provider_subject, o.id AS organization_id,
                o.name AS organization_name, om.role
            FROM business_users bu
            INNER JOIN organization_memberships om ON om.user_id = bu.id
                AND om.organization_id = :organization_id AND om.status = 'active'
            INNER JOIN organizations o ON o.id = om.organization_id AND o.status IN ('trial', 'active')
            WHERE bu.identity_provider = 'entra' AND bu.provider_subject = :provider_subject
              AND bu.status = 'active' LIMIT 1");
        $statement->execute(['organization_id' => $organizationId, 'provider_subject' => $providerSubject]);
        $user = $statement->fetch();
        return is_array($user) ? $user : null;
    }

    /** @return array<string, mixed>|null */
    private function findByEmail(int $organizationId, string $email): ?array
    {
        $statement = $this->db->prepare("SELECT bu.id, bu.email, bu.name, bu.password_hash,
                bu.identity_provider, bu.provider_subject, o.id AS organization_id,
                o.name AS organization_name, om.role
            FROM business_users bu
            INNER JOIN organization_memberships om ON om.user_id = bu.id
                AND om.organization_id = :organization_id AND om.status = 'active'
            INNER JOIN organizations o ON o.id = om.organization_id AND o.status IN ('trial', 'active')
            WHERE bu.email = :email AND bu.status = 'active' LIMIT 1");
        $statement->execute(['organization_id' => $organizationId, 'email' => $email]);
        $user = $statement->fetch();
        return is_array($user) ? $user : null;
    }

    /** @param array<string, mixed> $user */
    private function cannotBind(array $user, string $providerSubject): bool
    {
        $currentProvider = (string) ($user['identity_provider'] ?? 'local');
        $currentSubject = (string) ($user['provider_subject'] ?? '');
        return $currentSubject !== '' && ($currentProvider !== 'entra' || !hash_equals($currentSubject, $providerSubject));
    }

    /** @param array<string, mixed> $claims */
    private function claimEmail(array $claims): ?string
    {
        foreach (['preferred_username', 'email', 'upn'] as $claim) {
            $email = mb_strtolower(trim((string) ($claims[$claim] ?? '')));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($email) <= 191) {
                return $email;
            }
        }
        return null;
    }

    /** @return array<string, mixed>|null */
    private function decodePart(string $part): ?array
    {
        $decoded = $this->base64UrlDecode($part);
        if ($decoded === null) {
            return null;
        }
        try {
            $value = json_decode($decoded, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($value) ? $value : null;
    }

    private function unverifiedTenantId(string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $claims = $this->decodePart($parts[1]);
        $tenantId = is_array($claims) ? (string) ($claims['tid'] ?? '') : '';
        return self::isTenantId($tenantId) ? strtolower($tenantId) : null;
    }

    private function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1 || strlen($value) % 4 === 1) {
            return null;
        }
        $value = strtr($value, '-_', '+/');
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
        $decoded = base64_decode($value, true);
        return $decoded === false ? null : $decoded;
    }

    private function keyForKid(string $tenantId, string $kid): ?string
    {
        $keys = $this->jwks($tenantId, false);
        foreach ($keys as $key) {
            if (($key['kid'] ?? '') === $kid) {
                return $this->publicKey($key);
            }
        }
        $keys = $this->jwks($tenantId, true);
        foreach ($keys as $key) {
            if (($key['kid'] ?? '') === $kid) {
                return $this->publicKey($key);
            }
        }
        return null;
    }

    /** @return list<array<string, mixed>> */
    private function jwks(string $tenantId, bool $forceRefresh): array
    {
        $file = BASE_PATH . '/storage/cache/microsoft-jwks-' . hash('sha256', $tenantId) . '.json';
        $cached = $this->readJwksCache($file);
        $ttl = max(300, Env::int('MICROSOFT_ENTRA_JWKS_CACHE_SECONDS', 3600));
        if (!$forceRefresh && $cached !== null && is_file($file) && (filemtime($file) ?: 0) >= time() - $ttl) {
            return $cached;
        }
        try {
            $keys = $this->fetchJwks($tenantId);
            $directory = dirname($file);
            if (!is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
            $temporary = $file . '.' . bin2hex(random_bytes(6));
            file_put_contents($temporary, json_encode(['keys' => $keys], JSON_THROW_ON_ERROR), LOCK_EX);
            rename($temporary, $file);
            return $keys;
        } catch (RuntimeException $exception) {
            if ($cached !== null) {
                return $cached;
            }
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>>|null */
    private function readJwksCache(string $file): ?array
    {
        $contents = is_readable($file) ? file_get_contents($file) : false;
        if (!is_string($contents) || $contents === '') {
            return null;
        }
        try {
            $payload = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($payload) || !is_array($payload['keys'] ?? null)) {
            return null;
        }
        return array_values(array_filter($payload['keys'], 'is_array'));
    }

    /** @return list<array<string, mixed>> */
    private function fetchJwks(string $tenantId): array
    {
        $url = 'https://login.microsoftonline.com/' . rawurlencode($tenantId) . '/discovery/v2.0/keys';
        $body = false;
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) {
                throw new RuntimeException('Microsoft JWKS initialisatie mislukt.');
            }
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            if ($body === false || $status < 200 || $status >= 300) {
                throw new RuntimeException('Microsoft JWKS ophalen mislukt: ' . ($error !== '' ? $error : 'HTTP ' . $status));
            }
        } else {
            $context = stream_context_create(['http' => [
                'method' => 'GET',
                'timeout' => 8,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
            $body = @file_get_contents($url, false, $context);
            if ($body === false) {
                throw new RuntimeException('Microsoft JWKS ophalen mislukt.');
            }
        }
        try {
            $payload = json_decode((string) $body, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Microsoft JWKS is ongeldig.', 0, $exception);
        }
        if (!is_array($payload) || !is_array($payload['keys'] ?? null)) {
            throw new RuntimeException('Microsoft JWKS bevat geen sleutels.');
        }
        return array_values(array_filter($payload['keys'], 'is_array'));
    }

    /** @param array<string, mixed> $jwk */
    private function publicKey(array $jwk): ?string
    {
        if (($jwk['kty'] ?? '') !== 'RSA') {
            return null;
        }
        $certificate = $jwk['x5c'][0] ?? null;
        if (is_string($certificate) && $certificate !== '') {
            return "-----BEGIN CERTIFICATE-----\n" . chunk_split($certificate, 64, "\n") . "-----END CERTIFICATE-----\n";
        }
        $modulus = $this->base64UrlDecode((string) ($jwk['n'] ?? ''));
        $exponent = $this->base64UrlDecode((string) ($jwk['e'] ?? ''));
        if ($modulus === null || $exponent === null) {
            return null;
        }
        $rsa = $this->derSequence($this->derInteger($modulus) . $this->derInteger($exponent));
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        if ($algorithm === false) {
            return null;
        }
        $subjectPublicKey = "\x03" . $this->derLength(strlen("\x00" . $rsa)) . "\x00" . $rsa;
        $spki = $this->derSequence($algorithm . $subjectPublicKey);
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private function derInteger(string $value): string
    {
        if ($value === '' || (ord($value[0]) & 0x80) !== 0) {
            $value = "\x00" . $value;
        }
        return "\x02" . $this->derLength(strlen($value)) . $value;
    }

    private function derSequence(string $value): string
    {
        return "\x30" . $this->derLength(strlen($value)) . $value;
    }

    private function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }
        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function isClientId(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    private static function isTenantId(string $value): bool
    {
        return self::isClientId($value);
    }
}
