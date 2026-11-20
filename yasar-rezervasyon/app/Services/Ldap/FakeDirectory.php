<?php

namespace App\Services\Ldap;

/** Geliştirme ve test için sahte dizin. Tüm kullanıcıların şifresi "test". */
class FakeDirectory implements Directory
{
    public function __construct(private array $users) {}

    public function authenticate(string $username, string $password): ?DirectoryUser
    {
        $user = $this->find($username);

        return $user && $user->username === $username && hash_equals('test', $password) ? $user : null;
    }

    public function find(string $numberOrUsername): ?DirectoryUser
    {
        foreach ($this->users as $u) {
            if ($u['username'] === $numberOrUsername || $u['number'] === $numberOrUsername) {
                return new DirectoryUser($u['username'], "uid={$u['username']},OU=Fake", $u['name'], $u['email'],
                    $u['number'], $u['department'] ?? null, $u['role']);
            }
        }

        return null;
    }

    public function add(array $user): void
    {
        $this->users[] = $user;
    }
}
