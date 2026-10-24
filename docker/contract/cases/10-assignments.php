<?php
// Assignments, their rubrics, grades and comments. Assignment A carries the grading flow;
// assignment B gets team groups and a copied rubric, then is deleted.

$criteria = fn($score) => [
    ['description' => 'Content', 'sortorder' => 0, 'levels' => [
        ['score' => 0, 'definition' => 'Missing'],
        ['score' => $score, 'definition' => 'Complete'],
    ]],
];

return [
    [
        'fn' => 'core_files_get_unused_draft_itemid',
        'check' => fn($r) => ($r['itemid'] ?? 0) > 0 ? null : 'no draft item id',
        'save' => function ($r, $ctx) {
            $ctx->draftitemid = (int) $r['itemid'];
        },
    ],
    [
        'fn' => 'local_fivedays_create_draft_files',
        'params' => fn($ctx) => ['files' => [['filename' => 'brief.txt', 'content' => base64_encode('Contract brief')]]],
        'check' => fn($r) => ($r['draftitemid'] ?? 0) > 0 ? null : 'no draft item id',
        'save' => function ($r, $ctx) {
            $ctx->filesdraftitemid = (int) $r['draftitemid'];
        },
    ],
    [
        'fn' => 'local_fivedays_get_draft_files',
        'params' => fn($ctx) => ['draftitemid' => $ctx->filesdraftitemid],
        'check' => fn($r) => array_column($r['files'] ?? [], 'filename') === ['brief.txt'] ? null : 'brief.txt not in the draft area',
    ],
    [
        'fn' => 'local_fivedays_create_assignment',
        'params' => fn($ctx) => [
            'courseid' => $ctx->courseid,
            'name' => 'Contract assignment A',
            'intro' => 'Assignment A',
            'section' => $ctx->sectionnum,
            'duedate' => time() + 7 * DAYSECS,
            'grademax' => 100,
            'introattachments' => $ctx->draftitemid,
        ],
        'save' => function ($r, $ctx) {
            $ctx->assignid = (int) $r['id'];
            $ctx->assigncmid = (int) $r['coursemoduleid'];
        },
    ],
    [
        'fn' => 'local_fivedays_update_assignment',
        'params' => fn($ctx) => ['assignmentid' => $ctx->assignid, 'name' => 'Contract assignment A (edited)'],
    ],
    [
        'fn' => 'local_fivedays_get_assignment',
        'params' => fn($ctx) => ['assignmentid' => $ctx->assignid],
    ],
    [
        'fn' => 'mod_assign_get_assignments',
        // fivedays_ws is not enrolled, so without includenotenrolledcourses the course is skipped with a warning.
        'params' => fn($ctx) => ['courseids' => [$ctx->courseid], 'includenotenrolledcourses' => 1],
        'check' => fn($r, $ctx) => in_array($ctx->assignid, array_column($r['courses'][0]['assignments'] ?? [], 'id'))
            ? null : 'assignment A not listed',
    ],
    [
        'fn' => 'mod_assign_get_submission_status',
        'params' => fn($ctx) => ['assignid' => $ctx->assignid, 'userid' => $ctx->studentid],
    ],
    [
        'fn' => 'mod_assign_get_submissions',
        'params' => fn($ctx) => ['assignmentids' => [$ctx->assignid]],
    ],
    [
        'fn' => 'mod_assign_save_grade',
        'params' => fn($ctx) => [
            'assignmentid' => $ctx->assignid, 'userid' => $ctx->studentid, 'grade' => 60,
            'attemptnumber' => -1, 'addattempt' => 0, 'workflowstate' => '', 'applytoall' => 0,
        ],
    ],
    [
        'fn' => 'mod_assign_save_grades',
        'params' => fn($ctx) => [
            'assignmentid' => $ctx->assignid, 'applytoall' => 0,
            'grades' => [['userid' => $ctx->studentid, 'grade' => 65, 'attemptnumber' => -1, 'addattempt' => 0,
                'workflowstate' => '']],
        ],
    ],
    [
        'fn' => 'mod_assign_get_grades',
        'params' => fn($ctx) => ['assignmentids' => [$ctx->assignid]],
        'check' => fn($r) => !empty($r['assignments'][0]['grades']) ? null : 'saved grade not returned',
    ],
    [
        'fn' => 'local_fivedays_save_assignment_grade',
        'params' => fn($ctx) => ['assignmentid' => $ctx->assignid, 'userid' => $ctx->studentid, 'grade' => 70],
    ],
    [
        'fn' => 'local_fivedays_get_assignment_grades',
        'params' => fn($ctx) => ['assignmentid' => $ctx->assignid],
    ],
    [
        'fn' => 'local_fivedays_add_assignment_comment',
        'params' => fn($ctx) => ['assignmentid' => $ctx->assignid, 'userid' => $ctx->studentid, 'content' => 'Good start'],
        'save' => function ($r, $ctx) {
            $ctx->commentid = (int) $r['id'];
        },
    ],
    [
        'fn' => 'local_fivedays_update_assignment_comment',
        'params' => fn($ctx) => ['commentid' => $ctx->commentid, 'content' => 'Good start, add sources'],
    ],
    [
        'fn' => 'local_fivedays_get_assignment_comments',
        'params' => fn($ctx) => ['assignmentid' => $ctx->assignid, 'userid' => $ctx->studentid],
        'check' => fn($r, $ctx) => in_array($ctx->commentid, array_column($r['comments'], 'id')) ? null : 'comment not listed',
    ],
    [
        'fn' => 'local_fivedays_create_rubric',
        'params' => fn($ctx) => ['cmid' => $ctx->assigncmid, 'name' => 'Contract rubric', 'criteria' => $criteria(10)],
    ],
    [
        'fn' => 'local_fivedays_update_rubric',
        'params' => fn($ctx) => ['cmid' => $ctx->assigncmid, 'name' => 'Contract rubric', 'criteria' => $criteria(20)],
    ],
    [
        'fn' => 'local_fivedays_get_rubric',
        'params' => fn($ctx) => ['cmid' => $ctx->assigncmid],
        'check' => fn($r) => ($r['gradingmethod'] ?? '') === 'fivedays' && !empty($r['criteria']) ? null : 'rubric not active',
        'save' => function ($r, $ctx) {
            $ctx->criterionid = (int) $r['criteria'][0]['id'];
            $ctx->levelid = (int) end($r['criteria'][0]['levels'])['id'];
        },
    ],
    [
        'fn' => 'local_fivedays_fill_rubric',
        'params' => fn($ctx) => [
            'cmid' => $ctx->assigncmid, 'userid' => $ctx->studentid,
            'fillings' => [['criterionid' => $ctx->criterionid, 'levelid' => $ctx->levelid, 'remark' => 'Complete']],
        ],
    ],
    [
        'fn' => 'local_fivedays_get_rubric_filling',
        'params' => fn($ctx) => ['cmid' => $ctx->assigncmid, 'userid' => $ctx->studentid],
    ],
    [
        'fn' => 'local_fivedays_release_assignment_grades',
        'params' => fn($ctx) => [
            'assignmentid' => $ctx->assignid, 'userids' => [$ctx->studentid],
            'comments' => [['userid' => $ctx->studentid, 'comment' => 'Well done']],
        ],
    ],
    [
        'fn' => 'local_fivedays_get_grading_queue',
        'params' => fn($ctx) => ['courseid' => $ctx->courseid],
    ],
    [
        'fn' => 'local_fivedays_create_assignment',
        'params' => fn($ctx) => ['courseid' => $ctx->courseid, 'name' => 'Contract assignment B', 'section' => $ctx->sectionnum],
        'save' => function ($r, $ctx) {
            $ctx->assignbid = (int) $r['id'];
            $ctx->assignbcmid = (int) $r['coursemoduleid'];
        },
    ],
    [
        'fn' => 'local_fivedays_sync_assignment_groups',
        'params' => fn($ctx) => [
            'assignmentid' => $ctx->assignbid, 'enabled' => 1,
            'groups' => [['key' => 'team1', 'name' => 'Team 1', 'userids' => [$ctx->studentid]]],
        ],
    ],
    [
        'fn' => 'local_fivedays_get_assignment_teams',
        'params' => fn($ctx) => ['assignmentid' => $ctx->assignbid],
    ],
    [
        'fn' => 'local_fivedays_copy_rubric',
        'params' => fn($ctx) => ['sourcecmid' => $ctx->assigncmid, 'targetcmid' => $ctx->assignbcmid],
    ],
    [
        'fn' => 'local_fivedays_delete_rubric',
        'params' => fn($ctx) => ['cmid' => $ctx->assignbcmid],
    ],
    [
        'fn' => 'local_fivedays_delete_assignment_comment',
        'params' => fn($ctx) => ['commentid' => $ctx->commentid],
    ],
    [
        'fn' => 'local_fivedays_delete_assignment',
        'params' => fn($ctx) => ['cmid' => $ctx->assignbcmid],
    ],
];
