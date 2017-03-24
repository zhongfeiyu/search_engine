<?php

// 导入类库
require_once 'lib/stem.php';
require_once 'lib/reader.php';
require_once 'lib/data.php';

// 导入命名空间
// 命名空间所在文件均在lib文件夹下
use lib\stem\Stemmer as Stemmer;
use lib\reader\reader as Reader;
use lib\data\Term as Term;
use lib\data\Text as Text;
use lib\data\Path as Path;
use lib\data\Num as Num;
use lib\data\IDF as IDF;

// 记录初始时间
$time1 = microtime(true);

// 遍历目标文件夹下的所有文件
$reader = new Reader();
$total = $reader->scanSrc('src');

$totalNum = 0;  // 遍历文章的总数
$termAppear = array();

// 为目标文件夹下的每一个文件建立索引
foreach($total as $key=>$value){
    echo 'Making Index for '.$value."\n";
    $totalNum++;
    // 读出文件全部内容
    $content = $reader->readOne($value);
    // 把网页链接保存
    $path = new Path;
    $no = $path->save('http://shakespeare.mit.edu'.substr($value,15));

    // 处理文件内容
    // 去除空行和各种标点
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
    $num = 0;

    // 把处理后的文件内容保存起来
    $text = new Text;
    $text->save($no,$content);

    // 创建索引
    $stem = new Stemmer();
    $term = new Term();
    $flag = array();
    // 遍历每一行
    foreach($content as $k=>$v){
        $temp = explode(' ', $content[$k]);
        // 遍历每一行中的每一个词
        foreach($temp as $a=>$b) {
            // 提取词干
            $stemmed = $stem->stem($b);
            // 为词干创建索引
            $term->append($stemmed,$no,$k,$a);
            // 统计该词干在多少文章中出现过
            if($stemmed != null && !array_key_exists($stemmed,$flag)){
                if(array_key_exists($stemmed,$termAppear)) $termAppear[$stemmed]++;
                else $termAppear[$stemmed] = 1;
                $flag[$stemmed] = 1;
            }
            $num ++;
	    } 
    }

    // 保存该文章总长度
    $n = new Num();
    $n->save($no, $num);
}

// 计算IDF并保存
$idf = new IDF();
foreach($termAppear as $key=>$value){
    $idf->save($key,log($totalNum/$value,10));
}

// 显示总的处理时间
$time2 = microtime(true);
echo "Total time: ".($time2-$time1)."s\n";
