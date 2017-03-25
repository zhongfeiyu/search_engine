<?php
namespace lib\reader;
/*
 * Class reader
 *
 * PHP Lib
 * Scan and read file
 */
class reader
{
    /*
     *  public scanSrc
     *
     *  Scan all files under a directory
     *  @return array   Filenames under this directory
     *  @param  string  Directory to scan
     *  @param          Help param of DFS
     */
    public function scanSrc($dir, $total = array())
    {
        if ($handle = opendir($dir)) {
            while (($file = readdir($handle)) !== false) {
                if ($file != ".." && $file != ".") {
                    if (is_dir($dir . "/" . $file))
                        // Do DFS
                        $total += $this->scanSrc($dir . "/" . $file, $total);
                    else
                        array_push($total, $dir . '/' . $file);
                }
            }
            closedir($handle);
            return $total;
        }
    }

    /*
     *  public readOne
     *
     *  Read content of a file
     *  @return array   Content of the file
     *  @param  string  Filename
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


