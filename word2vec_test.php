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

function tfidf($questions, $questions_id){ 
    foreach(range(0, count($questions)-1) as $idx){
        if (is_int(key($questions[$idx]))){
            $questions[$idx] = array_count_values($questions[$idx]);
        }
    }

    $words = [];
    foreach($questions as $question){
        $words = array_unique(array_merge($words, array_keys($question)), SORT_REGULAR);
    }


    $document = []; 

    foreach(range(0, count($questions)-1) as $number){
        $document[$number] = $questions[$number]; 
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
        $tfidf[$questions_id[$number]] = $tfidfj;
    }
    
    return $tfidf;
}

function seek_tfidf($seek, $questions, $questions_id){ 
    $seek = array_count_values($seek);

    foreach(range(0, count($questions)-1) as $idx){
        if (is_int(key($questions[$idx]))){
            $questions[$idx] = array_count_values($questions[$idx]);
        }
    }

    $words = [];
    $words = array_unique(array_merge($words, array_keys($seek)), SORT_REGULAR);
    /*foreach($questions as $question){
        $words = array_unique(array_merge($words, array_keys($question)), SORT_REGULAR);
    }*/

    $document = []; 

    $document[0] = $seek; 
    foreach(range(0, count($questions)-1) as $number){
        $document[$number+1] = $questions[$number]; 
    }

    $tfj = [];
    $tf = [];
        foreach($document[0] as $i=>$xi){
            $tfj[$i] = log(($xi/sizeof($document[0]))+1,10); 
        }     
        array_push($tf, $tfj);
        $tfj = [];

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

        $tfidfj = []; 
        $tfj = $tf[0];

        foreach($words as $word){
            if(in_array($word, array_keys($tfj))){
                $tfidfj[$word] = $tfj[$word]*$idf[$word]; 
            }
        }
        //array_push($tfidf, $tfidfj); 
        $tfidf[0] = $tfidfj; 
        
    
    return $tfidf[0];
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

$word2vec = new Word2Vec();
$word2vec = $word2vec->load('model/my_word2vec_model_1100');

$questions = $DB->get_recordset_select('faq', "thread_type = 'question' AND post_type = 'CommentThread'");
$vectors = [];

foreach ($questions as $question) { 
    $file = "tfidf_w2v/" . $question->post_id . ".json";
    $set = json_decode(file_get_contents($file), true);
    $vectors[$question->post_id] = $set;
} 

/*$key_rate = [];
$lenin = [];  
foreach($key as $k => $v){
    $questions = $DB->get_recordset_select('faq', "thread_type = 'question' AND post_type = 'CommentThread'");
    $similarity = 0; 
    $answer = ""; 

    $sentence_embedding = []; 
    $setA = $tok->tokenize($v); 

    $questions_text = []; 
    $questions_id = []; 

    foreach ($questions as $question) {
        array_push($questions_text, $tok->tokenize($question->body)); 
        array_push($questions_id, $question->post_id); 
    } 
    
    $vectors[0] = seek_tfidf($setA, $questions_text, $questions_id);

    foreach($setA as $word){
        $wordEmbedding = $word2vec->embedWord($word);
        $sum = 0; 
        $count = 0; 
        foreach($wordEmbedding as $point){
            $sum += $point; 
            $count += 1; 
        }
        $average = $sum/$count; 
        $sentence_embedding[$word] = $average*$vectors[0][$word]; 
    } 

    $questions = $DB->get_recordset_select('faq', "thread_type = 'question' AND post_type = 'CommentThread'");
    foreach($questions as $question){

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
                    $question_embedding[$word] = $average*$vectors[$question->post_id][$word];
                }

        $commputesimilarity = cosine_similarity(
            $sentence_embedding, 
            $question_embedding
        );
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

print_r($key_rate); 

print_r($lenin);

$count = 0; 
$sum = 0;
foreach($key_rate as $k=>$v){
    $sum += $v;
    $count += 1; 
}

printf($sum/$count);
printf("
"); 
printf($count); 
printf("
"); 

foreach($key_rate as $k=>$v){
    printf($v);
    printf(", ");  
}
printf("
");*/

$questions = $DB->get_recordset_select('faq', "thread_type = 'question' AND post_type = 'CommentThread'");
$similarity = 0; 
$answer = ""; 

$test = "Quel est l'intérêt d'un taux proportionnel pendant qu'il existe le taux équivalent ?";
$sentence_embedding = []; 
$setA = $tok->tokenize($test); 

$questions_text = []; 
$questions_id = []; 

    foreach ($questions as $question) {
        array_push($questions_text, $tok->tokenize($question->body)); 
        array_push($questions_id, $question->post_id); 
    } 
    
    $vectors[0] = seek_tfidf($setA, $questions_text, $questions_id);

    foreach($setA as $word){
        $wordEmbedding = $word2vec->embedWord($word);
        $sum = 0; 
        $count = 0; 
        foreach($wordEmbedding as $point){
            $sum += $point; 
            $count += 1; 
        }
        $average = $sum/$count; 
        $sentence_embedding[$word] = $average*$vectors[0][$word]; 
    }  

    $questions = $DB->get_recordset_select('faq', "thread_type = 'question' AND post_type = 'CommentThread'");
    foreach($questions as $question){

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
                    $question_embedding[$word] = $average*$vectors[$question->post_id][$word];
                }

        $commputesimilarity = cosine_similarity(
            $sentence_embedding, 
            $question_embedding
        );
        if($commputesimilarity > $similarity){
            $similarity = $commputesimilarity; 
            $answer = $question->body; 
        }
    }

    printf($answer); 
    printf(" 
     
    ");
    printf($similarity); 



