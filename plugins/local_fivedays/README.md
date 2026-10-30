# FiveDays (local_fivedays)

REST API endpoints for programmatic Moodle course content management.

**Version:** 5.3 | **Requirements:** Moodle 5.0+, gradingform_fivedays plugin | **Developed for:** Limkokwing University

> **Important Moodle 5.0 Change:** Question categories are now created in the course's system question bank (`mod_qbank`) using module context, not course context. This is handled automatically by the plugin.

## Features

Web service functions:

- **Sections** (6): create, update, delete sections and subsections
- **Assignments** (6): create, update, delete, get grades, save grade, release grades
- **Pages** (3): create, update, delete
- **Files** (3): create, update, delete
- **URLs** (3): create, update, delete
- **Books** (6): create, update, delete, add/update chapters, get
- **Rubrics** (7): create, get, update, delete, copy, fill (grade), get filling - **uses FiveDays grading form**
- **BigBlueButton** (3): create, update, delete
- **Forums** (3): create, delete, add announcement
- **Quizzes** (12): create, update, delete, get, add/remove/reorder questions, add/update/remove/delete sections, set structure
- **Quiz Attempts** (5): get attempts, get attempt details, grade essay, add/get feedback
- **Question Categories** (2): get/create, list
- **Questions** (7): create multichoice/truefalse/shortanswer/essay/numerical, get, delete

## Dependencies

This plugin requires the **gradingform_fivedays** plugin to be installed for rubric functionality. Install it from `moodle-gradingform_fivedays` before installing this plugin.

## Setup

The Docker stack in this repo installs the plugin and runs `cli/setup.php` after every install or upgrade. Nothing is set up by hand:

- `db/services.php` declares the **FiveDays** web service (shortname `fivedays`) with every `local_fivedays_*` function plus the core functions the prod service already had. Moodle's upgrade keeps the service's function list equal to it. Version 2026102404 hands the older, runtime-created service row over to the plugin, keeping all its per-user tokens.
- `cli/setup.php` (idempotent, prints `ok` / `changed` / `FAIL` per item, exits 1 on any `FAIL`):
  - creates the `fivedays_ws` user with a system role copied from the Manager archetype plus `webservice/rest:use`, and prints its token once (luct's `MOODLE_TOKEN`);
  - creates or updates the Google OAuth issuer from `MOODLE_GOOGLE_CLIENT_ID` / `MOODLE_GOOGLE_CLIENT_SECRET`;
  - adds the Redis store `fivedays_redis` and maps the application cache to it;
  - turns on phone push (`airnotifier`) and adds it to every notification type's default outputs, never touching locked defaults or users' own preferences;
  - with `MOODLE_PUSH=1` on `https://luct.click`: requests the push access key and runs the push configuration check. With `MOODLE_PUSH_SOFT=1` (set by `bin/cutover`, D30) push problems print `WARN` and don't fail the run.

## API Usage

```
POST https://yourmoodle.com/webservice/rest/server.php
```

| Parameter            | Value          |
| -------------------- | -------------- |
| `wstoken`            | Your API token |
| `wsfunction`         | Function name  |
| `moodlewsrestformat` | `json`         |

**Important:** Boolean parameters must be passed as integers (`0` = false, `1` = true) when calling via REST API, not JSON booleans.

**Deleting and hiding activities:** every `delete_*` call that removes an activity, and the `visible` parameter of `update_page`, `update_file`, `update_quiz` and `update_bigbluebuttonbn`, go through Moodle's `core_courseformat\local\cmactions` (`delete()` and `set_visibility()`). A delete finishes before the call returns (it is not queued as a background task); when the course recycle bin is on, a copy is kept there first. Hiding or showing an activity also hides or shows its grade items and calendar events.

---

## Sections

### Create Section

`local_fivedays_create_section`

| Parameter    | Type   | Required |
| ------------ | ------ | -------- |
| `courseid`   | int    | Yes      |
| `name`       | string | No       |
| `summary`    | string | No       |
| `sectionnum` | int    | No       |

### Create Subsection

`local_fivedays_create_subsection`

| Parameter       | Type   | Required |
| --------------- | ------ | -------- |
| `courseid`      | int    | Yes      |
| `parentsection` | int    | Yes      |
| `name`          | string | Yes      |
| `summary`       | string | No       |
| `visible`       | int    | No       |

**Note:** Use returned `sectionnum` when adding activities to the subsection.

### Update Section/Subsection

`local_fivedays_update_section` / `local_fivedays_update_subsection`

Parameters: `sectionid` (required), `name`, `summary`, `visible`

### Delete Section

`local_fivedays_delete_section`

| Parameter    | Type | Required | Description              |
| ------------ | ---- | -------- | ------------------------ |
| `courseid`   | int  | Yes      | Course ID                |
| `sectionnum` | int  | Yes      | Section number to delete |

**Note:** Cannot delete section 0 (general section). Deletes all content within the section.

### Delete Subsection

`local_fivedays_delete_subsection`

Parameters: `cmid` (course module ID)

---

## Assignments

### Create Assignment

`local_fivedays_create_assignment`

| Parameter                  | Type   | Required | Description         |
| -------------------------- | ------ | -------- | ------------------- |
| `courseid`                 | int    | Yes      |                     |
| `name`                     | string | Yes      |                     |
| `intro`                    | string | No       | Description (HTML)  |
| `activity`                 | string | No       | Instructions (HTML) |
| `allowsubmissionsfromdate` | int    | No       | Unix timestamp      |
| `duedate`                  | int    | No       | Unix timestamp      |
| `cutoffdate`               | int    | No       | Defaults to `duedate` (closes submissions) |
| `section`                  | int    | No       | Default: 0          |
| `idnumber`                 | string | No       | Gradebook ID        |
| `grademax`                 | int    | No       | Default: 100        |
| `introfiles`               | string | No       | JSON array (base64) |
| `visible`                  | int    | No       | Default: 1          |

**Note:** By default, assignments use simple direct grading and marking workflow (grades stay hidden until released). Add a rubric using `create_rubric` to switch to FiveDays grading.

### Update Assignment

`local_fivedays_update_assignment`

Parameters: `assignmentid` (required), `name`, `intro`, `activity`, `allowsubmissionsfromdate`, `duedate`, `cutoffdate`, `idnumber`, `grademax`, `introfiles` (JSON array base64; when sent, replaces all intro attachments), `visible`

### Delete Assignment

`local_fivedays_delete_assignment`

Parameters: `cmid` (course module ID)

### Get Assignment Grades

`local_fivedays_get_assignment_grades`

| Parameter      | Type | Required |
| -------------- | ---- | -------- |
| `assignmentid` | int  | Yes      |

Each grade includes `userid`, `grade` (`-1` if none), and `releasestate`: `notgraded`, `notreleased`, `released`, or `beingedited`.

### Save Assignment Grade

`local_fivedays_save_assignment_grade`

Saves or overrides the overall mark. Does not release it. Changing a released grade sets `beingedited` (hidden again).

| Parameter      | Type  | Required |
| -------------- | ----- | -------- |
| `assignmentid` | int   | Yes      |
| `userid`       | int   | Yes      |
| `grade`        | float | Yes      |
| `alsouserids`  | int[] | No       |

`alsouserids` get the same grade in the same transaction (group members); those not enrolled are skipped. Returns `grade`, `releasestate`, `applieduserids` (the primary user and every also-user graded), `success`, `message`.

### Group Assignments (Team Submission)

`local_fivedays_get_assignment_teams` — `assignmentid`. Returns `teamsubmission`, `canchangemode` (no submissions or grades yet) and `teams[{ groupid, name, submitted, userids[] }]`, the groups of the assignment's team grouping (empty when team submission is off).  `submitted` means the group submission moved past `new`. Needs `local/fivedays:viewassignment` and `mod/assign:view`.

`local_fivedays_sync_assignment_groups` — `assignmentid`, `enabled`, `groups[{ key, name, userids[] }]` (default `[]`). Needs `local/fivedays:provision` and `moodle/course:managegroups`.

- The mode follows `enabled` only while the assignment has no submissions or grades (core's `has_submissions_or_grades`); otherwise the current mode stays and `locked` is returned true.
- Team mode: keeps the grouping `fivedays-assign-<id>` with one group per given group (idnumber `fivedays-a<id>-<key>`, name `<name> · <assignment>`) plus a one-person group (`u<userid>`) for every enrolled submitter not listed. Names and members are reconciled and stale `fivedays-a<id>-*` groups deleted. Sets `teamsubmission=1`, `teamsubmissiongroupingid`, `preventsubmissionnotingroup=1`, `requireallteammemberssubmit=0`.
- Individual mode: team submission off; the grouping and its `fivedays-a<id>-*` groups are deleted.
- Team mode kept on although `enabled=false`: the groups stay as they are; only submitters in no group get a one-person group.
- A group whose submission moved past `new` must keep exactly its members: some wanted group (app group or one-person group) must have the same members, and that group then takes over the existing Moodle group (idnumber and name), so its submission stays visible. Otherwise the call fails with `groupsubmitted` and changes nothing. A submitted group with no members left is ignored. A user listed in two groups fails with an invalid parameter error.
- Returns `teamsubmission`, `groups` (groups now in the grouping), `skipped` (listed users not enrolled in the course) and `locked`.

In team mode, grades are listed per member from their latest grade, and assignment comments use the group submission so members share one thread.

### Release Assignment Grades

`local_fivedays_release_assignment_grades`

Releases unpublished grades (`notreleased` and `beingedited`) for the given user IDs so those students can see them. Already released or ungraded students in the list are skipped. Optional comments are written as Moodle feedback comments only when non-empty.

| Parameter      | Type                         | Required |
| -------------- | ---------------------------- | -------- |
| `assignmentid` | int                          | Yes      |
| `userids`      | int[]                        | Yes      |
| `comments`     | `{ userid, comment }[]`      | No       |

---

## Pages

### Create Page

`local_fivedays_create_page`

| Parameter  | Type   | Required |
| ---------- | ------ | -------- |
| `courseid` | int    | Yes      |
| `name`     | string | Yes      |
| `intro`    | string | No       |
| `content`  | string | No       |
| `section`  | int    | No       |
| `visible`  | int    | No       |

### Update Page

`local_fivedays_update_page`

Parameters: `pageid` (required), `name`, `intro`, `content`, `visible`

### Delete Page

`local_fivedays_delete_page`

Parameters: `cmid` (course module ID)

---

## Files

### Create File

`local_fivedays_create_file`

| Parameter     | Type   | Required     |
| ------------- | ------ | ------------ |
| `courseid`    | int    | Yes          |
| `name`        | string | Yes          |
| `intro`       | string | No           |
| `filename`    | string | Yes          |
| `filecontent` | string | Yes (base64) |
| `section`     | int    | No           |
| `visible`     | int    | No           |

### Update File

`local_fivedays_update_file`

Parameters: `resourceid` (required), `name`, `intro`, `filename`, `filecontent`, `visible`

### Delete File

`local_fivedays_delete_file`

Parameters: `cmid` (course module ID)

---

## Folders

### Create Folder

`local_fivedays_create_folder`

Parameters: `courseid`, `name`, `draftitemid` (required, must hold at least one file), `section` (default 0), `visible` (default 1)

Creates a folder shown on its own page, sub-folders expanded, with a "Download folder" button. The draft files are copied into the folder. Returns `{id, coursemoduleid}`.

### Update Folder

`local_fivedays_update_folder`

Parameters: `folderid`, `name` (required), `draftitemid` (0 = no new files), `removefiles` (array of `{filepath, filename}`)

In one transaction: renames the folder, deletes the listed files (missing ones are skipped), copies in the draft files (a file with the same path and name replaces the existing one), bumps `revision` and fires `folder_updated`. Returns `{id, coursemoduleid, success, message}`.

---

## Reusing Activities

Both need `local/fivedays:provision` at system level (the `fivedays_ws` token).

### Get Activities Status

`local_fivedays_get_activities_status`

Parameters: `cmids` (array of course module IDs)

Returns `{activities: [{cmid, exists, courseid, modname, timemodified}]}`, one entry per requested id in input order. A missing activity, or one being deleted, has `exists = false`, `courseid = 0`, `modname = ''` and `timemodified = 0`. `timemodified` is the newest of the cm's `added`, the instance's `timemodified`, any book chapter's `timemodified` and any file's `timemodified` in the module context.

### Copy Activities

`local_fivedays_copy_activities`

Parameters: `courseid` (target course), `sectionname`, `sectionsummary` (default `''`), `items` (array of `{cmid, reusekey}`, in order; `reusekey` is the caller's id for the material, 1 to 64 characters)

Safe to retry: the plugin remembers the copy it made for each `(courseid, reusekey)` in `local_fivedays_reuse_copies`. When that copy still exists in the course, the item is not copied again and returns its `cmid` with `existing = true`. When it was deleted, the record is dropped and the activity is copied again. Records go with their course when it is deleted.

Otherwise the activity is copied with Moodle's single-activity import backup and restore (settings and files, no user data, same name) to the end of the first regular section named `sectionname`, which is created at the end of the course with `sectionsummary` when missing. Each item is handled on its own: a failure is reported and the rest continue. A copy that cannot be recorded is deleted again and reported as an error. Returns `{copies: [{sourcecmid, cmid, error, existing}]}`, with `cmid = 0` and a short `error` on failure. A section is only created when something is copied.

---

## URLs

### Create URL

`local_fivedays_create_url`

| Parameter     | Type   | Required | Description                               |
| ------------- | ------ | -------- | ----------------------------------------- |
| `courseid`    | int    | Yes      |                                           |
| `name`        | string | Yes      |                                           |
| `externalurl` | string | Yes      | The external URL                          |
| `intro`       | string | No       | Description (HTML)                        |
| `section`     | int    | No       | Default: 0                                |
| `visible`     | int    | No       | Default: 1                                |
| `display`     | int    | No       | 0=auto, 1=embed, 2=frame, 5=open, 6=popup |

### Update URL

`local_fivedays_update_url`

Parameters: `urlid` (required), `name`, `externalurl`, `intro`, `display`, `visible`

### Delete URL

`local_fivedays_delete_url`

Parameters: `cmid` (course module ID)

---

## Books

### Create Book

`local_fivedays_create_book`

| Parameter      | Type   | Required | Description                              |
| -------------- | ------ | -------- | ---------------------------------------- |
| `courseid`     | int    | Yes      |                                          |
| `name`         | string | Yes      |                                          |
| `intro`        | string | No       |                                          |
| `section`      | int    | No       |                                          |
| `visible`      | int    | No       |                                          |
| `numbering`    | int    | No       | 0=none, 1=numbers, 2=bullets, 3=indented |
| `navstyle`     | int    | No       | 0=none, 1=images, 2=text                 |
| `customtitles` | int    | No       |                                          |
| `chapters`     | array  | No       | Chapter objects                          |

**Chapter object:** `title` (required), `content`, `subchapter` (0=main, 1=sub), `hidden`, `tags`

### Add Book Chapter

`local_fivedays_add_book_chapter`

Parameters: `bookid` (required), `title` (required), `content`, `subchapter`, `hidden`, `pagenum`, `tags`

### Get Book

`local_fivedays_get_book`

Parameters: `bookid`

### Update Book

`local_fivedays_update_book`

Parameters: `bookid` (required), `name`, `intro`, `numbering`, `navstyle`, `customtitles`, `visible`

### Update Book Chapter

`local_fivedays_update_book_chapter`

Parameters: `chapterid` (required), `title`, `content`, `subchapter`, `hidden`, `tags`

### Save Book

`local_fivedays_save_book`

Parameters: `bookid`, `name`, `chapters` (array of `{id, title, content, subchapter}`, all required except `id` = 0 and `subchapter` = 0)

Saves the whole book in one transaction. Array order is the book order (`pagenum` 1..N). Chapters with an `id` of this book are updated, `id` = 0 adds a chapter, chapters of the book not listed are deleted with their files and tags. Refuses an empty list, a first chapter that is a sub-chapter, and an `id` from another book or listed twice. Renames the book, bumps `revision`, fires `chapter_created` / `chapter_updated` / `chapter_deleted`. Returns `{id, coursemoduleid, chapters: [{id, pagenum, title, subchapter}], success, message}`.

`get_book` returns chapter `content` raw (`@@PLUGINFILE@@` links not rewritten), so it can be edited and sent back as is.

### Delete Book

`local_fivedays_delete_book`

Parameters: `cmid` (course module ID)

---

## Rubrics (FiveDays Grading Form)

Advanced rubric management using the FiveDays grading form plugin. This provides flexible scoring with score ranges and custom scores.

### Grading Method Behavior

| Action | Grading Method |
|--------|---------------|
| New assignment created | Simple direct grading (default) |
| `create_rubric` called | FiveDays advanced grading |
| `delete_rubric` called | Simple direct grading (reverts) |
| `copy_rubric` to target | Target uses FiveDays grading |

### Create Rubric

`local_fivedays_create_rubric`

Creates a FiveDays rubric for an assignment, switching it from simple direct grading to FiveDays advanced grading.

| Parameter     | Type   | Required |
| ------------- | ------ | -------- |
| `cmid`        | int    | Yes      |
| `name`        | string | Yes      |
| `description` | string | No       |
| `criteria`    | array  | Yes      |
| `options`     | object | No       |

**Criterion:** `description` (required), `sortorder`, `levels` (required)

**Level:** `score` (required), `definition` (required)

**Options:**
- `sortlevelsasc` - Sort levels ascending (0 or 1, default: 1)
- `lockzeropoints` - Lock zero points (0 or 1, default: 1)
- `showdescriptionstudent` - Show description to student (default: 1)
- `showdescriptionteacher` - Show description to teacher (default: 1)
- `showscoreteacher` - Show score to teacher (default: 1)
- `showscorestudent` - Show score to student (default: 1)
- `showrangeteacher` - Show score ranges to teacher (default: 1)
- `showrangestudent` - Show score ranges to student (default: 1)
- `enableremarks` - Enable criterion remarks (default: 1)
- `showremarksstudent` - Show remarks to student (default: 1)
- `alwaysshowdefinition` - Always show rubric definition (default: 1)

**Example:**

```json
{
  "cmid": 123,
  "name": "Essay Rubric",
  "description": "Rubric for grading essays",
  "criteria": [
    {
      "description": "Content Quality",
      "sortorder": 1,
      "levels": [
        { "score": 0, "definition": "Poor - Missing key content" },
        { "score": 5, "definition": "Fair - Some content present" },
        { "score": 10, "definition": "Good - Most content covered" },
        { "score": 15, "definition": "Excellent - Comprehensive content" }
      ]
    },
    {
      "description": "Writing Style",
      "sortorder": 2,
      "levels": [
        { "score": 0, "definition": "Poor grammar and structure" },
        { "score": 5, "definition": "Acceptable writing" },
        { "score": 10, "definition": "Well-written and clear" }
      ]
    }
  ],
  "options": {
    "showrangeteacher": 1,
    "showrangestudent": 1,
    "enableremarks": 1
  }
}
```

**Response:**

```json
{
  "definitionid": 456,
  "success": true,
  "message": "FiveDays rubric created successfully. Assignment now uses FiveDays grading."
}
```

### Get Rubric

`local_fivedays_get_rubric`

Returns the rubric definition and current grading method for an assignment.

Parameters: `cmid`

**Response:**

```json
{
  "definitionid": 456,
  "name": "Essay Rubric",
  "description": "Rubric for grading essays",
  "status": 20,
  "criteria": [
    {
      "id": 1,
      "description": "Content Quality",
      "sortorder": 1,
      "levels": [
        { "id": 1, "score": 0, "definition": "Poor" },
        { "id": 2, "score": 5, "definition": "Fair" },
        { "id": 3, "score": 10, "definition": "Good" },
        { "id": 4, "score": 15, "definition": "Excellent" }
      ]
    }
  ],
  "options": {
    "sortlevelsasc": 1,
    "lockzeropoints": 1,
    "showdescriptionstudent": 1,
    "showdescriptionteacher": 1,
    "showscoreteacher": 1,
    "showscorestudent": 1,
    "showrangeteacher": 1,
    "showrangestudent": 1,
    "enableremarks": 1,
    "showremarksstudent": 1,
    "alwaysshowdefinition": 1
  },
  "maxscore": 25.0,
  "gradingmethod": "fivedays",
  "success": true,
  "message": "FiveDays rubric retrieved successfully"
}
```

**Grading Method Values:**
- `simple` - Assignment uses simple direct grading (no rubric)
- `fivedays` - Assignment uses FiveDays rubric
- Other values indicate different grading plugins

### Update Rubric

`local_fivedays_update_rubric`

Parameters: `cmid` (required), `name`, `description`, `criteria`, `options`

**Note:** If `criteria` is provided, it replaces all existing criteria. If not provided, existing criteria are preserved.

### Delete Rubric

`local_fivedays_delete_rubric`

Deletes the FiveDays rubric and reverts the assignment to simple direct grading.

Parameters: `cmid`

**Response:**

```json
{
  "success": true,
  "message": "FiveDays rubric deleted successfully, assignment now uses simple direct grading"
}
```

### Copy Rubric

`local_fivedays_copy_rubric`

Copies a FiveDays rubric from one assignment to another. The target assignment will switch to FiveDays grading.

Parameters: `sourcecmid`, `targetcmid`

**Response:**

```json
{
  "definitionid": 789,
  "success": true,
  "message": "FiveDays rubric copied successfully. Target assignment now uses FiveDays grading."
}
```

### Fill Rubric (Grade Student)

`local_fivedays_fill_rubric`

Grade a student's assignment submission using the FiveDays rubric. Supports both level selection and custom scores.

| Parameter       | Type   | Required | Description                         |
| --------------- | ------ | -------- | ----------------------------------- |
| `cmid`          | int    | Yes      | Course module ID of the assignment  |
| `userid`        | int    | Yes      | User ID of the student being graded |
| `fillings`      | array  | Yes      | Array of rubric fillings            |
| `overallremark` | string | No       | Overall feedback/remark             |
| `alsouserids`   | int[]  | No       | Other students who get the same fillings and grade (each their own grading instance); those not enrolled are skipped |

The response includes `applieduserids`: the primary user and every also-user graded.

**Filling object:**
- `criterionid` (int, required): Criterion ID
- `levelid` (int, optional): Level ID to select
- `score` (float, optional): Custom score (overrides levelid if provided)
- `remark` (string, optional): Feedback for this criterion

**Example with level selection:**

```json
{
  "cmid": 123,
  "userid": 456,
  "fillings": [
    {
      "criterionid": 1,
      "levelid": 3,
      "remark": "Good content coverage"
    },
    {
      "criterionid": 2,
      "levelid": 2,
      "remark": "Writing could be improved"
    }
  ],
  "overallremark": "Overall good submission"
}
```

**Example with custom scores:**

```json
{
  "cmid": 123,
  "userid": 456,
  "fillings": [
    {
      "criterionid": 1,
      "score": 12.5,
      "remark": "Between Good and Excellent"
    },
    {
      "criterionid": 2,
      "score": 7.0,
      "remark": "Above acceptable level"
    }
  ],
  "overallremark": "Custom scores used for precise grading"
}
```

**Response:**

```json
{
  "instanceid": 789,
  "grade": 85.5,
  "success": true,
  "message": "FiveDays rubric filled and grade saved successfully"
}
```

### Get Rubric Filling

`local_fivedays_get_rubric_filling`

Retrieve how a teacher graded a student using the FiveDays rubric.

| Parameter | Type | Required |
| --------- | ---- | -------- |
| `cmid`    | int  | Yes      |
| `userid`  | int  | Yes      |

**Response:**

```json
{
  "instanceid": 789,
  "grade": 85.5,
  "grader": "John Teacher",
  "graderid": 10,
  "timecreated": 1701234567,
  "timemodified": 1701234890,
  "fillings": [
    {
      "criterionid": 1,
      "criteriondescription": "Content Quality",
      "levelid": 3,
      "level": {
        "id": 3,
        "score": 10.0,
        "definition": "Good - Most content covered"
      },
      "customscore": null,
      "remark": "Good content coverage"
    },
    {
      "criterionid": 2,
      "criteriondescription": "Writing Style",
      "levelid": 0,
      "level": null,
      "customscore": 7.0,
      "remark": "Above acceptable level"
    }
  ],
  "success": true,
  "message": "FiveDays rubric filling retrieved successfully"
}
```

**Note:** When `customscore` is set, it overrides the level score. When `levelid` is 0 and `customscore` is set, a custom score was entered without selecting a level.

---

## BigBlueButton

### Create BigBlueButton

`local_fivedays_create_bigbluebuttonbn`

| Parameter                       | Type   | Required | Description                                       |
| ------------------------------- | ------ | -------- | ------------------------------------------------- |
| `courseid`                      | int    | Yes      |                                                   |
| `name`                          | string | Yes      |                                                   |
| `intro`                         | string | No       | Description (HTML)                                |
| `section`                       | int    | No       | Default: 0                                        |
| `visible`                       | int    | No       | Default: 1                                        |
| `type`                          | int    | No       | 0=room+recordings, 1=room only, 2=recordings only |
| `welcome`                       | string | No       | Welcome message                                   |
| `voicebridge`                   | int    | No       | 4-digit voice bridge number                       |
| `wait`                          | int    | No       | Wait for moderator (1/0)                          |
| `userlimit`                     | int    | No       | Max participants (0=unlimited)                    |
| `record`                        | int    | No       | Enable recording (1/0)                            |
| `muteonstart`                   | int    | No       | Mute on start (1/0)                               |
| `disablecam`                    | int    | No       | Disable webcams (1/0)                             |
| `disablemic`                    | int    | No       | Disable microphones (1/0)                         |
| `disableprivatechat`            | int    | No       | Disable private chat (1/0)                        |
| `disablepublicchat`             | int    | No       | Disable public chat (1/0)                         |
| `disablenote`                   | int    | No       | Disable shared notes (1/0)                        |
| `hideuserlist`                  | int    | No       | Hide user list (1/0)                              |
| `openingtime`                   | int    | No       | Unix timestamp (0=no restriction)                 |
| `closingtime`                   | int    | No       | Unix timestamp (0=no restriction)                 |
| `guestallowed`                  | int    | No       | Allow guests (1/0)                                |
| `mustapproveuser`               | int    | No       | Approve guests (1/0)                              |
| `recordings_deleted`            | int    | No       | Show deleted recordings (1/0)                     |
| `recordings_imported`           | int    | No       | Show imported recordings (1/0)                    |
| `recordings_preview`            | int    | No       | Show preview (1/0)                                |
| `showpresentation`              | int    | No       | Show presentation on page (1/0)                   |
| `completionattendance`          | int    | No       | Required attendance minutes                       |
| `completionengagementchats`     | int    | No       | Required chat messages                            |
| `completionengagementtalks`     | int    | No       | Required talk time                                |
| `completionengagementraisehand` | int    | No       | Required raise hand count                         |
| `completionengagementpollvotes` | int    | No       | Required poll votes                               |
| `completionengagementemojis`    | int    | No       | Required emoji count                              |

**Response:**

```json
{
  "id": 1,
  "coursemoduleid": 123,
  "meetingid": "unique-meeting-id",
  "name": "Weekly Meeting",
  "success": true,
  "message": "BigBlueButton activity created successfully"
}
```

### Update BigBlueButton

`local_fivedays_update_bigbluebuttonbn`

Parameters: `bigbluebuttonbnid` (required), plus any field from create (all optional)

### Delete BigBlueButton

`local_fivedays_delete_bigbluebuttonbn`

Parameters: `cmid` (course module ID)

---

## Forums

### Create Forum

`local_fivedays_create_forum`

| Parameter  | Type   | Required | Description                                                                         |
| ---------- | ------ | -------- | ----------------------------------------------------------------------------------- |
| `courseid` | int    | Yes      |                                                                                     |
| `name`     | string | Yes      |                                                                                     |
| `intro`    | string | No       | Forum description (HTML)                                                            |
| `type`     | string | No       | Forum type: general, news, social, eachuser, single, qanda, blog (default: general) |
| `section`  | int    | No       | Default: 0                                                                          |
| `idnumber` | string | No       | ID number                                                                           |

**Forum Types:**

- `general` - Standard forum for general use
- `news` - News forum (announcements)
- `social` - Social forum for off-topic discussions
- `eachuser` - Each person posts one discussion
- `single` - A single simple discussion
- `qanda` - Q&A forum (students see other responses after posting)
- `blog` - Blog-like format

**Response:**

```json
{
  "id": 1,
  "coursemoduleid": 123,
  "name": "General Discussion",
  "success": true,
  "message": "Forum created successfully"
}
```

### Delete Forum

`local_fivedays_delete_forum`

Parameters: `cmid` (course module ID)

### Add Announcement

`local_fivedays_add_announcement`

Posts a discussion in the course's announcements (news) forum, creating that forum if the course has none. Requires `local/fivedays:addannouncement` on the course; posting as another user also needs `local/fivedays:addannouncementasuser` (manager only). The author must have an active enrolment in the course and `mod/forum:addnews`. Attachments are kept only when both the caller and the author have `mod/forum:createattachment`.

| Parameter     | Type   | Required | Description                                                                 |
| ------------- | ------ | -------- | --------------------------------------------------------------------------- |
| `courseid`    | int    | Yes      |                                                                             |
| `subject`     | string | Yes      |                                                                             |
| `message`     | string | Yes      | HTML                                                                        |
| `draftitemid` | int    | No       | Draft area holding attachments (default 0 = none)                           |
| `timecreated` | int    | No       | Posted time (Unix, 2000-01-01 up to now); sets created/modified on the post and discussion (0 = now) |
| `userid`      | int    | No       | Author (default 0 = the calling user)                                       |
| `notify`      | bool   | No       | Default false: the post is marked as already mailed, so no email or notification is sent. True leaves it to the forum cron |

**Response:** `{ "discussionid": 12, "postid": 34 }`

---

## Quizzes

Complete quiz management API for Moodle 5.0+. These functions fill gaps not covered by core Moodle web services.

### Create Quiz

`local_fivedays_create_quiz`

Creates a new quiz with full configuration options.

| Parameter                     | Type   | Required | Default            | Description                           |
| ----------------------------- | ------ | -------- | ------------------ | ------------------------------------- |
| `courseid`                    | int    | Yes      |                    | Course ID                             |
| `name`                        | string | Yes      |                    | Quiz name                             |
| `intro`                       | string | No       | ''                 | Quiz description (HTML)               |
| `section`                     | int    | No       | 0                  | Course section number                 |
| `idnumber`                    | string | No       | ''                 | ID number for gradebook               |
| `timeopen`                    | int    | No       | 0                  | Open timestamp (0 = no restriction)   |
| `timeclose`                   | int    | No       | 0                  | Close timestamp (0 = no restriction)  |
| `timelimit`                   | int    | No       | 0                  | Time limit in seconds                 |
| `overduehandling`             | string | No       | 'autosubmit'       | autosubmit, graceperiod, autoabandon  |
| `graceperiod`                 | int    | No       | 0                  | Grace period in seconds               |
| `grade`                       | float  | No       | 10.0               | Maximum grade                         |
| `grademethod`                 | int    | No       | 1                  | 1=highest, 2=average, 3=first, 4=last |
| `decimalpoints`               | int    | No       | 2                  | Decimal places (0-5)                  |
| `questiondecimalpoints`       | int    | No       | -1                 | Question decimals (-1 = same as quiz) |
| `questionsperpage`            | int    | No       | 1                  | Questions per page (0 = all)          |
| `navmethod`                   | string | No       | 'free'             | free or sequential                    |
| `shuffleanswers`              | int    | No       | 1                  | Shuffle answer options                |
| `preferredbehaviour`          | string | No       | 'deferredfeedback' | Question behaviour                    |
| `canredoquestions`            | int    | No       | 0                  | Allow redo of questions               |
| `attempts`                    | int    | No       | 0                  | Max attempts (0 = unlimited)          |
| `attemptonlast`               | int    | No       | 0                  | Build on last attempt                 |
| `reviewattempt`               | int    | No       | 69904              | Review options bitmask                |
| `reviewcorrectness`           | int    | No       | 69904              | Review correctness bitmask            |
| `reviewmarks`                 | int    | No       | 69904              | Review marks bitmask                  |
| `reviewspecificfeedback`      | int    | No       | 69904              | Review specific feedback bitmask      |
| `reviewgeneralfeedback`       | int    | No       | 69904              | Review general feedback bitmask       |
| `reviewrightanswer`           | int    | No       | 69904              | Review right answer bitmask           |
| `reviewoverallfeedback`       | int    | No       | 4368               | Review overall feedback bitmask       |
| `reviewmaxmarks`              | int    | No       | 69904              | Review max marks bitmask              |
| `password`                    | string | No       | ''                 | Quiz password                         |
| `subnet`                      | string | No       | ''                 | Allowed IP addresses                  |
| `browsersecurity`             | string | No       | '-'                | - (none) or securewindow              |
| `delay1`                      | int    | No       | 0                  | Delay between attempts 1-2 (seconds)  |
| `delay2`                      | int    | No       | 0                  | Delay between later attempts          |
| `showuserpicture`             | int    | No       | 0                  | 0=no, 1=small, 2=large                |
| `showblocks`                  | int    | No       | 0                  | Show blocks during quiz               |
| `completionattemptsexhausted` | int    | No       | 0                  | Complete when attempts exhausted      |
| `completionminattempts`       | int    | No       | 0                  | Minimum attempts for completion       |
| `visible`                     | int    | No       | 1                  | Module visibility                     |
| `allowofflineattempts`        | int    | No       | 0                  | Allow offline attempts (mobile)       |

**Question Behaviours:**

- `deferredfeedback` - Deferred feedback (default)
- `adaptivenopenalty` - Adaptive mode (no penalties)
- `adaptive` - Adaptive mode
- `interactive` - Interactive with multiple tries
- `immediatefeedback` - Immediate feedback
- `immediatecbm` - Immediate feedback with CBM

**Review Options Bitmask:**

- Bits represent when to show: during (0x10000), immediately after (0x01000), later while open (0x00100), after close (0x00010)
- Default 69904 = show during + immediately after + later while open + after close

**Response:**

```json
{
  "id": 1,
  "coursemoduleid": 123,
  "name": "Week 1 Quiz",
  "success": true,
  "message": "Quiz created successfully"
}
```

### Update Quiz

`local_fivedays_update_quiz`

Updates an existing quiz. All parameters except `quizid` are optional - only provided fields are updated.

| Parameter                        | Type | Required |
| -------------------------------- | ---- | -------- |
| `quizid`                         | int  | Yes      |
| All other parameters from create | \*   | No       |

### Delete Quiz

`local_fivedays_delete_quiz`

| Parameter | Type | Required |
| --------- | ---- | -------- |
| `cmid`    | int  | Yes      |

### Get Quiz

`local_fivedays_get_quiz`

Retrieves complete quiz details including all questions, sections, settings, answers, and question-type-specific options.

| Parameter | Type | Required |
| --------- | ---- | -------- |
| `quizid`  | int  | Yes      |

**Important:** Each question now includes:
- `answers[]` - Array of all possible answers with their text, fraction (score), and feedback
  - `fraction`: 1.0 = correct answer, 0.0 = incorrect, values between for partial credit
- `options` - Question-type-specific settings:
  - **shortanswer**: `usecase` (case sensitivity)
  - **truefalse**: `trueanswer`, `falseanswer` (answer IDs)
  - **multichoice**: `single`, `shuffleanswers`, `answernumbering`, feedback fields

**Response:**

```json
{
  "id": 1,
  "coursemoduleid": 123,
  "courseid": 2,
  "coursename": "Course Name",
  "name": "Week 1 Quiz",
  "intro": "<p>Quiz description</p>",
  "timeopen": 0,
  "timeclose": 0,
  "timelimit": 3600,
  "grade": 100.0,
  "sumgrades": 50.0,
  "attemptcount": 15,
  "sections": [
    {
      "id": 1,
      "firstslot": 1,
      "heading": "Section 1",
      "shufflequestions": 0
    }
  ],
  "questions": [
    {
      "slotid": 1,
      "slot": 1,
      "page": 1,
      "maxmark": 10.0,
      "requireprevious": 0,
      "displaynumber": "",
      "questionbankentryid": 456,
      "questionid": 789,
      "questionidnumber": "Q001",
      "questionname": "What is 2+2?",
      "qtype": "multichoice",
      "questiontext": "<p>What is 2+2?</p>",
      "defaultmark": 10.0,
      "version": 1,
      "status": "ready",
      "answers": [
        {
          "id": 101,
          "answer": "3",
          "answerformat": 1,
          "fraction": 0.0,
          "feedback": "Incorrect",
          "feedbackformat": 1
        },
        {
          "id": 102,
          "answer": "4",
          "answerformat": 1,
          "fraction": 1.0,
          "feedback": "Correct!",
          "feedbackformat": 1
        }
      ],
      "options": {
        "single": 1,
        "shuffleanswers": 1,
        "answernumbering": "abc",
        "correctfeedback": "Well done!",
        "partiallycorrectfeedback": "Partially correct",
        "incorrectfeedback": "Try again"
      }
    }
  ],
  "success": true,
  "message": "Quiz retrieved successfully with 1 question(s)"
}
```

### Add Question to Quiz

`local_fivedays_add_question_to_quiz`

Adds an existing question from the question bank to a quiz.

| Parameter             | Type  | Required | Default | Description                        |
| --------------------- | ----- | -------- | ------- | ---------------------------------- |
| `quizid`              | int   | Yes      |         | Quiz instance ID                   |
| `questionbankentryid` | int   | Yes      |         | Question bank entry ID             |
| `page`                | int   | No       | 0       | Page number (0 = last page)        |
| `maxmark`             | float | No       | null    | Max mark (null = question default) |
| `requireprevious`     | int   | No       | 0       | Require previous question          |

**Note:** Use `questionbankentryid` not `questionid`. In Moodle 5.0+, questions are referenced through the question bank entry system.

**Response:**

```json
{
  "success": true,
  "message": "Question \"What is 2+2?\" added to quiz at slot 1",
  "slotid": 123,
  "slot": 1
}
```

### Remove Question from Quiz

`local_fivedays_remove_question_from_quiz`

| Parameter | Type | Required | Description           |
| --------- | ---- | -------- | --------------------- |
| `quizid`  | int  | Yes      | Quiz instance ID      |
| `slot`    | int  | Yes      | Slot number to remove |

**Note:** Removes the question from the specified slot. Subsequent slots are automatically renumbered.

### Update Quiz Slot Mark

`local_fivedays_update_quiz_slot_mark`

| Parameter | Type  | Required | Description                   |
| --------- | ----- | -------- | ----------------------------- |
| `quizid`  | int   | Yes      | Quiz instance ID              |
| `slot`    | int   | Yes      | Slot number                   |
| `maxmark` | float | Yes      | New maximum mark for the slot |

**Note:** Uses Moodle's `structure::update_slot_maxmark()`, which regrades existing attempts, then recomputes the quiz sum of grades.

### Reorder Quiz Questions

`local_fivedays_reorder_quiz_questions`

Reorders questions within a quiz by specifying new slot positions.

| Parameter | Type  | Required | Description                   |
| --------- | ----- | -------- | ----------------------------- |
| `quizid`  | int   | Yes      | Quiz instance ID              |
| `slots`   | array | Yes      | Array of slot reorder objects |

**Slot reorder object:**

- `slotid` (int, required): Slot ID
- `newslot` (int, required): New slot position (1-based)
- `page` (int, optional): New page number

**Example:**

```json
{
  "quizid": 1,
  "slots": [
    { "slotid": 101, "newslot": 3, "page": 1 },
    { "slotid": 102, "newslot": 1, "page": 1 },
    { "slotid": 103, "newslot": 2, "page": 1 }
  ]
}
```

### Quiz Sections

All five need `local/fivedays:managequizquestions` and `mod/quiz:manage`. They return `success` and `message`; a refused change (not found, rules below) returns `success` false with the reason instead of throwing. Except `update_quiz_section`, they are refused once the quiz has attempts (Moodle's `check_can_be_edited()`; previews do not count). Section 1 is the section that starts at slot 1.

`local_fivedays_add_quiz_section` (`quizid`, `slot` >= 2, `heading` non-empty): starts a section at that slot. Slot 1 and a slot that already starts a section are refused. If the slot is not first on its page, the page is split first. Returns `sectionid`, `firstslot`.

`local_fivedays_update_quiz_section` (`sectionid`, optional `heading`, optional `shufflequestions` 0/1): works with attempts. An empty heading is allowed only for section 1. Returns `heading`, `shufflequestions`.

`local_fivedays_remove_quiz_section` (`sectionid`): removes only the heading; its questions join the section above. Section 1 is refused. Returns `movedslots`.

`local_fivedays_delete_quiz_section` (`sectionid`): deletes the section and its questions in one transaction. Deleting section 1 moves the next section's heading and shuffle setting onto it. A one-section quiz is refused. Returns `deletedslots`.

`local_fivedays_set_quiz_structure` (`quizid`, `sections`: list of `{sectionid, slotids[]}`): every section id and every slot id (`quiz_slots.id`) exactly once, no empty section, and the first listed section must be the one that starts at slot 1. Renumbers slots 1..N in the given order, sets `page` = slot and every section's first slot. Returns `slotcount`, `sectioncount`.

---

## Quiz Attempts & Grading

API for viewing quiz attempts, grading essay questions, and managing feedback.

### Get Quiz Attempts

`local_fivedays_get_quiz_attempts`

Gets all quiz attempts for a quiz with student info.

| Parameter | Type | Required | Description      |
| --------- | ---- | -------- | ---------------- |
| `quizid`  | int  | Yes      | Quiz instance ID |

**Response:**

```json
{
  "success": true,
  "attempts": [
    {
      "id": 123,
      "userid": 456,
      "attempt": 1,
      "state": "finished",
      "timestart": 1702234567,
      "timefinish": 1702238167,
      "timemodified": 1702238167,
      "sumgrades": 8.5,
      "user": {
        "id": 456,
        "fullname": "John Doe",
        "profileimageurl": "https://..."
      }
    }
  ]
}
```

**Attempt states:** `inprogress`, `overdue`, `finished`, `abandoned`

### Get Quiz Attempt Details

`local_fivedays_get_quiz_attempt_details`

Gets detailed attempt with question responses.

| Parameter   | Type | Required | Description   |
| ----------- | ---- | -------- | ------------- |
| `attemptid` | int  | Yes      | The attempt ID |

**Response:**

```json
{
  "success": true,
  "attempt": {
    "id": 123,
    "userid": 456,
    "state": "finished",
    "timestart": 1702234567,
    "timefinish": 1702238167,
    "sumgrades": 8.5,
    "grade": 85.0,
    "questions": [
      {
        "slot": 1,
        "type": "multichoice",
        "name": "Question 1",
        "questiontext": "<p>What is 2+2?</p>",
        "maxmark": 2.0,
        "mark": 2.0,
        "response": "A",
        "rightanswer": "A",
        "state": "gradedright",
        "feedback": "Well done!"
      },
      {
        "slot": 2,
        "type": "essay",
        "name": "Essay Question",
        "questiontext": "<p>Describe...</p>",
        "maxmark": 5.0,
        "mark": null,
        "response": "Student's essay response...",
        "rightanswer": "",
        "state": "needsgrading",
        "feedback": null
      }
    ]
  }
}
```

**Question states:** `gradedright`, `gradedwrong`, `gradedpartial`, `needsgrading`, `gaveup`, `complete`, `todo`

### Grade Essay Question

`local_fivedays_grade_essay_question`

Manually grades an essay or other manually-graded question.

| Parameter   | Type   | Required | Default | Description              |
| ----------- | ------ | -------- | ------- | ------------------------ |
| `attemptid` | int    | Yes      |         | The attempt ID           |
| `slot`      | int    | Yes      |         | Question slot number     |
| `mark`      | float  | Yes      |         | Mark to assign           |
| `comment`   | string | No       | ''      | Feedback comment         |

**Note:** Mark must be between 0 and the question's maximum mark.

**Response:**

```json
{
  "success": true,
  "message": "Question graded successfully"
}
```

### Add Attempt Feedback

`local_fivedays_add_attempt_feedback`

Adds overall feedback to a quiz attempt.

| Parameter   | Type   | Required | Description      |
| ----------- | ------ | -------- | ---------------- |
| `attemptid` | int    | Yes      | The attempt ID   |
| `feedback`  | string | Yes      | Feedback text    |

**Response:**

```json
{
  "success": true,
  "message": "Feedback added successfully"
}
```

### Get Attempt Feedback

`local_fivedays_get_attempt_feedback`

Gets the overall feedback for a quiz attempt.

| Parameter   | Type | Required | Description    |
| ----------- | ---- | -------- | -------------- |
| `attemptid` | int  | Yes      | The attempt ID |

**Response:**

```json
{
  "success": true,
  "feedback": "Your overall performance was good..."
}
```

---

## Question Bank

Complete question bank management API for creating and managing questions in Moodle 5.0+.

### Get or Create Question Category

`local_fivedays_get_or_create_question_category`

Gets an existing category or creates a new one if it doesn't exist.

| Parameter          | Type   | Required | Default | Description                               |
| ------------------ | ------ | -------- | ------- | ----------------------------------------- |
| `courseid`         | int    | Yes      |         | Course ID                                 |
| `name`             | string | Yes      |         | Category name                             |
| `info`             | string | No       | ''      | Category description                      |
| `parentcategoryid` | int    | No       | 0       | Parent category ID (0 = course top-level) |

**Response:**

```json
{
  "id": 123,
  "name": "Week 1 Questions",
  "contextid": 456,
  "created": true,
  "success": true,
  "message": "Category created successfully"
}
```

### List Question Categories

`local_fivedays_list_question_categories`

| Parameter  | Type | Required |
| ---------- | ---- | -------- |
| `courseid` | int  | Yes      |

**Response:**

```json
{
  "categories": [
    {
      "id": 123,
      "name": "Default for Course",
      "info": "",
      "parent": 0,
      "contextid": 456,
      "sortorder": 999,
      "questioncount": 15,
      "idnumber": ""
    }
  ],
  "success": true,
  "message": "Found 1 category(ies)"
}
```

### Editing a Question

Every `create_*_question` call below also accepts an optional `questionbankentryid` (int, default 0). When it is set, the question is saved as a new version of that question bank entry instead of a new question. Quiz slots that use the latest version show the new version, and attempts already started keep the version they were taken on. The caller needs `moodle/question:editall`, or `moodle/question:editmine` for their own questions, in the entry's category context.

### Create Multiple Choice Question

`local_fivedays_create_multichoice_question`

Creates a multiple choice question supporting both single and multiple answer modes.

| Parameter                  | Type   | Required | Default | Description                                                  |
| -------------------------- | ------ | -------- | ------- | ------------------------------------------------------------ |
| `categoryid`               | int    | Yes      |         | Question category ID                                         |
| `name`                     | string | Yes      |         | Question name                                                |
| `questiontext`             | string | Yes      |         | Question text (HTML)                                         |
| `answers`                  | array  | Yes      |         | Array of answer objects                                      |
| `defaultmark`              | float  | No       | 1.0     | Default mark (points)                                        |
| `single`                   | int    | No       | 1       | 1 = single answer (radio), 0 = multiple answers (checkboxes) |
| `shuffleanswers`           | int    | No       | 1       | Shuffle answer order                                         |
| `answernumbering`          | string | No       | 'abc'   | abc, ABC, 123, iii, III, none                                |
| `correctfeedback`          | string | No       | ''      | Feedback for correct answer                                  |
| `partiallycorrectfeedback` | string | No       | ''      | Feedback for partially correct                               |
| `incorrectfeedback`        | string | No       | ''      | Feedback for incorrect answer                                |
| `generalfeedback`          | string | No       | ''      | General feedback                                             |
| `idnumber`                 | string | No       | ''      | ID number                                                    |
| `tags`                     | array  | No       | []      | Array of tag names                                           |

**Answer object:**

- `text` (string, required): Answer text (HTML)
- `fraction` (float, required): Grade fraction (1.0 = 100%, 0.5 = 50%, 0 = 0%, negative for penalties)
- `feedback` (string, optional): Feedback for this answer

**Example:**

```json
{
  "categoryid": 123,
  "name": "Capital of France",
  "questiontext": "<p>What is the capital of France?</p>",
  "single": 1,
  "answers": [
    { "text": "Paris", "fraction": 1.0, "feedback": "Correct!" },
    { "text": "London", "fraction": 0, "feedback": "That's the capital of UK" },
    {
      "text": "Berlin",
      "fraction": 0,
      "feedback": "That's the capital of Germany"
    },
    {
      "text": "Madrid",
      "fraction": 0,
      "feedback": "That's the capital of Spain"
    }
  ]
}
```

**Response:**

```json
{
  "questionid": 789,
  "questionbankentryid": 456,
  "name": "Capital of France",
  "success": true,
  "message": "Multiple choice question created successfully"
}
```

### Create True/False Question

`local_fivedays_create_truefalse_question`

| Parameter         | Type   | Required | Default | Description                  |
| ----------------- | ------ | -------- | ------- | ---------------------------- |
| `categoryid`      | int    | Yes      |         | Question category ID         |
| `name`            | string | Yes      |         | Question name                |
| `questiontext`    | string | Yes      |         | Question text (HTML)         |
| `correctanswer`   | int    | Yes      |         | 1 = True, 0 = False          |
| `defaultmark`     | float  | No       | 1.0     | Default mark                 |
| `feedbacktrue`    | string | No       | ''      | Feedback when True selected  |
| `feedbackfalse`   | string | No       | ''      | Feedback when False selected |
| `generalfeedback` | string | No       | ''      | General feedback             |
| `idnumber`        | string | No       | ''      | ID number                    |
| `tags`            | array  | No       | []      | Array of tag names           |

### Create Short Answer Question

`local_fivedays_create_shortanswer_question`

| Parameter         | Type   | Required | Default | Description                     |
| ----------------- | ------ | -------- | ------- | ------------------------------- |
| `categoryid`      | int    | Yes      |         | Question category ID            |
| `name`            | string | Yes      |         | Question name                   |
| `questiontext`    | string | Yes      |         | Question text (HTML)            |
| `answers`         | array  | Yes      |         | Array of accepted answers       |
| `defaultmark`     | float  | No       | 1.0     | Default mark                    |
| `usecase`         | int    | No       | 0       | Case sensitive: 1 = yes, 0 = no |
| `generalfeedback` | string | No       | ''      | General feedback                |
| `idnumber`        | string | No       | ''      | ID number                       |
| `tags`            | array  | No       | []      | Array of tag names              |

**Answer object:**

- `text` (string, required): Accepted answer text
- `fraction` (float, optional): Grade fraction (default 1.0)
- `feedback` (string, optional): Feedback for this answer

### Create Essay Question

`local_fivedays_create_essay_question`

| Parameter             | Type   | Required | Default  | Description                                           |
| --------------------- | ------ | -------- | -------- | ----------------------------------------------------- |
| `categoryid`          | int    | Yes      |          | Question category ID                                  |
| `name`                | string | Yes      |          | Question name                                         |
| `questiontext`        | string | Yes      |          | Question text (HTML)                                  |
| `defaultmark`         | float  | No       | 1.0      | Default mark                                          |
| `responseformat`      | string | No       | 'editor' | editor, editorfilepicker, plain, monospaced, noinline |
| `responserequired`    | int    | No       | 1        | Response required                                     |
| `responsefieldlines`  | int    | No       | 15       | Number of lines                                       |
| `minwordlimit`        | int    | No       | 0        | Minimum words (0 = no limit)                          |
| `maxwordlimit`        | int    | No       | 0        | Maximum words (0 = no limit)                          |
| `attachments`         | int    | No       | 0        | Attachments allowed: 0, 1, 2, 3, -1 (unlimited)       |
| `attachmentsrequired` | int    | No       | 0        | Required attachments                                  |
| `maxbytes`            | int    | No       | 0        | Max file size (0 = site default)                      |
| `filetypeslist`       | string | No       | ''       | Accepted file types (e.g., ".pdf,.doc")               |
| `graderinfo`          | string | No       | ''       | Information for graders                               |
| `responsetemplate`    | string | No       | ''       | Response template                                     |
| `generalfeedback`     | string | No       | ''       | General feedback                                      |
| `idnumber`            | string | No       | ''       | ID number                                             |
| `tags`                | array  | No       | []       | Array of tag names                                    |

### Create Numerical Question

`local_fivedays_create_numerical_question`

| Parameter         | Type   | Required | Default | Description                                                     |
| ----------------- | ------ | -------- | ------- | --------------------------------------------------------------- |
| `categoryid`      | int    | Yes      |         | Question category ID                                            |
| `name`            | string | Yes      |         | Question name                                                   |
| `questiontext`    | string | Yes      |         | Question text (HTML)                                            |
| `answers`         | array  | Yes      |         | Array of numerical answers                                      |
| `defaultmark`     | float  | No       | 1.0     | Default mark                                                    |
| `unitgradingtype` | int    | No       | 0       | 0 = not graded, 1 = fraction of response, 2 = fraction of total |
| `unitpenalty`     | float  | No       | 0.1     | Penalty for wrong unit                                          |
| `showunits`       | int    | No       | 3       | 0 = text input, 1 = multichoice, 2 = dropdown, 3 = not visible  |
| `unitsleft`       | int    | No       | 0       | 0 = right, 1 = left                                             |
| `units`           | array  | No       | []      | Array of unit objects                                           |
| `generalfeedback` | string | No       | ''      | General feedback                                                |
| `idnumber`        | string | No       | ''      | ID number                                                       |
| `tags`            | array  | No       | []      | Array of tag names                                              |

**Answer object:**

- `answer` (string, required): Numerical answer value
- `tolerance` (float, optional): Tolerance/error margin (default 0)
- `fraction` (float, optional): Grade fraction (default 1.0)
- `feedback` (string, optional): Feedback

**Unit object:**

- `unit` (string, required): Unit name (e.g., "m", "kg")
- `multiplier` (float, optional): Multiplier (default 1.0)

**Example:**

```json
{
  "categoryid": 123,
  "name": "Calculate Area",
  "questiontext": "<p>What is the area of a rectangle with width 5m and height 3m?</p>",
  "answers": [
    { "answer": "15", "tolerance": 0, "fraction": 1.0, "feedback": "Correct!" }
  ],
  "units": [
    { "unit": "m²", "multiplier": 1.0 },
    { "unit": "m2", "multiplier": 1.0 }
  ]
}
```

### Create Matching Question

`local_fivedays_create_match_question`

Common optional parameters: `defaultmark` (1.0), `shuffleanswers` (1), `correctfeedback`, `partiallycorrectfeedback`, `incorrectfeedback`, `generalfeedback`, `idnumber`, `tags`, `questionbankentryid`.

| Parameter    | Type   | Required | Description                                                                 |
| ------------ | ------ | -------- | --------------------------------------------------------------------------- |
| `categoryid` | int    | Yes      | Question category ID                                                        |
| `name`       | string | Yes      | Question name                                                               |
| `questiontext` | string | Yes    | Question text (HTML)                                                        |
| `pairs`      | string | Yes      | JSON `[{"question":"Stem","answer":"Match"}]`; at least 2 pairs with both parts. A pair with an empty `question` is an extra wrong answer. Answers are at most 255 characters. |

Invalid input throws `invalidquestiondata` with the reason.

### Create Description

`local_fivedays_create_description_question`

Information only: mark 0, no response. Parameters: `categoryid`, `name`, `questiontext` (required), `generalfeedback`, `idnumber`, `tags`, `questionbankentryid`. `add_question_to_quiz` always gives it mark 0 and `update_quiz_slot_mark` refuses a non-zero mark for it.

### Create Select Missing Words Question

`local_fivedays_create_gapselect_question`

Same optional parameters as the matching question. `choices` (required) is a JSON array `[{"answer":"word","group":1}]`; `[[1]]`, `[[2]]` in the question text point at a choice by its 1-based position. Every `[[n]]` must match a choice, the text needs at least one gap, and `group` is 1-20. Unused choices act as distractors.

### Create Drag and Drop into Text Question

`local_fivedays_create_ddwtos_question`

Same as the select missing words question, but `choices` is `[{"answer":"word","group":1,"infinite":false}]`, `group` is 1-8, and a choice used in more than one gap needs `infinite` true.

All four return `questionid`, `questionbankentryid`, `name`, `success` and `message`. `get_quiz` returns for `match`, `gapselect` and `ddwtos` the `options` `shuffleanswers`, `correctfeedback`, `partiallycorrectfeedback`, `incorrectfeedback` (with formats), plus `subquestions` (`id`, `questiontext`, `answertext`) for match or `choices` (`answer`, `choicegroup`, and `infinite` for ddwtos) for the other two.

### Get Questions

`local_fivedays_get_questions`

Get questions in a category for browsing and selection.

| Parameter              | Type   | Required | Default | Description                                   |
| ---------------------- | ------ | -------- | ------- | --------------------------------------------- |
| `categoryid`           | int    | Yes      |         | Question category ID                          |
| `includesubcategories` | int    | No       | 0       | Include subcategories: 1 = yes                |
| `qtype`                | string | No       | ''      | Filter by type (multichoice, truefalse, etc.) |
| `limit`                | int    | No       | 0       | Max results (0 = no limit)                    |
| `offset`               | int    | No       | 0       | Offset for pagination                         |

**Response:**

```json
{
  "questions": [
    {
      "questionid": 789,
      "questionbankentryid": 456,
      "name": "Capital of France",
      "questiontext": "<p>What is the capital of France?</p>",
      "qtype": "multichoice",
      "defaultmark": 1.0,
      "categoryid": 123,
      "idnumber": "Q001",
      "version": 1,
      "status": "ready",
      "timecreated": 1701234567,
      "timemodified": 1701234567
    }
  ],
  "totalcount": 1,
  "success": true,
  "message": "Found 1 question(s)"
}
```

### Delete Question

`local_fivedays_delete_question`

| Parameter             | Type | Required | Description            |
| --------------------- | ---- | -------- | ---------------------- |
| `questionbankentryid` | int  | Yes      | Question bank entry ID |

**Note:** Cannot delete questions that are used in quizzes. Remove from all quizzes first.

---

## Examples

### Create Assignment

```bash
curl -X POST "https://yourmoodle.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN&wsfunction=local_fivedays_create_assignment&moodlewsrestformat=json" \
  -d "courseid=2&name=Week 1 Assignment&duedate=1735689600"
```

### Create BigBlueButton Session

```bash
curl -X POST "https://yourmoodle.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN&wsfunction=local_fivedays_create_bigbluebuttonbn&moodlewsrestformat=json" \
  -d "courseid=2&name=Live Class&type=0&record=1&wait=1&openingtime=1735689600"
```

### Create Forum

```bash
curl -X POST "https://yourmoodle.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN&wsfunction=local_fivedays_create_forum&moodlewsrestformat=json" \
  -d "courseid=2&name=General Discussion&type=general&section=0"
```

### Create FiveDays Rubric

```bash
curl -X POST "https://yourmoodle.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN&wsfunction=local_fivedays_create_rubric&moodlewsrestformat=json" \
  -d "cmid=123&name=Essay Rubric" \
  -d "criteria[0][description]=Content&criteria[0][levels][0][score]=0&criteria[0][levels][0][definition]=Poor" \
  -d "criteria[0][levels][1][score]=10&criteria[0][levels][1][definition]=Excellent"
```

### Grade with Rubric

```bash
curl -X POST "https://yourmoodle.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN&wsfunction=local_fivedays_fill_rubric&moodlewsrestformat=json" \
  -d "cmid=123&userid=456" \
  -d "fillings[0][criterionid]=1&fillings[0][levelid]=3&fillings[0][remark]=Good work" \
  -d "overallremark=Well done!"
```

### Create Quiz

```bash
curl -X POST "https://yourmoodle.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN&wsfunction=local_fivedays_create_quiz&moodlewsrestformat=json" \
  -d "courseid=2&name=Week 1 Quiz&intro=<p>Test your knowledge</p>" \
  -d "timelimit=3600&attempts=3&grademethod=1&grade=100"
```

### Add Question to Quiz

```bash
curl -X POST "https://yourmoodle.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN&wsfunction=local_fivedays_add_question_to_quiz&moodlewsrestformat=json" \
  -d "quizid=1&questionbankentryid=123&maxmark=10"
```

### Get Quiz Details

```bash
curl -X POST "https://yourmoodle.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN&wsfunction=local_fivedays_get_quiz&moodlewsrestformat=json" \
  -d "quizid=1"
```

### Create Question Category

```bash
curl -X POST "https://yourmoodle.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN&wsfunction=local_fivedays_get_or_create_question_category&moodlewsrestformat=json" \
  -d "courseid=2&name=Week 1 Questions"
```

### Create Multiple Choice Question

```bash
curl -X POST "https://yourmoodle.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN&wsfunction=local_fivedays_create_multichoice_question&moodlewsrestformat=json" \
  -d "categoryid=123&name=Capital Question&questiontext=<p>What is the capital of France?</p>" \
  -d "answers[0][text]=Paris&answers[0][fraction]=1" \
  -d "answers[1][text]=London&answers[1][fraction]=0" \
  -d "answers[2][text]=Berlin&answers[2][fraction]=0"
```

### Get Questions in Category

```bash
curl -X POST "https://yourmoodle.com/webservice/rest/server.php" \
  -d "wstoken=TOKEN&wsfunction=local_fivedays_get_questions&moodlewsrestformat=json" \
  -d "categoryid=123&limit=50"
```

---

## Capabilities

All capabilities granted to **editing teachers** and **managers** by default.

| Capability                                    | Description                       |
| --------------------------------------------- | --------------------------------- |
| `local/fivedays:createassignment`       | Create assignments                |
| `local/fivedays:updateassignment`       | Update assignments                |
| `local/fivedays:deleteassignment`       | Delete assignments                |
| `local/fivedays:createsection`          | Create sections                   |
| `local/fivedays:updatesection`          | Update sections                   |
| `local/fivedays:deletesection`          | Delete sections                   |
| `local/fivedays:createsubsection`       | Create subsections                |
| `local/fivedays:updatesubsection`       | Update subsections                |
| `local/fivedays:deletesubsection`       | Delete subsections                |
| `local/fivedays:createpage`             | Create pages                      |
| `local/fivedays:updatepage`             | Update pages                      |
| `local/fivedays:deletepage`             | Delete pages                      |
| `local/fivedays:createfile`             | Create files                      |
| `local/fivedays:updatefile`             | Update files                      |
| `local/fivedays:deletefile`             | Delete files                      |
| `local/fivedays:createfolder`           | Create folders                    |
| `local/fivedays:updatefolder`           | Update folders                    |
| `local/fivedays:createurl`              | Create URLs                       |
| `local/fivedays:updateurl`              | Update URLs                       |
| `local/fivedays:deleteurl`              | Delete URLs                       |
| `local/fivedays:createbook`             | Create books                      |
| `local/fivedays:updatebook`             | Update books                      |
| `local/fivedays:deletebook`             | Delete books                      |
| `local/fivedays:readbook`               | Read books (includes students)    |
| `local/fivedays:managerubric`           | Manage FiveDays rubrics           |
| `local/fivedays:graderubric`            | Grade using FiveDays rubrics      |
| `local/fivedays:viewrubricfilling`      | View rubric gradings              |
| `local/fivedays:createbigbluebuttonbn`  | Create BigBlueButton              |
| `local/fivedays:updatebigbluebuttonbn`  | Update BigBlueButton              |
| `local/fivedays:deletebigbluebuttonbn`  | Delete BigBlueButton              |
| `local/fivedays:createforum`            | Create forums                     |
| `local/fivedays:deleteforum`            | Delete forums                     |
| `local/fivedays:createquiz`             | Create quizzes                    |
| `local/fivedays:updatequiz`             | Update quizzes                    |
| `local/fivedays:deletequiz`             | Delete quizzes                    |
| `local/fivedays:viewquiz`               | View quiz details (teachers+)     |
| `local/fivedays:managequizquestions`    | Add/remove/reorder quiz questions |
| `local/fivedays:managequestioncategory` | Manage question categories        |
| `local/fivedays:createquestion`         | Create questions                  |
| `local/fivedays:viewquestions`          | View questions (teachers+)        |
| `local/fivedays:deletequestion`         | Delete questions                  |
| `local/fivedays:viewquizattempts`       | View quiz attempts                |
| `local/fivedays:gradequizattempts`      | Grade quiz attempts               |

---

## Notes

- **Subsection visibility:** Activities inherit visibility from parent subsection
- **File uploads:** Must be base64-encoded
- **Book chapters:** `subchapter=0` for main, `subchapter=1` for nested under previous main
- **BigBlueButton:** Requires mod_bigbluebuttonbn to be installed and enabled
- **Rubrics:** Requires gradingform_fivedays plugin to be installed
- **Default grading:** Assignments use simple direct grading until a rubric is added

---

## License

Developed for luct.
