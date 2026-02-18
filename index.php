<?php

use Rajentrivedi\TokenizerX\TokenizerX;

require_once 'vendor/autoload.php';
session_start();
$url = '';
$url = preg_replace('~[^a-zA-Z\./:-]*~', '', $_GET['web']);
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">
  <link rel="icon" href="/docs/4.0/assets/img/favicons/favicon.ico">

  <title>Test - get Website Content </title>

  <!-- Bootstrap core CSS -->
  <link href="./dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    #markup {
      height: 350px;
      width: 100%;
      overflow: scroll;
    }
  </style>
</head>

<body class="text-center">
  <div class="container">
    <form method="get" class="form-signin">
      <h1 class="h3 mb-3 font-weight-normal">URL</h1>
      <label for="inputWeb" class="sr-only">Website</label>
      <input type="text" id="inputWeb" name="web" class="form-control" placeholder="http://www.example.com" required autofocus value="<?php echo htmlentities($url) ?>">
      <div class="row">
        <div class="col">
          <div class="form-check" style="text-align:left">
            <input class="form-check-input" type="radio" name="methode" value="browser" id="flexRadioDefault1" checked>
            <label class="form-check-label" for="flexRadioDefault1">
              headless browser
            </label>
          </div>
          <div class="form-check" style="text-align:left">
            <input class="form-check-input" type="radio" name="methode" value="file_get_contents" id="flexRadioDefault2">
            <label class="form-check-label" for="flexRadioDefault2">
              file_get_contents
            </label>
          </div>
        </div>
        <div class="col">
          <div class="form-check" style="text-align:left">
            <input class="form-check-input" type="radio" name="clean" value="clean" id="flexRadioDefault3" checked>
            <label class="form-check-label" for="flexRadioDefault3">
              script, style und head entfernen
            </label>
          </div>
          <div class="form-check" style="text-align:left">
            <input class="form-check-input" type="radio" name="clean" value="dirt" id="flexRadioDefault4">
            <label class="form-check-label" for="flexRadioDefault4">
              script, style und head da lassen
            </label>
          </div>
        </div>
      </div>


      <button class="btn btn-lg btn-primary btn-block" type="submit">Inhalt auslesen</button>

    </form>


    <?php
    $copyme = '<button type="button" onclick="copyme(this)" class="btn btn-primary">copy</button>';
    //$copyme='';

    if ($_GET['methode'] == 'browser') {
      echo '<h2>Browser ' . $copyme . '</h2><textarea style="text-align:left" id="markup">';
      $command = "sudo -u server /usr/bin/node " . __DIR__ . "/pup.js $url";
      exec($command, $output, $return_var);
      $output = array_filter($output);
      $echo =  (join("\r\n", $output));
    } else {
      echo '<h2>file_get_contents ' . $copyme . '</h2><textarea style="text-align:left" id="markup">';
      $echo =  (file_get_contents($url));
    }
    if ($_GET['clean'] == 'clean') {
      $echo = preg_replace(['/<script.*<\/script>/sU', '/<style.*<\/style>/sU', '/<head.*<\/head>/sU', '/\t*/','/<[^\/][\S]+\s*>\s*<\/[\S]+>/'], '', $echo);
      $echo = preg_replace([
        "/<([a-z][a-z0-9]*)([^>]*)class=\"[^\"]*\"([^>]*)>/iU",
        "/<([a-z][a-z0-9]*)([^>]*)id=\"[^\"]*\"([^>]*)>/iU",
        "/<([a-z][a-z0-9]*)([^>]*)style=\"[^\"]*\"([^>]*)>/iU",
        "/<([a-z][a-z0-9]*)([^>]*)onmouseover=\"[^\"]*\"([^>]*)>/iU",
        "/<([a-z][a-z0-9]*)([^>]*)onmouseout=\"[^\"]*\"([^>]*)>/iU",
        "/<([a-z][a-z0-9]*)([^>]*)onkeyup=\"[^\"]*\"([^>]*)>/iU",
      ],
      [
        '<$1$2$3>',
      ], $echo);
      $echo = preg_replace(['/\r\n\r\n/','/<div >/','/&nbsp;/'], ["\n",'<div>',' '], $echo);
    }
    echo htmlentities($echo);
    $_SESSION['tobeanalysed'] = $echo;
    ?></textarea>
    <h1>Tokens: <?php
      echo number_format(TokenizerX::count($echo), 0, ',', '.'); ?></h1>

  </div>
  <script>
    const copyme = async function(button) {
      button.classList.add("btn-success");
      let element = document.getElementById("markup");

      let range = new Range();
      range.selectNodeContents(element);
      document.getSelection().removeAllRanges();
      document.getSelection().addRange(range);
      try {
        await navigator.clipboard.writeText(range.cloneContents());
      } catch (error) {
        document.execCommand("copy", false, range.cloneContents());
      }
      setTimeout(()=>{button.classList.remove("btn-success");}, 1000);
    }
  </script>
</body>

</html>