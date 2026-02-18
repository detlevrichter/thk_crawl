<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
  <style>
    body { font-family: sans-serif; margin: 2em; }
    progress {
      width: 100%;
      height: 30px;
      border-radius: 0.5rem;
      overflow: hidden;
      background-color: #e9ecef;
      -webkit-appearance: none;
    }
    progress {
    color: #e9ecef;
    }

    progress::-moz-progress-bar {
    background: #e9ecef;
    }

    progress::-webkit-progress-value {
    background: var(--primary);;
    }

    progress::-webkit-progress-bar {
    background:  #e9ecef;
    }
  </style>
    <title>Crawl</title>
  </head>

<body class="bg-light">
  <div class="container">
    <h1>Crawl</h1>
    <div class="d-flex gap-2">
      <button id="startBtn" class="btn btn-primary">Crawl starten</button> 
      <button id="killBtn" class="btn btn-danger">Crawl Abbrechen</button> 
    </div> 
    <p id="status">Status: wartet...</p>
    <p id="job">...</p>
    <progress  id="progressBar" value="0" max="100"></progress>
    
    <p id="time"></p>
    <script>
        (function() {
            const bar = document.getElementById('progressBar');
            const status = document.getElementById('status');
            const startBtn = document.getElementById('startBtn');
            const job = document.getElementById('job');       
            const timeDisplay = document.getElementById('time');
            const killBtn = document.getElementById('killBtn');
            let pollingInterval = null;

            document.getElementById('startBtn').addEventListener('click', () => {
                fetch('start_crawl.php')
                    .then(r => r.json())
                    .then(data => {
                        console.log(data.message);
                        pollProgress();
                    });
            });
            document.getElementById('killBtn').addEventListener('click', () => {
                fetch('progress.php?kill=true')
                    .then(r => r.json())
                    .then(data => {
                        console.log(data.message);  
                        status.textContent = data.message;
                        job.textContent = data.message;
                        clearInterval(pollingInterval);
                        setTimeout( ()=>{ location.reload()},5000);
                    });
            });
            window.addEventListener('load', () => {
                checkExistingProgress();
            });

            function checkExistingProgress() {
                fetch('progress.php')
                    .then(r => r.json())
                    .then(data => {
                        const p = data.progress || 0;
                        bar.value = p;
                        killBtn.value = data.pid || 0;
                        if (data.status === 'running' || data.status === 'starting') {
                            // Es läuft schon was – sofort mit Polling starten
                            status.textContent = `Fortschritt: ${p}% (${data.status})`;
                           // startBtn.disabled = true;
                            pollProgress();
                        } else if (data.status === 'done') {
                            status.textContent = '✅ Crawl abgeschlossen!';
                            startBtn.disabled = false;
                        } else {
                            status.textContent = 'Bereit zum Starten.';
                            startBtn.disabled = false;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        status.textContent = 'Fehler beim Laden des Status.';
                        startBtn.disabled = false;
                    });
            }

            function pollProgress() {
                if (pollingInterval) clearInterval(pollingInterval);


                pollingInterval = setInterval(() => {
                    fetch('progress.php')
                        .then(r => r.json())
                        .then(data => {
                            bar.value = data.progress;
                            status.textContent = `Fortschritt: ${data.progress}% (${data.status})`;
                            job.textContent = `${data.job}`;
                            if (data.status === 'done') {
                                clearInterval(pollingInterval);
                                status.textContent = '✅ Crawl abgeschlossen!';
                                job.textContent = '';
                            }
                        });
                }, 1000);
            }
        })(); // Ende der IIFE
    </script>
  </div>
</body>

</html>