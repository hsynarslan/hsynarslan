<?php

namespace App\Services\Ldap;

final class DirectoryUser
{
    public function __construct(
        public readonly string $username,
        public readonly string $dn,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $number,
        public readonly ?string $department,
        public readonly string $baseRole,
    ) {}
}
