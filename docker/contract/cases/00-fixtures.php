<?php
// Fixtures every later case uses: one course, one lecturer and one student enrolled in it.
// Values land in $ctx (courseid, sectionid, lecturer/student emails, ids and tokens).

$suffix = date('ymdHis') . random_int(100, 999);

return [
    [
        'fn' => 'core_webservice_get_site_info',
        'check' => fn($r) => ($r['username'] ?? '') === 'fivedays_ws' ? null : 'token does not belong to fivedays_ws',
    ],
    [
        'fn' => 'local_fivedays_ensure_course',
        'params' => fn($ctx) => [
            'fullname' => "Contract test {$suffix}",
            'shortname' => "CT{$suffix}",
            'categoryname' => 'CONTRACT',
            'idnumber' => '9999:' . substr($suffix, -9),
        ],
        'save' => function ($r, $ctx) {
            $ctx->courseid = (int) $r['courseId'];
            $ctx->categoryid = (int) $r['categoryId'];
        },
    ],
    [
        'fn' => 'local_fivedays_provision_users',
        'params' => function ($ctx) use ($suffix) {
            $ctx->lectureremail = "ct.lecturer.{$suffix}@example.com";
            $ctx->studentemail = "ct.student.{$suffix}@example.com";
            return ['users' => [
                ['email' => $ctx->lectureremail, 'firstname' => 'Contract', 'lastname' => 'Lecturer'],
                ['email' => $ctx->studentemail, 'firstname' => 'Contract', 'lastname' => 'Student'],
            ]];
        },
        'save' => function ($r, $ctx) {
            foreach ($r['users'] as $user) {
                $role = $user['email'] === $ctx->lectureremail ? 'lecturer' : 'student';
                $ctx->{"{$role}id"} = (int) $user['moodleUserId'];
                $ctx->{"{$role}token"} = $user['token'];
            }
        },
    ],
    [
        'fn' => 'local_fivedays_enrol_users',
        'params' => fn($ctx) => ['enrolments' => [
            ['email' => $ctx->lectureremail, 'courseid' => $ctx->courseid, 'roleshortname' => 'editingteacher'],
            ['email' => $ctx->studentemail, 'courseid' => $ctx->courseid, 'roleshortname' => 'student'],
        ]],
    ],
    [
        'fn' => 'core_course_get_contents',
        'params' => fn($ctx) => ['courseid' => $ctx->courseid],
        'save' => function ($r, $ctx) {
            $ctx->sectionid = (int) $r[0]['id'];
            $ctx->sectionnum = (int) $r[0]['section'];
        },
    ],
];
