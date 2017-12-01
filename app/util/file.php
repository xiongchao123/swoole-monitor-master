<?php
/**
 * Created by PhpStorm.
 * User: XC
 * Date: 2017/11/27
 * Time: 16:00
 */

/**
 * get directory lists
 * @param $path
 * @return array
 */
function get_files($path) {
    $dir = new RecursiveDirectoryIterator($path);
    return getFiles($dir);

}

function getFiles($dir){

    $files = array();
    for (; $dir->valid(); $dir->next()) {
        if ($dir->isDir() && !$dir->isDot()) {
            if ($dir->haschildren()) {
                $wrong_path=str_replace('\\','/',dirname($dir->getChildren()->getPathName()));
                exit("Unknown path: ".$wrong_path);
                //$files = array_merge($files, getFiles($dir->getChildren()));
            }
        }else if($dir->isFile()){
            $files[] = $dir->getPathName();
        }
    }
    return $files;
}