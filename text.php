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

$questions = $DB->get_recordset_select('faq', "thread_type = 'question' AND post_type = 'CommentThread'");
$text = ""; 
foreach($questions as $question){
    $text = $text . $question->body . " "; 

    $file = "text.txt";
    file_put_contents($file, $text);
}