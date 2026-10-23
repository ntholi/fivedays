# FiveDays changelog

Entries before 2026-10-22 are for `local_activity_utils`, which became `local_fivedays`.

## 2026-10-23

### Added
- `get_rubric` returns `gradedcount` on every criterion: how many students have an active assessment that filled that criterion. The app uses it to ask for a typed confirmation before deleting an assessed criterion.
- `get_assignment_teams` returns whether an assignment uses team submission, whether it has submissions (any status other than `new`), and the groups of its team grouping with their members. Requires `local/fivedays:viewassignment` and `mod/assign:view`.
- `sync_assignment_groups` turns team submission on or off for an assignment and keeps its groups in step with the app: one grouping `fivedays-assign-<id>`, one group per app group (`fivedays-a<id>-<key>`) and a one-person group for every other submitter. It reconciles names and members, deletes stale groups, and returns the users it skipped because they are not enrolled. Changing the mode once the assignment has submissions fails with `assignmenthassubmissions`. Requires `local/fivedays:provision` and `moodle/course:managegroups`.
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
