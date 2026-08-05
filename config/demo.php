<?php

use App\Models\User;

return [
    'enabled' => env('DEMO_DATA_ENABLED', in_array(env('APP_ENV'), ['local', 'testing'], true)),

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
                    'name' => 'Rasa Klimienė',
                    'phone' => '+37061110001',
                    'is_active' => true,
                    'purpose' => 'Vilnius library administrator.',
                ],
            ],
            'staff' => [
                ['key' => 'lib-x-staff-main', 'email' => 'staffx@test.com', 'name' => 'Paulius Mockus', 'phone' => '+37062220001', 'branch_code' => 'LIB-X-BR-01', 'is_active' => true, 'purpose' => 'Pagrindinio filialo darbuotojo paskyra.'],
                ['key' => 'lib-x-staff-senamiestis', 'email' => 'staffx.senamiestis@test.com', 'name' => 'Rūta Senamiesčio', 'phone' => '+37062220011', 'branch_code' => 'LIB-X-BR-02', 'is_active' => true, 'purpose' => 'Senamiesčio filialo darbuotojo paskyra.'],
                ['key' => 'lib-x-staff-zirmunai', 'email' => 'staffx.zirmunai@test.com', 'name' => 'Marius Žirmūnų', 'phone' => '+37062220012', 'branch_code' => 'LIB-X-BR-03', 'is_active' => true, 'purpose' => 'Žirmūnų filialo darbuotojo paskyra.'],
                ['key' => 'lib-x-staff-antakalnis', 'email' => 'staffx.antakalnis@test.com', 'name' => 'Eglė Antakalnio', 'phone' => '+37062220013', 'branch_code' => 'LIB-X-BR-04', 'is_active' => true, 'purpose' => 'Antakalnio filialo darbuotojo paskyra.'],
                ['key' => 'membership-change-staff', 'email' => 'membership.change@test.com', 'name' => 'Narystės keitimo darbuotojas', 'phone' => '+37062220999', 'branch_code' => 'LIB-X-BR-01', 'is_active' => true, 'purpose' => 'Atskira paskyra narystės ir sesijos atnaujinimo testams.'],
            ],
            'members' => [
                ['key' => 'lib-x-member-egle', 'email' => 'egle.petrauskaite@example.com', 'name' => 'Eglė Petrauskaitė', 'phone' => '+37061234003', 'is_active' => true, 'purpose' => 'Pranešimų katalogo skaitytoja.'],
            ],
        ],

        'LIB-Y' => [
            'admins' => [
                [
                    'key' => 'lib-y-admin',
                    'email' => 'adminy@test.com',
                    'name' => 'Dalia Varnienė',
                    'phone' => '+37061110002',
                    'is_active' => true,
                    'purpose' => 'Kaunas library administrator.',
                ],
            ],
            'staff' => [
                ['key' => 'lib-y-staff-main', 'email' => 'staffy@test.com', 'name' => 'Mantas Jašiūnas', 'phone' => '+37062220002', 'branch_code' => 'LIB-Y-BR-01', 'is_active' => true, 'purpose' => 'Pagrindinio filialo darbuotojo paskyra.'],
                ['key' => 'lib-y-staff-silainiai', 'email' => 'staffy.silainiai@test.com', 'name' => 'Rima Šilainių', 'phone' => '+37062221011', 'branch_code' => 'LIB-Y-BR-02', 'is_active' => true, 'purpose' => 'Šilainių filialo darbuotojo paskyra.'],
                ['key' => 'lib-y-staff-dainava', 'email' => 'staffy.dainava@test.com', 'name' => 'Tomas Dainavos', 'phone' => '+37062221012', 'branch_code' => 'LIB-Y-BR-03', 'is_active' => true, 'purpose' => 'Dainavos filialo darbuotojo paskyra.'],
                ['key' => 'lib-y-staff-kalnieciai', 'email' => 'staffy.kalnieciai@test.com', 'name' => 'Aistė Kalniečių', 'phone' => '+37062221013', 'branch_code' => 'LIB-Y-BR-04', 'is_active' => true, 'purpose' => 'Kalniečių filialo darbuotojo paskyra.'],
            ],
            'members' => [
                ['key' => 'lib-y-member-ieva', 'email' => 'ieva.noreikaite@example.com', 'name' => 'Ieva Noreikaitė', 'phone' => '+37061235001', 'is_active' => true, 'purpose' => 'Kauno demo skaitytoja.'],
            ],
        ],

        'KALT-ASTU-001' => [
            'admins' => [
                [
                    'key' => 'kalt-admin',
                    'email' => 'admin@kaltinenubiblioteka.lt',
                    'name' => 'Kaltinėnų bibliotekos administratorius',
                    'phone' => '+37061111111',
                    'is_active' => true,
                    'purpose' => 'Kaltinėnų bibliotekos administratorius.',
                ],
            ],
            'staff' => [
                ['key' => 'kalt-staff-main', 'email' => 'ieva@kaltinenubiblioteka.lt', 'name' => 'Ieva Jonaitė', 'phone' => '+37062222222', 'branch_code' => 'MAIN', 'is_active' => true, 'purpose' => 'Pagrindinio skyriaus darbuotojo paskyra.'],
                ['key' => 'kalt-staff-kids', 'email' => 'tomas@kaltinenubiblioteka.lt', 'name' => 'Tomas Petrauskas', 'phone' => '+37063333333', 'branch_code' => 'KIDS', 'is_active' => true, 'purpose' => 'Vaikų ir jaunimo skyriaus darbuotojo paskyra.'],
            ],
            'members' => [
                ['key' => 'kalt-member-lukas', 'email' => 'lukas.skaitytojas@example.com', 'name' => 'Lukas Petrauskas', 'phone' => '+37064444444', 'is_active' => true, 'purpose' => 'Kaltinėnų demo skaitytojas.'],
                ['key' => 'kalt-member-emilija', 'email' => 'emilija.skaitytoja@example.com', 'name' => 'Emilija Jankauskaitė', 'phone' => '+37065555555', 'is_active' => true, 'purpose' => 'Kaltinėnų demo skaitytoja.'],
                ['key' => 'kalt-member-matas', 'email' => 'matas.skaitytojas@example.com', 'name' => 'Matas Vaitkus', 'phone' => '+37066666666', 'is_active' => true, 'purpose' => 'Kaltinėnų demo skaitytojas.'],
                ['key' => 'kalt-member-gabija', 'email' => 'gabija.skaitytoja@example.com', 'name' => 'Gabija Rimkutė', 'phone' => '+37067777777', 'is_active' => true, 'purpose' => 'Kaltinėnų demo skaitytoja.'],
                ['key' => 'kalt-member-saule', 'email' => 'saule.skaitytoja@example.com', 'name' => 'Saulė Girdžiūtė', 'phone' => '+37068888888', 'is_active' => true, 'purpose' => 'Kaltinėnų demo skaitytoja.'],
                ['key' => 'kalt-member-karolina', 'email' => 'karolina.skaitytoja@example.com', 'name' => 'Karolina Butkevičiūtė', 'phone' => '+37069900001', 'is_active' => true, 'purpose' => 'Kaltinėnų demo skaitytoja.'],
                ['key' => 'kalt-member-tadas', 'email' => 'tadas.skaitytojas@example.com', 'name' => 'Tadas Veverskis', 'phone' => '+37069900002', 'is_active' => true, 'purpose' => 'Kaltinėnų demo skaitytojas.'],
                ['key' => 'kalt-member-aiste', 'email' => 'aiste.skaitytoja@example.com', 'name' => 'Aistė Mačiulytė', 'phone' => '+37069900003', 'is_active' => true, 'purpose' => 'Kaltinėnų demo skaitytoja.'],
                ['key' => 'kalt-member-pijus', 'email' => 'pijus.skaitytojas@example.com', 'name' => 'Pijus Zabiela', 'phone' => '+37069900004', 'is_active' => true, 'purpose' => 'Kaltinėnų demo skaitytojas.'],
                ['key' => 'kalt-member-greta', 'email' => 'greta.skaitytoja@example.com', 'name' => 'Greta Šimkutė', 'phone' => '+37069900005', 'is_active' => true, 'purpose' => 'Kaltinėnų demo skaitytoja.'],
            ],
        ],
    ],

    'presentation' => [
        'library_code' => 'LIB-X',
        'dataset_key' => 'presentation-demo-v2',
        'staff_prefix' => 'presentation.staff.',
        'member_prefix' => 'presentation.member.',
        'staff' => [
            ['email' => 'presentation.staff.001@example.com', 'branch_code' => 'PRES-B01'],
            ['email' => 'presentation.staff.002@example.com', 'branch_code' => 'PRES-B02'],
            ['email' => 'presentation.staff.003@example.com', 'branch_code' => 'PRES-B03'],
            ['email' => 'presentation.staff.004@example.com', 'branch_code' => 'PRES-B04'],
            ['email' => 'presentation.staff.005@example.com', 'branch_code' => 'PRES-B05'],
            ['email' => 'presentation.staff.006@example.com', 'branch_code' => 'PRES-B06'],
            ['email' => 'presentation.staff.007@example.com', 'branch_code' => 'PRES-B07'],
            ['email' => 'presentation.staff.008@example.com', 'branch_code' => 'PRES-B08'],
            ['email' => 'presentation.staff.009@example.com', 'branch_code' => 'PRES-B01'],
            ['email' => 'presentation.staff.010@example.com', 'branch_code' => 'PRES-B02'],
            ['email' => 'presentation.staff.011@example.com', 'branch_code' => 'PRES-B03'],
            ['email' => 'presentation.staff.012@example.com', 'branch_code' => 'PRES-B04'],
            ['email' => 'presentation.staff.013@example.com', 'branch_code' => 'PRES-B05'],
            ['email' => 'presentation.staff.014@example.com', 'branch_code' => 'PRES-B06'],
            ['email' => 'presentation.staff.015@example.com', 'branch_code' => 'PRES-B07'],
            ['email' => 'presentation.staff.016@example.com', 'branch_code' => 'PRES-B08'],
            ['email' => 'presentation.staff.017@example.com', 'branch_code' => 'PRES-B01'],
            ['email' => 'presentation.staff.018@example.com', 'branch_code' => 'PRES-B02'],
        ],
    ],

    'roles' => [
        'admin' => User::ROLE_ADMIN,
        'staff' => User::ROLE_STAFF,
        'member' => User::ROLE_MEMBER,
        'superadmin' => User::ROLE_SUPER_ADMIN,
    ],
];
