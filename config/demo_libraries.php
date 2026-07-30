<?php

use App\Models\User;

return [
    'password' => 'password',

    'superadmins' => [
        [
            'key' => 'global-superadmin',
            'email' => 'superadmin@test.com',
            'name' => 'Superadministratorius',
            'phone' => '+37060000000',
            'is_active' => true,
            'purpose' => 'Global demo administration account.',
        ],
    ],

    'libraries' => [
        'LIB-X' => [
            'admins' => [
                [
                    'key' => 'lib-x-admin',
                    'email' => 'adminx@test.com',
                    'name' => 'Rasa Klimiene',
                    'phone' => '+37061110001',
                    'is_active' => true,
                    'purpose' => 'Vilnius library administrator.',
                ],
            ],
            'staff' => [
                ['key' => 'lib-x-staff-main', 'email' => 'staffx@test.com', 'name' => 'Paulius Mockus', 'phone' => '+37062220001', 'branch_code' => 'LIB-X-BR-01', 'is_active' => true, 'purpose' => 'Main branch staff account.'],
                ['key' => 'lib-x-staff-senamiestis', 'email' => 'staffx.senamiestis@test.com', 'name' => 'Ruta Senamiescio', 'phone' => '+37062220011', 'branch_code' => 'LIB-X-BR-02', 'is_active' => true, 'purpose' => 'Senamiestis branch staff account.'],
                ['key' => 'lib-x-staff-zirmunai', 'email' => 'staffx.zirmunai@test.com', 'name' => 'Marius Zirmunu', 'phone' => '+37062220012', 'branch_code' => 'LIB-X-BR-03', 'is_active' => true, 'purpose' => 'Zirmunai branch staff account.'],
                ['key' => 'lib-x-staff-antakalnis', 'email' => 'staffx.antakalnis@test.com', 'name' => 'Egle Antakalnio', 'phone' => '+37062220013', 'branch_code' => 'LIB-X-BR-04', 'is_active' => true, 'purpose' => 'Antakalnis branch staff account.'],
            ],
            'members' => [
                ['key' => 'lib-x-member-egle', 'email' => 'egle.petrauskaite@example.com', 'name' => 'Egle Petrauskaite', 'phone' => '+37061234003', 'is_active' => true, 'purpose' => 'Notification catalog member.'],
            ],
        ],

        'LIB-Y' => [
            'admins' => [
                [
                    'key' => 'lib-y-admin',
                    'email' => 'adminy@test.com',
                    'name' => 'Dalia Varniene',
                    'phone' => '+37061110002',
                    'is_active' => true,
                    'purpose' => 'Kaunas library administrator.',
                ],
            ],
            'staff' => [
                ['key' => 'lib-y-staff-main', 'email' => 'staffy@test.com', 'name' => 'Mantas Jasiunas', 'phone' => '+37062220002', 'branch_code' => 'LIB-Y-BR-01', 'is_active' => true, 'purpose' => 'Main branch staff account.'],
                ['key' => 'lib-y-staff-silainiai', 'email' => 'staffy.silainiai@test.com', 'name' => 'Rima Silainiu', 'phone' => '+37062221011', 'branch_code' => 'LIB-Y-BR-02', 'is_active' => true, 'purpose' => 'Silainiai branch staff account.'],
                ['key' => 'lib-y-staff-dainava', 'email' => 'staffy.dainava@test.com', 'name' => 'Tomas Dainavos', 'phone' => '+37062221012', 'branch_code' => 'LIB-Y-BR-03', 'is_active' => true, 'purpose' => 'Dainava branch staff account.'],
                ['key' => 'lib-y-staff-kalnieciai', 'email' => 'staffy.kalnieciai@test.com', 'name' => 'Aiste Kalnieciu', 'phone' => '+37062221013', 'branch_code' => 'LIB-Y-BR-04', 'is_active' => true, 'purpose' => 'Kalnieciai branch staff account.'],
            ],
            'members' => [
                ['key' => 'lib-y-member-ieva', 'email' => 'ieva.noreikaite@example.com', 'name' => 'Ieva Noreikaite', 'phone' => '+37061235001', 'is_active' => true, 'purpose' => 'Kaunas demo member.'],
            ],
        ],

        'KALT-ASTU-001' => [
            'admins' => [
                [
                    'key' => 'kalt-admin',
                    'email' => 'admin@kaltinenubiblioteka.lt',
                    'name' => 'Kaltinenu bibliotekos administratorius',
                    'phone' => '+37061111111',
                    'is_active' => true,
                    'purpose' => 'Kaltinenai library administrator.',
                ],
            ],
            'staff' => [
                ['key' => 'kalt-staff-main', 'email' => 'ieva@kaltinenubiblioteka.lt', 'name' => 'Ieva Jonaite', 'phone' => '+37062222222', 'branch_code' => 'MAIN', 'is_active' => true, 'purpose' => 'Pagrindinis skyrius staff account.'],
                ['key' => 'kalt-staff-kids', 'email' => 'tomas@kaltinenubiblioteka.lt', 'name' => 'Tomas Petrauskas', 'phone' => '+37063333333', 'branch_code' => 'KIDS', 'is_active' => true, 'purpose' => 'Vaiku ir jaunimo skyrius staff account.'],
            ],
            'members' => [
                ['key' => 'kalt-member-lukas', 'email' => 'lukas.skaitytojas@example.com', 'name' => 'Lukas Petrauskas', 'phone' => '+37064444444', 'is_active' => true, 'purpose' => 'Kaltinenai demo reader.'],
                ['key' => 'kalt-member-emilija', 'email' => 'emilija.skaitytoja@example.com', 'name' => 'Emilija Jankauskaite', 'phone' => '+37065555555', 'is_active' => true, 'purpose' => 'Kaltinenai demo reader.'],
                ['key' => 'kalt-member-matas', 'email' => 'matas.skaitytojas@example.com', 'name' => 'Matas Vaitkus', 'phone' => '+37066666666', 'is_active' => true, 'purpose' => 'Kaltinenai demo reader.'],
                ['key' => 'kalt-member-gabija', 'email' => 'gabija.skaitytoja@example.com', 'name' => 'Gabija Rimkute', 'phone' => '+37067777777', 'is_active' => true, 'purpose' => 'Kaltinenai demo reader.'],
                ['key' => 'kalt-member-saule', 'email' => 'saule.skaitytoja@example.com', 'name' => 'Saule Girdziute', 'phone' => '+37068888888', 'is_active' => true, 'purpose' => 'Kaltinenai demo reader.'],
                ['key' => 'kalt-member-karolina', 'email' => 'karolina.skaitytoja@example.com', 'name' => 'Karolina Butkeviciute', 'phone' => '+37069900001', 'is_active' => true, 'purpose' => 'Kaltinenai demo reader.'],
                ['key' => 'kalt-member-tadas', 'email' => 'tadas.skaitytojas@example.com', 'name' => 'Tadas Veverskis', 'phone' => '+37069900002', 'is_active' => true, 'purpose' => 'Kaltinenai demo reader.'],
                ['key' => 'kalt-member-aiste', 'email' => 'aiste.skaitytoja@example.com', 'name' => 'Aiste Maciulyte', 'phone' => '+37069900003', 'is_active' => true, 'purpose' => 'Kaltinenai demo reader.'],
                ['key' => 'kalt-member-pijus', 'email' => 'pijus.skaitytojas@example.com', 'name' => 'Pijus Zabiela', 'phone' => '+37069900004', 'is_active' => true, 'purpose' => 'Kaltinenai demo reader.'],
                ['key' => 'kalt-member-greta', 'email' => 'greta.skaitytoja@example.com', 'name' => 'Greta Simkute', 'phone' => '+37069900005', 'is_active' => true, 'purpose' => 'Kaltinenai demo reader.'],
            ],
        ],
    ],

    'presentation' => [
        'library_code' => 'LIB-X',
        'dataset_key' => 'presentation-demo-v2',
        'staff_prefix' => 'presentation.staff.',
        'member_prefix' => 'presentation.member.',
    ],

    'roles' => [
        'admin' => User::ROLE_ADMIN,
        'staff' => User::ROLE_STAFF,
        'member' => User::ROLE_MEMBER,
        'superadmin' => User::ROLE_SUPER_ADMIN,
    ],
];
