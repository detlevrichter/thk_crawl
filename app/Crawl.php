<?php

use function Laravel\Prompts\table;
use AIConnection\Utils\Show;
use League\HTMLToMarkdown\HtmlConverter;

class Crawl extends Model
{
    private string $model;

    public const PAGE_TYPE = 'page_type';
    public const CRAWL_LIST_TABLE = 'crawl_list';
    public const CRAWL_MASTER_TABLE = 'crawl_master';

    public const PAGE_TYPE_EVENT_DETAIL = 'event-detail';
    public const PAGE_TYPE_EVENT_LIST = 'event-list';
    public const PAGE_TYPE_OTHER = 'other';

    public const EVENT = 'event';

    public const LANUGAGE = 'language';

    public const ERROR_LLM_NOT_ACTIVE = 'LLM_NOT_ACTIVE';
    public const ERROR_NO_API_KEY = 'NO_API_KEY';
    public const ERROR_API_ERROR = 'API_ERROR';
    public const ERROR_JSON_PARSE_FAILED = 'JSON_PARSE_FAILED';
    public const ERROR_EMPTY_RESPONSE = 'EMPTY_RESPONSE';

    public stdClass $masterCrawlData;
    public HtmlConverter $converter;
    public Show $client;
    public $badPagesCount = 0;
    public $pobject;
    private $content = '';
    private $promptTokens = 0;
    private $completionTokens = 0;
    private $stopReason = '';


    /**
     * @param string $model
     */
    public function __construct(string $model = '')
    {
        $pdo = DB::DB()->PDO();
        self::setConnection($pdo);
        $this->model = $model?:LLM_MODEL;
        $this->client = new Show();
        $this->converter = new HtmlConverter();
        $this->converter->getConfig()->setOption('strip_tags', true);
        $this->pobject = new Prompt();
    }

    public function setCrawlMasterData(stdClass $masterCrawlData){
        $this->masterCrawlData = $masterCrawlData;
    }

    public function savePossibleDetailPage(string $url){
        $enty = new self();
        $enty->table = self::CRAWL_LIST_TABLE;
        $res = $enty->getByAttribute(['url'=>$url ]);
        $id = NULL;
        if(isset($res[0])){
            $id = $res[0]->id ;
        }
        $data = [
            'id' => $id,
            'master_id' => $this->masterCrawlData->id,
            'url' => $url,
        ];
        $enty->fill($data);
        $enty->save();

    }


    protected function performInsert(): bool
    {
        $this->attributes['created_at'] = date('Y-m-d H:i:s');
        $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        // Arbeitskopie
        $data = $this->attributes;

        // Primärschlüssel beim Insert raus, wenn leer
        if (array_key_exists($this->primaryKey, $data) && empty($data[$this->primaryKey])) {
            unset($data[$this->primaryKey]);
        }

        if (empty($data)) {
            throw new \Exception("Keine Daten zum Einfügen vorhanden.");
        }

        // Spalten/Parameter aufbauen + Werte normalisieren
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($data as $col => $val) {
            // Sicherheitshalber noch einmal PK filtern, falls null
            if ($col === $this->primaryKey && ($val === null || $val === '')) {
                continue;
            }

            $columns[] = $col;
            $placeholders[] = ':' . $col;
            $params[$col] = $this->normalizeForDatabase($val);
        }

        if (empty($columns)) {
            throw new \Exception("Keine gültigen Spalten zum Einfügen.");
        }

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)  ",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        ). " ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP";

        $stmt = self::$connection->prepare($sql);
        $result = $stmt->execute($params);

        // PK nachziehen
        if ($result && !isset($this->attributes[$this->primaryKey])) {
            $this->attributes[$this->primaryKey] = self::$connection->lastInsertId();
        }

        return $result;
    }

    public function setBadPage(string $url, $markdown = ''){
        $this->table = self::CRAWL_LIST_TABLE;
        $dbresult = $this->getByAttribute(['url' => $url], PDO::FETCH_ASSOC);
        $enty = new self();
        $data = $dbresult[0];
        $data['status'] = 'FALSE';
        $data['markup'] = $markdown;
        $enty->fill($data);

        $enty->table = self::CRAWL_LIST_TABLE;
        $enty->save();


    }
    public function setGoodPage(string $url, $markdown = ''){
        $this->table = self::CRAWL_LIST_TABLE;
        $dbresult = $this->getByAttribute(['url' => $url], PDO::FETCH_ASSOC);
                $enty = new self();
        $data = $dbresult[0];
        $data['status'] = 'TRUE';
        $data['markup'] = $markdown;
        $enty->fill($data);

        $enty->table = self::CRAWL_LIST_TABLE;
        $enty->save();


    }
/**
 * Diese Funtkion kann eigentlich immer ausgeführt werden, denn sie soll nur verwaiste Einträge löschen.
 */
    public function referentialIntegrity(){
        $masterCrawlTable = self::CRAWL_MASTER_TABLE;
        $crawlListTable = self::CRAWL_LIST_TABLE;
        $offerTable = Offer::OFFER_TABLE;
        $offerCompetencies = 'offer_competencies';
        // nichts in der crawl list wo es keinen master gibt
        DB::DB()->query("DELETE $crawlListTable FROM $crawlListTable LEFT JOIN $masterCrawlTable ON $masterCrawlTable.id = master_id WHERE $masterCrawlTable.id  is null" );
        // keine offers die nicht in der crawl liste sind
        DB::DB()->query("DELETE $offerTable FROM $offerTable LEFT JOIN $crawlListTable ON $crawlListTable.id = crawl_list_id WHERE $crawlListTable.id  is null" );
        // keine offer-Eigenschaften (offer_competencies) wenn es keine offers gibt
        DB::DB()->query("DELETE $offerCompetencies FROM $offerCompetencies LEFT JOIN $offerTable ON $offerTable.id = offer_id WHERE $offerTable.id  is null" );

    }
/**
 * precrawl füllt die crawl_list
 */
    public function preCrawl(){
        $gesamt = 0;
        $masterCrawlTable = self::CRAWL_MASTER_TABLE;
        $masterCrawlURLs = DB::DB()->query("SELECT * FROM $masterCrawlTable WHERE Aktiv = 1");
        $masterUrURL = '';
        foreach($masterCrawlURLs as $masterCrawlURL ){
            // wenn pagination nötig, dann jetzt
            if($masterCrawlURL->Paginierung == 'URL'){
                if(is_numeric($masterCrawlURL->PaginierungsStopp) && $masterCrawlURL->PaginierungsStopp < 50){
                    $masterUrURL = str_replace($masterCrawlURL->PaginierungsEigenschaft, '', $masterCrawlURL->URL);
                    for($page=1; $page<=$masterCrawlURL->PaginierungsStopp; $page++){
                        $masterCrawlURL->URL = $masterUrURL . preg_replace('/[01]+$/', $page, $masterCrawlURL->PaginierungsEigenschaft);
                        //trigger_error($masterCrawlURL->URL);
                        $this->setCrawlMasterData($masterCrawlURL);
                        $detailUrls = $this->harvestDetailUrls($masterCrawlURL->URL, $masterCrawlURL );
                        foreach($detailUrls  as $detailUrl){
                            $gesamt += $this->composeAndSaveDetailUrl($detailUrl, $masterCrawlURL);
                        }
                    }
                }
            }else{
                $this->setCrawlMasterData($masterCrawlURL);
                $detailUrls = $this->harvestDetailUrls($masterCrawlURL->URL, $masterCrawlURL );
                foreach($detailUrls  as $detailUrl){
                    $gesamt += $this->composeAndSaveDetailUrl($detailUrl, $masterCrawlURL);
                    $this->setProgress(1, 'Detailliste holen '. $gesamt );
                }
            }
        }
        return $gesamt;
    }
    public function harvestDetailUrls(string $masterUrl, $masterCrawlURL = null) : array{
        $options = '{}';
        if($masterCrawlURL){
            $options = json_encode(json_decode($masterCrawlURL->customCommands));
        }
        $command = NODEJS_EXE . " " . dirname(__DIR__) . "/pup.js " . escapeshellarg($masterUrl) . " " . escapeshellarg($options);

        exec($command, $output, $return_var);
      //  exec($command . " 2>&1", $output, $return_var);

        $output = array_filter($output);
         $source =  join("\r\n", $output);
// echo '<hr>Anzahlderkurse: ';
// echo $count = substr_count($source, '/p/veranstaltungsprogramm');
        preg_match_all('~href="([^#][^"]*)"~m', $source, $detailUrls);
      //  echo count( $detailUrls[1]);
        return $detailUrls[1];
    }
    public function composeAndSaveDetailUrl(string $detailUrl, stdClass $masterCrawlURL) : int {
        $teilsumme = 0;
        $parsedMasterURL = parse_url($masterCrawlURL->URL);
        $parsedDetailURL = parse_url($detailUrl);
        if(!isset($parsedDetailURL['host'])){
            $detailUrl = '/' . ltrim($detailUrl, '/');
            $detailUrl = $parsedMasterURL['scheme'].'://'.$parsedMasterURL['host']. $detailUrl;
        }
        if ($masterCrawlURL->includeRegex && !preg_match($masterCrawlURL->includeRegex, $detailUrl)) {
            // Include-Regel nicht erfüllt
            return $teilsumme;
        }

        if ($masterCrawlURL->excludeRegex && preg_match($masterCrawlURL->excludeRegex, $detailUrl)) {
            // Exclude-Regel erfüllt
            return $teilsumme;
        }

        if(strstr($detailUrl, $masterCrawlURL->Verzeichnis)!==false  && strcmp($detailUrl, $masterCrawlURL->URL ) != 0 ){
            $this->savePossibleDetailPage($detailUrl);
            $teilsumme++;
        }
        return $teilsumme;
    }

    /**
     * Prüft anhand der robots.txt des Hosts, ob $url gecrawlt werden darf.
     * Fehlt die robots.txt oder ist sie nicht erreichbar, gilt das Crawlen als erlaubt.
     * Im Gegensatz zu Show::robots_allowed() erzeugt das hier bei fehlender/nicht erreichbarer
     * robots.txt keine PHP-Warnung.
     */
    private function robotsAllowed(string $url): bool
    {
        $parsed = parse_url($url);
        if (!isset($parsed['scheme'], $parsed['host'])) {
            return true;
        }

        $target = "{$parsed['scheme']}://{$parsed['host']}/robots.txt";
        $context = stream_context_create([
            'http' => ['timeout' => 5, 'ignore_errors' => true],
            'https' => ['timeout' => 5, 'ignore_errors' => true],
        ]);
        $robotstxt = @file_get_contents($target, false, $context);
        $statusLine = $http_response_header[0] ?? '';

        // keine/kaputte robots.txt, 4xx/5xx-Antwort oder HTML-Fehlerseite statt Textdatei -> erlaubt
        if ($robotstxt === false || trim($robotstxt) === ''
            || preg_match('~\s[45]\d\d\s~', $statusLine)
            || stripos($robotstxt, '<body') !== false) {
            return true;
        }

        $path = $parsed['path'] ?? '/';
        $rules = [];
        $ruleApplies = false;
        $line = strtok($robotstxt, "\r\n");
        while ($line !== false) {
            $line = trim($line);
            if ($line === '') {
                $line = strtok("\r\n");
                continue;
            }
            if (preg_match('/^User-agent:\s*(.*)/i', $line, $match)) {
                $ruleApplies = (trim($match[1]) === '*');
            } elseif ($ruleApplies && preg_match('/^Disallow:\s*(.*)/i', $line, $regs)) {
                $rule = trim($regs[1]);
                if ($rule !== '') {
                    $rules[] = $rule;
                }
            }
            $line = strtok("\r\n");
        }

        foreach ($rules as $rule) {
            if (strpos($path, $rule) === 0) {
                return false;
            }
        }
        return true;
    }

    public function crawl(bool $re = false){
        $crawlListTable = 'crawl_list';
        $this->badPagesCount = 0;
        if($re){
            $crawlListURLs = DB::DB()->query("SELECT * FROM $crawlListTable WHERE status LIKE 'FALSE' ");
        }else{
            $crawlListURLs = DB::DB()->query("SELECT * FROM $crawlListTable ");
        }
        $gesamtSeiten = count((array)$crawlListURLs);
        $markdown = '';
        $result = false;
        $i = 0;
        foreach ($crawlListURLs as $crawlListURL) {
            usleep(MICRO_SLEEP_TIME);
            $i++;
            $this->setProgress(round($i/$gesamtSeiten,3)*100, 'KI fragen - Seite '. $i .' von ' . $gesamtSeiten . ' davon schlechte Seiten: '. $this->badPagesCount);
            echo ('<strong>Crawle ' . ' Detailseite</strong> <small>' . $crawlListURL->url . '</small>');

            if (!$re && !$this->robotsAllowed($crawlListURL->url)) {
                echo ('<br><em>Durch robots.txt für Crawler gesperrt, Seite wird übersprungen.</em>');
                $this->setBadPage($crawlListURL->url, 'Durch robots.txt für Crawler gesperrt.');
                $this->badPagesCount++;
                continue;
            }

            // beim recrawlen muss man den Quelltext nich noch mal holen, da ist er ja schon da
            if($re){
                $markdown = $crawlListURL->markup;
            }else{
                $markdown = $this->getMarkdown($crawlListURL);
                $markdown = "\n". 'Quell-URL: '.$crawlListURL->url . "\n" . $markdown;
            }
            $result = $this->handleMarkdown($crawlListURL, $markdown  );
            if($result){
                $this->setGoodPage($crawlListURL->url,$markdown);
            }else{
                $this->setBadPage($crawlListURL->url, $markdown);
                $this->badPagesCount++;
            }

        }
    }


    public function getMarkdown(stdClass $crawlListURL) : string{
            echo ('Hole Quelltext');
            $command = NODEJS_EXE . " " . dirname(__DIR__) . "/pup.js " . escapeshellarg($crawlListURL->url);
            exec($command, $output, $return_var);
            // entfernen leerer Elemente
            $output = array_filter($output);
            $source =  join("\r\n", $output);
            $source = Show::cleanHtml($source);
            return Show::cleanMarkup($this->converter->convert($source));
    }
    public function handleMarkdown(stdClass $crawlListURL, string $markdown) :bool {
        $master = DB::DB()->query("SELECT * from crawl_master WHERE id =".(int)$crawlListURL->master_id );
        echo ('Frage bei der KI nach');
        $prompt = $this->pobject->get((int)$crawlListURL->master_id);
        $answers = $this->client->chat($markdown, $this->model, $prompt);
        $offer = null;
        foreach ($answers as $answer) {
            echo ('<br><strong>Ergebnis</strong><hr>' . nl2br($answer) . '<hr>');
            $answer = trim($answer, "json \n\r\t\v\0`");
            if (strcmp($answer, 'FALSE') == 0 || strstr($answer,'Keine Antwort von der AI') !== false) {
                return false;
            }
            try {
                $eventInfo = json_decode($answer, true);
            } catch (\JsonException $jsonException) {
                throw new JsonException("Etwas ist schiefgelaufen mit JSON" . $jsonException);
            }
            $offer = new Offer([
                Offer::OFFER_CRAWL_LIST_ID => $crawlListURL->id,   //$webtext->id,
                Offer::OFFER_URL => $crawlListURL->url, // $webtext->{Webtexts::WEBTEXTS_URL},
            ]);
            if (is_null($eventInfo)) {
                continue;
            }
            $eventInfo['provider'] = $master[0]->Name;
            try {
                //code...
                $offer->updateFromLLM($eventInfo);
            } catch (\Throwable $th) {
                //throw $th;
                trigger_error(print_r($eventInfo,1));
                //throw new Exception(print_r($eventInfo,1), 1);
                $this->logThrowable($th);
                continue;
            }
            if (!$offer->id) continue;
            $eventInfo['offer_id'] = $offer->id;

            $offercompetency = new OfferCompetency($eventInfo);
            $offercompetency->purge()->save();
        }
        return true;
    }
    /**
     *
     */
    public function runCrawl(string $progressFile){
        $this->progressFile = $progressFile;
        $this->setProgress(1, 'Detailliste holen');
        $this->preCrawl();
        $this->setProgress(9, 'Detailliste geholt');
        $this->referentialIntegrity();
        $this->setProgress(10, 'referenzielle Integrität hergestellt');
        $this->crawl();
       // $this->crawl($re = true);

    }

    /**
     *
     */
    public function setProgress(int $i, $job = 'working'){
        file_put_contents($this->progressFile, json_encode([
                'time' => date('Y-m-d H:i:s'),
                'job' => $job,
                'progress' => round($i,3),
                'status' => $i < 100 ? 'running' : 'done',
                'pid' => getmypid()
            ]));
    }

}
