<?php
namespace lib\reader;
/*
** Lab: reader
**
** Scan and read files
*/
class reader
{
    // Scan every file under a directory
    public function scanSrc($dir, $total = array())
    {
        if ($handle = opendir($dir)) {
            while (($file = readdir($handle)) !== false) {
                if ($file != ".." && $file != ".") {
                    if (is_dir($dir . "/" . $file)) {
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

    // Scan the text of a file
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


