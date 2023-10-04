<?php

require_once __DIR__ . '/../vendor/autoload.php';
/*
 * 使用方法:
 * 1实例化对象:  $zip = new tekintian\TZip();
 * CI4里面使用工厂对象来实例化 $zip = Factories::library(\tekintian\TZip::class);
 *
 * 2读取目录 $zip->readDir( '/usr/www/xxx', false );
 * 添加目录 $zip->addDir('xxx');  向目录添加数据 $zip->addData('xxx/demo.txt', $data);
 * 3下载或者打包目录中的文件 $zip->download( $file );   $zip->archive( $path );
 * 4清理  $zip->clearData();
 *
 */
// 指定你要压缩的文件路径,可以是相对路径或者绝对路径
$path = "../";
// 输出的文件路径
$outfile = __DIR__ . "/test.zip";

// 实例化对象
$zip = new tekintian\TZip();
// 读取目录
$zip->readDir($path, false);
// 在压缩包内添加一个目录 demo1 这里的目录是你最终的压缩包中的目录,非你本地现有的目录!
$zip->addDir('demo1');
$data = file_get_contents('./demo.txt');
// 将demo.txt数据存放到压缩包的demo1/demo.txt 注意这个操作说指定的目录demo1必须先通过 addDir创建
$zip->addData('demo1/demo.txt', $data);

// 将文件通过浏览器下载
// $zip->download($outfile);
// 打包目录中的文件为test.zip
$zip->archive($outfile);
# 清理数据
$zip->clearData();
