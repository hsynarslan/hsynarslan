<?php

namespace App\Services\Ldap;

interface Directory
{
    /** Kullanıcı adı ve şifreyi doğrular. Başarısızsa null döner (hata nedeni dışarı sızdırılmaz). */
    public function authenticate(string $username, string $password): ?DirectoryUser;

    /** Öğrenci/personel numarası ya da kullanıcı adıyla arar (oyuncu/katılımcı eklemek için). */
    public function find(string $numberOrUsername): ?DirectoryUser;
}
