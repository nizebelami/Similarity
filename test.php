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
 * Plugin version and other meta-data are defined here.
 *
 * @package     local_similarity
 * @copyright   2022 Belami <nizebelami@hotmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// No login check is expected here bacause the project is not working yet.
// @codingStandardsIgnoreLine.
require_once('../../vendor/autoload.php');

define('CLI_SCRIPT', true);

require_once('../../config.php');

require_once($CFG->dirroot. '/local/similarity/lib.php');

include('vendor/autoload.php');

global $DB;

$questions = $DB->get_recordset_select('faq', "thread_type = 'question' AND post_type = 'CommentThread'");

$test = []; 

$question_list = [];
foreach($questions as $question){
    $question_list[$question->post_id] = $question->body; 
}

            foreach(range(1, 120) as $number){
                $nbr = PHP_INT_MAX; 
                $key; 
                $value;
                foreach($question_list as $k=>$v){
                    $nbr_of_words = str_word_count($v);
                    if($nbr_of_words < $nbr){
                        $key = $k; 
                        $value = $v;  
                        $nbr = $nbr_of_words; 
                    }
                }
                $test[$key] = $value; 
                unset($question_list[$key]);
            }

$count = 1; 
foreach($test as $sentence){
    $file = "tests/" . $count . ".txt";
    file_put_contents($file, $sentence);
    $count += 1; 
}