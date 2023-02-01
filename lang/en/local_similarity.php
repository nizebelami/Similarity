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

$string['pluginname'] = 'faq';

$string['greetinguser'] = 'Ask you question.';
$string['greetingloggedinuser'] = 'Ask your question, {$a}.';

$string['yourmessage'] = 'Your message';

$string['jaccard'] = 'Jaccard Index';
$string['dice'] = 'Sørensen–Dice Coefficient';
$string['cosine'] = 'Cosine Similarity';
$string['cosine2'] = 'Cosine Similarity with TF-IDF';
$string['cosine3'] = 'Cosine Similarity with Word2Vec';
$string['euclidean'] = 'Euclidean Distance';
$string['euclidean1'] = 'Euclidean Distance with TF-IDF';
$string['euclidean2'] = 'Euclidean Distance with Word2Vec';
