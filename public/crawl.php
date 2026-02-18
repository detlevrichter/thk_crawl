<?php

use AIConnection\Utils\Show;
use Rajentrivedi\TokenizerX\TokenizerX;
use League\HTMLToMarkdown\HtmlConverter;


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../autoload.php';

$crawlListTable = 'crawl_list';
$model = 'meta-llama-3.1-70b-instruct';
$c = new Crawl();
// var_dump($c);
$client = new Show();
$converter = new HtmlConverter();
$converter->getConfig()->setOption('strip_tags', true);
$eventInfo = '';
function verbose($msg){
  echo $msg .'<br>';
  flush();
}
$competencies = DB::DB()->query("SELECT * from competency_types ORDER BY id ASC");

$crawlListURLs = DB::DB()->query("SELECT * FROM $crawlListTable " );
$examplesArray = [
                    Offer::OFFER_TITLE => 'Einführung in die Python Programmierung',
                    Offer::OFFER_DESCRIPTION => 'In diesem Kurs lernen Sie die Grundlagen der Python Programmierung.',
                    OfferDate::OFFER_DATE_PRICE => '100 EUR',
                    OfferDate::OFFER_DATE_DURATION => '1 Tag',
                    OfferDate::OFFER_DATE_ZIP => '10247',
                    OfferDate::OFFER_DATE_PLACE => 'Berlin',
                    OfferDate::OFFER_DATE_START => '01.02.2025',
                    Offer::OFFER_LEVEL => '0.25'
];
$bspval = [0,0,1,1,0.5,0.25,0.75];
$i =0;
foreach($competencies as $competence){
     $examplesArray[$competence->query_value] =  $bspval[mt_rand(0, count($bspval)-1)];
}
// verbose( '<pre>');
// verbose(var_export($crawlListURLs, true));
foreach($crawlListURLs as $crawlListURL ){
  verbose('<strong>Crawle '.' Detailseite</strong> <small>'.$crawlListURL->url.'</small>');
  verbose('Hole Quelltext');
  $command = "sudo -u server /usr/bin/node " . dirname(__DIR__) . "/pup.js ". $crawlListURL->url;
  exec($command, $output, $return_var);
  // entfernen leerer Elemente
  $output = array_filter($output);
  $source =  join("\r\n", $output);
  $source = Show::cleanHtml($source);
  $markdown = Show::cleanMarkup($converter->convert($source));
  verbose('Frage bei der KI nach');
 // if($i++ > 5)die;
  $basicEventInfoMessage =  "Bitte extrahiere folgende Information des Seminars / Kurses als JSON-Objekt mit folgenden Feldern:\n\n" .
             '<fields>' . "\n" .
                Offer::OFFER_TITLE  . ": Titel des Seminars, Kurs oder der Veranstaltung.\n" .
                Offer::OFFER_DESCRIPTION  . ": Kurzbeschreibung des Seminars, Kurs oder der Veranstaltung.\n" .
                Offer::OFFER_PROVIDER . ": Name des Seminaranbieters. Leerer String wenn keine Angabe gefunden wird.\n" .
                OfferDate::OFFER_DATE_PRICE . ': Preis des Seminars. Leerer String wenn keine Angabe gefunden wird.' . "\n" .
                OfferDate::OFFER_DATE_DURATION . ': Dauer des Seminars. Leerer String wenn keine Angabe gefunden wird.' . "\n" .
                OfferDate::OFFER_DATE_ZIP . ': Postleitzahl des Veranstaltungsorts oder leer wenn Online.' . "\n" .
                OfferDate::OFFER_DATE_PLACE . ': Veranstaltungsort des Seminars. Leerer String wenn keine Angabe gefunden wird.' . "\n" .
                OfferDate::OFFER_DATE_START . ': Startdatum des Termins. Leerer String wenn keine Angabe gefunden wird.' . "\n" .
                Offer::OFFER_LEVEL . ': Geschätzte Schwierigkeit / Einstiegshöhe des Seminars, Kurs oder der Veranstaltung. Grundkurse werden leichter eingeschätzt als Kurse für die Erfahrung vorausgesetzt wird. Wert zwischen 0 leicht und 1 schwer' . "\n" ;
  foreach($competencies as $competence){
      $basicEventInfoMessage .=  $competence->query_value . ': ' .  str_replace(["\r","\n"], '', $competence->description). "\n";
  }

   $basicEventInfoMessage .=             '</fields>' . "\n\n" .
              //   'ES IST SEHR WICHTIG DAS DIE ANTWORT IN JSON FORMATIERT IST UND DIE FELDER WIE OBEN BESCHRIEBEN ENTHÄLT.' . "\n\n" .
                 'Weil deine Antwort als JSON weiterverarbeitet werden soll, ist es wichtig, dass nur das JSON Objekt ausgegeben wird ohne erklärende Texte oder Hinweise.' . "\n\n" .
                 'Wenn du auf der gegebenen Seite kein Seminar / Kurs findest, oder wenn es sich um eine Übersichtsseite mit mehreren Kursen handelt dann antworte mit FALSE.' . "\n\n" .
                 '<example>' . "\n" .
                 json_encode($examplesArray, JSON_PRETTY_PRINT) . "\n" .
                 '</example>';
                 
          /*  $response =  $this->makeRequest($client, [
                'messages' => [
                    ['role' => 'system', 'content' =>  $basicEventInfoMessage],
                    ['role' => 'user', 'content' => $message],
                ],
                'response_format' => [
                    'type' => 'json_object',
                ],
            ]);*/
 //echo $basicEventInfoMessage; die;
  $answers = $client->chat(  $markdown , $model, $basicEventInfoMessage);
  $offer = null;
  foreach ($answers as $answer) {
    verbose( '<br><strong>Ergebnis</strong><hr>'.nl2br($answer) . '<hr>');
    $answer = trim($answer,"json \n\r\t\v\0`");
    if(strcmp($answer, 'FALSE') == 0){
      // Sperre diese Seite
      $c->setBadPage($crawlListURL->url);
       continue;
    }
    try {
        $eventInfo = json_decode($answer, true);
    } catch (\JsonException $jsonException) {
        throw new JsonException("Etwas ist schiefgelaufen mit JSON". $jsonException );
    }
    $offer = new Offer(            [
        Offer::OFFER_CRAWL_LIST_ID => $crawlListURL->id,   //$webtext->id,
        Offer::OFFER_URL => $crawlListURL->url ,// $webtext->{Webtexts::WEBTEXTS_URL},
    ]);
    if(is_null($eventInfo)){
      continue;
    }
    $offer->updateFromLLM($eventInfo);
    $eventInfo[OfferDate::OFFER_DATE_OFFER_ID] = $offer->id;
    if(!$offer->id)die('weg');
    $offerdate = new OfferDate($eventInfo);
    $offerdate->save();
    if(!isset($eventInfo[OfferCategory::CATEGORY_ID])){
      $eventInfo[OfferCategory::CATEGORY_ID] = 1;
    }
    $offercategory = new OfferCategory($eventInfo);
    $offercategory->purge()->save();
    $offercompetency = new OfferCompetency($eventInfo);
    $offercompetency->purge()->save();

    // andere Kategorien auch eintragen 
    $eventInfo[OfferCategory::CATEGORY_ID] = 2;
    $offercategory = new OfferCategory($eventInfo);
    $offercategory->purge()->save();
    $offercompetency = new OfferCompetency($eventInfo);
    $offercompetency->purge()->save();

    $eventInfo[OfferCategory::CATEGORY_ID] = 3;
    $offercategory = new OfferCategory($eventInfo);
    $offercategory->purge()->save();
    $offercompetency = new OfferCompetency($eventInfo);
    $offercompetency->purge()->save();




    $offercompetency = new OfferCompetency($eventInfo);
    $offercompetency->purge()->save();
  
  }



}