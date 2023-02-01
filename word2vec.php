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

use PHPW2V\Word2Vec;
use PHPW2V\SoftmaxApproximators\NegativeSampling;

use Wamania\Snowball\StemmerFactory;

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

/*global $DB;

$questions = $DB->get_recordset_select('faq', "thread_type = 'question' AND post_type = 'CommentThread'");

$sentences = []; 
foreach ($questions as $question) {
    array_push($sentences, $question->body); 
}

$sentences = array_slice($sentences, 1050, 50);

print_r($sentences);*/

$dimensions     = 150; //vector dimension size
$sampling       = new NegativeSampling; //Softmax Approximator
$minWordCount   = 2; //minimum word count
$alpha          = .05; //the learning rate
$window         = 3; //window for skip-gram
$epochs         = 500; //how many epochs to run
$subsample      = 0.05; //the subsampling rate

$word2vec = new Word2Vec($dimensions, $sampling, $window, $subsample,  $alpha, $epochs, $minWordCount);

$sentences = [];

foreach(range(1, 50) as $number){
    $file = "tests/" . $number. ".txt";
    $sentence = file_get_contents($file);
    $stemmed = tokenize($sentence); 
    
    $sentence = ""; 
    foreach($stemmed as $word){
        $sentence = $sentence . " " . $word; 
    }
    array_push($sentences, $sentence); 
}

//$word2vec = $word2vec->load('my_word2vec_model_1050');

//$sentences = ['Bonjour Marie-Ange, Combien de semaines comporte une année?'];

$word2vec->train($sentences); 

print_r($word2vec->vocab());

$word2vec->save('word2vec_model_stemmed_test');