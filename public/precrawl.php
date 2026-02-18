<?php

use AIConnection\Utils\Show;
use League\HTMLToMarkdown\HtmlConverter;


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../autoload.php';

$masterCrawlTable = 'crawl_master';
$crawlListTable = 'crawl_list';
$model = 'meta-llama-3.1-70b-instruct';
$c = new Crawl();
// var_dump($c);
$eventInfo = '';
function verbose($msg){
  echo $msg .'<br>';
  flush();
}
$c->preCrawl();

die;



/*


// bisschen aufräumen
DB::DB()->query("DELETE $crawlListTable FROM $crawlListTable LEFT JOIN $masterCrawlTable ON $masterCrawlTable.id = master_id WHERE $masterCrawlTable.id  is null" );


$masterCrawlURLs = DB::DB()->query("SELECT * FROM $masterCrawlTable WHERE Detailseite LIKE :Detailseite OR Detailseite is null",['Detailseite'=>'Nein']);
// verbose( '<pre>');
// verbose(var_export($masterCrawlURLs, true));
foreach($masterCrawlURLs as $masterCrawlURL ){ 
  $parsedURL = parse_url($masterCrawlURL->URL);
  // scheme host path
  verbose('<strong>Crawle '.$masterCrawlURL->Seite.' Übersichsseite</strong> <small>'.$masterCrawlURL->URL.'</small>');
  verbose('Hole Quelltext');
  $command = "sudo -u server /usr/bin/node " . dirname(__DIR__) . "/pup.js ". $masterCrawlURL->URL;
  exec($command, $output, $return_var);
  // entfernen leerer Elemente
  $output = array_filter($output);
  $source =  join("\r\n", $output);
 // $source = Show::cleanHtml($source);
//  $markdown = Show::cleanMarkup($converter->convert($source));
  echo '<pre>' ;
//   echo $source;
  preg_match_all('~href="([^#][^"]*)"~m', $source, $detailUrls);
  //print_r($detailUrls);
  $c->setCrawlMasterData($masterCrawlURL);
  foreach($detailUrls[1] as $detailUrl){
    $parsedDetailURL = parse_url($detailUrl); 
    if(!isset($parsedDetailURL['host'])){
      $detailUrl = $parsedURL['scheme'].'://'.$parsedURL['host']. $detailUrl;
    }
    if(strstr($detailUrl, $masterCrawlURL->Verzeichnis)!==false  && strcmp($detailUrl, $masterCrawlURL->URL ) != 0 ){
      $c->savePossibleDetailPage($detailUrl);
      echo $detailUrl;
      echo '<br>';
    }

  }




}*/