<?php
use Curl\Curl;
session_start();
require __DIR__ . '/../vendor/autoload.php';
// echo htmlentities($_SESSION['tobeanalysed']);

// curl \
//     -X POST \
//     -H 'Content-Type: application/json' \
//     -d '{"id":"1","content":"Hello world!","date":"2015-06-30 19:42:21"}' \
//     "https://httpbin.org/post"

$data = [
    'model' => 'qwen2',
    'prompt' => $_SESSION['tobeanalysed']. "\n\n". 'Extrahiere die Links zu den Seminaren, Kursen und schulungen! gib auch das Datum an',
     "stream" => false,
];

$curl = new Curl();
$curl->setHeader('Content-Type', 'application/json');
$curl->setTimeout(500);
$curl->post('http://localhost:11434/api/generate', $data);
echo '<pre>';
echo $curl->response->response;
echo '</pre>';