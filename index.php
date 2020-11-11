<?php 
// ini_set('display_errors', '1');
// ini_set('display_startup_errors', '1');
// error_reporting(E_ALL);
error_reporting(0);
ini_set('display_errors', 0);

# TODO Ex.: 2115 CREDIBILIDADE
# Conexão
$link = mysqli_connect('127.0.0.1', 'root', 'root1234', 'ongs') or die('Erro ao conectar ao banco');

# Load Class
require_once "class_scrapper.php";


$start = microtime(true);

$sql = 'SELECT id FROM `ongs` ORDER BY id DESC LIMIT 1;';
$res = mysqli_query($link, $sql);
$row = mysqli_fetch_array($res);

for ($i=$row['id']; $i < 20000; $i++) { 
    
    $scrapper = new Scrapper;

    $scrapper->setId($i);
    $scrapper->getAll();
    
}
$end = microtime(true);

$time = number_format(($end - $start), 2);

echo NW.'Processo finalizado em ', $time, ' segundos'.NW;