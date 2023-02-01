<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     local_similarity
 * @category    string
 * @copyright   2022 Belami <nizebelami@hotmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php'); 

class local_similarity_message_form extends moodleform {
    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form; // Don't forget the underscore! 

        $mform->addElement('textarea', 'message', get_string('yourmessage', 'local_similarity'));
        $mform->setType('message', PARAM_TEXT);

        $radioarray=array();
        /*$radioarray[] = $mform->createElement('radio', 'yesno', '', get_string('yes'), 1, "");
        $radioarray[] = $mform->createElement('radio', 'yesno', '', get_string('no'), 1, "");*/

        $radioarray[] = $mform->createElement('radio', 'similarity', '', get_string('jaccard', 'local_similarity'), 0, "");
        $radioarray[] = $mform->createElement('radio', 'similarity', '', get_string('dice', 'local_similarity'), 1, "");
        $radioarray[] = $mform->createElement('radio', 'similarity', '', get_string('cosine', 'local_similarity'), 2, "");
        $radioarray[] = $mform->createElement('radio', 'similarity', '', get_string('cosine2', 'local_similarity'), 3, "");
        $radioarray[] = $mform->createElement('radio', 'similarity', '', get_string('cosine3', 'local_similarity'), 4, "");
        $radioarray[] = $mform->createElement('radio', 'similarity', '', get_string('euclidean', 'local_similarity'), 5, "");
        $radioarray[] = $mform->createElement('radio', 'similarity', '', get_string('euclidean1', 'local_similarity'), 6, "");
        $radioarray[] = $mform->createElement('radio', 'similarity', '', get_string('euclidean2', 'local_similarity'), 7, "");


        $mform->addGroup($radioarray, 'radioar', '', array(' '), false);
        
        $submitlabel = get_string('submit');
        $mform->addElement('submit', 'submitmessage', $submitlabel);
    }
}
