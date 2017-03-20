<?php
require_once 'lib/stem.php';
require_once 'lib/reader.php';
require_once 'lib/data.php';

use lib\stem\Stemmer as Stemmer;
use lib\data\Term as Term;
use lib\data\Text as Text;
use lib\data\Path as Path;
use lib\data\Cache as Cache;

define("APP_DEBUG", 1);
define("LARGE_DATASET",0);
// Define const parameter
$stopWords = ['i','about','a','an','and','are','as','at','be','by',
    'com','de','en','for','from','have','he','how','in','is','it','la','my','not',
    'on','or','of','that','the','this','to','was','what','when',
    'where','who','will','with','und','the','www','you'];
$roleModel = [1, 0.9, 0.8, 0.7, 0.6];

// Access post parameter
$page = APP_DEBUG ? 1 : intval($_POST['page']);
$page_size = APP_DEBUG ? 20 : intval($_POST['page_size']);
if(APP_DEBUG) echo "Input the words you want to search and Enter: ";
$words = APP_DEBUG ? fgets(STDIN) : $_POST['words'];
if($page < 1 || $page_size*$page >100) return 0;
$time1 = microtime(true);
// Handle searching string
$words = strtolower($words);
$words = str_replace('"','',$words);
$words = str_replace('.','',$words);
$words = str_replace(',','',$words);
$words = str_replace(':','',$words);
$words = str_replace(';','',$words);
$words = str_replace('!','',$words);
$words = str_replace('|','',$words);
$words = str_replace('?','',$words);
$words = str_replace('  ',' ',$words);

// Query if the searching words have been cached.
// If so, get the result from cache directly.
$c = new Cache();
if(LARGE_DATASET && $c->isSearched($words)){
    $cache = $c->get($words);
    $show = array_splice($cache,$page_size*($page-1),$page_size);
}
else {
    $exwords = explode(' ', $words);
    $stem = new Stemmer();
    $term = new Term();
    $scores = array();
    // Score the role, stem the words and get their indexes.
    foreach ($exwords as $key => $value) {
        $role[$key] = $key < 5 ? $roleModel[$key] : 0.5;
        $role[$key] = in_array($value, $stopWords) ? $role[$key] * 0.01 : $role[$key];
        $result[$key] = $term->get($stem->stem($value));
    }
    
    // Score the result
    foreach ($result as $key => $value) {
        foreach ($value as $k => $v) {
            $scores[$k] = array_key_exists($k, $scores) ? count($result[$key][$k]) * $role[$key] + $scores[$k]
                : count($result[$key][$k]) * $role[$key];
            // Score the sequent key words
            if ($key != 0) {
                foreach ($v as $v2) {
                    $temp = [$v2[0], $v2[1] - 1];
                    if (array_key_exists($k, $result[$key-1]) && in_array($temp, $result[$key - 1][$k])) $scores[$k] += $role[$key] * 2 + $role[$key - 1] * 2;
                }
            }
        }
    }
    // Get top 100 results
    arsort($scores);
    $scoreResult = array_slice(array_keys($scores),0,100);

    
    // Make array for storing cache.
    $cache = array();
    foreach ($scoreResult as $key => $value) {
        $temp = array();
        $temp[0] = $value;
        $i = 0;
        foreach ($result as $k1 => $v1) {
	if(array_key_exists($value,$v1))	
            foreach ($v1[$value] as $k2 => $v2) {
                array_push($temp, $v2[0]);
                array_push($temp, $v2[1]);
                if (++$i > 4) break;
            }
        }
        array_push($cache, $temp);
    }
    if(LARGE_DATASET) $c->save($words, $cache);
    $show = array_slice($cache,$page_size*($page-1),$page_size);
}
// Make return array
$path = new Path();
$text = new Text();
$return = array();
foreach($show as $key=>$value){
    $no = array_shift($value);
    $temp['path'] = $path->get($no);
    $temp['preview'] = array();
    while(($a = array_shift($value))!=null){
        $b = array_shift($value);
	array_push($temp['row'],$a);
        array_push($temp['preview'],$text->context($no,$a,$b));
    }
    array_push($return,$temp);
}
$time2 = microtime(true);
if(!APP_DEBUG) echo ['data'=>$return, 'time'=>($time2-$time1)];
else {
    foreach($return as $key=>$value){
	echo ($key+1).' '.$value['path']."\n";
	foreach($value['preview'] as $k=>$v) echo $v."\n";
    }
    echo "Total time: ".($time2-$time1)."s\n";	
}

