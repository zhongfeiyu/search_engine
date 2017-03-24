<?php
namespace lib\data;
/*
 * PHP Lab: Data
 *
 * 读取、储存Redis的数据
 */

/*
 * Class Base
 *
 * Redis的基础连接
 */
class Base{
    protected $redis;

    public function __construct(){
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1',6379);
	    $this->redis->auth('engine');
    }
}

/*
 * Class Term
 *
 * Hash term 的处理
 */
class Term extends Base{

    /*
     * public append
     *
     * 添加索引
     * @param string 词干
     * @param int    文章编号
     * @param int    行号
     * @param int    列号
     */
    public function append($term, $file, $row, $column){
        $value = $this->redis->hGet('term',$term);
        if($value != null) $this->redis->hSet('term', $term, $value.';'.$file.' '.$row.' '.$column);
        else $this->redis->hSet('term', $term, $file.' '.$row.' '.$column);
    }

    /*
     * public get
     *
     * 获取某个词干的索引
     * @param string 词干
     * @return array 索引的数组
     */
    public function get($term){
        $value = $this->redis->hGet('term',$term);
        if($value != null){
            $pos = explode(';', $value);
            $return = array();
            foreach($pos as $value){
                $temp = explode(' ', $value);
                if(array_key_exists($temp[0],$return)) array_push($return[$temp[0]],[$temp[1],$temp[2]]);
                else $return[$temp[0]][0] = [$temp[1],$temp[2]];
            }
            return $return;
        }
    }
}

/*
 * Class Text
 *
 * Hash text 的处理
 */
class Text extends Base{

    /*
     * public save
     *
     * 保存文章
     * @param int    文章编号
     * @param array  文章内容 每一个索引是一行
     */
    public function save($no, $content){
        $this->redis->hSet('text', 'text'.$no, implode(';',$content));
    }

    /*
     * public context
     *
     * 获取一些词的上下文
     * @param int    文章编号
     * @param int    行号
     * @param array  列号
     */
    public function context($no, $row, $array){
        // 从列号最小的开始，最大的结束，最长15个单词
        $start = min($array);
        $end = max($array);
        if($end - $start>15) $end = $start+15;
        $text = $this->redis->hGet('text', 'text'.$no);
        if($text != null){
            $text = explode(';',$text);
            $sentence = explode(' ', $text[$row]); 
            $return = '';
            for($i = $start;$i<=$end;$i++) $return = $return.' '.$sentence[$i];
            // 分析上文
            switch ($start){
                case 0:
                    break;
                case 1:
                    $return = $sentence[0].' '.$return;
                    break;
                case 2:
                    $return = $sentence[0].' '.$sentence[1].' '.$return;
                    break;
                default :
                    $return = '...'.$sentence[$start-2].' '.$sentence[$start-1].' '.$return;
            }
            // 分析下文
            switch (sizeof($sentence)-$end-1){
                case 0:
                    break;
                case 1:
                    $return = $return.' '.$sentence[$end+1];
                    break;
                case 2:
                    $return = $return.' '.$sentence[$end+1].' '.$sentence[$end+2];
                    break;
                default :
                    $return = $return.' '.$sentence[$end+1].' '.$sentence[$end+2].'...';
            }
            return $return;
        }
    }
}

/*
 * Class Path
 *
 * Hash path 的处理
 */
class Path extends Base{
    /*
     * public save
     *
     * 保存链接
     * @param  string  网页链接
     * @return int     文章编号
     */
    public function save($path){
        $now = $this->redis->hLen('path');
        $this->redis->hSet('path', 'text'.($now+1), $path);
        return $now+1;
    }
    /*
     * public get
     *
     * 读取链接
     * @param  int     文章编号
     * @return string  网页链接
     */
    public function get($no){
        return $this->redis->hGet('path', 'text'.$no);
    }
}

/*
 * Class Cache
 *
 * Hash cache 的处理
 */
class Cache extends Base{
    /*
     * public isSearched
     *
     * 判断当前搜索词是否有缓存
     * @param  string  搜索词
     * @return bool
     */
    public function isSearched($word){
        return $this->redis->hExists('cache', strtr($word,' ', ';'));
    }
    /*
     * public save
     *
     * 进行缓存
     * @param  string  搜索词
     * @param  array   搜索结果
     */
    public function save($word, $results){
        foreach($results as $key=>$value){
            $temp[$key] = implode('_',$value);
        }
        $this->redis->hSet('cache',strtr($word,' ', ';'), implode(';',$temp));
    }
    /*
     * public get
     *
     * 读取缓存
     * @param  string  搜索词
     * @return array   搜索结果
     */
    public function get($word){
        $cache = $this->redis->hGet('cache', strtr($word,' ', ';'));
        $cache = explode(';',$cache);
        foreach($cache as $key=>$value){
            $return[$key] = explode('_',$value);
        }
        return $return;
    }
}
/*
 * Class Num
 *
 * Hash num 的处理
 */
class Num extends Base{
    /*
     * public save
     *
     * 保存文章长度
     * @param  int  文章编号
     * @param  int  文章长度
     */
    public function save($no, $num){
        $this->redis->hSet('num', 'text'.($no), $num);
    }
    /*
     * public get
     *
     * 获取文章长度
     * @param  int  文章编号
     * @return int  文章长度
     */
    public function get($no){
        return $this->redis->hGet('num', 'text'.($no));
    }
}

/*
 * Class IDF
 *
 * Hash idf 的处理
 */
class IDF extends Base{
    /*
     * public save
     *
     * 保存IDF
     * @param  string 词干
     * @param  float  IDF值
     */
    public function save($term, $num){
        $this->redis->hSet('idf', $term, $num);
    }
    /*
     * public get
     *
     * 读取IDF
     * @param  string 词干
     * @return float  IDF值
     */
    public function get($term){
        return $this->redis->hGet('idf', $term);
    }
}
