<?php

require_once  __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';
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

  <title>Test - chat HIMS </title>

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
    <?php
  
      $command = "sudo -u server /usr/bin/node " . __DIR__ . "/chatgpt.js $url";
      exec($command, $output, $return_var);
      $output = array_filter($output);
      $echo =  (join("\r\n", $output));

      echo $echo;
  ?>
</body>

</html>