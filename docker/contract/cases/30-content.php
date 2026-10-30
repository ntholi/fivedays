<?php
// Course content (sections, pages, books, files, urls, forums, BigBlueButton) in the fixture course,
// plus the core course/user/enrolment functions. Destructive calls only touch what this file creates:
// a second course, two extra users and the content below.

$suffix = date('ymdHis') . random_int(100, 999);

return [
    [
        'fn' => 'core_course_get_categories',
        'params' => fn($ctx) => ['criteria' => [['key' => 'id', 'value' => $ctx->categoryid]]],
        'check' => fn($r, $ctx) => (int) ($r[0]['id'] ?? 0) === $ctx->categoryid ? null : 'fixture category not returned',
    ],
    [
        'fn' => 'core_course_create_courses',
        // newsitems 0 keeps Moodle from adding an announcements forum, so local_fivedays_delete_course
        // does not hit the forum backup failure (see local_fivedays_delete_forum).
        'params' => fn($ctx) => ['courses' => [[
            'fullname' => "Contract spare {$suffix}",
            'shortname' => "CTS{$suffix}",
            'categoryid' => $ctx->categoryid,
            'format' => 'topics',
            'newsitems' => 0,
        ]]],
        'save' => function ($r, $ctx) {
            $ctx->sparecourseid = (int) $r[0]['id'];
        },
    ],
    [
        'fn' => 'core_course_get_courses_by_field',
        'params' => fn($ctx) => ['field' => 'id', 'value' => $ctx->courseid],
        'check' => fn($r, $ctx) => (int) ($r['courses'][0]['id'] ?? 0) === $ctx->courseid ? null : 'fixture course not returned',
    ],
    [
        'fn' => 'core_user_create_users',
        'params' => function ($ctx) use ($suffix) {
            $ctx->extraemails = ["ct.extra1.{$suffix}@example.com", "ct.extra2.{$suffix}@example.com"];
            return ['users' => array_map(fn($email) => [
                'username' => str_replace('@', '.', $email),
                'password' => 'Contract-pw1!',
                'firstname' => 'Contract',
                'lastname' => 'Extra',
                'email' => $email,
            ], $ctx->extraemails)];
        },
        'save' => function ($r, $ctx) {
            $ctx->extraids = array_map(fn($user) => (int) $user['id'], $r);
        },
    ],
    [
        'fn' => 'core_user_get_users',
        'params' => fn($ctx) => ['criteria' => [['key' => 'email', 'value' => $ctx->extraemails[0]]]],
        'check' => fn($r, $ctx) => (int) ($r['users'][0]['id'] ?? 0) === $ctx->extraids[0] ? null : 'created user not found',
    ],
    [
        'fn' => 'enrol_manual_enrol_users',
        // Without a mail server (prod, D20) the welcome message's email output fails after the enrolment is
        // committed, and core throws "Message was not sent." (lib/classes/message/manager.php:382-383), as
        // local_fivedays' own enrol path expects (provision_helper.php). luct doesn't call this function.
        'allow' => ['Message was not sent.'],
        'params' => function ($ctx) {
            global $DB;
            $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
            return ['enrolments' => array_map(
                fn($userid) => ['roleid' => $roleid, 'userid' => $userid, 'courseid' => $ctx->courseid],
                $ctx->extraids
            )];
        },
    ],
    [
        'fn' => 'core_enrol_get_enrolled_users',
        'params' => fn($ctx) => ['courseid' => $ctx->courseid],
        'check' => fn($r, $ctx) => in_array($ctx->studentid, array_map(fn($u) => (int) $u['id'], $r), true)
            ? null : 'fixture student missing',
    ],
    [
        'fn' => 'core_enrol_get_enrolled_users_with_capability',
        'params' => fn($ctx) => ['coursecapabilities' => [
            ['courseid' => $ctx->courseid, 'capabilities' => ['moodle/course:manageactivities']],
        ]],
        'check' => fn($r, $ctx) => in_array($ctx->lecturerid, array_map(fn($u) => (int) $u['id'], $r[0]['users'] ?? []), true)
            ? null : 'lecturer missing from users with capability',
    ],
    [
        'fn' => 'core_enrol_get_users_courses',
        'params' => fn($ctx) => ['userid' => $ctx->studentid],
        'check' => fn($r, $ctx) => in_array($ctx->courseid, array_map(fn($c) => (int) $c['id'], $r), true)
            ? null : 'course missing from user courses',
    ],

    [
        'fn' => 'local_fivedays_create_section',
        'params' => fn($ctx) => ['courseid' => $ctx->courseid, 'name' => 'Contract section'],
        'save' => function ($r, $ctx) {
            $ctx->newsectionid = (int) $r['id'];
            $ctx->newsectionnum = (int) $r['sectionnum'];
        },
    ],
    [
        'fn' => 'local_fivedays_update_section',
        'params' => fn($ctx) => ['sectionid' => $ctx->newsectionid, 'name' => 'Contract section updated'],
    ],
    [
        'fn' => 'local_fivedays_create_subsection',
        'params' => fn($ctx) => ['courseid' => $ctx->courseid, 'parentsection' => $ctx->newsectionnum, 'name' => 'Contract subsection'],
        'save' => function ($r, $ctx) {
            $ctx->subsectionid = (int) $r['id'];
            $ctx->subsectioncmid = (int) $r['coursemoduleid'];
        },
    ],
    [
        'fn' => 'local_fivedays_update_subsection',
        'params' => fn($ctx) => ['sectionid' => $ctx->subsectionid, 'name' => 'Contract subsection updated'],
    ],

    [
        'fn' => 'local_fivedays_create_page',
        'params' => fn($ctx) => [
            'courseid' => $ctx->courseid,
            'name' => 'Contract page',
            'content' => '<p>Page</p>',
            'section' => $ctx->newsectionnum,
        ],
        'save' => function ($r, $ctx) {
            $ctx->pageid = (int) $r['id'];
            $ctx->pagecmid = (int) $r['coursemoduleid'];
        },
    ],
    [
        'fn' => 'local_fivedays_update_page',
        'params' => fn($ctx) => ['pageid' => $ctx->pageid, 'content' => '<p>Page updated</p>'],
    ],
    [
        'fn' => 'mod_page_get_pages_by_courses',
        'params' => fn($ctx) => ['courseids' => [$ctx->courseid]],
        'check' => fn($r, $ctx) => in_array($ctx->pageid, array_map(fn($p) => (int) $p['id'], $r['pages']), true)
            ? null : 'page missing',
    ],
    [
        'fn' => 'mod_page_view_page',
        'params' => fn($ctx) => ['pageid' => $ctx->pageid],
        'check' => fn($r) => !empty($r['status']) ? null : 'status false',
    ],
    [
        'fn' => 'core_course_get_course_module',
        'params' => fn($ctx) => ['cmid' => $ctx->pagecmid],
        'check' => fn($r, $ctx) => (int) ($r['cm']['instance'] ?? 0) === $ctx->pageid ? null : 'wrong course module',
    ],
    [
        'fn' => 'core_course_get_course_module_by_instance',
        'params' => fn($ctx) => ['module' => 'page', 'instance' => $ctx->pageid],
        'check' => fn($r, $ctx) => (int) ($r['cm']['id'] ?? 0) === $ctx->pagecmid ? null : 'wrong course module',
    ],
    [
        'fn' => 'local_fivedays_get_activities_status',
        'params' => fn($ctx) => ['cmids' => [$ctx->pagecmid]],
        'check' => fn($r, $ctx) => !empty($r['activities'][0]['exists']) && $r['activities'][0]['modname'] === 'page'
            ? null : 'page not reported',
    ],
    [
        'fn' => 'local_fivedays_copy_activities',
        'params' => fn($ctx) => ['courseid' => $ctx->sparecourseid, 'sectionname' => 'Material', 'cmids' => [$ctx->pagecmid]],
        'check' => fn($r) => !empty($r['copies'][0]['cmid']) ? null : 'not copied: ' . ($r['copies'][0]['error'] ?? ''),
    ],

    [
        'fn' => 'local_fivedays_create_book',
        'params' => fn($ctx) => [
            'courseid' => $ctx->courseid,
            'name' => 'Contract book',
            'section' => $ctx->newsectionnum,
            'chapters' => [['title' => 'Chapter one', 'content' => '<p>One</p>']],
        ],
        'save' => function ($r, $ctx) {
            $ctx->bookid = (int) $r['id'];
            $ctx->bookcmid = (int) $r['coursemoduleid'];
        },
    ],
    [
        'fn' => 'local_fivedays_add_book_chapter',
        'params' => fn($ctx) => ['bookid' => $ctx->bookid, 'title' => 'Chapter two', 'content' => '<p>Two</p>'],
        'save' => function ($r, $ctx) {
            $ctx->chapterid = (int) $r['id'];
        },
    ],
    [
        'fn' => 'local_fivedays_update_book',
        'params' => fn($ctx) => ['bookid' => $ctx->bookid, 'name' => 'Contract book updated'],
    ],
    [
        'fn' => 'local_fivedays_update_book_chapter',
        'params' => fn($ctx) => ['chapterid' => $ctx->chapterid, 'title' => 'Chapter two updated'],
    ],
    [
        'fn' => 'local_fivedays_get_book',
        'params' => fn($ctx) => ['bookid' => $ctx->bookid],
        'check' => fn($r) => count($r['chapters'] ?? []) === 2 ? null : 'expected 2 chapters',
    ],

    [
        'fn' => 'local_fivedays_create_file',
        'params' => fn($ctx) => [
            'courseid' => $ctx->courseid,
            'name' => 'Contract file',
            'filename' => 'contract.txt',
            'filecontent' => base64_encode('contract'),
            'section' => $ctx->newsectionnum,
        ],
        'save' => function ($r, $ctx) {
            $ctx->resourceid = (int) $r['id'];
            $ctx->resourcecmid = (int) $r['coursemoduleid'];
        },
    ],
    [
        'fn' => 'local_fivedays_update_file',
        'params' => fn($ctx) => [
            'resourceid' => $ctx->resourceid,
            'filename' => 'contract-v2.txt',
            'filecontent' => base64_encode('contract v2'),
        ],
    ],

    [
        'fn' => 'local_fivedays_create_url',
        'params' => fn($ctx) => [
            'courseid' => $ctx->courseid,
            'name' => 'Contract url',
            'externalurl' => 'https://example.com/',
            'section' => $ctx->newsectionnum,
        ],
        'save' => function ($r, $ctx) {
            $ctx->urlid = (int) $r['id'];
            $ctx->urlcmid = (int) $r['coursemoduleid'];
        },
    ],
    [
        'fn' => 'local_fivedays_update_url',
        'params' => fn($ctx) => ['urlid' => $ctx->urlid, 'externalurl' => 'https://example.org/'],
    ],
    [
        'fn' => 'mod_url_get_urls_by_courses',
        'params' => fn($ctx) => ['courseids' => [$ctx->courseid]],
        'check' => fn($r, $ctx) => in_array($ctx->urlid, array_map(fn($u) => (int) $u['id'], $r['urls']), true)
            ? null : 'url missing',
    ],

    [
        'fn' => 'local_fivedays_create_forum',
        'params' => fn($ctx) => [
            'courseid' => $ctx->courseid,
            'name' => 'Contract forum',
            // Kept out of the new section so local_fivedays_delete_section does not depend on deleting the forum.
            'section' => $ctx->sectionnum,
        ],
        'save' => function ($r, $ctx) {
            $ctx->forumid = (int) $r['id'];
            $ctx->forumcmid = (int) $r['coursemoduleid'];
        },
    ],
    [
        'fn' => 'mod_forum_get_forums_by_courses',
        'params' => fn($ctx) => ['courseids' => [$ctx->courseid]],
        'check' => fn($r, $ctx) => in_array($ctx->forumid, array_map(fn($f) => (int) $f['id'], $r), true)
            ? null : 'created forum missing',
    ],
    [
        'fn' => 'mod_forum_add_discussion',
        'params' => fn($ctx) => ['forumid' => $ctx->forumid, 'subject' => 'Contract discussion', 'message' => 'Hello'],
        'save' => function ($r, $ctx) {
            $ctx->discussionid = (int) $r['discussionid'];
        },
    ],
    [
        'fn' => 'mod_forum_get_forum_discussions',
        'params' => fn($ctx) => ['forumid' => $ctx->forumid],
        'check' => fn($r, $ctx) => in_array($ctx->discussionid, array_map(fn($d) => (int) $d['discussion'], $r['discussions']), true)
            ? null : 'discussion missing',
    ],
    [
        'fn' => 'mod_forum_get_discussion_posts',
        'params' => fn($ctx) => ['discussionid' => $ctx->discussionid],
        'check' => fn($r) => !empty($r['posts']) ? null : 'no posts',
        'save' => function ($r, $ctx) {
            $ctx->firstpostid = (int) $r['posts'][0]['id'];
            $ctx->postauthorid = (int) $r['posts'][0]['author']['id'];
        },
    ],
    [
        'fn' => 'mod_forum_add_discussion_post',
        'params' => fn($ctx) => ['postid' => $ctx->firstpostid, 'subject' => 'Re: Contract discussion', 'message' => 'Reply'],
        'save' => function ($r, $ctx) {
            $ctx->replypostid = (int) $r['postid'];
        },
    ],
    [
        'fn' => 'mod_forum_get_discussion_posts_by_userid',
        'params' => fn($ctx) => ['userid' => $ctx->postauthorid, 'cmid' => $ctx->forumcmid],
        'check' => fn($r) => !empty($r['discussions']) ? null : 'no discussions for author',
    ],
    [
        'fn' => 'mod_forum_delete_post',
        'params' => fn($ctx) => ['postid' => $ctx->replypostid],
        'check' => fn($r) => !empty($r['status']) ? null : 'status false',
    ],

    [
        'fn' => 'local_fivedays_create_bigbluebuttonbn',
        'params' => fn($ctx) => ['courseid' => $ctx->courseid, 'name' => 'Contract meeting', 'section' => $ctx->newsectionnum],
        'save' => function ($r, $ctx) {
            $ctx->bbbid = (int) $r['id'];
            $ctx->bbbcmid = (int) $r['coursemoduleid'];
        },
    ],
    [
        'fn' => 'local_fivedays_update_bigbluebuttonbn',
        'params' => fn($ctx) => ['bigbluebuttonbnid' => $ctx->bbbid, 'name' => 'Contract meeting updated'],
    ],
    [
        'fn' => 'mod_bigbluebuttonbn_get_join_url',
        'params' => fn($ctx) => ['cmid' => $ctx->bbbcmid],
        // BigBlueButton is disabled on a fresh local install, so its course modules are hidden from modinfo.
        'allow' => ['invalidcoursemoduleid'],
    ],
    [
        'fn' => 'local_fivedays_delete_bigbluebuttonbn',
        'params' => fn($ctx) => ['cmid' => $ctx->bbbcmid],
        // BigBlueButton is disabled on a fresh local install, so its course modules are hidden from modinfo.
        'allow' => ['invalidcoursemoduleid'],
    ],

    [
        'fn' => 'local_fivedays_ensure_google_login',
        // On a site that already has the Google issuer (prod, during the cutover) pass its own credentials, so
        // the call changes nothing; dummy credentials only where there is no issuer yet.
        'params' => function () {
            $issuer = \local_fivedays\provision_helper::find_google_issuer();
            return $issuer ? ['clientid' => $issuer->get('clientid'), 'clientsecret' => $issuer->get('clientsecret')]
                : ['clientid' => 'local-dev-client', 'clientsecret' => 'local-dev-secret'];
        },
    ],
    [
        'fn' => 'local_fivedays_unenrol_users',
        'params' => fn($ctx) => ['enrolments' => [['email' => $ctx->extraemails[0], 'courseid' => $ctx->courseid]]],
        'check' => fn($r) => (int) $r['unenrolled'] === 1 ? null : 'expected 1 unenrolment',
    ],
    [
        'fn' => 'enrol_manual_unenrol_users',
        'params' => fn($ctx) => ['enrolments' => [['userid' => $ctx->extraids[1], 'courseid' => $ctx->courseid]]],
    ],
    [
        'fn' => 'local_fivedays_rebind_user_email',
        'params' => fn($ctx) => ['fromemail' => $ctx->extraemails[0], 'toemail' => "ct.rebound.{$suffix}@example.com"],
        'check' => fn($r, $ctx) => (int) $r['moodleUserId'] === $ctx->extraids[0] ? null : 'rebound a different user',
    ],

    [
        'fn' => 'local_fivedays_delete_url',
        'params' => fn($ctx) => ['cmid' => $ctx->urlcmid],
    ],
    [
        'fn' => 'local_fivedays_delete_file',
        'params' => fn($ctx) => ['cmid' => $ctx->resourcecmid],
    ],
    [
        'fn' => 'local_fivedays_delete_book',
        'params' => fn($ctx) => ['cmid' => $ctx->bookcmid],
    ],
    [
        'fn' => 'local_fivedays_delete_page',
        'params' => fn($ctx) => ['cmid' => $ctx->pagecmid],
    ],
    [
        'fn' => 'local_fivedays_delete_forum',
        'params' => fn($ctx) => ['cmid' => $ctx->forumcmid],
    ],
    [
        'fn' => 'local_fivedays_delete_subsection',
        'params' => fn($ctx) => ['cmid' => $ctx->subsectioncmid],
    ],
    [
        'fn' => 'local_fivedays_delete_section',
        'params' => fn($ctx) => ['courseid' => $ctx->courseid, 'sectionnum' => $ctx->newsectionnum],
    ],
    [
        'fn' => 'core_course_delete_modules',
        // The only spare module is the BigBlueButton one, which modinfo hides while BigBlueButton is disabled.
        'params' => fn($ctx) => ['cmids' => [$ctx->bbbcmid]],
        'allow' => ['invalidcoursemoduleid'],
    ],
    [
        'fn' => 'local_fivedays_delete_course',
        'params' => fn($ctx) => ['courseid' => $ctx->sparecourseid],
        'check' => fn($r) => !empty($r['success']) ? null : 'course not deleted',
    ],
];
