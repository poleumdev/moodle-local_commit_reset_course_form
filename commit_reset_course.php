<?php

// for the form look at course/reset_form.php
// for the reset itself (this file) look at course/reset.php

require_once('../../config.php');
require_once('./commit_reset_course_form.php');

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');

$PAGE->set_title(get_string('reset'));
$PAGE->set_heading($SITE->fullname);

$PAGE->set_url("$CFG->wwwroot/local/commit/commit_reset_course.php");

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reset'), 2);

$form = new commit_reset_course_form();

if ($form_data = $form->get_data()) {

    if (isset($form_data->selectdefault)) {
        $_POST = array();
        $form = new commit_reset_course_form();
        $form->load_defaults();
    } else if (isset($form_data->deselectall)) {
        $_POST = array();
        $form = new commit_reset_course_form();
    } else {
        // Reset goes here

        $filter_courses = explode(',', $form_data->filter_courses);
        $filter_courses = array_map('trim', $filter_courses);

        if( empty($filter_courses) ) {
            $filter = '';
        } else {

            $filter = 'WHERE {course}.id ';

            // 1 is include, 0 is exclude.
            if ( '1' == $form_data->filter_type ) {
                // Make sure to exclude course 1 (home).
                $key = array_search('1', $filter_courses);
                if ( FALSE !== $key ) {
                    unset($filter_courses[$key]);
                }
                $filter .= 'IN(' . implode(',', $filter_courses) . ')';
            } else {
                // Make sure to exclude course 1.
                $filter_courses[] = 1;
                $filter .= 'NOT IN(' . implode(',', $filter_courses) . ')';
            }
        }

        if ( isset($form_data->enrol_plugins) ) {
            $enrol_plugins = array();
            foreach($form_data->enrol_plugins as $plugin) {
                $enrol_plugins[$plugin] = enrol_get_plugin($plugin);
            }
        }

        $courses = $DB->get_records_sql('SELECT {course}.id, {course}.startdate, {course}.fullname FROM {course} ' . $filter);
        $logfile = "$CFG->dataroot/commitresetcourse-" . date("YmdHis", $_SERVER['REQUEST_TIME']) . ".log";
        // FIXME translate.
        echo $OUTPUT->container("Fichier de log " . $logfile);
        foreach ($courses as $course) {
            // Set data for default moodle reset.
            $form_data->id = $course->id;
            $form_data->reset_start_date_old = $course->startdate;
            // Execute default moodle reset.
            $status = reset_course_userdata($form_data);

            // Enrol reset.
            if ( isset($form_data->enrol_plugins) ) {
                $instances = enrol_get_instances($course->id, false);

                foreach ($instances as $instance) {
                    // If enrol plugin not selected continue.
                    if ( !in_array($instance->enrol, $form_data->enrol_plugins) ) {
                        continue;
                    }
                    // Get enrol plugin.
                    $plugin = $enrol_plugins[$instance->enrol];
                    // Action is 0 for disable, 1 for delete.
                    if ( '1' == $form_data->enrol_plugins_action ) {
                        $plugin->delete_instance($instance);
                        $action = get_string('deleted');
                    } else {
                        if ($instance->status != ENROL_INSTANCE_DISABLED) {
                            $plugin->update_status($instance, ENROL_INSTANCE_DISABLED);
                        }
                        // FIXME translate this.
                        $action = 'désactivé';
                    }
                    $status[] = array(
                        'component' => 'enrol_plugin',
                        'item' => $instance->enrol,
                        'error' => $action
                    );
                }
            }
            $log = $course->id . " " . $course->fullname;
            echo $OUTPUT->heading($log, 2);
            file_put_contents($logfile, $log . PHP_EOL, FILE_APPEND);
            $data = array();
            foreach ($status as $item) {
                $line = array();
                $line[] = $item['component'];
                $line[] = $item['item'];
                $line[] = (false === $item['error']) ? get_string('ok') : $item['error'];
                $data[] = $line;
                $log = implode("\t", $line);
                file_put_contents($logfile, $log . PHP_EOL, FILE_APPEND);
            }

            $table = new html_table();
            $table->head  = array(get_string('resetcomponent'), get_string('resettask'), get_string('resetstatus'));
            $table->size  = array('20%', '40%', '40%');
            $table->align = array('left', 'left', 'left');
            $table->width = '80%';
            $table->data  = $data;
            echo html_writer::table($table);
        }

        echo $OUTPUT->footer();
        exit;
    }
}

$form->display();
echo $OUTPUT->footer();
