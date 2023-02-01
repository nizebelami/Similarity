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
use tool_brickfield\local\areas\core_question\answerbase;

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

$tok = new WhitespaceAndPunctuationTokenizer();

$J = new JaccardIndex();
$cos = new CosineSimilarity();
$eucl = new Euclidean(); 
$dice = new DiceSimilarity();

$good_answer = []; 
$keys = []; 
$entire = []; 
foreach(range(1, 50) as $number){
    $file = "tests/" . $number. ".txt";
    $sentence = file_get_contents($file);
    $good_answer[$number] = $sentence; 
}

foreach(range(1, 50) as $number){
    $file = "tests/" . $number. "_key.txt";
    $sentence = file_get_contents($file);
    $key[$number] = $sentence; 
}

foreach(range(1, 50) as $number){
    $file = "tests/" . $number. "_entire.txt";
    $sentence = file_get_contents($file);
    $entire[$number] = $sentence; 
}

$lenin = []; 

$key_rate = []; 
foreach($key as $k => $v){
    $questions = $DB->get_recordset_select('faq', "thread_type = 'question' AND post_type = 'CommentThread'");
    $similarity = 0; 
    $answer = ""; 
    $setA = tokenize($v); 
    foreach ($questions as $question){

        $file = "stemming/" . $question->post_id . ".json";
        $setB = json_decode(file_get_contents($file), true); 

        $distance = $eucl->dist(
            $setA, 
            $setB
        );

        $commputesimilarity = 1/(1+$distance);
        if($commputesimilarity > $similarity){
            $similarity = $commputesimilarity; 
            $answer = $question->body; 
        }
    } 
    if(strcmp($answer, $good_answer[$k]) == 0){
        $key_rate[$k] =  $similarity; 
    }
    else{
        $lenin[$k] = [$answer, $similarity];
    }
}

$entire_rate = []; 
foreach($entire as $k => $v){
    $questions = $DB->get_recordset_select('faq', "thread_type = 'question' AND post_type = 'CommentThread'");
    $similarity = 0; 
    $answer = ""; 
    $setA = tokenize($v); 
    foreach ($questions as $question){

        $file = "stemming/" . $question->post_id . ".json";
        $setB = json_decode(file_get_contents($file), true);

        $distance = $eucl->dist(
            $setA, 
            $setB
        );

        $commputesimilarity = 1/(1+$distance);

        if($commputesimilarity > $similarity){
            $similarity = $commputesimilarity; 
            $answer = $question->body; 
        }
    } 
    if(strcmp($answer, $good_answer[$k]) == 0){
        $entire_rate[$k] =  $similarity; 
    }
    $time_elapsed_secs = microtime(true) - $start;
}
/*print_r($entire_rate); 

print_r($lenin);

$count = 0; 
$sum = 0;
foreach($entire_rate as $k=>$v){
    $sum += $v;
    $count += 1; 
}

printf($sum/$count);
printf("
"); 
printf($count); 
printf("
"); 

foreach($entire_rate as $k=>$v){
    printf($v);
    printf(", ");  
}
printf("
");*/

printf($eucl->dist(
    tokenize("C’est quoi un remboursement au pair ?"), 
    tokenize("Qu’est-ce qu’un remboursement au pair ?")
)); 