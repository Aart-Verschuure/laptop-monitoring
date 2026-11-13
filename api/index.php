<?php

// Vercel draait alleen bestanden in de map api/ als serverless-functie.
// Dit bestand stuurt elk verzoek door naar Laravel.
require __DIR__.'/../public/index.php';
