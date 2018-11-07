<?php
/**
 * @author wsfuyibing <websearch@163.com>
 * @date   2018-11-05
 */
include __DIR__.'/Classes/Console.php';
include __DIR__.'/Classes/Base.php';
include __DIR__.'/Classes/Config.php';
include __DIR__.'/Classes/Export.php';
include __DIR__.'/Classes/ExportInstall.php';


$base = new Base();
$config = new Config($base);
$export = new ExportInstall($base, $config);
$export->run();

