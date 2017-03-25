<?php
// Import library:
// stem.php
// reader.php
// data.php
require_once 'lib/stem.php';
require_once 'lib/reader.php';
require_once 'lib/data.php';

// Use namespace
// All these namespaces come from library
use lib\stem\Stemmer as Stemmer;
use lib\data\Term as Term;
use lib\data\Text as Text;
use lib\data\Path as Path;
use lib\data\Cache as Cache;
use lib\data\Num as Num;
use lib\data\IDF as IDF;

// Debug model config
// 1 for shell output, 0 for browser output
define("APP_DEBUG", 0);

// Large dataset config
// 1 to open cache, 0 to close cache
define("LARGE_DATASET",0);

// Stop words define
$stopWords = ['i','about','a','an','are','as','at','be','by','com','for','from','how','in','is','it',
    'on','or','of','that','the','this','to','was','what','when', 'where','who','will','with','the','www'];

// Position role define
$roleModel = [100, 90, 80, 70, 60];

// Get post params
// (for browser)
// Include: page, page_size, word
$page = APP_DEBUG ? 1 : intval($_POST['page']);
$page_size = APP_DEBUG ? 20 : intval($_POST['page_size']);
if(APP_DEBUG) echo "Input the words you want to search and Enter: ";
$words = APP_DEBUG ? fgets(STDIN) : $_POST['words'];
if($page < 1 || $page_size*$page >100) return 0;

// Record start time
$time1 = microtime(true);

// Handle search words
// Turn to lower-case letters
$words = strtolower($words);
// Remove punctuations
$words = str_replace('"','',$words);
$words = str_replace('.','',$words);
$words = str_replace(',','',$words);
$words = str_replace(':','',$words);
$words = str_replace(';','',$words);
$words = str_replace('!','',$words);
$words = str_replace('|','',$words);
$words = str_replace('?','',$words);
// Remove extra spaces
$words = str_replace('  ',' ',$words);

// If in large dataset model
// If the search words are in cache,
// get result directly from cache.
$c = new Cache();
if(LARGE_DATASET && $c->isSearched($words)){
    $cache = $c->get($words);
    $show = array_splice($cache,$page_size*($page-1),$page_size);
}
// If no
else {
    // Split search words (string) to an array by space
    $exwords = explode(' ', $words);
    $stem = new Stemmer();
    $term = new Term();
    $num = new Num();
    $idf = new IDF();
    $scores = array();

    // Get Position Role, IDF, indexes of a word.
    // $key is numbers from 0, $value is word.
    foreach ($exwords as $key => $value) {
        // Get Position Role
        $role[$key] = $key < 5 ? $roleModel[$key] : 50;
        // If the word is stop word or not
        $role[$key] = in_array($value, $stopWords) ? 0 : $role[$key];
        // Stemming
        $stemmed[$key] = $stem->stem($value);
        // Det indexes
        $result[$key] = $term->get($stemmed[$key]);
        // Get IDF
        $idfs[$key] = $idf->get($stemmed[$key]);
    }

    // Score results
    // $key is number from 0, $value is word
        foreach ($result as $key => $value) {
        // If this word is stop word, skip.
        if($role[$key] == 0) continue;
        // Score every word
        // $k is file number, $v is appear positions
        foreach ($value as $k => $v) {
            $length = $num->get($k);
            // Score = TF * IDF * Position Role * Sequence Score
            // TF: Term frequency
            // IDF: Inverse document frequency
            // Position Role: Position of the word in search words.
            //                First is 100, then 90 , 80, 70, 60, 50. Min is 50
            // Sequence Score: If a file has several sequent key words in the order like search words,
            //                 the file get extra score time 20^(sequent length)
            // Handle TF * IDF * Position Role
            $thisScore = $idfs[$key] * count($result[$key][$k])/$length * $role[$key];
            // Handle Sequence Score
            if ($key != 0) {
                foreach ($v as $v2) {
                    for($i = 1;$i<=$key;$i++){
                        $temp = [$v2[0], $v2[1] - $i];
                        if (array_key_exists($k, $result[$key-$i]) && in_array($temp, $result[$key - $i][$k]))
                            $thisScore *= pow(20,$i);
                        else break;
                    }
                }
            }
            $scores[$k] = array_key_exists($k, $scores) ? $thisScore + $scores[$k] : $thisScore;
        }
    }
    // Sort and get top 100 results
    arsort($scores);
    $scoreResult = array_slice(array_keys($scores),0,100);

    // Handle indexes for saving cache and output
    $cache = array();
    // $key is rank number, $value is file number
    foreach ($scoreResult as $key => $value) {
        $temp = array();
        $temp[0] = $value;
        $i = 0;
        // $k1 is file number, $v1 is index array
        foreach ($result as $k1 => $v1) {
	    if(array_key_exists($value,$v1))
            // $v2 is array of [row, column]
            foreach ($v1[$value] as $k2 => $v2) {
                array_push($temp, $v2[0]);
                array_push($temp, $v2[1]);
            }
        }
        array_push($cache, $temp);
    }
    // If in large dataset model, save result to cache
    if(LARGE_DATASET) $c->save($words, $cache);
    // Get results to show by param page and page_size
    $show = array_slice($cache,$page_size*($page-1),$page_size);
}
// Handle return data
$path = new Path();
$text = new Text();
$return = array();
// Handle every result
foreach($show as $key=>$value){
    // Get link path for a result
    $no = array_shift($value);
    $temp['path'] = $path->get($no);

    // Make summary for result
    // Score every row of a result
    $temp['preview'] = array();
    $previewNo = array();
    // Get columns of key words in a row
    // Now $previewNo is an array. First index is row number, second index is column array
    while(($a = array_shift($value))!=null){
        $b = array_shift($value);
        if(array_key_exists($a,$previewNo)) array_push($previewNo[$a],$b);
	    else $previewNo[$a][0] = $b;
    }
    $times = array();
    // Calculate score
    // $k is row number, $v is column array
    foreach($previewNo as $k=>$v){
        $times[$k] = count($v);
        $num = sizeof($v);
        // Score mainly depends on Sequence Score
	    foreach($v as $t=>$p){
            for($i = 1; $i< $num;$i++){
		        if($t-$i>0 && $v[$t]-$v[$t-$i] == $i)$times[$k] += min(pow(10,$i),1000);
		        else break;
            }
        }
    }
    // Sort and get top 5 summary for a file
    arsort($times);
    $countResult = array_slice(array_keys($times),0,5);
    // Get the context of these 5 summary
    foreach($countResult as $k=>$v){
	    array_push($temp['preview'],$text->context($no,$v,$previewNo[$v]));
    }
    array_push($return,$temp);
}

// Record end time
$time2 = microtime(true);

// Make json data for browser output
if(!APP_DEBUG) echo json_encode(['data'=>$return, 'time'=>($time2-$time1), 'page'=>$page, 'page_size'=>$page_size, 'total'=>ceil(count($cache)/$page_size)]);
// Make shell output
else {
    // $key is rank-1, $value is context array
    foreach($return as $key=>$value){
	    echo ($key+1).' '.$value['path']."\n";
	    foreach($value['preview'] as $k=>$v) echo $v."\n";
    }
    // Echo total time
    echo "Total time: ".($time2-$time1)."s\n";	
}

