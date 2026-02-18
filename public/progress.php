<?php
$progressFile = __DIR__.'/tmp/crawl_progress.json';
$pidFile = __DIR__.'/tmp/crawl_pid.txt';
if(isset($_GET['kill'])){ 

    if (!file_exists($pidFile)) {
        echo json_encode(['error' => 'Kein laufender Prozess gefunden']);
        exit;
    }

    $pid = (int)file_get_contents($pidFile);

    // Prüfen, ob Prozess existiert
    if (posix_kill($pid, 0)) {
        // Prozess wirklich beenden
        //posix_kill($pid, SIGTERM);

        exec("kill $pid 2>/dev/null", $output, $result);
        // Optional: Fortschrittsdatei aktualisieren
        if (file_exists($progressFile)) {
            $data = json_decode(file_get_contents($progressFile), true);
            $data['status'] = 'abgebrochen';
            file_put_contents($progressFile, json_encode($data));
        }

        unlink($pidFile);
        echo json_encode(['message' => "Prozess $pid beendet"]);
    } else {
        echo json_encode(['message' => "Kein Prozess mit PID $pid aktiv"]);
        unlink($pidFile);
    }
    die;
}
if (file_exists($progressFile)) {
    echo file_get_contents($progressFile);
} else {
    echo json_encode(['progress' => 0, 'status' => 'unknown']);
}