<?php

namespace App\Services;

use App\Models\User;
use App\Services\Ldap\Directory;
use App\Services\Ldap\DirectoryUser;

/** LDAP kullanıcılarını yerel kullanıcı tablosuna eşler. */
class UserDirectory
{
    public function __construct(private readonly Directory $directory) {}

    public function sync(DirectoryUser $d): User
    {
        $user = User::firstOrNew(['username' => $d->username]);
        $user->fill([
            'ldap_dn' => $d->dn,
            'name' => $d->name,
            'email' => $d->email,
            'number' => $d->number,
            'department' => $d->department,
            'base_role' => $d->baseRole,
        ]);
        $user->save();

        return $user;
    }

    /** Numara veya kullanıcı adıyla kullanıcıyı bulur; yerelde yoksa LDAP'tan getirip kaydeder. */
    public function findOrImport(string $numberOrUsername): ?User
    {
        $numberOrUsername = trim($numberOrUsername);
        if ($numberOrUsername === '') {
            return null;
        }

        $local = User::where('number', $numberOrUsername)->orWhere('username', $numberOrUsername)->first();
        if ($local) {
            return $local;
        }

        $d = $this->directory->find($numberOrUsername);

        return $d ? $this->sync($d) : null;
    }
}
