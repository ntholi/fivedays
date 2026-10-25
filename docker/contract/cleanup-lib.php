<?php
// Removes what the contract test (and bin/cutover's upload QA) created, through Moodle's own APIs, so nothing
// stays visible to students or lecturers on a live site (cutover phases 6 and 9). Used by run.php under
// CONTRACT_CLEANUP=1 and by cleanup.php. What stays: deleted-user rows (Moodle keeps them with deleted = 1,
// lib/moodlelib.php delete_user), log, event and grade-history rows, and the fivedays_ws draft files (draft
// areas are emptied by cron).

/**
 * Deletes the given courses, then the category named CONTRACT if it is now empty, then the given users.
 *
 * @param int[] $courseids courses the run created (missing ones are skipped)
 * @param int[] $userids users the run created (missing or already deleted ones are skipped)
 * @return int number of problems (0 = clean)
 */
function contract_cleanup(array $courseids, array $userids): int {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->dirroot . '/user/lib.php');

    \core\session\manager::set_user(get_admin());
    $problems = 0;
    $categoryids = [];
    $courseids = array_unique(array_filter(array_map('intval', $courseids)));

    // bigbluebuttonbn_delete_instance() needs the activity in modinfo (mod/bigbluebuttonbn/lib.php:181 →
    // classes/instance.php:442 → course/classes/modinfo.php:260), and modinfo leaves out activities of a disabled
    // module, so deleting a course that holds a BigBlueButton activity fails while BigBlueButton is disabled
    // (prod, fresh installs). The module is enabled just for the deletion and disabled again; enable_plugin()
    // restores every other BigBlueButton activity's visibility on the way back (lib/classes/plugininfo/mod.php:48-104).
    $bbb = $DB->get_record('modules', ['name' => 'bigbluebuttonbn']);
    $togglebbb = false;
    if ($bbb && !$bbb->visible && $courseids) {
        [$insql, $inparams] = $DB->get_in_or_equal($courseids);
        $togglebbb = $DB->record_exists_select('course_modules', "module = ? AND course $insql",
            array_merge([$bbb->id], $inparams));
    }
    if ($togglebbb) {
        \core\plugininfo\mod::enable_plugin('bigbluebuttonbn', 1);
        echo "cleanup  enabled mod_bigbluebuttonbn while deleting the contract courses\n";
    }
    try {
        $problems += contract_cleanup_courses($courseids, $categoryids);
    } finally {
        if ($togglebbb) {
            \core\plugininfo\mod::enable_plugin('bigbluebuttonbn', 0);
            echo "cleanup  disabled mod_bigbluebuttonbn again\n";
        }
    }

    foreach (array_unique($categoryids) as $categoryid) {
        $category = core_course_category::get($categoryid, IGNORE_MISSING, true);
        if (!$category || $category->name !== 'CONTRACT') {
            contract_cleanup_recyclebin($categoryid, $courseids);
            continue;
        }
        if ($DB->record_exists('course', ['category' => $categoryid]) ||
                $DB->record_exists('course_categories', ['parent' => $categoryid])) {
            contract_cleanup_recyclebin($categoryid, $courseids);
            printf("cleanup  category %d (CONTRACT) kept: it holds other courses or categories\n", $categoryid);
            continue;
        }
        // delete_full() first empties the category recycle bin (tool_recyclebin_pre_course_category_delete,
        // admin/tool/recyclebin/lib.php:194-199), course/classes/category.php:2020.
        $category->delete_full(false);
        printf("cleanup  deleted empty category %d (CONTRACT) and its recycle bin\n", $categoryid);
    }

    foreach (array_unique(array_filter(array_map('intval', $userids))) as $userid) {
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user) {
            continue;
        }
        if ($user->username === 'fivedays_ws' || is_siteadmin($user)) {
            printf("FAIL     cleanup: refusing to delete user %d (%s)\n", $userid, $user->username);
            $problems++;
            continue;
        }
        // delete_user() also removes the user's web-service tokens (lib/moodlelib.php:3701-3702).
        delete_user($user);
        printf("cleanup  deleted user %d\n", $userid);
    }
    return $problems;
}

/** Deletes the courses (and notes their categories); returns the number that could not be deleted. */
function contract_cleanup_courses(array $courseids, array &$categoryids): int {
    global $DB;
    $problems = 0;
    foreach ($courseids as $courseid) {
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            continue;
        }
        $categoryids[] = (int) $course->category;
        contract_cleanup_orphans($course);
        // delete_course() (lib/moodlelib.php) also fires tool_recyclebin_pre_course_delete, which keeps a backup
        // in the category recycle bin (admin/tool/recyclebin/lib.php:171-186); removed below with the category,
        // or item by item when the category stays.
        if (delete_course($course, false)) {
            printf("cleanup  deleted course %d (%s)\n", $courseid, $course->shortname);
        } else {
            printf("FAIL     cleanup: course %d (%s) not deleted\n", $courseid, $course->shortname);
            $problems++;
        }
    }
    return $problems;
}

/**
 * Puts course modules that sit in no section back into section 0. A contract run leaves one behind on sites where
 * BigBlueButton is disabled: local_fivedays_delete_bigbluebuttonbn fails there (an allowed error), and
 * local_fivedays_delete_section then removes its section around it. modinfo is built from the section sequences
 * (course/classes/modinfo.php:1050-1063), so such a module can't be found and delete_course() stops on it.
 */
function contract_cleanup_orphans(stdClass $course): void {
    global $DB;
    $insequence = [];
    foreach ($DB->get_records('course_sections', ['course' => $course->id], '', 'id, sequence') as $section) {
        foreach (array_filter(explode(',', (string) $section->sequence)) as $cmid) {
            $insequence[(int) $cmid] = true;
        }
    }
    foreach ($DB->get_records('course_modules', ['course' => $course->id], 'id', 'id') as $cm) {
        if (!isset($insequence[(int) $cm->id])) {
            course_add_cm_to_section($course, $cm->id, 0);
            printf("cleanup  course module %d was in no section; put back in section 0 so the course can be deleted\n", $cm->id);
        }
    }
    rebuild_course_cache($course->id, true);
}

/** Removes the category recycle-bin copies of the given courses (when their category stays). */
function contract_cleanup_recyclebin(int $categoryid, array $courseids): void {
    if (!class_exists('\tool_recyclebin\category_bin')) {
        return;
    }
    $bin = new \tool_recyclebin\category_bin($categoryid);
    foreach ($bin->get_items() as $item) {
        if (in_array((int) $item->courseid, array_map('intval', $courseids), true)) {
            $bin->delete_item($item);
            printf("cleanup  removed recycle-bin copy of course %d\n", $item->courseid);
        }
    }
}
