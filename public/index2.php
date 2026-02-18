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
  'meta-llama-3-8b-instruct',
  'mixtral-8x7b-instruct',
  'meta-llama-3-70b-instruct',
  'qwen2-72b-instruct',
];
$echo = '';
$source = '';
$url = '';
$markdown = '';
$url = preg_replace('~[^a-zA-Z\./:-]*~', '', $_POST['web']);
$preprompt = $_POST['preprompt'];
$prompt = $_POST['prompt'];
$model = $_POST['model'];
$source = $_POST['source'] ?? $source;
$systemprompt = $_POST['systemprompt'] ?? 'You are a helpful assistent.';

if ($url ?? false) {

  $head = '<h5 class="card-title">Browser</h5> ';
  $command = "sudo -u server /usr/bin/node " . dirname(__DIR__) . "/pup.js $url";
  exec($command, $output, $return_var);
  // entfernen leerer Elemente
  $output = array_filter($output);
  $source =  join("\r\n", $output);
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
            </div>
          </div>
          <div class="row pt-2">
            <div class="col">
              <div class="form-floating">
                <textarea name="preprompt" class="form-control prompt" placeholder="Prompt" id="floatingTextarea1"><?php echo $preprompt; ?></textarea>
                <label for="floatingTextarea1">Pre-Prompt</label>
              </div>
            </div>
            <div class="col">
              <div class="form-floating">
                <textarea name="prompt" class="form-control prompt" placeholder="Prompt" id="floatingTextarea3"><?php echo $prompt; ?></textarea>
                <label for="floatingTextarea3">Post-Prompt</label>
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
              <div class="card card-body">
                <?php
                // clean duplicate content
                if (true && $markdown && $model ?? false) {

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
            <h5>geschätzte Token (Quelltext und Prompts): <?php
                echo number_format(TokenizerX::count($preprompt . "\r\n" . $source . "\r\n" . $prompt), 0, ',', '.'); ?>
      Zeichen (Quelltext und Prompts): <span><?php
                                      echo number_format(mb_strlen($preprompt . "\r\n" . $source . "\r\n" . $prompt), 0, ',', '.'); ?></span></h5>
    

              <h4>Quelltext</h4>
              <code>
                <?php echo htmlentities($source); ?>
              </code>
              
            </div>
            <div class="col card source">
              <h4>Markdown</h4>
              <h5>geschätzte Token (Markdown und Prompts): <?php
                echo number_format(TokenizerX::count($preprompt . "\r\n" . $markdown . "\r\n" . $prompt), 0, ',', '.'); ?>
      Zeichen (Markdown und Prompts): <span><?php
                                      echo number_format(mb_strlen($preprompt . "\r\n" . $markdown . "\r\n" . $prompt), 0, ',', '.'); ?></span></h5>
    
              <code>
                <?php echo $markdown; ?>
              </code>

            </div>
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
      </div>
    </form>
  </div>
  </div>

  <script src="dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
  <script>
    document.querySelectorAll('#floatingTextarea1, #floatingTextarea3').forEach(function(el) {
      el.addEventListener('keyup', function() {
        document.querySelector('#Zeichenzahl').innerText = getStringLength();
      });
    });

    function getStringLength() {
      let len = 0;
      document.querySelectorAll('#floatingTextarea1, #floatingTextarea3').forEach(function(el) {
        len += el.value.length;
      });
      return len;
    }

    function cleanErgebnis() {
      document.querySelector('#collapseErgebnis .card').innerHTML = document.querySelector('#collapseErgebnis .card').innerHTML.replace(/(<br>){2,500}/, '');
    }
    document.addEventListener("DOMContentLoaded", function() {
      cleanErgebnis();
    });
  </script>
</body>

</html>