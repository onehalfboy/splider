<?php
//加载爬虫类
include_once "splider.php";
set_time_limit(3600);

//$url = "http://www.kokuyo.cn/";
// $url = "http://www.kokuyo.com/";
//$url = "http://www.kokuyo.co.jp/";
//$url = "http://search.kokuyo.co.jp/";
$url = "http://www.robotphoenix.com/";
$splider = new splider($url, "index.html");
$splider->getPage($url);
$splider->logResult();
$splider->showResult();
