<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once($CFG->dirroot.'/grade/export/lib.php');

class grade_export_xls extends grade_export {

    public $plugin = 'xls';

    /**
     * Constructor should set up all the private variables ready to be pulled
     * @param object $course
     * @param int $groupid id of selected group, 0 means all
     * @param stdClass $formdata The validated data from the grade export form.
     */
    public function __construct($course, $groupid, $formdata) {
        parent::__construct($course, $groupid, $formdata);

        // Overrides.
        $this->usercustomfields = true;
    }

    /**
     * To be implemented by child classes
     */
    public function print_grades() {
        global $CFG;
        require_once($CFG->dirroot.'/lib/excellib.class.php');

        $export_tracking = $this->track_exports();

        $strgrades = get_string('grades');

        // Calculate file name
        $shortname = format_string($this->course->shortname, true, array('context' => context_course::instance($this->course->id)));
        $downloadfilename = clean_filename("$shortname $strgrades.xls");
        // Creating a workbook
        $workbook = new MoodleExcelWorkbook("-");
        // Sending HTTP headers
        $workbook->send($downloadfilename);
        // Adding the worksheet
        $myxls = $workbook->add_worksheet($strgrades);

        // add Header of grading

        // add format of cell
        $header_str = ["ព្រះរាជាណាចក្រកម្ពុជា", "ជាតិ        សាសនា         ព្រះមហាក្សត្រ","ក្រសួងអប់រំ យុវជន និងកីឡា" , "សាកលវិទ្យាល័យភូមិន្ទភ្នំពេញ", "តារាងសម្រង់វត្តមាន និង​វាយតំលៃបញ្ចប់ឆមាសទី២ របស់និស្សិត​ឆ្នាំទី៣( ឆ្នាំសិក្សា ២០១៧ - ២០១៨)", "មហាវិទ្យាល៍យវិស្វកម្មឯកទេស   វិស្វកម្មបច្ចេវិទ្យាពត៍មាន (អាហារូបករណ៍)   Group A  មុខវិជ្ជា:", ""];
        $formatCenter = $workbook->add_format();
        $formatCenter->set_align('center');
        $formatCenter->set_bottom(4);
        $num = 0;
        foreach($header_str as $item){
            $myxls->set_row($num, 17, $formatCenter);
            $myxls->merge_cells($num, 0, $num, 10);
            $myxls->write_string($num, 0, $item);
            $num++;
        }

        $startRow = 7;
        // Print names of all the fields
        $profilefields = grade_helper::get_user_profile_fields($this->course->id, $this->usercustomfields);
//        foreach ($profilefields as $id => $field) {
//            $myxls->write_string($startRow, $id, $field->fullname);
//        }

        //TODO generate header
        $header1_str = ["ល រ", "គោត្តនាម នាម", "ភេទ", "វត្តមាន និងអវត្តមាន", "វាយតម្លៃក្នុងថ្នាក់​ ៤០%=៤/១០", "ពិន្ទុកប្រឡងឆមាស", "ពិន្ទុកសរុបរួម", "ផ្សេងៗ"];
        $col = 0;
        foreach ($header1_str as $item){
            $myxls->write_string($startRow, $col, $item);
            $col++;
        }
//
//        $pos = count($profilefields);
//        if (!$this->onlyactive) {
//            $myxls->write_string($startRow, $pos++, get_string("suspended"));
//        }
//        foreach ($this->columns as $grade_item) {
//            foreach ($this->displaytype as $gradedisplayname => $gradedisplayconst) {
//                $myxls->write_string($startRow, $pos++, $this->format_column_name($grade_item, false, $gradedisplayname));
//            }
//            // Add a column_feedback column
//            if ($this->export_feedback) {
//                $myxls->write_string($startRow, $pos++, $this->format_column_name($grade_item, true));
//            }
//        }
//        // Last downloaded column header.
//        $myxls->write_string($startRow, $pos++, get_string('timeexported', 'gradeexport_xls'));

        // Print all the lines of data.
        $i = $startRow;
        $geub = new grade_export_update_buffer();
        $gui = new graded_users_iterator($this->course, $this->columns, $this->groupid);
        $gui->require_active_enrolment($this->onlyactive);
        $gui->allow_user_custom_fields($this->usercustomfields);
        $gui->init();
        while ($userdata = $gui->next_user()) {
            $i++;
            $user = $userdata->user;

            foreach ($profilefields as $id => $field) {
                $fieldvalue = grade_helper::get_user_field_value($user, $field);
                $myxls->write_string($i, $id, $fieldvalue);
            }
            $j = count($profilefields);
            if (!$this->onlyactive) {
                $issuspended = ($user->suspendedenrolment) ? get_string('yes') : '';
                $myxls->write_string($i, $j++, $issuspended);
            }
            foreach ($userdata->grades as $itemid => $grade) {
                if ($export_tracking) {
                    $status = $geub->track($grade);
                }
                foreach ($this->displaytype as $gradedisplayconst) {
                    $gradestr = $this->format_grade($grade, $gradedisplayconst);
                    if (is_numeric($gradestr)) {
                        $myxls->write_number($i, $j++, $gradestr);
                    } else {
                        $myxls->write_string($i, $j++, $gradestr);
                    }
                }
                // writing feedback if requested
                if ($this->export_feedback) {
                    $myxls->write_string($i, $j++, $this->format_feedback($userdata->feedbacks[$itemid]));
                }
            }
            // Time exported.
            $myxls->write_string($i, $j++, time());
        }

        $footerStr = ["កំណត់ចំណាំ:សាស្រ្តាចារ្យបង្រៀនទាំងអស់ត្រូវប្រគល់បញ្ជីវត្តមានដល់ការិយាល័យសិក្សាជារៀងរាល់ចុងខែ ។                                            រាជធានីភ្នំពេញ ថ្ងៃទី",
            "រាជធានីភ្នំពេញ ថ្ងៃទី                                                                                                      ​​​​​​​​​​​​​​​​​​​​​​​​​​                           សាស្រ្តាចារ្យ",
            "ប្រធានការិយាល័យសិក្សា",
            "វេង ឆាង"
            ];
        $row = $i+1;
        foreach ($footerStr as $item){
            $myxls->set_row($row, 17, $formatCenter);
            $myxls->merge_cells($row, 0, $row, 10);
            $myxls->write_string($row, 0, $item);
        }
        $gui->close();
        $geub->close();

    /// Close the workbook
        $workbook->close();

        exit;
    }
}


