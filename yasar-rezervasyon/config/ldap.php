<?php

/*
 * LDAP ayarları (ISO 27001 A.8.5 Güvenli kimlik doğrulama).
 * Üretimde "native" sürücü kullanılır ve bağlantı LDAPS/StartTLS ile şifrelenir.
 * "fake" sürücü yalnızca yerel geliştirme ve testler içindir; production ortamında reddedilir.
 */
return [
    'driver' => env('LDAP_DRIVER', 'native'),

    'host' => env('LDAP_HOST', 'ldaps://ldap.yasar.edu.tr'),
    'port' => (int) env('LDAP_PORT', 636),
    'start_tls' => (bool) env('LDAP_START_TLS', false),
    'timeout' => (int) env('LDAP_TIMEOUT', 5),
    'ca_cert' => env('LDAP_CA_CERT'),

    'base_dn' => env('LDAP_BASE_DN', 'dc=yasar,dc=edu,dc=tr'),
    // Kullanıcıyı aramak için salt-okunur servis hesabı.
    'bind_dn' => env('LDAP_BIND_DN'),
    'bind_password' => env('LDAP_BIND_PASSWORD'),

    'attributes' => [
        'username' => env('LDAP_ATTR_USERNAME', 'uid'), // Active Directory için: sAMAccountName
        'name' => env('LDAP_ATTR_NAME', 'displayName'),
        'email' => env('LDAP_ATTR_EMAIL', 'mail'),
        'number' => env('LDAP_ATTR_NUMBER', 'employeeNumber'),
        'department' => env('LDAP_ATTR_DEPARTMENT', 'department'),
    ],

    // DN içinde aranan OU parçası → temel rol. Sıra önemlidir; ilk eşleşen kullanılır.
    'role_ous' => [
        'student' => env('LDAP_OU_STUDENT', 'OU=Ogrenci'),
        'academic' => env('LDAP_OU_ACADEMIC', 'OU=Akademik'),
        'admin_staff' => env('LDAP_OU_ADMIN_STAFF', 'OU=Idari'),
        'alumni' => env('LDAP_OU_ALUMNI', 'OU=Mezun'),
    ],

    // Yalnızca LDAP_DRIVER=fake iken: şifre her kullanıcı için "test".
    'fake_users' => [
        ['username' => 'ogrenci', 'name' => 'Elif Yılmaz', 'email' => 'elif.yilmaz@stu.yasar.edu.tr', 'number' => '20231045', 'department' => 'Bilgisayar Mühendisliği', 'role' => 'student'],
        ['username' => 'ogrenci2', 'name' => 'Can Öztürk', 'email' => 'can.ozturk@stu.yasar.edu.tr', 'number' => '20231046', 'department' => 'İşletme', 'role' => 'student'],
        ['username' => 'ogrenci3', 'name' => 'Zeynep Arslan', 'email' => 'zeynep.arslan@stu.yasar.edu.tr', 'number' => '20231047', 'department' => 'Mimarlık', 'role' => 'student'],
        ['username' => 'akademik', 'name' => 'Dr. Deniz Şahin', 'email' => 'deniz.sahin@yasar.edu.tr', 'number' => 'P-2001', 'department' => 'Fen Fakültesi', 'role' => 'academic'],
        ['username' => 'idari', 'name' => 'Murat Kaya', 'email' => 'murat.kaya@yasar.edu.tr', 'number' => 'P-1102', 'department' => 'SKS Müdürlüğü', 'role' => 'admin_staff'],
        ['username' => 'antrenor', 'name' => 'Efe Koç', 'email' => 'efe.koc@yasar.edu.tr', 'number' => 'P-3005', 'department' => 'SKS Spor Birimi', 'role' => 'admin_staff'],
        ['username' => 'gorevli', 'name' => 'Ali Demir', 'email' => 'ali.demir@yasar.edu.tr', 'number' => 'P-3010', 'department' => 'SKS Spor Birimi', 'role' => 'admin_staff'],
        ['username' => 'sksadmin', 'name' => 'Ayşe Demir', 'email' => 'ayse.demir@yasar.edu.tr', 'number' => 'P-0007', 'department' => 'SKS Müdürlüğü', 'role' => 'admin_staff'],
        ['username' => 'mezun', 'name' => 'Selin Aydın', 'email' => 'selin.aydin@alumni.yasar.edu.tr', 'number' => '20181010', 'department' => 'Endüstri Mühendisliği', 'role' => 'alumni'],
    ],
];
