<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../autoload.php';

$sql[] = 'ALTER TABLE `crawl_master`
    DROP IF EXISTS  `Headerfilter`,
    DROP IF EXISTS  `Footerfilter`;';
$sql[] = 'ALTER TABLE `crawl_master` ADD if not exists `FilterFormular` JSON NULL 
    COMMENT \'Manche Webseiten sollten vor dem scrapen gefiltert werden. Das hier eingegebene JSON führt zum Absenden des Filter-Formulars.\' AFTER `Detailseite`;';
$sql[] = 'ALTER TABLE `crawl_master`
 ADD IF NOT EXISTS `Aktiv` TINYINT(1) NOT NULL DEFAULT 1 AFTER `Seite`;';

foreach($sql as $s){
    DB::DB()->query($s);
}
echo 'fertig';