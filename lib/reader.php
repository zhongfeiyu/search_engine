<?php
namespace lib\reader;

/*
 * Class reader
 *
 * PHP Lib
 * 扫描、读取文件
 */
class reader
{
    /*
     *  public scanSrc
     *
     *  @return array   目录下所有文件的文件名
     *  @param  string  要扫描的目录
     *  @param          DFS的辅助参数，初次调用应为空
     */
    public function scanSrc($dir, $total = array())
    {
        if ($handle = opendir($dir)) {
            while (($file = readdir($handle)) !== false) {
                if ($file != ".." && $file != ".") {
                    if (is_dir($dir . "/" . $file)) {
                        // 进行DFS
                        $total += $this->scanSrc($dir . "/" . $file, $total);
                    } else {
                        array_push($total, $dir . '/' . $file);
                    }
                }
            }
            closedir($handle);
            return $total;
        }
    }

    /*
     *  public readOne
     *
     *  @return array   读出文件的所有内容
     *  @param  string  要扫描的文件名
     */
    public function readOne($src)
    {
        $file = fopen($src, "r");
        $content = array();
        while (!feof($file)) {
            array_push($content, fgets($file));

        }
        fclose($file);
        return $content;
    }

}


