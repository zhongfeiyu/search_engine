<?php
// 导入类库
require_once 'lib/stem.php';
require_once 'lib/reader.php';
require_once 'lib/data.php';

// 导入命名空间
// 命名空间所在文件均在lib文件夹下
use lib\stem\Stemmer as Stemmer;
use lib\data\Term as Term;
use lib\data\Text as Text;
use lib\data\Path as Path;
use lib\data\Cache as Cache;
use lib\data\Num as Num;
use lib\data\IDF as IDF;

// 调试模式配置
// 开启是命令行端的输出，关闭是浏览器端的输出
define("APP_DEBUG", 0);

// 大数据集模式配置
// 开启时会缓存每次搜索结果
define("LARGE_DATASET",0);

// 停止词定义
$stopWords = ['i','about','a','an','are','as','at','be','by','com','for','from','how','in','is','it',
    'on','or','of','that','the','this','to','was','what','when', 'where','who','will','with','the','www'];

// 位置权值定义
$roleModel = [100, 90, 80, 70, 60];

// 获取post参数
$page = APP_DEBUG ? 1 : intval($_POST['page']);
$page_size = APP_DEBUG ? 20 : intval($_POST['page_size']);
if(APP_DEBUG) echo "Input the words you want to search and Enter: ";
$words = APP_DEBUG ? fgets(STDIN) : $_POST['words'];
if($page < 1 || $page_size*$page >100) return 0;
$time1 = microtime(true);

// 处理搜索的字符串：
// 全部变为小写，去除标点符号和多余空格
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

// 判断缓存中有没有这次查询的词
// 如果有的话，直接从缓存中读取结果
$c = new Cache();
if(LARGE_DATASET && $c->isSearched($words)){
    $cache = $c->get($words);
    $show = array_splice($cache,$page_size*($page-1),$page_size);
}
else {
    $exwords = explode(' ', $words);
    $stem = new Stemmer();
    $term = new Term();
    $num = new Num();
    $idf = new IDF();
    $scores = array();

    // 分别处理搜索词中的每一个词
    foreach ($exwords as $key => $value) {
        // 判定位置权值
        $role[$key] = $key < 5 ? $roleModel[$key] : 50;
        // 判定是否是截止词
        $role[$key] = in_array($value, $stopWords) ? 0 : $role[$key];
        // 获取词干
        $stemmed[$key] = $stem->stem($value);
        // 读出该词干的所有索引
        $result[$key] = $term->get($stemmed[$key]);
        // 读出该词干的IDF
        $idfs[$key] = $idf->get($stemmed[$key]);
    }


    // 为搜索结果评分
    // 分别对搜索词的每一个词评分
    foreach ($result as $key => $value) {
        // 判定截止词
        if($role[$key] == 0) continue;
        // 对含有这个词的每条结果进行评分
        foreach ($value as $k => $v) {
            // 评分公式 TF*IDF*位置权值*连续性得分
            $length = $num->get($k);
            $thisScore = $idfs[$key] * count($result[$key][$k])/$length * $role[$key];
            // 连续性得分
            // 如果某条搜索结果S出现了i个连续的搜索词A B C...H，则为S的最后一个连续词H的得分乘上20的i次幂
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
    // 排序得到得分前100的搜索结果
    arsort($scores);
    $scoreResult = array_slice(array_keys($scores),0,100);

    
    // 处理索引，方便输出和缓存
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
            }
        }
        array_push($cache, $temp);
    }
    if(LARGE_DATASET) $c->save($words, $cache);
    // 得到最终输出的搜索结果的索引数组
    $show = array_slice($cache,$page_size*($page-1),$page_size);
}
// 处理返回数组
$path = new Path();
$text = new Text();
$return = array();
// 遍历每个搜索结果
foreach($show as $key=>$value){
    // 取出搜索结果的链接
    $no = array_shift($value);
    $temp['path'] = $path->get($no);

    // 为搜索结果创建摘要

    // 摘要评分
    // 对文章每一行进行评分，与搜索结果评分公式相似
    $temp['preview'] = array();
    $previewNo = array();
    while(($a = array_shift($value))!=null){
        $b = array_shift($value);
        if(array_key_exists($a,$previewNo)) array_push($previewNo[$a],$b);
	    else $previewNo[$a][0] = $b;
    }
    $times = array();
    foreach($previewNo as $k=>$v){
        $times[$k] = count($v);
        $num = sizeof($v);
	    foreach($v as $t=>$p){
            for($i = 1; $i< $num;$i++){
		        if($t-$i>0 && $v[$t]-$v[$t-$i] == $i)$times[$k] += min(pow(10,$i),1000);
		        else break;
            }
        }
    }
    // 排序并取出前5个摘要
    arsort($times);
    $countResult = array_slice(array_keys($times),0,5);
    foreach($countResult as $k=>$v){
	    array_push($temp['preview'],$text->context($no,$v,$previewNo[$v]));
    }
    array_push($return,$temp);
}

// 计算运行时间
$time2 = microtime(true);

// 构造浏览器端返回的json数据
if(!APP_DEBUG) echo json_encode(['data'=>$return, 'time'=>($time2-$time1), 'page'=>$page, 'page_size'=>$page_size, 'total'=>ceil(count($cache)/$page_size)]);
// 构造命令行端的输出
else {
    foreach($return as $key=>$value){
	echo ($key+1).' '.$value['path']."\n";
	foreach($value['preview'] as $k=>$v) echo $v."\n";
    }
    echo "Total time: ".($time2-$time1)."s\n";	
}

