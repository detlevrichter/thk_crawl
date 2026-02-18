<?php

use AIConnection\Utils\Show;
use Rajentrivedi\TokenizerX\TokenizerX;
use League\HTMLToMarkdown\HtmlConverter;


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
$client = new Show();
$converter = new HtmlConverter();
$converter->getConfig()->setOption('strip_tags', true);
$models = [
  'meta-llama-3.1-8b-instruct',
  'mistral-large-instruct',
  'meta-llama-3.1-70b-instruct',
  'qwen2.5-72b-instruct',
  'deepseek-r1-distill-llama-70b',
];
$echo = '';
$source = '';
$url = '';
$markdown = '';
$url = preg_replace('~[^a-zA-Z0-9#\?\./:-]*~', '', $_POST['web']);
$url =   $_POST['web'] ;
$preprompt = $_POST['preprompt'];
$prompt = $_POST['prompt'];
$model = $_POST['model'];
$source = $_POST['source'] ?? $source;
$systemprompt = !empty($_POST['systemprompt']) ? $_POST['systemprompt'] : 'You are a helpful assistant.';
$methode = 1;
if ($url ?? false) {
 
  $head = '<h5 class="card-title">Browser</h5> ';
  $command = "sudo -u server /usr/bin/node " . dirname(__DIR__) . "/pup.js $url $methode";
  exec($command, $output, $return_var);
  // entfernen leerer Elemente
  $output = array_filter($output);
  $source =  join("\r\n", $output);
  preg_match_all('/<button[^>]*next[^>]*>(.*)<\/button>/imU',$source,$matches);
  $nextButton = str_replace($matches[1],'',$matches[0]);
  $nextButtonFound = false;
  foreach($matches as $match){
    if(!empty($match)){
      $nextButtonFound = true;
    }
  }
  //print_r($matches); die;
  $nextButtonDetectionMessage = '';
  if($nextButtonFound){
    $nextButtonDetectionMessage = 'Es wurde ein "next" Button auf der Seite gefunden. Das deutet auf eine Pagination hin. Falls die aktuelle Seite keine Pagination hat, meldet dieses bitte!';
  }else{
    $nextButtonDetectionMessage = 'Es wurde keine Pagination auf der Seite gefunden. Falls das nicht richtig ist (und es eine Pagination gibt) meldet dieses bitte!';
  }
  $source = Show::cleanHtml($source);
  $markdown = Show::cleanMarkup($converter->convert($source));
}

?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">

  <title>Test</title>

  <!-- Bootstrap core CSS -->
  <link href="./dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    #markup {
      height: 350px;
      width: 100%;
      overflow: scroll;
    }

    .row .col .prompt {
      height: 125px;
    }

    .card.source {
      max-height: 500px;
      overflow-y: scroll;
    }
  </style>
</head>

<body class="">
  <nav class="navbar bg-body-tertiary">
    <div class="container">
      <a class="navbar-brand" href="#">
        <img src="/dist/logo.svg" alt="TH Köln" width="80">
      </a>
      <a href="index.php">start</a>
    </div>
  </nav>
  <div class="container">
    <ul class="nav nav-tabs" id="myTab" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="home-tab" data-bs-toggle="tab" data-bs-target="#home" type="button" role="tab" aria-controls="home" aria-selected="true">Analyse</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false">Quelltext</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab" aria-controls="contact" aria-selected="false">Einstellungen</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="help-tab" data-bs-toggle="tab" data-bs-target="#help" type="button" role="tab" aria-controls="help" aria-selected="false">Hilfe</button>
      </li>
    </ul>
    <form method="post" class="form-signin">
      <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
          <div class="row pt-4">
            <div class="col">
              <div class="input-group mb-3">
                <input type="text" id="inputWeb" name="web" class="form-control" placeholder="http://www.example.com" autofocus value="<?php echo htmlentities($url) ?>">
                <button class="btn btn-outline-secondary" type="submit" id="button-addon2">Analysieren</button>
              </div>
              <?php if(!Show::robots_allowed($url)): ?>
              <div class="alert alert-danger" role="alert">
                <h1 class="alert">Der Zugang zu dieser Seite ist per robots.txt für bots verboten!</h1>
              </div>
          <?php endif; ?>
              <div class="alert alert-primary" role="alert">
                <p class="alert"><?php echo $nextButtonDetectionMessage; ?></p>
              </div>
            </div>
          </div>
          <div class="row pt-2">
            <div class="col">
              <div class="form-floating">
                <textarea name="preprompt" class="form-control prompt" placeholder="Prompt" id="preprompt"><?php echo $preprompt; ?></textarea>
                <label for="preprompt">Pre-Prompt</label>
              </div>
            </div>
            <div class="col">
              <div class="form-floating">
                <textarea name="prompt" class="form-control prompt" placeholder="Prompt" id="postprompt"><?php echo $prompt; ?></textarea>
                <label for="postprompt">Post-Prompt</label>
              </div>
            </div>
          </div>
          <div class="row pt-2">
            <div class="col">
              Zeichenanzahl Pre und Postpromt: <span id="Zeichenzahl"></span>
            </div>
          </div>
          <div class="row pt-2">
            <div class="col">
              <div class="card card-body" id="KIAusgabe">
                <?php
                // clean duplicate content
                if ( ($preprompt || $prompt ) && $model ?? false) {

                  $answers = $client->chat($preprompt . "\r\n" . $markdown . "\r\n" . $prompt, $model, $systemprompt);
                  foreach ($answers as $answer) {
                    echo nl2br($answer) . '<hr><hr>';
                  }
                }
                ?>
              </div>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">

          <div class="row pt-2">
            <div class="col card source">
            <?php if($source ?? false): ?>
            <p>geschätzte Token (Quelltext und Prompts): <?php
                echo number_format(TokenizerX::count($preprompt . "\r\n" . $source . "\r\n" . $prompt), 0, ',', '.'); ?><br>
      Zeichen (Quelltext und Prompts): <span><?php
                                      echo number_format(mb_strlen($preprompt . "\r\n" . $source . "\r\n" . $prompt), 0, ',', '.'); ?></span></p>
    
            <?php endif; ?>
              <h4>Quelltext</h4>
              <code>
                <?php echo htmlentities($source); ?>
              </code>
              
            </div>
            <div class="col card source">
              <h4>Markdown</h4>
              <?php if($markdown ?? false): ?>
              <p>geschätzte Token (Markdown und Prompts): <?php
                echo number_format(TokenizerX::count($preprompt . "\r\n" . $markdown . "\r\n" . $prompt), 0, ',', '.'); ?><br>
      Zeichen (Markdown und Prompts): <span><?php
                                      echo number_format(mb_strlen($preprompt . "\r\n" . $markdown . "\r\n" . $prompt), 0, ',', '.'); ?></span></p>
             <?php endif; ?>
              <code>
                <?php echo $markdown; ?>
              </code>

            </div>
            <img src="/dist/img/screen.png?<?php echo microtime(true); ?>" />
            <img src="/dist/img/screen2.png?<?php echo microtime(true); ?>" />
          </div>
        </div>
        <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
          <div class="row">
            <div class="col">

              <label for="model" class="sr-only">Model</label>
              <select name="model" id="model" class="form-select" aria-label="Default select  ">
                <?php foreach ($models as $mymodel) {
                  $selected = $mymodel == $model ? 'selected' : '';
                  echo '<option ' . $selected . ' value="' . $mymodel . '">' . $mymodel . '</option>';
                }
                ?>
              </select>
              <label for="systemprompt" class="sr-only">Systemprompt</label>
              <input type="text" name="systemprompt" id="systemprompt" value="<?php echo $systemprompt; ?>" class="form-control" placeholder="You are a helpful assistant." />
            </div>
          </div>
        </div>
        
        <div class="tab-pane fade" id="help" role="tabpanel" aria-labelledby="help-tab">
          <div class="row">
            <div class="col">
              <h4>Hilfe</h4>
              <ul class="list-group">
                <li class="list-group-item">Pre-Prompt, Post-Prompt, URL und Einstellungen bleiben im Browser gespeichert. 
                  Wenn zum Beispiel ein anderer Systemprompt gelten soll, dann muss dieser aktiv geändert werden. Es gibt keine 
                  Historie für Eingaben. Die Eingaben und Ausgaben werden momentan nicht gespeichert.
                   </li>
                <li class="list-group-item">Um eine Anfrage an die KI zu schicken ist ein Prompt (Pre- oder Post-) Voraussetzung. 
                  Wird keine URL angegeben, dann werden eben nur die Prompts verarbeitet.
                </li>
                <li class="list-group-item">An die KI wird das Markdown übergeben und nicht der Seitenquelltext.</li>
                <li class="list-group-item">Wird kein Systemprompt angegeben, dann wird standardmäßig "You are a helpful assistant." verwendet.</li>
                <li class="list-group-item">Zwischen dem Markdown und Pre- / Post-Prompt werden Leerzeilen eingefügt, damit die KI die 
                Chance hat, die Anforderungen und den zu analysierenden Text voneinander zu trennen.</li>
                <li class="list-group-item">Wird "Keine Antwort von der AI - :c" angezeigt, dann gibt es ein Problem bei der Verbindung
                  zu der AI. Wartungsarbeiten könnten ein Grund für solche Störungen sein. Die Webseiten werden dennoch ausgelesen, die AI kann nur nicht antworten.
                </li>
              </ul>
              <h4>Glossar</h4>
              <ul class="list-group">
                <li class="list-group-item">Pre-Prompt / Post-Prompt<br>
                Eingaben aus dem Feld Pre-Prompt werden vor dem Markdown der Webseite an die KI übergeben.
                Eingaben aus dem Feld Post-Prompt werden nach dem Markdown der Webseite an die KI übergeben.
              </li>
              <li class="list-group-item">Systemprompt<br>
                wird gesondert als Systemnachricht an die KI übergeben und soll das grundsätzliche Verhalten 
                der KI beeinflussen. Direkte Fragen sind hier nicht vorgesehen.
              </li>
              <li class="list-group-item">Markdown<br>
                ist eine vereinfachte Auszeichnungssprache in der die meisten semantischen Konstrukte
                von HTML abgebildet werden können, die dafür aber wesentlich weniger Zeichen benötigt.
              </li>
              </ul>
              <h4>Versions Verbesserungen / Roadmap</h4>
              <ul class="list-group">
              <li class="list-group-item">Pagination<br>
                Manche Seiten haben mehrere Unterseiten. Eigentlich sollten ja alle Unterseiten ausgelesen werden. Aber zum einen 
                sind die Page-Navigationen sehr unterschiedlich und lassen sich daher allgemeingültig schwer bedienen und zum
                zweiten gibt es Seiten mit so vielen Unterseiten, dass das den Rahmen sprengen könnte.
              </li>
              <li class="list-group-item">Screenshots<br>
                Damit man sehen kann, wie die Seite für das Tool gezeigt wird, gibt es jetzt zwei screenshots. Der erste Screenshot wird
                direkt nach Aufruf der Seite geschossen und der zweite nach dem scrollen und warten auf Netzwerk Inaktivität. Beide shots werden
                unter dem Tab Quelltext untereinander angezeigt.
              </li>
              <li class="list-group-item">Infinite Scroll<br>
                Manche Seiten laden Inhalte nach, wenn man weiter nach unten scrollt. Durch eine autoScroll-Funktion sollten solche Seiten
                jetzt auch komplett gelesen werden. Das autoscrollen ist momentan auf 10 scrolls beschränkt. Informationen der Tester, wären sehr willkommen.
              </li>
              <li class="list-group-item">Nachladen von Inhalten<br>
                Bei bestimmten Seiten wurde im Quelltext kaum Inhalte gezeigt (z.B. oersi.org). Das Warten bis das Netzwerk inaktiv ist,
                führt hier zum kompletten Laden der Seite.
              </li>

              </ul>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
  </div>

  <script src="dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
  <script>
    document.querySelectorAll('#preprompt, #postprompt').forEach(function(el) {
      el.addEventListener('keyup', function() {
        document.querySelector('#Zeichenzahl').innerText = crawldemo.getStringLength();
      });
    });
    document.querySelector('#model').addEventListener('change', function(el) {
        localStorage.model = el.target.value;
    });
    document.querySelectorAll('#systemprompt').forEach(function(el) {
      el.addEventListener('keyup', function() {
        crawldemo.saveInput();
      });
    });


    function cleanErgebnis() {
      document.querySelector('#KIAusgabe').innerHTML = document.querySelector('#KIAusgabe').innerHTML.replace(/(<br>){2,500}/, '');
    }
    class crawldemoclass {
      saveFields = ['preprompt', 'postprompt', 'systemprompt'];
      saveIDs = [];
      constructor(){ 
        let me = this;
        this.saveFields.forEach(function(field){
          me.saveIDs.push('#'+field);
        });
      }
      weStart(){  
        this.saveFields.forEach(function(el) {
          if(document.querySelector('#'+el).value.length < 1 || (document.querySelector('#'+el).value = 'You are a helpful assistant.' && localStorage.getItem(el) && localStorage.getItem(el).length > 1)){
            document.querySelector('#'+el).value = localStorage.getItem(el); 
          }
        });
        if(typeof localStorage.model != "undefined" && localStorage.model.length > 0 && localStorage.model != "undefined"){
          document.querySelector("select#model").value = localStorage.model;
        }
        document.querySelector('#Zeichenzahl').innerText = crawldemo.getStringLength();
      }
      getStringLength() {        
        let me = this;
        let len = 0;
        document.querySelectorAll('#preprompt, #postprompt').forEach(function(el) {
          len += el.value.length;
          let mem = el.id;
          me.saveInput();
        });
        return len;
      }
      saveInput(){ 
        document.querySelectorAll(this.saveIDs).forEach(function(el) {
          localStorage.setItem(el.id,el.value);
        });
      }
    } 
    var crawldemo = new crawldemoclass();
    document.addEventListener("DOMContentLoaded", function() {
      cleanErgebnis();
      crawldemo.weStart();
    });
  </script>
</body>

</html>