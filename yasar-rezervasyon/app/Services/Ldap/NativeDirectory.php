<?php

namespace App\Services\Ldap;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/** PHP ldap eklentisiyle LDAP erişimi. */
class NativeDirectory implements Directory
{
    public function __construct(private readonly array $config) {}

    public function authenticate(string $username, string $password): ?DirectoryUser
    {
        // Boş şifre anonim bind'a dönüşür; kesinlikle reddedilir.
        if ($password === '' || ! preg_match('/^[A-Za-z0-9._-]{2,100}$/', $username)) {
            return null;
        }
        $conn = $this->connect();
        try {
            $entry = $this->search($conn, $this->config['attributes']['username'], $username);
            if (! $entry) {
                return null;
            }
            if (! @ldap_bind($conn, $entry['dn'], $password)) {
                return null;
            }

            return $this->toUser($entry);
        } finally {
            @ldap_unbind($conn);
        }
    }

    public function find(string $numberOrUsername): ?DirectoryUser
    {
        if (! preg_match('/^[A-Za-z0-9._-]{2,100}$/', $numberOrUsername)) {
            return null;
        }
        $conn = $this->connect();
        try {
            $entry = $this->search($conn, $this->config['attributes']['number'], $numberOrUsername)
                ?? $this->search($conn, $this->config['attributes']['username'], $numberOrUsername);

            return $entry ? $this->toUser($entry) : null;
        } finally {
            @ldap_unbind($conn);
        }
    }

    private function connect()
    {
        if (! function_exists('ldap_connect')) {
            throw new RuntimeException('PHP ldap eklentisi yüklü değil.');
        }
        if ($this->config['ca_cert']) {
            ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $this->config['ca_cert']);
        }
        ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND);

        $conn = ldap_connect($this->config['host'].':'.$this->config['port']);
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, $this->config['timeout']);

        if ($this->config['start_tls'] && ! @ldap_start_tls($conn)) {
            throw new RuntimeException('LDAP StartTLS başarısız.');
        }
        if (! str_starts_with($this->config['host'], 'ldaps://') && ! $this->config['start_tls']) {
            throw new RuntimeException('LDAP bağlantısı şifreli olmalıdır (ldaps:// veya StartTLS).');
        }
        if (! @ldap_bind($conn, $this->config['bind_dn'], $this->config['bind_password'])) {
            Log::error('LDAP servis hesabı ile bağlanılamadı.');
            throw new RuntimeException('LDAP servis hesabı ile bağlanılamadı.');
        }

        return $conn;
    }

    private function search($conn, string $attribute, string $value): ?array
    {
        $filter = sprintf('(%s=%s)', $attribute, ldap_escape($value, '', LDAP_ESCAPE_FILTER));
        $attrs = array_values($this->config['attributes']);
        $result = @ldap_search($conn, $this->config['base_dn'], $filter, $attrs, 0, 2);
        if (! $result) {
            return null;
        }
        $entries = ldap_get_entries($conn, $result);

        return ($entries['count'] ?? 0) === 1 ? $entries[0] : null;
    }

    private function toUser(array $entry): ?DirectoryUser
    {
        $role = $this->roleFromDn($entry['dn']);
        if (! $role) {
            return null;
        }
        $a = $this->config['attributes'];
        $get = fn ($attr) => $entry[strtolower($attr)][0] ?? null;

        return new DirectoryUser(
            username: (string) $get($a['username']),
            dn: $entry['dn'],
            name: (string) ($get($a['name']) ?? $get($a['username'])),
            email: $get($a['email']),
            number: $get($a['number']),
            department: $get($a['department']),
            baseRole: $role,
        );
    }

    private function roleFromDn(string $dn): ?string
    {
        foreach ($this->config['role_ous'] as $role => $ou) {
            if ($ou && stripos($dn, $ou.',') !== false) {
                return $role;
            }
        }

        return null;
    }
}
