<?php
$pdo = new PDO('mysql:host=localhost;dbname=faktury24_ksef', 'root', '');
$stmt = $pdo->query("SHOW TABLES");
foreach ($stmt as $row) {
    echo $row[0] . PHP_EOL;
}
