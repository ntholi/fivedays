<?php
// Quizzes and the question bank: one category, one question of each type, a quiz built from them
// (sections, order, marks), then a student attempt that is graded, then the deletes.

$ok = fn($r) => ($r['success'] ?? false) === true ? null : 'success is not true: ' . ($r['message'] ?? '');
$question = fn(string $type, array $extra) => fn($ctx) => [
    'categoryid' => $ctx->qcategoryid,
    'name' => "Contract {$type}",
    'questiontext' => "<p>Contract {$type} question</p>",
] + $extra;
$savequestion = fn(string $type) => function ($r, $ctx) use ($type) {
    $ctx->qbe[$type] = (int) $r['questionbankentryid'];
};
$slotid = fn($ctx, string $type) => $ctx->slotids[$type];

return [
    [
        'fn' => 'local_fivedays_get_or_create_question_category',
        'params' => fn($ctx) => ['courseid' => $ctx->courseid, 'name' => 'Contract questions'],
        'check' => $ok,
        'save' => function ($r, $ctx) {
            $ctx->qcategoryid = (int) $r['id'];
            $ctx->qbe = [];
        },
    ],
    [
        'fn' => 'local_fivedays_list_question_categories',
        'params' => fn($ctx) => ['courseid' => $ctx->courseid],
        'check' => fn($r, $ctx) => in_array($ctx->qcategoryid, array_map('intval', array_column($r['categories'], 'id')), true)
            ? null : 'new category not listed',
    ],
    [
        'fn' => 'local_fivedays_create_multichoice_question',
        'params' => $question('multichoice', ['answers' => json_encode([
            ['text' => 'Right', 'fraction' => 1.0, 'feedback' => ''],
            ['text' => 'Wrong', 'fraction' => 0.0, 'feedback' => ''],
        ])]),
        'check' => $ok,
        'save' => $savequestion('multichoice'),
    ],
    [
        'fn' => 'local_fivedays_create_truefalse_question',
        'params' => $question('truefalse', ['correctanswer' => 1]),
        'check' => $ok,
        'save' => $savequestion('truefalse'),
    ],
    [
        'fn' => 'local_fivedays_create_shortanswer_question',
        'params' => $question('shortanswer', ['answers' => json_encode([['text' => 'Maseru', 'fraction' => 1.0, 'feedback' => '']])]),
        'check' => $ok,
        'save' => $savequestion('shortanswer'),
    ],
    [
        'fn' => 'local_fivedays_create_essay_question',
        'params' => $question('essay', []),
        'check' => $ok,
        'save' => $savequestion('essay'),
    ],
    [
        'fn' => 'local_fivedays_create_numerical_question',
        'params' => $question('numerical', ['answers' => json_encode([['answer' => '42', 'tolerance' => 0, 'fraction' => 1.0, 'feedback' => '']])]),
        'check' => $ok,
        'save' => $savequestion('numerical'),
    ],
    [
        'fn' => 'local_fivedays_create_match_question',
        'params' => $question('match', ['pairs' => json_encode([
            ['question' => 'Red', 'answer' => 'Stop'],
            ['question' => 'Green', 'answer' => 'Go'],
            ['question' => 'Amber', 'answer' => 'Wait'],
        ])]),
        'check' => $ok,
        'save' => $savequestion('match'),
    ],
    [
        'fn' => 'local_fivedays_create_gapselect_question',
        'params' => fn($ctx) => [
            'categoryid' => $ctx->qcategoryid,
            'name' => 'Contract gapselect',
            'questiontext' => '<p>The sky is [[1]] and grass is [[2]].</p>',
            'choices' => json_encode([['answer' => 'blue', 'group' => 1], ['answer' => 'green', 'group' => 1]]),
        ],
        'check' => $ok,
        'save' => $savequestion('gapselect'),
    ],
    [
        'fn' => 'local_fivedays_create_ddwtos_question',
        'params' => fn($ctx) => [
            'categoryid' => $ctx->qcategoryid,
            'name' => 'Contract ddwtos',
            'questiontext' => '<p>The sky is [[1]] and grass is [[2]].</p>',
            'choices' => json_encode([
                ['answer' => 'blue', 'group' => 1, 'infinite' => false],
                ['answer' => 'green', 'group' => 1, 'infinite' => false],
            ]),
        ],
        'check' => $ok,
        'save' => $savequestion('ddwtos'),
    ],
    [
        'fn' => 'local_fivedays_create_description_question',
        'params' => $question('description', []),
        'check' => $ok,
        'save' => $savequestion('description'),
    ],
    [
        'fn' => 'local_fivedays_get_questions',
        'params' => fn($ctx) => ['categoryid' => $ctx->qcategoryid],
        'check' => fn($r) => count($r['questions']) === 9 ? null : 'expected 9 questions, got ' . count($r['questions']),
    ],
    [
        'fn' => 'local_fivedays_create_quiz',
        'params' => fn($ctx) => ['courseid' => $ctx->courseid, 'name' => 'Contract quiz', 'section' => $ctx->sectionnum],
        'check' => $ok,
        'save' => function ($r, $ctx) {
            $ctx->quizid = (int) $r['id'];
            $ctx->quizcmid = (int) $r['coursemoduleid'];
        },
    ],
    [
        'fn' => 'local_fivedays_update_quiz',
        'params' => fn($ctx) => ['quizid' => $ctx->quizid, 'name' => 'Contract quiz renamed'],
        'check' => $ok,
    ],
    [
        'fn' => 'local_fivedays_add_question_to_quiz',
        'params' => fn($ctx) => ['quizid' => $ctx->quizid, 'questionbankentryid' => $ctx->qbe['multichoice']],
        'check' => $ok,
        // The case covers the function; the other questions go in the same way so the quiz has slots to arrange.
        'save' => function ($r, $ctx) {
            $ctx->slotids = ['multichoice' => (int) $r['slotid']];
            foreach (array_diff(array_keys($ctx->qbe), ['multichoice']) as $type) {
                $added = contract_call('local_fivedays_add_question_to_quiz',
                    ['quizid' => $ctx->quizid, 'questionbankentryid' => $ctx->qbe[$type]]);
                if (empty($added['success'])) {
                    throw new coding_exception("adding {$type} failed: " . json_encode($added));
                }
                $ctx->slotids[$type] = (int) $added['slotid'];
            }
        },
    ],
    [
        'fn' => 'local_fivedays_add_quiz_section',
        'params' => fn($ctx) => ['quizid' => $ctx->quizid, 'slot' => 4, 'heading' => 'Part B'],
        'check' => $ok,
        // A third section lets both remove_quiz_section and delete_quiz_section succeed later.
        'save' => function ($r, $ctx) {
            $ctx->sectionb = (int) $r['sectionid'];
            $added = contract_call('local_fivedays_add_quiz_section', ['quizid' => $ctx->quizid, 'slot' => 7, 'heading' => 'Part C']);
            if (empty($added['success'])) {
                throw new coding_exception('adding Part C failed: ' . json_encode($added));
            }
            $ctx->sectionc = (int) $added['sectionid'];
        },
    ],
    [
        'fn' => 'local_fivedays_update_quiz_section',
        'params' => fn($ctx) => ['sectionid' => $ctx->sectionb, 'heading' => 'Part B written', 'shufflequestions' => 0],
        'check' => $ok,
    ],
    [
        'fn' => 'local_fivedays_reorder_quiz_questions',
        'params' => fn($ctx) => ['quizid' => $ctx->quizid, 'slots' => [
            ['slotid' => $slotid($ctx, 'truefalse'), 'newslot' => 1],
            ['slotid' => $slotid($ctx, 'multichoice'), 'newslot' => 2],
        ]],
        'check' => $ok,
    ],
    [
        'fn' => 'local_fivedays_get_quiz',
        'params' => fn($ctx) => ['quizid' => $ctx->quizid],
        'save' => function ($r, $ctx) {
            $ctx->sectiona = (int) $r['sections'][0]['id'];
        },
        'check' => fn($r) => count($r['sections']) === 3 && count($r['questions']) === 9 ? null : 'expected 3 sections and 9 questions',
    ],
    [
        'fn' => 'local_fivedays_set_quiz_structure',
        // The essay moves into the first section so it survives delete_quiz_section and can be graded.
        'params' => fn($ctx) => ['quizid' => $ctx->quizid, 'sections' => [
            ['sectionid' => $ctx->sectiona, 'slotids' => array_map(fn($t) => $slotid($ctx, $t), ['truefalse', 'multichoice', 'essay'])],
            ['sectionid' => $ctx->sectionb, 'slotids' => array_map(fn($t) => $slotid($ctx, $t), ['shortanswer', 'numerical', 'match'])],
            ['sectionid' => $ctx->sectionc, 'slotids' => array_map(fn($t) => $slotid($ctx, $t), ['gapselect', 'ddwtos', 'description'])],
        ]],
        'check' => fn($r) => ($r['slotcount'] ?? 0) === 9 && ($r['sectioncount'] ?? 0) === 3 ? null : 'structure not applied: ' . json_encode($r),
    ],
    [
        'fn' => 'local_fivedays_update_quiz_slot_mark',
        'params' => fn($ctx) => ['quizid' => $ctx->quizid, 'slot' => 3, 'maxmark' => 5.0],
        'check' => $ok,
    ],
    [
        'fn' => 'local_fivedays_remove_question_from_quiz',
        'params' => fn($ctx) => ['quizid' => $ctx->quizid, 'slot' => 9],
        'check' => $ok,
    ],
    [
        'fn' => 'local_fivedays_remove_quiz_section',
        'params' => fn($ctx) => ['sectionid' => $ctx->sectionc],
        'check' => $ok,
    ],
    [
        'fn' => 'local_fivedays_delete_quiz_section',
        'params' => fn($ctx) => ['sectionid' => $ctx->sectionb],
        'check' => fn($r) => ($r['deletedslots'] ?? 0) === 5 ? null : 'expected 5 deleted slots: ' . json_encode($r),
    ],
    [
        'fn' => 'mod_quiz_get_quizzes_by_courses',
        'params' => fn($ctx) => ['courseids' => [$ctx->courseid]],
        'check' => fn($r, $ctx) => in_array($ctx->quizid, array_map('intval', array_column($r['quizzes'], 'id')), true)
            ? null : 'quiz not returned',
    ],
    [
        'fn' => 'local_fivedays_get_quiz_attempts',
        // No function on the service starts an attempt, so the student's finished attempt is made in-process.
        'params' => function ($ctx) {
            global $CFG, $DB;
            require_once($CFG->dirroot . '/mod/quiz/locallib.php');
            \core\cron::setup_user($DB->get_record('user', ['id' => $ctx->studentid], '*', MUST_EXIST));
            $quizobj = \mod_quiz\quiz_settings::create($ctx->quizid, $ctx->studentid);
            $attempt = quiz_prepare_and_start_new_attempt($quizobj, 1, null, false, [], [], $ctx->studentid);
            $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
            $qa = $attemptobj->get_question_attempt(3);
            $attemptobj->process_submitted_actions(time(), false, [
                $qa->get_control_field_name('sequencecheck') => $qa->get_sequence_check_count(),
                $qa->get_qt_field_name('answer') => '<p>Contract essay answer</p>',
                $qa->get_qt_field_name('answerformat') => FORMAT_HTML,
            ]);
            $attemptobj->process_submit(time(), false);
            $attemptobj->process_grade_submission(time());
            \core\cron::setup_user();
            $ctx->attemptid = (int) $attempt->id;
            return ['quizid' => $ctx->quizid];
        },
        'check' => fn($r, $ctx) => in_array($ctx->attemptid, array_map('intval', array_column($r['attempts'], 'id')), true)
            ? null : 'attempt not listed',
    ],
    [
        'fn' => 'local_fivedays_get_quiz_attempt_details',
        'params' => fn($ctx) => ['attemptid' => $ctx->attemptid],
        'check' => fn($r) => ($r['attempt']['state'] ?? '') === 'finished' && count($r['attempt']['questions']) === 3
            ? null : 'expected a finished attempt with 3 questions',
    ],
    [
        'fn' => 'local_fivedays_grade_essay_question',
        'params' => fn($ctx) => ['attemptid' => $ctx->attemptid, 'slot' => 3, 'mark' => 4.0, 'comment' => 'Good'],
        'check' => $ok,
    ],
    [
        'fn' => 'local_fivedays_add_attempt_feedback',
        'params' => fn($ctx) => ['attemptid' => $ctx->attemptid, 'feedback' => '<p>Well done</p>'],
        'check' => $ok,
    ],
    [
        'fn' => 'local_fivedays_get_attempt_feedback',
        'params' => fn($ctx) => ['attemptid' => $ctx->attemptid],
        'check' => fn($r) => ($r['feedback'] ?? '') === '<p>Well done</p>' ? null : 'feedback not returned',
    ],
    [
        'fn' => 'local_fivedays_delete_question',
        'params' => fn($ctx) => ['questionbankentryid' => $ctx->qbe['description']],
        'check' => $ok,
    ],
    [
        'fn' => 'local_fivedays_delete_quiz',
        'params' => fn($ctx) => ['cmid' => $ctx->quizcmid],
        'check' => $ok,
    ],
];
