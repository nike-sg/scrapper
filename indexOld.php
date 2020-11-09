<?php 
error_reporting(0);
ini_set('display_errors', 0);

# TODO Ex.: 2115 CREDIBILIDADE


$link = mysqli_connect('127.0.0.1', 'root', 'root1234', 'ongs') or die('Erro ao conectar ao banco');
require_once "simple_html_dom.php";

// new line 
if(is_null($_SERVER['HTTP_HOST'])){
    define("NW", PHP_EOL);
}else{
    define("NW", "<br/>");
}


function getInfos($id){
    global $link;
    $html = file_get_html('http://www.ongsbrasil.com.br/default.asp?Pag=2&Destino=InstituicoesTemplate&CodigoInstituicao='.$id);
    if($html==""){
        
    }

    $del = "DELETE FROM ongs WHERE id=".$id;
    mysqli_query($link, $del);

    // #########  TABLE ###########
    $tableColumns = array();
    $sql = 'SELECT coluna,texto FROM ongs_colunas;';
    $res = mysqli_query($link, $sql);
    while($row = mysqli_fetch_array($res)){
        $tableColumns[$row['texto']] = $row['coluna'];
    }
    // var_dump($tableColumns);
    // Dados da tabela da ONG
    // $table = $html->find('table');

    $name = trim($html->find('h1.h1.text-capitalize',0)->plaintext);
    echo $name.NW;
    foreach($html->find('.table.table-striped tr') as $row) {
        $item['title'] = $row->find('td', 0)->plaintext;
        
        if($row->find('td', 1)->plaintext==""){
            switch (trim($item['title'])) {
                case 'Lgootipo:':
                    $item['value'] = $row->find('img',0)->src;
                    break;

                default:
                    $item['value'] = $row->find('td input[type=text]',0)->value;
                    break;
            }
            
        }else{
            $item['value']    = $row->find('td', 1)->plaintext;
        }
        $linha[] = $item;
    }
    // var_dump($linha);
    // $linhaJson = json_encode($linha);
    $colunas = '';
    $valores = '';
    foreach ( $linha as $l ){
        $campo = trim(str_replace(":", "", $l['title']));
        // verifica se ja temos este campo na tabela
        if(array_key_exists($campo, $tableColumns)){
            $colunas .= $tableColumns[$campo].',';

            if($tableColumns[$campo]!='telefone' && $tableColumns[$campo]!='whatsapp'){
                $valores .= '"'.trim($l['value']).'",';
            }else{
                $valor = preg_replace("/[^0-9]/", "",$l['value']);
                $valores .= '"'.trim($valor).'",';
            }
        }else{
            // $campoSQL = $campo;
            // $alter = 'ALTER TABLE ongs ADD '.$campoSQL.' varchar(255);';
            // $q = mysql_query($alter);
            // if($q){
            //     $insColumnTable = 'INSERT INTO ongs_colunas (coluna, texto) VALUES ("'.$campoSQL.'", "'.$campo.'");';
            //     mysqli_query($link, $insColumnTable);
            //     $tableColumns[$campo] = $campoSQL;
            // }

            echo "\nfound a new column: ".$campo."\n";
            exit; 
        }
        // echo ":" .$l['value']."<br>";

    } // end foreach
    $colunas = substr($colunas, 0,-1);
    $valores = substr($valores, 0,-1);
    $sqlOng = 'SELECT id FROM ongs WHERE id='.$id;
    $resOng = mysqli_query($link, $sqlOng);
    if(mysqli_num_rows($resOng)==0){
        $ins = 'INSERT INTO ongs (id,nome,dataHora, '.$colunas.') VALUES ('.$id.',"'.$name.'",NOW(), '.$valores.');';
        // echo $ins;
        mysqli_query($link, $ins);
        $idOng = mysqli_insert_id($link);
    }else{
        $rowOng = mysqli_fetch_array($resOng);
        $idOng = $rowOng['id'];
    }



    ######### END ONG ###########

    ######### CATEGORIAS e ATIVIDADES ##########

    // $titles = $html->find('h2.h2');
    // echo $titles;

    foreach($html->find('.col-md-12.col-sm-12.col-xs-12') as $block) {
        // busca pelo título dos blocos
        $item['title'] = $block->find('h2.h2', 0)->plaintext;
        
        // achou sobre as categorias
        if(!is_null($item['title']) && $item['title']=="Classificação da Organização"){
            // Acha as categorias
            foreach($block->find('p') as $row) {
                // limpa a string
                $cat = str_replace("&nbsp;","",str_replace("ONG de ","",trim($row->plaintext)));
                // Verifica se a categoria já existe
                $sqlCat = 'SELECT * FROM categorias WHERE nome="'.$cat.'"';
                $resCat = mysqli_query($link, $sqlCat);
                if(mysqli_num_rows($resCat)>0){
                    // Categoria já existente
                    // echo "existe";
                    $rowCat = mysqli_fetch_array($resCat);
                    $idCat = $rowCat['id'];
                }else{
                    // Nova categoria
                    // echo "ñ existe";
                    $insCat = 'INSERT INTO categorias (nome) VALUES ("'.$cat.'")';
                    $resCat = mysqli_query($link, $insCat);
                    if($resCat){
                        // echo "inseriu";
                        $idCat = mysqli_insert_id($link);
                    }else{
                        echo "Error: ".$insCat;
                    }
                }
                // Insere o relacionamento 
                // echo "insere o relacionamento";
                $insCatOng = 'INSERT INTO categorias_x_ongs (idOng, idCategoria) VALUES ('.$idOng.','.$idCat.')';
                $resCatOng = mysqli_query($link, $insCatOng);
            }
            
            // var_dump($categorias);
        }
        if(!is_null($item['title']) && $item['title']=="Atividades da Organização"){
            // echo $item['title'];
            // Acha as Atividades
            foreach($block->find('p') as $row) {
                // var_dump($row);
                // limpa a string
                $atv = str_replace("&nbsp;","",trim($row->plaintext));
                // echo $atv;
                // Verifica se a atividade já existe
                $sqlAtv = 'SELECT * FROM atividades WHERE nome="'.$atv.'"';
                $resAtv = mysqli_query($link, $sqlAtv);
                if(mysqli_num_rows($resAtv)>0){
                    // Atividade já existente
                    // echo "existe<br>";
                    $rowAtv = mysqli_fetch_array($resAtv);
                    $idAtv = $rowAtv['id'];
                }else{
                    // Nova Atividade
                    // echo "ñ existe<br>";
                    $insAtv = 'INSERT INTO atividades (nome) VALUES ("'.$atv.'")';
                    $resAtv = mysqli_query($link, $insAtv);
                    if($resAtv){
                        // echo "inseriu<br>";
                        $idAtv = mysqli_insert_id($link);
                    }else{
                        echo "Error: ".$insAtv;
                    }
                }
                // Insere o relacionamento 
                // echo "insere o relacionamento<br><br>";
                $insAtvOng = 'INSERT INTO atividades_x_ongs (idOng, idAtividade) VALUES ('.$idOng.','.$idAtv.')';
                $resAtvOng = mysqli_query($link, $insAtvOng);
            }
            
        }
    }



} // end function getInfos


$start = microtime(true);
for ($i=4587; $i < 4588; $i++) { 
    echo "Getting:".$i.NW;
    getInfos($i);
    sleep(1);
    echo "Finish:".$i.NW.NW;
}
$end = microtime(true);

$time = number_format(($end - $start), 2);

echo NW.'Processo finalizado em ', $time, ' segundos'.NW;