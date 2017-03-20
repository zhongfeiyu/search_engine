<?php
require_once 'lib/stem.php';
require_once 'lib/reader.php';
require_once 'lib/data.php';

use lib\stem\Stemmer as Stemmer;
use lib\reader\reader as Reader;
use lib\data\Term as Term;
use lib\data\Text as Text;
use lib\data\Path as Path;

$time1 = microtime(true);
// Scan every file under directory 'src'.
$reader = new Reader();
$total = $reader->scanSrc('src');
foreach($total as $key=>$value){
    echo 'Making Index for '.$value."\n";

    $content = $reader->readOne($value);
    $path = new Path;
    $no = $path->save('http://shakespeare.mit.edu'.substr($value,15));

    // Handle the text.
    foreach($content as $k=>$v){
        $content[$k] = str_replace('
','',$v);
        $content[$k] = str_replace('"','',$content[$k]);
        $content[$k] = str_replace('.','',$content[$k]);
        $content[$k] = str_replace(',','',$content[$k]);
        $content[$k] = str_replace(':','',$content[$k]);
        $content[$k] = str_replace(';','',$content[$k]);
        $content[$k] = str_replace('!','',$content[$k]);
        $content[$k] = str_replace('|','',$content[$k]);
        $content[$k] = str_replace('?','',$content[$k]);
        $content[$k] = str_replace('  ',' ',$content[$k]);
        if($content[$k] == ' ' || $content[$k] == null) unset($content[$k]);
    }
    $content = array_merge($content);

    // Save the text.
    $text = new Text;
    $text->save($no,$content);
    // Create and save index.
    $stem = new Stemmer();
    $term = new Term();
    foreach($content as $k=>$v){
        $temp = explode(' ', $content[$k]);
        foreach($temp as $a=>$b) { 
            $term->append($stem->stem($b),$no,$k,$a);
	}
    }
}
$time2 = microtime(true);
echo "Total time: ".($time2-$time1)."s\n";
