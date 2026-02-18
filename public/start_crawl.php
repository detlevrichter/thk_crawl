<?php

$progressFile = __DIR__.'/tmp/crawl_progress.json';
$pidFile = __DIR__.'/tmp/crawl_pid.txt';
// Lege Datei mit Startwert an
file_put_contents($progressFile, json_encode(['progress' => 0, 'status' => 'starting']));

// Starte Prozess im Hintergrund
$cmd = sprintf('php run_crawl.php %s > /dev/null 2>&1 & echo $!', escapeshellarg($progressFile));
$pid = shell_exec($cmd);
file_put_contents($pidFile, trim($pid));

echo json_encode(['message' => 'Crawl gestartet', 'pid' => trim($pid)]);