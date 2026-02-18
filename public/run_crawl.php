<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../autoload.php';

$progressFile = $argv[1] ?? __DIR__.'/tmp/crawl_progress.json';
$crawl = new Crawl();
$crawl->runCrawl($progressFile);