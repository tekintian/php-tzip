# PHP zip打包压缩工具

简单,快速,高效的在线ZIP文件打包压缩下载工具. 支持php5, php7, php8

## 使用方法： 

### Composer 方式

1. Composer安装类库

   ~~~sh
   # 加载php-tzip类库
   composer require  tekintian/php-tzip
   
   ~~~

   

2. 使用示例

   可查看 tests/demo.php文件中的相关演示

   ~~~php
   <?php
   
   require_once __DIR__ . '/../vendor/autoload.php';
   
   // 实例化对象
   $zip = new tekintian\TZip();
   
   // 指定你要压缩的文件路径,可以是相对路径或者绝对路径
   $path = "../";
   // 输出的文件路径
   $outfile = __DIR__ . "/test.zip";
   
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
   
   
   ~~~




专业企业信息化软件研发, 个性化软件定制开发,手机APP开发服务商.
服务热线:13888011868 
QQ:932256355

