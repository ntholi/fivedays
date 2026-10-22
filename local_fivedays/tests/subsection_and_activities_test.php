<?php
namespace local_activity_utils\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use advanced_testcase;
use context_course;
use local_activity_utils\external\assignment\create_assignment;
use local_activity_utils\external\file\create_file;
use local_activity_utils\external\page\create_page;
use local_activity_utils\external\section\create_subsection;

class subsection_and_activities_test extends advanced_testcase {

    private $course;
    private $user;

    protected function setUp(): void {
        $this->resetAfterTest(true);

        $this->course = $this->getDataGenerator()->create_course([
            'fullname' => 'Test Course',
            'shortname' => 'TEST101',
            'numsections' => 5,
        ]);

        $this->user = $this->getDataGenerator()->create_user();
        $teacherrole = $this->getDataGenerator()->get_role_by_shortname('editingteacher');
        $this->getDataGenerator()->role_assign($teacherrole->id, $this->user->id, context_course::instance($this->course->id));

        $this->setUser($this->user);
    }

    public function test_create_subsection_sets_delegation_metadata(): void {
        global $DB;

        $result = create_subsection::execute(
            courseid: $this->course->id,
            parentsection: 1,
            name: 'Week 1.1: Introduction',
            summary: 'Getting started with the course',
            visible: 1
        );

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['sectionnum']);
        $this->assertNotEmpty($result['coursemoduleid']);

        $section = $DB->get_record('course_sections', ['id' => $result['id']]);
        $this->assertNotNull($section);
        $this->assertEquals('mod_subsection', $section->component);
        $cm = $DB->get_record('course_modules', ['id' => $result['coursemoduleid']]);
        $this->assertNotNull($cm);
        $this->assertEquals($cm->instance, $section->itemid);
        $this->assertEquals(1, $section->visible);

        $parentsection = $DB->get_record('course_sections', [
            'course' => $this->course->id,
            'section' => 1,
        ]);
        $this->assertEquals($parentsection->id, $cm->section);
    }

    public function test_subsection_in_parent_sequence(): void {
        global $DB;

        $result = create_subsection::execute(
            courseid: $this->course->id,
            parentsection: 1,
            name: 'Subsection 1',
            visible: 1
        );

        $parentsection = $DB->get_record('course_sections', [
            'course' => $this->course->id,
            'section' => 1
        ]);

        $sequence = array_map('intval', explode(',', $parentsection->sequence));
        $this->assertContains($result['coursemoduleid'], $sequence);
    }

    public function test_create_page_in_subsection(): void {
        global $DB;

        $subsection = create_subsection::execute(
            courseid: $this->course->id,
            parentsection: 1,
            name: 'Week 1.1',
            visible: 1
        );

        $pageresult = create_page::execute(
            courseid: $this->course->id,
            name: 'Getting Started Guide',
            intro: 'Read this first',
            content: '<h2>Getting Started</h2><p>Follow these steps...</p>',
            section: $subsection['sectionnum'],
            visible: 1
        );

        $this->assertTrue($pageresult['success']);

        $subsection_section = $DB->get_record('course_sections', ['id' => $subsection['id']]);
        $sequence = array_map('intval', explode(',', $subsection_section->sequence ?? ''));
        $this->assertContains($pageresult['coursemoduleid'], $sequence);

        $pagecm = $DB->get_record('course_modules', ['id' => $pageresult['coursemoduleid']]);
        $this->assertEquals($subsection['id'], $pagecm->section);
    }

    public function test_create_assignment_in_subsection(): void {
        global $DB;

        $subsection = create_subsection::execute(
            courseid: $this->course->id,
            parentsection: 2,
            name: 'Week 2.1: Assignments',
            visible: 1
        );

        $assignresult = create_assignment::execute(
            courseid: $this->course->id,
            name: 'Assignment 1',
            intro: 'Complete this assignment',
            activity: 'Answer all questions',
            section: $subsection['sectionnum'],
            duedate: 0,
            grademax: 100
        );

        $this->assertTrue($assignresult['success']);

        $subsection_section = $DB->get_record('course_sections', ['id' => $subsection['id']]);
        $sequence = array_map('intval', explode(',', $subsection_section->sequence ?? ''));
        $this->assertContains($assignresult['coursemoduleid'], $sequence);

        $assigncm = $DB->get_record('course_modules', ['id' => $assignresult['coursemoduleid']]);
        $this->assertEquals($subsection['id'], $assigncm->section);
    }

    public function test_create_file_in_subsection(): void {
        global $DB;

        $subsection = create_subsection::execute(
            courseid: $this->course->id,
            parentsection: 3,
            name: 'Week 3.1: Resources',
            visible: 1
        );

        $filecontent = base64_encode('This is test file content');
        $fileresult = create_file::execute(
            courseid: $this->course->id,
            name: 'Course Syllabus',
            intro: 'Download the syllabus',
            filename: 'syllabus.txt',
            filecontent: $filecontent,
            section: $subsection['sectionnum'],
            visible: 1
        );

        $this->assertTrue($fileresult['success']);

        $subsection_section = $DB->get_record('course_sections', ['id' => $subsection['id']]);
        $sequence = array_map('intval', explode(',', $subsection_section->sequence ?? ''));
        $this->assertContains($fileresult['coursemoduleid'], $sequence);

        $filecm = $DB->get_record('course_modules', ['id' => $fileresult['coursemoduleid']]);
        $this->assertEquals($subsection['id'], $filecm->section);
    }

    public function test_activity_visibility_respects_hidden_subsection(): void {
        global $DB;

        $subsection = create_subsection::execute(
            courseid: $this->course->id,
            parentsection: 1,
            name: 'Hidden Subsection',
            visible: 0
        );

        $pageresult = create_page::execute(
            courseid: $this->course->id,
            name: 'Page in Hidden Subsection',
            content: 'This content is in a hidden subsection',
            section: $subsection['sectionnum'],
            visible: 1
        );

        $pagecm = $DB->get_record('course_modules', ['id' => $pageresult['coursemoduleid']]);
        $this->assertEquals(0, $pagecm->visible, 'Page should be hidden when its subsection is hidden');
    }

    public function test_multiple_activities_in_subsection(): void {
        global $DB;

        $subsection = create_subsection::execute(
            courseid: $this->course->id,
            parentsection: 1,
            name: 'Multi-activity Subsection',
            visible: 1
        );

        $page1 = create_page::execute(
            courseid: $this->course->id,
            name: 'Page 1',
            section: $subsection['sectionnum']
        );
        $page2 = create_page::execute(
            courseid: $this->course->id,
            name: 'Page 2',
            section: $subsection['sectionnum']
        );
        $page3 = create_page::execute(
            courseid: $this->course->id,
            name: 'Page 3',
            section: $subsection['sectionnum']
        );

        $subsection_section = $DB->get_record('course_sections', ['id' => $subsection['id']]);
        $sequence = array_map('intval', explode(',', $subsection_section->sequence ?? ''));

        $page1pos = array_search($page1['coursemoduleid'], $sequence);
        $page2pos = array_search($page2['coursemoduleid'], $sequence);
        $page3pos = array_search($page3['coursemoduleid'], $sequence);

        $this->assertNotFalse($page1pos);
        $this->assertNotFalse($page2pos);
        $this->assertNotFalse($page3pos);
        $this->assertLessThan($page2pos, $page1pos);
        $this->assertLessThan($page3pos, $page2pos);
    }

    public function test_nested_subsections(): void {
        global $DB;

        $level1 = create_subsection::execute(
            courseid: $this->course->id,
            parentsection: 1,
            name: 'Level 1 Subsection',
            visible: 1
        );

        $level2 = create_subsection::execute(
            courseid: $this->course->id,
            parentsection: $level1['sectionnum'],
            name: 'Level 2 Subsection',
            visible: 1
        );

        $pageresult = create_page::execute(
            courseid: $this->course->id,
            name: 'Nested Page',
            section: $level2['sectionnum'],
            visible: 1
        );

        $level2section = $DB->get_record('course_sections', ['id' => $level2['id']]);
        $this->assertEquals('mod_subsection', $level2section->component);

        $pagesection = $DB->get_record('course_sections', ['id' => $level2['id']]);
        $sequence = array_map('intval', explode(',', $pagesection->sequence ?? ''));
        $this->assertContains($pageresult['coursemoduleid'], $sequence);
    }

    public function test_course_cache_rebuilt(): void {
        global $CFG;

        $subsection = create_subsection::execute(
            courseid: $this->course->id,
            parentsection: 1,
            name: 'Cache Test',
            visible: 1
        );

        create_page::execute(
            courseid: $this->course->id,
            name: 'Cache Test Page',
            section: $subsection['sectionnum']
        );

        require_once($CFG->dirroot . '/course/lib.php');
        $modinfo = get_fast_modinfo($this->course);

        $this->assertNotNull($modinfo);
        $this->assertGreater(count($modinfo->sections), 0);
    }
}
