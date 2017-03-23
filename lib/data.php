<?php
namespace lib\data;
/*
** Lab: Date
**
** Reading and saving data from redis.
*/

// Class: Base
// Provide fundamental connection for redis.
class Base{
    protected $redis;

    public function __construct(){
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1',6379);
	$this->redis->auth('engine');
    }
}

// Class: Term
// Hash table Term in redis
class Term extends Base{

    // to append index
    public function append($term, $file, $row, $column){
        $value = $this->redis->hGet('term',$term);
        if($value != null) $this->redis->hSet('term', $term, $value.';'.$file.' '.$row.' '.$column);
        else $this->redis->hSet('term', $term, $file.' '.$row.' '.$column);
    }

    // to read and deserialization
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

// Class: Text
// Hash table Text in redis
class Text extends Base{

    public function save($no, $content){
        $this->redis->hSet('text', 'text'.$no, implode(';',$content));
    }

    // get context of a word
    public function context($no, $row, $array){
        //sort($array);
        $start = min($array);
        $end = max($array);
        if($end - $start>15) $end = $start+15;
        $text = $this->redis->hGet('text', 'text'.$no);
        if($text != null){
            $text = explode(';',$text);
            $sentence = explode(' ', $text[$row]); 
            $return = '';
            for($i = $start;$i<=$end;$i++) $return = $return.' '.$sentence[$i];
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

// Class: Path
// Hash table Path in redis
class Path extends Base{
    public function save($path){
        $now = $this->redis->hLen('path');
        $this->redis->hSet('path', 'text'.($now+1), $path);
        return $now+1;
    }

    public function get($no){
        return $this->redis->hGet('path', 'text'.$no);
    }
}

// Class: Cache
// Hash table Path in redis
class Cache extends Base{
    // To query if words are cached
    public function isSearched($word){
        return $this->redis->hExists('cache', strtr($word,' ', ';'));
    }

    public function save($word, $results){
        foreach($results as $key=>$value){
            $temp[$key] = implode('_',$value);
        }
        $this->redis->hSet('cache',strtr($word,' ', ';'), implode(';',$temp));
    }

    public function get($word){
        $cache = $this->redis->hGet('cache', strtr($word,' ', ';'));
        $cache = explode(';',$cache);
        foreach($cache as $key=>$value){
            $return[$key] = explode('_',$value);
        }
        return $return;
    }
}
class Num extends Base{
    public function save($no, $num){
        $this->redis->hSet('num', 'text'.($no), $num);
    }

    public function get($no){
        return $this->redis->hGet('num', 'text'.($no));
    }
}

class IDF extends Base{
    public function save($term, $num){
        $this->redis->hSet('idf', $term, $num);
    }

    public function get($term){
        return $this->redis->hGet('idf', $term);
    }
}
