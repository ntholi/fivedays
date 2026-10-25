# Pre-check: ip-172-26-4-221.eu-central-1.compute.internal

Generated 2026-10-25 16:18 (UTC). Read-only.

## Host

```
os: Ubuntu 24.04.4 LTS
cpus: 2
                 total        used        free      shared  buff/cache   available
  Mem:           1.9Gi       984Mi       433Mi        65Mi       760Mi       923Mi
  Swap:             0B          0B          0B
  Filesystem      Size  Used Avail Use% Mounted on
  /dev/root        58G   27G   31G  47% /
php: 8.3.6
mariadb: 10.11.14-MariaDB-0ubuntu0.24.04.1
nginx: nginx version: nginx/1.24.0 (Ubuntu)
```

## Moodle code

```
root: /var/www/html/sites/moodle
version.php: /var/www/html/sites/moodle/public/version.php
$version  = 2026042002.01;              // 20260420      = branching date YYYYMMDD - do not modify!
$release  = '5.2.2+ (Build: 20260818)';    // Human-friendly version name
$branch   = '502';                      // This version's branch.
$maturity = MATURITY_STABLE;            // This version's maturity level.
git describe: v5.2.2-30-gb07dd04b40e
git HEAD: b07dd04b40ece3b2d43b2e6ec1213db2a997df7f
git branch: MOODLE_502_STABLE
remote: https://github.com/moodle/moodle.git
vendor/ tracked: no (present on disk: yes)
```

## Local changes to Moodle code (git status; content changes and untracked files, mode changes ignored)

```
?? public/grade/grading/form/fivedays/
?? public/local/fivedays/
```

## Schema files of installed plugins (sha256; cutover preflight compares them with the transfer image)

```
a4349e43aeebbc8027c0032f1a2ccb624a3cd200cd3fcc46a3d1656433094bfb  local/fivedays/db/install.xml
9a72179eca56f47a8dc91b43cac0be237469c7cf03ce2c433c5703bededb22a2  grade/grading/form/fivedays/db/install.xml
```

## config.php keys (values hidden except non-secret ones)

```
dbtype: mariadb
dblibrary: native
dbname: moodle
prefix: mdl_
wwwroot: https://luct.click
dataroot: /var/www/data/moodledata
all $CFG keys set in config.php:
  admin
  dataroot
  dbhost
  dblibrary
  dbname
  dboptions
  dbpass
  dbtype
  dbuser
  directorypermissions
  prefix
  slasharguments
  wwwroot
dboptions keys:
  dbcollation
  dbpersist
  dbport
  dbsocket
```

## Non-core plugins (uninstall_plugins.php --show-contrib, dry listing)

```
gradingform_fivedays	FiveDays Rubric
local_fivedays	FiveDays
```

## Settings

```
release: 5.2.2+ (Build: 20260818)
version: 2026042002.01
auth: email,oauth2
enablewebservices: 1
enablemobilewebservice: 1
webserviceprotocols: rest
maxbytes: 20971520
slasharguments: 1
routerconfigured: 
antiviruses: (empty)
smtphosts: (empty)
smtpsecure: (empty)
smtpauthtype: LOGIN
noreplyaddress: noreply@luct.click
noemailever: 
airnotifierurl: https://messages.moodle.net
airnotifierport: 443
airnotifiermobileappname: com.moodle.moodlemobile
airnotifierappname: commoodlemoodlemobile
smtpuser: empty
smtppass: empty
airnotifieraccesskey: empty
registration_hubs: https://stats.moodle.org confirmed=1 
airnotifier devices: 0
enabled message outputs: airnotifier,email,popup
tool_mobile typeoflogin: 1
clamav pathtoclam:   commandline  
```

## Web services

```
id	name	shortname	component	enabled	restrictedusers	requiredcapability	downloadfiles	uploadfiles	tokens	authorised_users	functions
1	Moodle mobile web service	moodle_mobile_app	moodle	1	0	NULL	1	1	409	0	429
2	FiveDays	fivedays	NULL	1	0		1	1	6654	0	109
```

## Functions on the FiveDays service (shortname fivedays)

```
core_course_create_courses
core_course_delete_modules
core_course_get_categories
core_course_get_contents
core_course_get_course_module
core_course_get_course_module_by_instance
core_course_get_courses_by_field
core_enrol_get_enrolled_users
core_enrol_get_enrolled_users_with_capability
core_enrol_get_users_courses
core_files_get_unused_draft_itemid
core_user_create_users
core_user_get_users
core_webservice_get_site_info
enrol_manual_enrol_users
enrol_manual_unenrol_users
local_fivedays_add_assignment_comment
local_fivedays_add_attempt_feedback
local_fivedays_add_book_chapter
local_fivedays_add_question_to_quiz
local_fivedays_copy_rubric
local_fivedays_create_assignment
local_fivedays_create_bigbluebuttonbn
local_fivedays_create_book
local_fivedays_create_draft_files
local_fivedays_create_essay_question
local_fivedays_create_file
local_fivedays_create_forum
local_fivedays_create_multichoice_question
local_fivedays_create_numerical_question
local_fivedays_create_page
local_fivedays_create_quiz
local_fivedays_create_rubric
local_fivedays_create_section
local_fivedays_create_shortanswer_question
local_fivedays_create_subsection
local_fivedays_create_truefalse_question
local_fivedays_create_url
local_fivedays_delete_assignment
local_fivedays_delete_assignment_comment
local_fivedays_delete_bigbluebuttonbn
local_fivedays_delete_book
local_fivedays_delete_course
local_fivedays_delete_file
local_fivedays_delete_forum
local_fivedays_delete_page
local_fivedays_delete_question
local_fivedays_delete_quiz
local_fivedays_delete_rubric
local_fivedays_delete_section
local_fivedays_delete_subsection
local_fivedays_delete_url
local_fivedays_enrol_users
local_fivedays_ensure_course
local_fivedays_ensure_google_login
local_fivedays_fill_rubric
local_fivedays_get_assignment
local_fivedays_get_assignment_comments
local_fivedays_get_assignment_grades
local_fivedays_get_attempt_feedback
local_fivedays_get_book
local_fivedays_get_draft_files
local_fivedays_get_or_create_question_category
local_fivedays_get_questions
local_fivedays_get_quiz
local_fivedays_get_quiz_attempt_details
local_fivedays_get_quiz_attempts
local_fivedays_get_rubric
local_fivedays_get_rubric_filling
local_fivedays_grade_essay_question
local_fivedays_list_question_categories
local_fivedays_provision_users
local_fivedays_rebind_user_email
local_fivedays_release_assignment_grades
local_fivedays_remove_question_from_quiz
local_fivedays_reorder_quiz_questions
local_fivedays_save_assignment_grade
local_fivedays_unenrol_users
local_fivedays_update_assignment
local_fivedays_update_assignment_comment
local_fivedays_update_bigbluebuttonbn
local_fivedays_update_book
local_fivedays_update_book_chapter
local_fivedays_update_file
local_fivedays_update_page
local_fivedays_update_quiz
local_fivedays_update_quiz_slot_mark
local_fivedays_update_rubric
local_fivedays_update_section
local_fivedays_update_subsection
local_fivedays_update_url
mod_assign_get_assignments
mod_assign_get_grades
mod_assign_get_submission_status
mod_assign_get_submissions
mod_assign_save_grade
mod_assign_save_grades
mod_bigbluebuttonbn_get_join_url
mod_forum_add_discussion
mod_forum_add_discussion_post
mod_forum_delete_post
mod_forum_get_discussion_posts
mod_forum_get_discussion_posts_by_userid
mod_forum_get_forum_discussions
mod_forum_get_forums_by_courses
mod_page_get_pages_by_courses
mod_page_view_page
mod_quiz_get_quizzes_by_courses
mod_url_get_urls_by_courses
```

## Web-service tokens (counts only)

```
service	tokentype	tokens	users	creators	used_30d	expiring
1	0	409	409	409	325	409
2	0	6654	6654	1	859	0
```
```
service	creatorid	creator	tokens
2	3	ntholi.nkhatho@limkokwing.ac.ls	6654
```

## Outgoing mail (smtphosts empty means PHP mail() through the host's sendmail)

```
sendmail binary: none 
php sendmail_path: /usr/sbin/sendmail -t -i
postfix: not installed / inactive
exim4: not installed / inactive
msmtp: not installed / inactive
nullmailer: not installed / inactive
mail.log lines: 0
mail.log status counts (last 2 files):
mail.log last delivery line date: 
outbound port 25 reachable (gmail-smtp-in): no
```

## OAuth2 issuers

```
id	name	enabled	requireconfirmation	alloweddomains
1	Google	1	0	
```

## Data size

```
moodledata: 1018M
filedir: 955M, 7265 files
mdl_files rows: 12163
distinct contenthash: 7264
DB size (MB): 1768
backups dir: moodle_backup_2026-10-23.sql moodle_backup_2026-10-24.sql moodle_backup_2026-10-25.sql 
```

## Cron

```
www-data:
  * * * * * /usr/bin/php /var/www/html/sites/moodle/admin/cli/cron.php >/dev/null
root:
  0 2 * * * mysqldump --defaults-file=/root/.my.cnf moodle > /var/backups/moodle/moodle_backup_$(date +\%F).sql
  0 3 * * * find /var/backups/moodle -name "moodle_backup_*.sql" -type f -mtime +7 -delete
```

## Row count per table (baseline for just verify)

```
mdl_adminpresets	2
mdl_adminpresets_app	0
mdl_adminpresets_app_it	0
mdl_adminpresets_app_it_a	0
mdl_adminpresets_app_plug	0
mdl_adminpresets_it	22
mdl_adminpresets_it_a	0
mdl_adminpresets_plug	103
mdl_ai_action_explain_text	0
mdl_ai_action_generate_image	0
mdl_ai_action_generate_text	0
mdl_ai_action_register	0
mdl_ai_action_summarise_text	0
mdl_ai_policy_register	0
mdl_ai_providers	0
mdl_analytics_indicator_calc	0
mdl_analytics_models	5
mdl_analytics_models_log	0
mdl_analytics_predictions	0
mdl_analytics_prediction_actions	0
mdl_analytics_predict_samples	0
mdl_analytics_train_samples	0
mdl_analytics_used_analysables	0
mdl_analytics_used_files	0
mdl_assign	117
mdl_assignfeedback_comments	0
mdl_assignfeedback_editpdf_annot	0
mdl_assignfeedback_editpdf_cmnt	0
mdl_assignfeedback_editpdf_quick	0
mdl_assignfeedback_editpdf_rot	3
mdl_assignfeedback_file	0
mdl_assignsubmission_file	784
mdl_assignsubmission_onlinetext	0
mdl_assign_allocated_marker	6
mdl_assign_grades	367
mdl_assign_mark	0
mdl_assign_overrides	0
mdl_assign_plugin_config	1263
mdl_assign_submission	1615
mdl_assign_user_flags	361
mdl_assign_user_mapping	1405
mdl_auth_lti_linked_login	0
mdl_auth_oauth2_linked_login	6674
mdl_backup_controllers	93
mdl_backup_courses	0
mdl_backup_logs	0
mdl_badge	0
mdl_badge_alignment	0
mdl_badge_backpack	0
mdl_badge_backpack_oauth2	0
mdl_badge_criteria	0
mdl_badge_criteria_met	0
mdl_badge_criteria_param	0
mdl_badge_endorsement	0
mdl_badge_external	0
mdl_badge_external_backpack	1
mdl_badge_external_identifier	0
mdl_badge_issued	0
mdl_badge_manual_award	0
mdl_badge_related	0
mdl_bigbluebuttonbn	2
mdl_bigbluebuttonbn_logs	0
mdl_bigbluebuttonbn_recordings	0
mdl_block	40
mdl_block_instances	2000
mdl_block_positions	0
mdl_block_recentlyaccesseditems	2993
mdl_block_recent_activity	0
mdl_block_rss_client	0
mdl_blog_association	0
mdl_blog_external	0
mdl_book	1
mdl_book_chapters	3
mdl_cache_filters	0
mdl_cache_flags	40
mdl_capabilities	800
mdl_choice	0
mdl_choice_answers	0
mdl_choice_options	0
mdl_cohort	0
mdl_cohort_members	0
mdl_comments	44
mdl_communication	0
mdl_communication_customlink	0
mdl_communication_user	0
mdl_competency	0
mdl_competency_coursecomp	0
mdl_competency_coursecompsetting	0
mdl_competency_evidence	0
mdl_competency_framework	0
mdl_competency_modulecomp	0
mdl_competency_plan	0
mdl_competency_plancomp	0
mdl_competency_relatedcomp	0
mdl_competency_template	0
mdl_competency_templatecohort	0
mdl_competency_templatecomp	0
mdl_competency_usercomp	0
mdl_competency_usercompcourse	0
mdl_competency_usercompplan	0
mdl_competency_userevidence	0
mdl_competency_userevidencecomp	0
mdl_config	590
mdl_config_log	1865
mdl_config_plugins	2035
mdl_contentbank_content	0
mdl_context	10250
mdl_context_temp	0
mdl_course	690
mdl_course_categories	10
mdl_course_completions	761
mdl_course_completion_aggr_methd	0
mdl_course_completion_criteria	0
mdl_course_completion_crit_compl	0
mdl_course_completion_defaults	0
mdl_course_format_options	1381
mdl_course_modules	874
mdl_course_modules_completion	0
mdl_course_modules_viewed	0
mdl_course_published	0
mdl_course_request	0
mdl_course_sections	787
mdl_customfield_category	0
mdl_customfield_data	0
mdl_customfield_field	0
mdl_customfield_shared	0
mdl_data	0
mdl_data_content	0
mdl_data_fields	0
mdl_data_records	0
mdl_enrol	2067
mdl_enrol_flatfile	0
mdl_enrol_lti_app_registration	0
mdl_enrol_lti_context	0
mdl_enrol_lti_deployment	0
mdl_enrol_lti_lti2_consumer	0
mdl_enrol_lti_lti2_context	0
mdl_enrol_lti_lti2_nonce	0
mdl_enrol_lti_lti2_resource_link	0
mdl_enrol_lti_lti2_share_key	0
mdl_enrol_lti_lti2_tool_proxy	0
mdl_enrol_lti_lti2_user_result	0
mdl_enrol_lti_resource_link	0
mdl_enrol_lti_tools	0
mdl_enrol_lti_tool_consumer_map	0
mdl_enrol_lti_users	0
mdl_enrol_lti_user_resource_link	0
mdl_enrol_paypal	0
mdl_event	155
mdl_events_handlers	0
mdl_events_queue	0
mdl_events_queue_handlers	0
mdl_event_subscriptions	0
mdl_external_functions	830
mdl_external_services	2
mdl_external_services_functions	538
mdl_external_services_users	0
mdl_external_tokens	7063
mdl_favourite	462
mdl_feedback	0
mdl_feedback_completed	0
mdl_feedback_completedtmp	0
mdl_feedback_item	0
mdl_feedback_sitecourse_map	0
mdl_feedback_template	0
mdl_feedback_value	0
mdl_feedback_valuetmp	0
mdl_files	12163
mdl_files_reference	0
mdl_file_conversion	0
mdl_filter_active	6
mdl_filter_config	0
mdl_folder	0
mdl_forum	690
mdl_forum_digests	0
mdl_forum_discussions	17
mdl_forum_discussion_subs	1
mdl_forum_grades	0
mdl_forum_posts	17
mdl_forum_queue	0
mdl_forum_read	0
mdl_forum_subscriptions	0
mdl_forum_track_prefs	0
mdl_glossary	0
mdl_glossary_alias	0
mdl_glossary_categories	0
mdl_glossary_entries	0
mdl_glossary_entries_categories	0
mdl_glossary_formats	7
mdl_gradepenalty_duedate_rule	1
mdl_grade_categories	114
mdl_grade_categories_history	228
mdl_grade_grades	3053
mdl_grade_grades_history	5229
mdl_grade_import_newitem	0
mdl_grade_import_values	0
mdl_grade_items	257
mdl_grade_items_history	1575
mdl_grade_letters	0
mdl_grade_outcomes	0
mdl_grade_outcomes_courses	0
mdl_grade_outcomes_history	0
mdl_grade_settings	0
mdl_gradingform_fivedays_criteria	68
mdl_gradingform_fivedays_fillings	6067
mdl_gradingform_fivedays_levels	378
mdl_gradingform_guide_comments	0
mdl_gradingform_guide_criteria	0
mdl_gradingform_guide_fillings	0
mdl_gradingform_rubric_criteria	0
mdl_gradingform_rubric_fillings	0
mdl_gradingform_rubric_levels	0
mdl_grading_areas	20
mdl_grading_definitions	9
mdl_grading_instances	977
mdl_groupings	0
mdl_groupings_groups	0
mdl_groups	0
mdl_groups_members	0
mdl_h5p	0
mdl_h5pactivity	0
mdl_h5pactivity_attempts	0
mdl_h5pactivity_attempts_results	0
mdl_h5p_contents_libraries	0
mdl_h5p_libraries	159
mdl_h5p_libraries_cachedassets	0
mdl_h5p_library_dependencies	502
mdl_imscp	0
mdl_infected_files	0
mdl_label	0
mdl_lesson	2
mdl_lesson_answers	0
mdl_lesson_attempts	0
mdl_lesson_branch	0
mdl_lesson_grades	0
mdl_lesson_overrides	0
mdl_lesson_pages	0
mdl_lesson_timer	0
mdl_license	9
mdl_local_fivedays_quizfeedback	0
mdl_lock_db	0
mdl_log	0
mdl_logstore_standard_log	4752574
mdl_log_display	183
mdl_log_queries	0
mdl_lti	0
mdl_ltiservice_gradebookservices	0
mdl_lti_access_tokens	0
mdl_lti_coursevisible	0
mdl_lti_submission	0
mdl_lti_tool_proxies	0
mdl_lti_tool_settings	0
mdl_lti_types	0
mdl_lti_types_categories	0
mdl_lti_types_config	0
mdl_matrix_room	0
mdl_message	0
mdl_messageinbound_datakeys	0
mdl_messageinbound_handlers	3
mdl_messageinbound_messagelist	0
mdl_messages	99
mdl_message_airnotifier_devices	0
mdl_message_contacts	4
mdl_message_contact_requests	6
mdl_message_conversations	496
mdl_message_conversation_actions	0
mdl_message_conversation_members	531
mdl_message_email_messages	0
mdl_message_popup	0
mdl_message_popup_notifications	1574392
mdl_message_processors	4
mdl_message_providers	51
mdl_message_read	0
mdl_message_users_blocked	0
mdl_message_user_actions	52
mdl_mnet_application	2
mdl_mnet_host	2
mdl_mnet_host2service	0
mdl_mnet_log	0
mdl_mnet_remote_rpc	0
mdl_mnet_remote_service2rpc	0
mdl_mnet_rpc	0
mdl_mnet_service	0
mdl_mnet_service2rpc	0
mdl_mnet_session	0
mdl_mnet_sso_access_control	0
mdl_modules	23
mdl_my_pages	781
mdl_notifications	1577280
mdl_oauth2_access_token	0
mdl_oauth2_endpoint	6
mdl_oauth2_issuer	1
mdl_oauth2_refresh_token	0
mdl_oauth2_system_account	0
mdl_oauth2_user_field_mapping	9
mdl_page	0
mdl_paygw_paypal	0
mdl_payments	0
mdl_payment_accounts	0
mdl_payment_gateways	0
mdl_portfolio_instance	0
mdl_portfolio_instance_config	0
mdl_portfolio_instance_user	0
mdl_portfolio_log	0
mdl_portfolio_tempdata	0
mdl_post	0
mdl_profiling	0
mdl_qbank	4
mdl_qtype_ddimageortext	0
mdl_qtype_ddimageortext_drags	0
mdl_qtype_ddimageortext_drops	0
mdl_qtype_ddmarker	0
mdl_qtype_ddmarker_drags	0
mdl_qtype_ddmarker_drops	0
mdl_qtype_essay_options	47
mdl_qtype_match_options	0
mdl_qtype_match_subquestions	0
mdl_qtype_multichoice_options	0
mdl_qtype_ordering_options	0
mdl_qtype_randomsamatch_options	0
mdl_qtype_shortanswer_options	8
mdl_question	55
mdl_question_answers	8
mdl_question_attempts	893
mdl_question_attempt_steps	3056
mdl_question_attempt_step_data	4408
mdl_question_bank_entries	27
mdl_question_calculated	0
mdl_question_calculated_options	0
mdl_question_categories	54
mdl_question_datasets	0
mdl_question_dataset_definitions	0
mdl_question_dataset_items	0
mdl_question_ddwtos	0
mdl_question_gapselect	0
mdl_question_hints	0
mdl_question_multianswer	0
mdl_question_numerical	0
mdl_question_numerical_options	0
mdl_question_numerical_units	0
mdl_question_references	23
mdl_question_response_analysis	52
mdl_question_response_count	295
mdl_question_set_references	0
mdl_question_statistics	8
mdl_question_truefalse	0
mdl_question_usages	200
mdl_question_versions	55
mdl_quiz	24
mdl_quizaccess_seb_quizsettings	0
mdl_quizaccess_seb_template	0
mdl_quiz_attempts	200
mdl_quiz_feedback	4
mdl_quiz_grades	145
mdl_quiz_grade_items	0
mdl_quiz_overrides	0
mdl_quiz_overview_regrades	0
mdl_quiz_reports	4
mdl_quiz_sections	24
mdl_quiz_slots	23
mdl_quiz_statistics	2
mdl_rating	0
mdl_registration_hubs	1
mdl_reportbuilder_audience	0
mdl_reportbuilder_column	0
mdl_reportbuilder_filter	0
mdl_reportbuilder_report	3
mdl_reportbuilder_schedule	0
mdl_reportbuilder_user_filter	1
mdl_repository	8
mdl_repository_instances	8
mdl_repository_instance_config	0
mdl_repository_onedrive_access	0
mdl_resource	26
mdl_resource_old	0
mdl_role	9
mdl_role_allow_assign	7
mdl_role_allow_override	11
mdl_role_allow_switch	9
mdl_role_allow_view	24
mdl_role_assignments	26448
mdl_role_capabilities	1628
mdl_role_context_levels	12
mdl_role_names	0
mdl_scale	2
mdl_scale_history	0
mdl_scorm	0
mdl_scorm_aicc_session	0
mdl_scorm_attempt	0
mdl_scorm_element	0
mdl_scorm_scoes	0
mdl_scorm_scoes_data	0
mdl_scorm_scoes_value	0
mdl_scorm_seq_mapinfo	0
mdl_scorm_seq_objective	0
mdl_scorm_seq_rolluprule	0
mdl_scorm_seq_rolluprulecond	0
mdl_scorm_seq_rulecond	0
mdl_scorm_seq_ruleconds	0
mdl_search_index_requests	0
mdl_search_simpledb_index	0
mdl_sessions	28
mdl_shortlink	3698
mdl_sms_gateways	0
mdl_sms_messages	0
mdl_stats_daily	0
mdl_stats_monthly	0
mdl_stats_user_daily	0
mdl_stats_user_monthly	0
mdl_stats_user_weekly	0
mdl_stats_weekly	0
mdl_stored_progress	0
mdl_subsection	2
mdl_tag	0
mdl_tag_area	13
mdl_tag_coll	1
mdl_tag_correlation	0
mdl_tag_instance	0
mdl_task_adhoc	99
mdl_task_log	3209
mdl_task_scheduled	120
mdl_tiny_autosave	200
mdl_tool_brickfield_areas	0
mdl_tool_brickfield_cache_acts	0
mdl_tool_brickfield_cache_check	0
mdl_tool_brickfield_checks	31
mdl_tool_brickfield_content	0
mdl_tool_brickfield_errors	0
mdl_tool_brickfield_process	0
mdl_tool_brickfield_results	0
mdl_tool_brickfield_schedule	0
mdl_tool_brickfield_summary	0
mdl_tool_cohortroles	0
mdl_tool_customlang	0
mdl_tool_customlang_components	0
mdl_tool_dataprivacy_category	0
mdl_tool_dataprivacy_contextlist	0
mdl_tool_dataprivacy_ctxexpired	0
mdl_tool_dataprivacy_ctxinstance	0
mdl_tool_dataprivacy_ctxlevel	0
mdl_tool_dataprivacy_ctxlst_ctx	0
mdl_tool_dataprivacy_purpose	0
mdl_tool_dataprivacy_purposerole	0
mdl_tool_dataprivacy_request	8
mdl_tool_dataprivacy_rqst_ctxlst	0
mdl_tool_mfa	0
mdl_tool_mfa_auth	0
mdl_tool_mfa_secrets	0
mdl_tool_monitor_events	0
mdl_tool_monitor_history	0
mdl_tool_monitor_rules	0
mdl_tool_monitor_subscriptions	0
mdl_tool_policy	0
mdl_tool_policy_acceptances	0
mdl_tool_policy_versions	0
mdl_tool_recyclebin_category	0
mdl_tool_recyclebin_course	0
mdl_tool_usertours_steps	9
mdl_tool_usertours_tours	5
mdl_upgrade_log	2568
mdl_url	6
mdl_user	6683
mdl_user_devices	469
mdl_user_enrolments	26446
mdl_user_info_category	1
mdl_user_info_data	6
mdl_user_info_field	1
mdl_user_lastaccess	1641
mdl_user_password_history	0
mdl_user_password_resets	0
mdl_user_preferences	7453
mdl_user_private_key	1110
mdl_wiki	0
mdl_wiki_links	0
mdl_wiki_locks	0
mdl_wiki_pages	0
mdl_wiki_subwikis	0
mdl_wiki_synonyms	0
mdl_wiki_versions	0
mdl_workshop	0
mdl_workshopallocation_scheduled	0
mdl_workshopeval_best_settings	0
mdl_workshopform_accumulative	0
mdl_workshopform_comments	0
mdl_workshopform_numerrors	0
mdl_workshopform_numerrors_map	0
mdl_workshopform_rubric	0
mdl_workshopform_rubric_config	0
mdl_workshopform_rubric_levels	0
mdl_workshop_aggregations	0
mdl_workshop_assessments	0
mdl_workshop_grades	0
mdl_workshop_submissions	0
mdl_xapi_states	0
```
