<?php

// Vercel draait alleen bestanden in de map api/ als serverless-functie.
// Dit bestand stuurt elk verzoek door naar Laravel.
//
// Laravel leidt het basispad af uit SCRIPT_NAME. Omdat dit script in /api staat,
// zou het anders denken dat de hele site onder /api draait, en van /api/report
// alleen /report overhouden. Doe daarom alsof public/index.php in de root staat.
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require __DIR__.'/../public/index.php';
