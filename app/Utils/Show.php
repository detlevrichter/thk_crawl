<?php
namespace AIConnection\Utils;

use ErrorException;
use OpenAI;

//require_once __DIR__ . '/../vendor/autoload.php';
//require_once __DIR__ . '/../../config.php';
class Show{
    public $client;
    public function __construct()
    {
        $this->client = OpenAI::factory()
        ->withApiKey(API_KEY)
        ->withOrganization('th-koeln') // default: null
        ->withBaseUri(API_ENDPOINT)
        ->withHttpClient($client = new \GuzzleHttp\Client([]))
        ->make();
    } 

    public function createPrompts(array $messages, string $model, string $systemprompt = 'You are a helpful assistant'):array{
        $prompts = [];
        foreach($messages as $message){
            $prompts[] = [
                'model' => $model,
                'messages' =>  [["role"=>"system","content"=>$systemprompt],['role' => 'user', 'content' => $message]],
                /*'temperature' => 1*/
                
            ];
            
        }
        return $prompts;
    }
    public function chunk(string $message):array{
        $splitcontent = explode("@|@", wordwrap($message, MAX_STRLEN, "@|@", false));
        return $splitcontent;
    }
    public function chat(string $content = 'Hello!',string $model = 'meta-llama-3-8b-instruct', string $systemprompt = 'You are a helpful assistant' ):array{
        $messages = [];
        $finalPrompts = [];
        $answers = [];
        if(!$this->client){
            throw new ErrorException('No Client iniciated.');
        }
        if( mb_strlen($content) > MAX_STRLEN){
            $splitcontent = $this->chunk($content);
            foreach($splitcontent as $con){
                $messages[] = ($con);
            }

        }else{
            $messages[] = $content;
        }

        $finalPrompts = $this->createPrompts($messages, $model, $systemprompt);
        foreach($finalPrompts as $prompt){
           // print_r($prompt); die;
           try {
            //code...
                $result = $this->client->chat()->create($prompt);
                $answers[] = $result->choices[0]->message->content;
           } catch (\Throwable $th) {
            //throw $th;
                $answers[] = 'Keine Antwort von der AI - :c';
           }
        }
        return $answers;
    }
    public static function cleanHtml($echo){
        
        
        $echo = preg_replace(['/<script.*<\/script>/sU', '/<style.*<\/style>/sU', '/<head.*<\/head>/sU', '/\t*/','/<[^\/][\S]+\s*>\s*<\/[\S]+>/'], '', $echo);
        $echo = preg_replace([
        "/<([a-z0-9]*) ([^>]*)class=\"[^\"]*\"([^>]*)>/iU",
        "/<([a-z0-9]*) ([^>]*)id=\"[^\"]*\"([^>]*)>/iU",
        "/<([a-z0-9]*) ([^>]*)style=\"[^\"]*\"([^>]*)>/iU",
        "/<([a-z0-9]*) ([^>]*)onmouseover=\"[^\"]*\"([^>]*)>/iU",
        "/<([a-z0-9]*) ([^>]*)onmouseout=\"[^\"]*\"([^>]*)>/iU",
        "/<([a-z0-9]*) ([^>]*)onkeyup=\"[^\"]*\"([^>]*)>/iU",
        ],
        [
        '<$1>','<$1>','<$1>','<$1>','<$1>','<$1>',
        ], $echo);
      //  $echo = preg_replace("/<([a-z0-9]*) ([^>]*)style=\"[^\"]*\"([^>]*)>/iU",'<$1>',$echo);
        $echo = preg_replace(['/\r\n\r\n/','/<div >/','/&nbsp;/','/\s{2,100}/'], ["\n",'<div>',' ',' '], $echo);
        return $echo;
    }
    public static function cleanMarkup($echo){
         
        //$echo = preg_replace(['/\r?\n\r?\n/', '/ {2,100}/', '/\r?\n{2,100}/' ], ["\n",  ' ', "\n"], $echo);
        return $echo;
    }
    public static function robots_allowed($url, $useragent = FALSE)
    {

        $agents = [
        preg_quote('*')
        ];
        if($useragent) {
        $agents[] = preg_quote($useragent);
        }
        $agents = implode('|', $agents);
        $parsed = parse_url($url);
        $target = "{$parsed['scheme']}://{$parsed['host']}/robots.txt";
        $robotstxt = file_get_contents($target);
        
        if(empty($robotstxt) || strpos($http_response_header[0],'40') !== false  || strpos($robotstxt,'<body') !== false ) {
        return true;
        }
        $rules = [];
        $line = strtok($robotstxt, "\r\n");
        $rule_applies = false;
        while(false !== $line) {
        if(!$line = trim($line)) continue;
        // following rules only apply if User-agent matches $useragent or '*'
        if(preg_match('/^\s*User-agent: (.*)/i', $line, $match)) {
            $rule_applies = preg_match("/($agents)/i", $match[1]);
        }

        if($rule_applies && preg_match('/^\s*Disallow:(.*)/i', $line, $regs)) {
            if(!$regs[1]) return TRUE;
            $rules[] = preg_quote(trim($regs[1]), "/");
        }
        $line = strtok("\r\n");

        }
        foreach($rules as $rule) {
        // check if page is disallowed to us
        if(preg_match("/^$rule/", $parsed['path'])) {
            return false;
        }
        }
        return true;

    }

}



