<?php
namespace app\AIConnection;

use ErrorException;
use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Renderer\AbstractRenderer;
use Jfcherng\Diff\SequenceMatcher;
use OpenAI;

require_once __DIR__ . '/../../config.php';
class PuppetierConnection{
    
    public $url = '';
    public $content = '';
    public function init(string $url){
        $this->url = $url;
    }
    public function getURLContent($url){
        $command = "sudo -u server /usr/bin/node " . ROOT_DIR . "/pup.js $url";
        exec($command, $output, $return_var);
        $output = array_filter($output);
        return join("\r\n", $output);   
    }
    
    public function removeDuplicate(){
        $rendererName = 'Unified';
        $differOptions = [
            'context' => Differ::CONTEXT_ALL,
            'ignoreCase' => true,
            'ignoreLineEnding' => true,
            'lengthLimit' => 20000,
            'fullContextIfIdentical' => true
        ];
        $diff = DiffHelper::calculate($standardPage, $myPage, $rendererName, $differOptions);
        $cleanContent = collect(explode("\n", $diff))
        ->filter(function ($item){
            return str_starts_with($item, AbstractRenderer::SYMBOL_MAP[SequenceMatcher::OP_INS]);
        })
        ->map(function($item){
            return ltrim($item, AbstractRenderer::SYMBOL_MAP[SequenceMatcher::OP_INS]);
        })
        ->join("\n");
        return $cleanContent;

    }
    

}



