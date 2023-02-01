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

require_once('../../config.php');

require_once($CFG->dirroot. '/local/similarity/lib.php');

require_once($CFG->dirroot. '/local/similarity/message_form.php');

include('vendor/autoload.php');

use core\plugininfo\local;
use core_calendar\external\export\token;
use core_reportbuilder\local\filters\text;
use \NlpTools\Similarity\JaccardIndex;
use \NlpTools\Similarity\CosineSimilarity;
use \NlpTools\Similarity\Euclidean; 
use \NlpTools\Similarity\DiceSimilarity;

use \NlpTools\Tokenizers\WhitespaceAndPunctuationTokenizer;
use Phpml\Math\Set;
use PhpParser\Node\Expr\Print_;
use Wamania\Snowball\StemmerFactory;

use PHPW2V\Word2Vec;
use PHPW2V\SoftmaxApproximators\NegativeSampling;

global $DB;

function tokenize($str){
    $str = str_replace(['?', '!', '.', ',', '\'', '-'], ' ', $str);
    $stemmer = StemmerFactory::create('fr');

        $arr = array();
        // for the character classes
        // see http://php.net/manual/en/regexp.reference.unicode.php
        $pat = '/
                    ([\pZ\pC]*)			# match any separator or other
                                        # in sequence
                    (
                        [^\pP\pZ\pC]+ |	# match a sequence of characters
                                        # that are not punctuation,
                                        # separator or other
                        .				# match punctuations one by one
                    )
                    ([\pZ\pC]*)			# match a sequence of separators
                                        # that follows
                /xu';
        preg_match_all($pat,$str,$arr);

        $tokens = $arr[2]; 

        foreach($tokens as $key=>$value){
            $tokens[$key] = $stemmer->stem($value);
        }

        return $tokens;
}   


function tfidf($seek, $questions, $questions_id){ 
    $seek = array_count_values($seek);

    foreach(range(0, count($questions)-1) as $idx){
        if (is_int(key($questions[$idx]))){
            $questions[$idx] = array_count_values($questions[$idx]);
        }
    }

    $words = [];
    $words = array_unique(array_merge($words, array_keys($seek)), SORT_REGULAR);
    foreach($questions as $question){
        $words = array_unique(array_merge($words, array_keys($question)), SORT_REGULAR);
    }

    $document = []; 

    $document[0] = $seek; 
    foreach(range(0, count($questions)-1) as $number){
        $document[$number+1] = $questions[$number]; 
    }

    $tfj = [];
    $tf = [];
    foreach($document as $doc){
        foreach($doc as $i=>$xi){
            $tfj[$i] = log(($xi/sizeof($doc))+1,10); 
        }     
        array_push($tf, $tfj);
        $tfj = [];
    }

    $df = [];
    $idf = [];
    foreach($words as $i=>$xi){
        $df[$xi] = 0;

        foreach($document as $doc){
            $voc = array_keys($doc);

            if(in_array($xi, $voc)){     
                $df[$xi] += 1; 
            }
        }

        $idf[$xi] = log(sizeof($document)/$df[$xi], 10);
    }

    $tfidf = [];

    foreach(range(0, sizeof($document)-1) as $number){
        $tfidfj = []; 
        $tfj = $tf[$number];

        foreach($words as $word){
            if(in_array($word, array_keys($tfj))){
                $tfidfj[$word] = $tfj[$word]*$idf[$word]; 
            }
        }
        //array_push($tfidf, $tfidfj); 
        if($number == 0){
            $tfidf[0] = $tfidfj; 
        }
        else{
            $tfidf[$questions_id[$number-1]] = $tfidfj;
        }
    }
    
    return $tfidf;
}

function cosine_similarity($A, $B)
{
    // This means they are simple text vectors
    // so we need to count to make them vectors
    $v1 = $A;
    $v2 = $B;

    $prod = 0.0;
    $v1_norm = 0.0;
    foreach ($v1 as $i=>$xi) {
        if (isset($v2[$i])) {
            $prod += $xi*$v2[$i];
        }
        $v1_norm += $xi*$xi;
    }
    $v1_norm = sqrt($v1_norm);
    if ($v1_norm==0)
        return 0;

    $v2_norm = 0.0;
    foreach ($v2 as $i=>$xi) {
        $v2_norm += $xi*$xi;
    }
    $v2_norm = sqrt($v2_norm);
    if(empty($v2)){
        print_r(array_count_values($B));
    }
    if ($v2_norm==0){
        return 0; 
    }
    return $prod/($v1_norm*$v2_norm);
}


$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/similarity/index.php'));
$PAGE->set_pagelayout('standard');

$PAGE->set_title($SITE->fullname);

$PAGE->set_heading(get_string('pluginname', 'similarity'));

$tok = new WhitespaceAndPunctuationTokenizer();

$J = new JaccardIndex();
$cos = new CosineSimilarity();
$eucl = new Euclidean(); 
//$simhash = new Simhash(16); // 16 bits hash
$dice = new DiceSimilarity();

$messageform = new local_similarity_message_form();

echo $OUTPUT->header();

$messageform->display();

//echo $OUTPUT->box_start('card-columns');

if ($data = $messageform->get_data()) {
    $message = required_param('message', PARAM_TEXT);

    if (!empty($message)) {

        if (isset ($_POST ['similarity'])) {
            $id = $_POST['similarity'];
        }

        $questions = $DB->get_recordset_select('faq', "thread_type = 'question' AND post_type = 'CommentThread'");
        $similarity = 0;
        $distance = PHP_INT_MAX;
        $samequestion;
        $samequestionid;

        $s1 = $message;
        $setA = tokenize($s1);

        //Cosine similarity/Euclidean Distance with TF-IDF 
        if($id == 3 || $id == 6){
            $stem_questions = array(); 
            $questions_id = []; 

            foreach ($questions as $question) {
                $file = "stemming/" . $question->post_id . ".json";
                $set = json_decode(file_get_contents($file), true);

                array_push($stem_questions, $set); 
                array_push($questions_id, $question->post_id); 
            } 

            $vectors = tfidf($setA, $stem_questions, $questions_id); 

            foreach($questions_id as $question_id){
                if($id == 3){
                    $computesimilarity = cosine_similarity($vectors[0], $vectors[$question_id]); 


                    if($computesimilarity > $similarity) {
                        $similarity = $computesimilarity;
                        $samequestionid = $question_id;
                        $samequestionrecord = $DB->get_record('faq', ['thread_type' => 'question', 'post_type' => 'CommentThread', 'post_id' => $samequestionid]);
                        $samequestion = $samequestionrecord->body; 
                    }
                }
                else{
                    $computedistance = $eucl-> dist(
                        $vectors[0],
                        $vectors[$question_id]
                    ); 

                    if($computedistance < $distance) {
                        $distance = $computedistance;
                        $samequestionid = $question_id;
                        $samequestionrecord = $DB->get_record('faq', ['thread_type' => 'question', 'post_type' => 'CommentThread', 'post_id' => $samequestionid]);
                        $samequestion = $samequestionrecord->body; 
                    } 
                }
            }
        }

        //Cosine similarity / Euclidean Distance with word2vec
        elseif($id == 4 || $id == 7){             
            $word2vec = new Word2Vec();
            $word2vec = $word2vec->load('model/my_word2vec_model_1100');

            $mostSimilar = $word2vec->mostSimilar(['comprendre']);

            $sentence_embedding = []; 
            $setA = $tok->tokenize($message); 
            foreach($setA as $word){
                $wordEmbedding = $word2vec->embedWord($word);
                $sum = 0; 
                $count = 0; 
                foreach($wordEmbedding as $point){
                    $sum += $point; 
                    $count += 1; 
                }
                $average = $sum/$count; 
                $sentence_embedding[$word] = $average; 
            }

            $questions = $DB->get_recordset_select('faq', "thread_type = 'question' AND post_type = 'CommentThread'");
            foreach ($questions as $question) {
                $s = $tok->tokenize($question->body);
                $question_embedding = [];

                foreach($s as $word){
                    $wordEmbedding = $word2vec->embedWord($word);
                    $sum = 0; 
                    $count = 0; 
                    foreach($wordEmbedding as $point){
                        $sum += $point; 
                        $count += 1; 
                    }
                    $average = $sum/$count; 
                    $question_embedding[$word] = $average; 
                }

                //Cosine similarity / Euclidean Distance with word2vec
                if($id == 4){
                    $computesimilarity = cosine_similarity(
                        $sentence_embedding,
                        $question_embedding
                    ); 

                    if($computesimilarity > $similarity) {
                        $similarity = $computesimilarity;
                        $samequestionid = $question->post_id;
                        $samequestion = $question->body; 
                    }
                }
                //Euclidean Distance with Word2Vec  
                else{
                    $computedistance = $eucl->dist(
                        $sentence_embedding, 
                        $question_embedding
                    );
    
                    if($computedistance < $distance) {
                        $distance = $computedistance;
                        $samequestionid = $question->post_id;
                        $samequestion = $question->body; 
                    }
                }
            }

            //$vectors = tfidf($setA, $stem_questions, $questions_id); 
        }
   
        else{
            foreach ($questions as $question) {


                $file = "stemming/" . $question->post_id . ".json";
                $setB = json_decode(file_get_contents($file), true);
 
                //Jaccard Index
                if($id == 0){
                    $computesimilarity = $J->similarity(
                    $setA,
                    $setB
                    );
                }

                //Sørensen–Dice Coefficient
                elseif($id == 1){
                    $computesimilarity = $dice->similarity(
                    $setA,
                    $setB
                    );
                }

                //Cosine Similarity
                elseif($id == 2){

                    $computesimilarity = $cos->similarity(
                    $setA,
                    $setB
                    );
                }

                //Euclidean Distance
                elseif($id == 5){
                    $computedistance = $eucl->dist(
                        $setA, 
                        $setB
                    );
                }

                if($id >= 5){
                    if($computedistance < $distance){
                        $distance = $computedistance;
                        $samequestion = $question->body;
                    }
                }
            
                elseif ($computesimilarity > $similarity) {
                    $similarity = $computesimilarity;
                    $samequestion = $question->body;
                    $samequestionid = $question->thread_id; 
                }
            } 
        }

        if($id < 5){
            if($similarity == 0){
                echo $OUTPUT->heading('It seems your question doesn\'t match...', 4);
                return;
            }

            echo $OUTPUT->heading('Similarity score : ' . strval($similarity), 4);

            echo html_writer::start_tag('div', array('class' => 'card'));
            echo html_writer::start_tag('div', array('class' => 'card-body'));
            echo html_writer::tag('p', $samequestion, array('class' => 'card-text'));
            echo html_writer::start_tag('p', array('class' => 'card-text'));
            echo html_writer::end_tag('p');

            echo $OUTPUT->heading('These are some answers : ', 4);

            $answers = $DB->get_recordset('faq',
            ['thread_type' => 'question', 'post_type' => 'Comment', 'thread_id' => $samequestionid]);

            foreach ($answers as $answer) {
                echo html_writer::start_tag('div', array('class' => 'card'));
                echo html_writer::start_tag('div', array('class' => 'card-body'));
                echo html_writer::tag('p', $answer->body, array('class' => 'card-text'));
                echo html_writer::start_tag('p', array('class' => 'card-text'));
                echo html_writer::end_tag('p');
                echo html_writer::end_tag('div');
                echo html_writer::end_tag('div');
            }
        }
        else{
            if($distance == PHP_INT_MAX){
                echo $OUTPUT->heading('It seems your question doesn\'t match...', 4);
                return;
            }

            echo $OUTPUT->heading('Distance : ' . strval($distance), 4);

            echo html_writer::start_tag('div', array('class' => 'card'));
            echo html_writer::start_tag('div', array('class' => 'card-body'));
            echo html_writer::tag('p', $samequestion, array('class' => 'card-text'));
            echo html_writer::start_tag('p', array('class' => 'card-text'));
            echo html_writer::end_tag('p');

            echo $OUTPUT->heading('These are some answers : ', 4);

            /*$answers = $DB->get_recordset('faq',
            ['thread_type' => 'question', 'post_type' => 'Comment', 'discussion_id' => $samequestionid]);

            foreach ($answers as $answer) {
                echo html_writer::start_tag('div', array('class' => 'card'));
                echo html_writer::start_tag('div', array('class' => 'card-body'));
                echo html_writer::tag('p', $answer->body, array('class' => 'card-text'));
                echo html_writer::start_tag('p', array('class' => 'card-text'));
                echo html_writer::end_tag('p');
                echo html_writer::end_tag('div');
                echo html_writer::end_tag('div');
            }*/ 
        }
    }
}

echo $OUTPUT->footer();
