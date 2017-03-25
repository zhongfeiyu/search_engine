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
use lib\reader\reader as Reader;
use lib\data\Term as Term;
use lib\data\Text as Text;
use lib\data\Path as Path;
use lib\data\Num as Num;
use lib\data\IDF as IDF;

// Record start time
$time1 = microtime(true);

// Scan every file under directory src
// Return filenames
$reader = new Reader();
$total = $reader->scanSrc('src');

// Total num of files
$totalNum = 0;
// Array to record how many files a word appear
$termAppear = array();

// Make index
// $key is number, $value is filename.
foreach($total as $key=>$value){
    echo 'Making Index for '.$value."\n";
    $totalNum++;
    // Read the content of file
    $content = $reader->readOne($value);
    // Save link path for the content
    $path = new Path;
    $no = $path->save('http://shakespeare.mit.edu'.substr($value,15));

    // Handle file content
    // $k is row, $v is content in string
    foreach($content as $k=>$v){
        // Remove Enter
        $content[$k] = str_replace('
','',$v);
        // Remove punctuations
        $content[$k] = str_replace('"','',$content[$k]);
        $content[$k] = str_replace('.','',$content[$k]);
        $content[$k] = str_replace(',','',$content[$k]);
        $content[$k] = str_replace(':','',$content[$k]);
        $content[$k] = str_replace(';','',$content[$k]);
        $content[$k] = str_replace('!','',$content[$k]);
        $content[$k] = str_replace('|','',$content[$k]);
        $content[$k] = str_replace('?','',$content[$k]);
        $content[$k] = str_replace('  ',' ',$content[$k]);
        // Remove extra space
        if($content[$k] == ' ' || $content[$k] == null) unset($content[$k]);
    }
    // Now $content is an content array
    // Every index of $content is a row
    $content = array_merge($content);
    $num = 0;

    // Save handled content.
    $text = new Text;
    $text->save($no,$content);

    // Make index from here
    $stem = new Stemmer();
    $term = new Term();
    $flag = array();
    // Foreach to handle every line
    // $k is every row, $v is content in string
    foreach($content as $k=>$v){
        // now $v is an array divided by space
        $temp = explode(' ', $content[$k]);
        // Foreach to handle every word
        // $a is column, $b is the word
        foreach($temp as $a=>$b) {
            // Stemming
            $stemmed = $stem->stem($b);
            // Make index
            $term->append($stemmed,$no,$k,$a);
            // Record how many files a word appear
            // If thw word haven't appear in this file before
            if($stemmed != null && !array_key_exists($stemmed,$flag)){
                if(array_key_exists($stemmed,$termAppear)) $termAppear[$stemmed]++;
                else $termAppear[$stemmed] = 1;
                $flag[$stemmed] = 1;
            }
            $num ++;
	    } 
    }

    // Save length of content
    $n = new Num();
    $n->save($no, $num);
}

// Calculate IDF and save
$idf = new IDF();
// Foreach to calculate every IDF of a word
// $key is word, $value is how many files it appears.
foreach($termAppear as $key=>$value){
    $idf->save($key,log($totalNum/$value,10));
}

// Echo total times
$time2 = microtime(true);
echo "Total time: ".($time2-$time1)."s\n";
