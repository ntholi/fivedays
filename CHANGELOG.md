# FiveDays changelog

Entries before 2026-10-22 are for `local_activity_utils`, which became `local_fivedays`.

## 2026-10-30 (2)

### Changed
- `local_fivedays` 2026103001: `add_announcement` posts as another user (`userid` other than the caller) only with the new manager-only capability `local/fivedays:addannouncementasuser`, so a lecturer token can no longer post as a colleague; the `fivedays_ws` role (Manager archetype) keeps it. The author needs an active enrolment. Attachments are kept only when both the caller and the author have `mod/forum:createattachment`. `timecreated` must be 0 or from 2000-01-01 up to now. `notify` now defaults to false (silent). No database change.

## 2026-10-30

### Added
- `local_fivedays` 2026103000: `add_announcement(courseid, subject, message, draftitemid, timecreated, userid, notify)` posts in the course's announcements forum (created with `forum_get_course_forum` when missing) as `userid`, who must be enrolled and hold `mod/forum:addnews`. `timecreated` keeps the original posted date on the post and discussion. `notify=0` stores the post with `mailed = FORUM_MAILED_SUCCESS`, so `mod_forum\task\cron_task` (the only sender of forum emails and notifications) skips it. New capability `local/fivedays:addannouncement` (editing teacher, manager). luct uses it to import Google Classroom announcements. No database change.

## 2026-10-28 (2)

### Added
- `local_fivedays` 2026102800: `search_rubrics(targetcmid, query, limit)` lists FiveDays rubrics (with at least one criterion) on any assignment site-wide, matching every query word against the assignment name, course full name or short name; rubrics in the target's own course come first, then newest. `preview_rubric(sourcecmid, targetcmid)` returns a source rubric's criteria and levels. Both only require `local/fivedays:managerubric` on the target, so a lecturer can reuse a rubric from a course they do not teach. luct imports by appending the previewed criteria through `create_rubric` / `update_rubric`. No database change.

## 2026-10-28

### Changed
- `just release` refuses to build when a plugin file other than its `README.md` or `tests/` changed after the plugin's last `$plugin->version` bump, or when a bump lowered the version. Without a bump Moodle never runs the plugin's upgrade, so new web-service functions, capabilities and tables would not install in prod.
- `just deploy` and `just rollback` end with `host/plugin-versions.php`: every plugin's `version.php` in the image must equal its installed version in `config_plugins`, else the command fails with `just rollback <host>`. It reads both directly, past the Moodle caches. No plugin version change.

## 2026-10-27 (2)

### Changed
- `local_fivedays` 2026102701: registry-web is now called luct. `cli/setup.php` names the `fivedays_ws` token `luct` and describes the `fivedays_ws` role as used by luct; on an existing site it renames the token and updates the role description (reported as `changed`). `bin/cutover-lib.sh` reads the app checkout from `LUCT_DIR` (default `~/dev/luct`). No database change.

## 2026-10-27

### Changed
- `local_fivedays` 2026102700: `delete_assignment` also deletes the groups and grouping made for that assignment (idnumbers `fivedays-a<assignmentid>-*` and `fivedays-assign-<assignmentid>`), so deleting a group assignment no longer leaves its students in orphan groups. Other course groups stay. The activity still goes to the recycle bin when it is on. No database change.

## 2026-10-24 (6)

### Changed
- `local_fivedays` 2026102406: `cli/setup.php` honours `MOODLE_PUSH_SOFT=1` (D30): the push access key and push check items print `WARN` instead of `FAIL` and don't change the exit code, and an exception while talking to the push server becomes a `WARN` too. `bin/cutover` sets it; everywhere else push problems still fail. No database change.

## 2026-10-24 (5)

### Changed
- `local_fivedays` 2026102405 deletes, hides and shows activities through `core_courseformat\local\cmactions` (`formatactions::cm($courseid)->delete()` and `->set_visibility()`) instead of `course_delete_module()` (deprecated in Moodle 5.2, removed in 6.0) and `set_coursemodule_visible()`. The calls behave as before: `delete_assignment`, `delete_book`, `delete_file`, `delete_forum`, `delete_page`, `delete_quiz`, `delete_url`, `delete_bigbluebuttonbn` and `delete_subsection` still delete at once (not in a background task), keep a recycle-bin copy when the course recycle bin is on, and fire `course_module_deleted`; the `visible` parameter of `update_page`, `update_file`, `update_quiz` and `update_bigbluebuttonbn`, and activities added to a hidden subsection, still set `visible` and `visibleold` and hide or show the grade items and calendar events. No database change.
- The grading-queue test finishes its quiz attempt with `process_submit()` and `process_grade_submission()` instead of the deprecated `process_finish()`.

## 2026-10-24 (4)

### Added
- `db/services.php` declares the FiveDays web service (`$services`, shortname `fivedays`): every `local_fivedays_*` function plus the 34 core functions the prod service already had. Moodle's upgrade now keeps the service in sync, so registry-web no longer adds functions to it.
- `cli/setup.php` sets the site up from the environment: the `fivedays_ws` web-service user with a Manager-archetype system role and its token, the Google issuer, the Redis application cache, and phone push on by default for every notification type (plus the push access key on `https://luct.click` when `MOODLE_PUSH=1`).

### Changed
- Version 2026102404 hands the existing `fivedays` service row (created at runtime, no component) over to `local_fivedays`. Its per-user tokens stay. The upgrade stops with an error if a `fivedays` service exists under another name or component.
- `provision_helper::ensure_rest_service()` is now `rest_service()`: it only loads the service and no longer creates it, renames it or changes web-service settings.
- The plugins live under `plugins/`.

### Fixed
Found by the new web-service contract test (`docker/contract`), which calls every FiveDays service function with the `fivedays_ws` token:
- `gradingform_fivedays` 2026102400: deleting any activity that supports advanced grading (assignment, forum) failed with `multiple_optigroup_duplicate_element` whenever the recycle bin was on, and course backup and activity duplication failed the same way. The backup reused core rubric's element names; they are now `fivedayscriteria`, `fivedayscriterion`, `fivedayslevels`, `fivedayslevel`, `fivedaysfillings` and `fivedaysfilling`, and restore reads those paths. No older backup can hold the old names, because every such backup failed.
- `create_draft_files` failed with "Call to undefined function file_get_unused_draft_itemid()": it now loads `lib/filelib.php`.
- `reorder_quiz_questions` saved the new order but answered "Database transaction error": its `slot_moved` events used `previousslot`/`afterslot` instead of `previousslotnumber`/`afterslotnumber`, and a failure after the commit tried to roll back the finished transaction. The events are now built correctly and fired after the commit.

## 2026-10-24 (3)

### Added
- `get_grading_queue(courseid)` lists the assignments and quizzes in a course that have work waiting to be graded: `type` (`assign` or `quiz`), `instanceid`, `cmid`, `name`, `waiting` (submissions Moodle counts as needing grading, or finished attempts with a question awaiting a manual mark) and `oldest` (when the oldest waiting item arrived, 0 when unknown). Activities with nothing waiting are left out, as are activities the caller cannot grade. Needs the new course capability `local/fivedays:viewgradingqueue`, given to teachers, editing teachers and managers.

## 2026-10-24 (2)

### Added
- Quiz section calls. Each returns `success` and `message`; a refused change returns `success` false with the reason instead of throwing. All need `local/fivedays:managequizquestions` and `mod/quiz:manage`.
- `add_quiz_section(quizid, slot, heading)` starts a section at a question slot and returns `sectionid` and `firstslot`. Slot 1 and a slot that already starts a section are refused. If the slot is not first on its page, the page is split first.
- `update_quiz_section(sectionid, heading, shufflequestions)` changes either or both. It works after students have attempted the quiz, like Moodle's own heading and shuffle edits. An empty heading is allowed only for the first section.
- `remove_quiz_section(sectionid)` removes only the heading, so the questions join the section above. The first section cannot be removed.
- `delete_quiz_section(sectionid)` deletes the section and all its questions in one transaction and returns `deletedslots`. Deleting the first section moves the next section's heading and shuffle setting onto it. A quiz with one section is refused.
- `set_quiz_structure(quizid, sections)` takes every section with its slot ids in the wanted order and renumbers slots, pages and section start slots in one transaction. The first listed section must stay the one that starts at slot 1.
- Every call that changes the structure (all except `update_quiz_section`) is refused once the quiz has attempts.

## 2026-10-24

### Added
- `create_match_question`, `create_description_question`, `create_gapselect_question` and `create_ddwtos_question` create matching, description, select missing words and drag and drop into text questions. Each accepts an optional `questionbankentryid` to save a new version, like the other `create_*_question` functions. Invalid pairs, choices or gaps throw `invalidquestiondata` with the reason. A description always has mark 0.
- `get_quiz` returns `options` for these types: `shuffleanswers` and the combined feedback, plus `subquestions` (match) or `choices` with `choicegroup` and, for ddwtos, `infinite`.

### Fixed
- `add_question_to_quiz` gives a description slot mark 0 and `update_quiz_slot_mark` refuses a non-zero mark for one.
- `get_quiz_attempt_details` reports the finished state of a description as `complete` instead of `unknown`.
- `delete_question` also removes the options rows of match, gapselect and ddwtos questions.

## 2026-10-23 (3)

### Changed
- `sync_assignment_groups` no longer creates a group with no members enrolled in the assignment's course, and removes ones it made before. The app sends a module's groups to every class course, so each course now only shows its own class's groups.

## 2026-10-23 (2)

### Changed
- `get_assignment_teams` also returns `courseid` and `duedate` (the assignment's base due date, 0 when none; user and group overrides are ignored). The app uses them to stop group leaders changing members after the due date.

## 2026-10-23

### Added
- `get_rubric` returns `gradedcount` on every criterion: how many students have an active assessment that filled that criterion. The app uses it to ask for a typed confirmation before deleting an assessed criterion.
- `get_assignment_teams` returns whether an assignment uses team submission, `canchangemode` (no submissions or grades yet, Moodle's own rule for freezing team submission), and the groups of its team grouping with their members and `submitted` flag. Requires `local/fivedays:viewassignment` and `mod/assign:view`.
- `sync_assignment_groups` keeps an assignment's groups in step with the app: one grouping `fivedays-assign-<id>`, one group per app group (`fivedays-a<id>-<key>`) and a one-person group for every other submitter. It reconciles names and members, deletes stale groups, and returns the users it skipped because they are not enrolled. The wanted mode (`enabled`) is applied only while the assignment has no submissions or grades; otherwise the current mode stays and `locked` is true. While kept on only because of existing work, the groups stay as they are and only new submitters get one-person groups. A group that has submitted keeps its members and is never deleted (`groupsubmitted`); an app group with exactly its members takes it over, so a re-created app group still works. Team mode sets `preventsubmissionnotingroup=1`, so nobody submits into Moodle's shared default group. Requires `local/fivedays:provision` and `moodle/course:managegroups`.
- `save_assignment_grade` and `fill_rubric` take optional `alsouserids`: the same grade (or rubric fillings, each in its own grading instance) is saved for those enrolled students in the same transaction. Both return `applieduserids`.

### Fixed
- Team submission: `get_assignment_grades` and release list each member's latest grade (members have no submission of their own), and assignment comments use the group submission so members share one thread.

## 2026-10-22 (2)

### Removed
- The one-time migration from `local_activity_utils` (`db/install.php` and `clonepermissionsfrom` in `db/access.php`), the fallback to the old `registry_lms` service, and the unused `fivedays` string. Production has switched, so a new install is clean.

## 2026-10-22

### Changed
- `local_activity_utils` is now `local_fivedays` (shown in Moodle as "FiveDays"). Web-service functions are `local_fivedays_*`, capabilities `local/fivedays:*`, and capability names read "… via FiveDays".
- Quiz attempt feedback is stored in `local_fivedays_quizfeedback` (was `quiz_attempt_feedback`).
- `local_fivedays` and `gradingform_fivedays` now live in one repo, `ntholi/fivedays`.

## 2026-10-15

### Added
- `delete_course` deletes a course and everything in it with `delete_course()`, without checking for content. It fails with `cannotdeletesitecourse` for the site course and `coursenotfound` for a missing course. Requires `local/activity_utils:provision` and `moodle/course:delete` on the course. The app uses it to retire duplicate class courses.

## 2026-10-13 (3)

### Changed
- `get_assignment`, `get_quiz` and `get_quiz_attempts` fail with error code `activitynotfound` when the activity or its course module was deleted. Moodle 5.2 reports a missing record without naming the table, so the app could not tell a deleted activity from other errors.

## 2026-10-13 (2)

### Added
- `get_assignment` returns an assignment's course module and course by instance id. Unlike `mod_assign_get_assignments`, it does not need the caller to be enrolled, so the system token can find a linked assignment in any class.

## 2026-10-13

### Added
- `get_draft_files` returns the calling user's draft-area files (base64) for a draft item id, so the app can send an upload as `introfiles` to classes it writes with the system token.
- `create_draft_files` saves base64 files into the calling user's draft area and returns the draft item id, so the system token can attach files to `mod_forum_add_discussion` without `webservice/upload.php`.

## 2026-10-12 (2)

### Fixed
- Quizzes: `update_quiz` uses `set_coursemodule_visible` (grade item and events follow visibility), rescales grades on a max-grade change, recomputes on a grade-method change, and refreshes events/open attempts on date or time-limit changes. Visibility/idnumber/grade-only updates are no longer reported as "no changes".
- `update_quiz_slot_mark` regrades existing attempts when the mark changes; `grade_essay_question` recomputes the final grade so the gradebook updates.
- `add_question_to_quiz` checks `question:use` on the question and that the quiz can still be edited; `get_or_create_category` checks `managecategory` on the parent's context.
- `save_question_version` takes the next version from `core_question\versions`.
- `get_quiz_attempt_details` returns the teacher comment as `comment`; `feedback` is general feedback only.
- Rubric: `fill_rubric` grades through the grading instance (`submit_and_get_grade`), so only one active instance per student; validates score ranges; returns `releasestate` on every failure; creates the empty submission like typed grades do. `update_rubric` marks existing gradings for regrade when the rubric changes.
- Assignments: `save_assignment_grade` fails when Moodle rejects the grade. Turning on marking workflow keeps already-visible grades released.
- BigBlueButton: `create_bigbluebuttonbn` goes through `add_moduleinfo` (owner log, calendar event, grade item); update refreshes calendar/grade item; closing time must be after opening time.
- Provisioning: existing users are found by email case-insensitively; usernames that collide get a numeric suffix.

### Requires
- `gradingform_fivedays` 2026101200.

## 2026-10-12

### Added
- `update_quiz_slot_mark` changes a quiz slot's maximum mark through `structure::update_slot_maxmark()` and recomputes the quiz sum of grades.
- `get_quiz` returns each numerical answer's `tolerance` and the essay options (`responseformat`, `responserequired`, `responsefieldlines`, `minwordlimit`, `maxwordlimit`, `attachments`, `attachmentsrequired`, `graderinfo`, `responsetemplate`), so a question can be edited without losing its settings.
- `create_multichoice_question`, `create_truefalse_question`, `create_shortanswer_question`, `create_essay_question` and `create_numerical_question` accept an optional `questionbankentryid`. When set, the question is saved as a new version of that entry, so quiz slots that use the latest version pick it up and earlier attempts keep the version they were taken on.

## 2026-09-04

### Added
- `create_file` / `update_file` accept an optional `draftitemid` from `webservice/upload.php` and save it through Moodle's resource draft area.
- `create_assignment` / `update_assignment` accept an optional `introattachments` draft item id. Update replaces the intro attachment area when that id is set.

## 2026-08-26

### Fixed
- Quiz question removal now uses Moodle's `structure::remove_slot()` so the `slot_deleted` event includes `questionreferenceid`.

## 2026-05-28

### Fixed
- Corrected PHPUnit imports for subsection/activity tests so they reference the real external API namespaces.
- Replaced manual course section sequence editing in the shared module-placement helper with Moodle's `course_add_cm_to_section()` API.
- Ensured course modules added through the helper are linked to the correct `course_sections.id` instead of relying on section numbers.
- Refactored subsection creation to use Moodle's module creation flow for `mod_subsection`, preserving the existing API response while letting Moodle create the subsection instance, course module, delegated section, events, and caches.
- Updated subsection tests to assert delegated-section metadata against the subsection instance id and parent course section id.
- Fixed quiz attempt read services so optional response fields are omitted instead of returned as `null`.
- Changed page and file update permissions to require existing-activity management rights instead of create-instance rights, and routed visibility changes through Moodle's visibility API.
- Wrapped file resource create/update operations in delegated transactions to avoid orphaned modules or missing files after partial failures.
- Wrapped subsection creation and delegated-section summary updates in one transaction.
- Wrapped book chapter add/update writes in transactions.
- Refactored page, URL, book, forum, and file creation to use Moodle's `add_moduleinfo()` lifecycle instead of direct `{course_modules}` inserts.
- Refactored assignment creation to use Moodle's `assign_add_instance()` lifecycle through `add_moduleinfo()`, preserving file-submission plugin settings and intro attachments.
- Refactored quiz creation to use Moodle's `quiz_add_instance()` lifecycle through `add_moduleinfo()`, including review bitmask conversion to Moodle's expected form-style review options.

### Known follow-up
- BigBlueButton creation still needs a dedicated `add_moduleinfo()` lifecycle refactor because it has module-specific meeting defaults.
