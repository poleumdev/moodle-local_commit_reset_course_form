<?php
defined('MOODLE_INTERNAL') || die;


require_once("$CFG->libdir/formslib.php");

class commit_reset_course_form extends moodleform {
    public function definition() {
        global $CFG, $DB, $COURSE;

        $mform =& $this->_form;

        $mform->addElement('header', 'commit_header_filter_course', 'Course filtre');

        $filter_type = array();
        $filter_type[] =& $mform->createElement('radio', 'filter_type', '', 'inclure', 1);
        $filter_type[] =& $mform->createElement('radio', 'filter_type', '', 'exclure', 0);
        $mform->addGroup($filter_type, 'filter_type', 'Filtre', array(' '), false);
        $mform->setDefault('filter_type', 1);

        $mform->addElement(
            'textarea',
            'filter_courses',
            'ID des cours à exclure ou inclure de la réinitialisation séparés par virgule sans espaces !'
        );

        $mform->addElement('header', 'commit_header_enrol_plugins', get_string('enrolmentinstances', 'enrol'));

        // get only enabled plugins
        $enrol_plugins = array_keys(enrol_get_plugins(true));
        $enrol_plugins = array_combine($enrol_plugins, $enrol_plugins);

        $mform->addElement('select', 'enrol_plugins', get_string('plugin'), $enrol_plugins, array('multiple' => 'multiple', 'size' => count($enrol_plugins)));

        $enrol_plugins_action = array();
        $enrol_plugins_action[] =& $mform->createElement('radio', 'enrol_plugins_action', '', get_string('delete'), 1);
        $enrol_plugins_action[] =& $mform->createElement('radio', 'enrol_plugins_action', '', get_string('disable'), 0);
        $mform->addGroup($enrol_plugins_action, 'filter_type', get_string('action'), array(' '), false);

        // reset form starts here
        $mform->addElement('header', 'generalheader', get_string('general'));

        $mform->addElement('date_selector', 'reset_start_date', get_string('startdate'), array('optional'=>true));
        $mform->addHelpButton('reset_start_date', 'startdate');
        $mform->addElement('checkbox', 'reset_events', get_string('deleteevents', 'calendar'));
        $mform->addElement('checkbox', 'reset_notes', get_string('deletenotes', 'notes'));
        $mform->addElement('checkbox', 'reset_comments', get_string('deleteallcomments', 'moodle'));
        $mform->addElement('checkbox', 'reset_completion', get_string('deletecompletiondata', 'completion'));
        $mform->addElement('checkbox', 'delete_blog_associations', get_string('deleteblogassociations', 'blog'));
        $mform->addHelpButton('delete_blog_associations', 'deleteblogassociations', 'blog');


        $mform->addElement('header', 'rolesheader', get_string('roles'));

        $roles = get_assignable_roles(context_course::instance($COURSE->id));
        $roles[0] = get_string('noroles', 'role');
        $roles = array_reverse($roles, true);

        $mform->addElement('select', 'unenrol_users', get_string('unenrolroleusers', 'enrol'), $roles, array('multiple' => 'multiple'));
        $mform->addElement('checkbox', 'reset_roles_overrides', get_string('deletecourseoverrides', 'role'));
        $mform->setAdvanced('reset_roles_overrides');
        $mform->addElement('checkbox', 'reset_roles_local', get_string('deletelocalroles', 'role'));


        $mform->addElement('header', 'gradebookheader', get_string('gradebook', 'grades'));

        $mform->addElement('checkbox', 'reset_gradebook_items', get_string('removeallcourseitems', 'grades'));
        $mform->addElement('checkbox', 'reset_gradebook_grades', get_string('removeallcoursegrades', 'grades'));
        $mform->disabledIf('reset_gradebook_grades', 'reset_gradebook_items', 'checked');

        $mform->addElement('header', 'groupheader', get_string('groups'));

        $mform->addElement('checkbox', 'reset_groups_remove', get_string('deleteallgroups', 'group'));
        $mform->setAdvanced('reset_groups_remove');
        $mform->addElement('checkbox', 'reset_groups_members', get_string('removegroupsmembers', 'group'));
        $mform->setAdvanced('reset_groups_members');
        $mform->disabledIf('reset_groups_members', 'reset_groups_remove', 'checked');

        $mform->addElement('checkbox', 'reset_groupings_remove', get_string('deleteallgroupings', 'group'));
        $mform->setAdvanced('reset_groupings_remove');
        $mform->addElement('checkbox', 'reset_groupings_members', get_string('removegroupingsmembers', 'group'));
        $mform->setAdvanced('reset_groupings_members');
        $mform->disabledIf('reset_groupings_members', 'reset_groupings_remove', 'checked');

        $unsupported_mods = array();
        if ($allmods = $DB->get_records('modules') ) {
            foreach ($allmods as $mod) {
                $modname = $mod->name;
                $modfile = $CFG->dirroot."/mod/$modname/lib.php";
                $mod_reset_course_form_definition = $modname.'_reset_course_form_definition';
                $mod_reset__userdata = $modname.'_reset_userdata';
                if (file_exists($modfile)) {
                    include_once($modfile);
                    if (function_exists($mod_reset_course_form_definition)) {
                        $mod_reset_course_form_definition($mform);
                    } else if (!function_exists($mod_reset__userdata)) {
                        $unsupported_mods[] = $mod;
                    }
                } else {
                    debugging('Missing lib.php in '.$modname.' module');
                }
            }
        }
        // mention unsupported mods
        if (!empty($unsupported_mods)) {
            $mform->addElement('header', 'unsupportedheader', get_string('resetnotimplemented'));
            foreach($unsupported_mods as $mod) {
                $mform->addElement('static', 'unsup'.$mod->name, get_string('modulenameplural', $mod->name));
                $mform->setAdvanced('unsup'.$mod->name);
            }
        }

        $buttonarray = array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('resetcourse'));
        $buttonarray[] = &$mform->createElement('submit', 'selectdefault', get_string('selectdefault'));
        $buttonarray[] = &$mform->createElement('submit', 'deselectall', get_string('deselectall'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    function load_defaults() {
        global $CFG, $COURSE, $DB;

        $mform =& $this->_form;

        $defaults = array ('reset_events'=>1, 'reset_roles_local'=>1, 'reset_gradebook_grades'=>1, 'reset_notes'=>1);

        // Set student as default in unenrol user list, if role with student archetype exist.
        if ($studentrole = get_archetype_roles('student')) {
            $defaults['unenrol_users'] = array_keys($studentrole);
        }

        if ($allmods = $DB->get_records('modules') ) {
            foreach ($allmods as $mod) {
                $modname = $mod->name;
                $modfile = $CFG->dirroot."/mod/$modname/lib.php";
                $mod_reset_course_form_defaults = $modname.'_reset_course_form_defaults';
                if (file_exists($modfile)) {
                    @include_once($modfile);
                    if (function_exists($mod_reset_course_form_defaults)) {
                        if ($moddefs = $mod_reset_course_form_defaults($COURSE)) {
                            $defaults = $defaults + $moddefs;
                        }
                    }
                }
            }
        }

        foreach ($defaults as $element=>$default) {
            $mform->setDefault($element, $default);
        }
    }
}
