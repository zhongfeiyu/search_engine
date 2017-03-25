<?php
namespace lib\data;
/*
 * PHP Lab: Data
 *
 * Read and save data from redis
 */

/*
 * Class Base
 *
 * Base connection of Redis
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
 * Handel hash table: term
 */
class Term extends Base{

    /*
     * public append
     *
     * Add index
     * @param string Stem
     * @param int    File number
     * @param int    Row
     * @param int    Column
     */
    public function append($term, $file, $row, $column){
        $value = $this->redis->hGet('term',$term);
        if($value != null) $this->redis->hSet('term', $term, $value.';'.$file.' '.$row.' '.$column);
        else $this->redis->hSet('term', $term, $file.' '.$row.' '.$column);
    }

    /*
     * public get
     *
     * Get indexes of a stem
     * @param  string  Stem
     * @return  array  Indexes array
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
 * Handel hash table: text
 */
class Text extends Base{

    /*
     * public save
     *
     * Save content
     * @param int    File number
     * @param array  File content
     */
    public function save($no, $content){
        $this->redis->hSet('text', 'text'.$no, implode(';',$content));
    }

    /*
     * public context
     *
     * Get context
     * @param int    File number
     * @param int    Row
     * @param array  Column array
     */
    public function context($no, $row, $array){
        // Start from min column to max column
        // Max length is 15
        $start = min($array);
        $end = max($array);
        if($end - $start>15) $end = $start+15;
        $text = $this->redis->hGet('text', 'text'.$no);
        if($text != null){
            $text = explode(';',$text);
            $sentence = explode(' ', $text[$row]); 
            $return = '';
            for($i = $start;$i<=$end;$i++) $return = $return.' '.$sentence[$i];
            // Handle the front half
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
            // Handle the back half
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
 * Handel hash table: path
 */
class Path extends Base{
    /*
     * public save
     *
     * Save link path
     * @param  string  Link path
     * @return int     File number
     */
    public function save($path){
        $now = $this->redis->hLen('path');
        $this->redis->hSet('path', 'text'.($now+1), $path);
        return $now+1;
    }
    /*
     * public get
     *
     * Get link path
     * @param  int     File number
     * @return string  Link path
     */
    public function get($no){
        return $this->redis->hGet('path', 'text'.$no);
    }
}

/*
 * Class Cache
 *
 * Handel hash table: cache
 */
class Cache extends Base{
    /*
     * public isSearched
     *
     * If there are results of these words in cache
     * @param  string  Search words
     * @return bool
     */
    public function isSearched($word){
        return $this->redis->hExists('cache', strtr($word,' ', ';'));
    }
    /*
     * public save
     *
     * Make cache
     * @param  string  Search words
     * @return array   Search result
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
     * Get cache
     * @param  string  Search words
     * @return array   Search result
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
 * Handel hash table: num
 */
class Num extends Base{
    /*
     * public save
     *
     * Save length of a file
     * @param  int  file number
     * @param  int  File length
     */
    public function save($no, $num){
        $this->redis->hSet('num', 'text'.($no), $num);
    }
    /*
     * public get
     *
     * Get length of a file
     * @param  int  File number
     * @return int  File length
     */
    public function get($no){
        return $this->redis->hGet('num', 'text'.($no));
    }
}

/*
 * Class IDF
 *
 * Handel hash table: idf
 */
class IDF extends Base{
    /*
     * public save
     *
     * Save IDF
     * @param  string Stem
     * @param  float  IDF
     */
    public function save($term, $num){
        $this->redis->hSet('idf', $term, $num);
    }
    /*
     * public get
     *
     * Read IDF
     * @param  string Stem
     * @return float  IDF
     */
    public function get($term){
        return $this->redis->hGet('idf', $term);
    }
}
