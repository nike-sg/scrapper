<?php 
# TODO Ex.: 2115 CREDIBILIDADE

# load class
require_once "simple_html_dom.php";

# set new line
if(is_null($_SERVER['HTTP_HOST'])){
    define("NW", PHP_EOL);
}else{
    define("NW", "<br/>");
}

class Scrapper{

    private $id;
    // private $link;
    public $html;
    public $contResponse=1;

    // private function connectionDB(){
    //     $this->link = mysqli_connect('127.0.0.1', 'root', 'root1234', 'ongs') or die('Erro ao conectar ao banco');
    // }

    public function setId($id){
        $this->id = $id;
    }

    public function getId(){
        return $this->id;
    }

    public function addContResponse(){
        $this->contResponse = $this->getContResponse()+1;
    }

    public function resetContResponse(){
        $this->contResponse = 1;
    }

    public function getContResponse(){
        return $this->contResponse;
    }

    public function getHtml(){
        $url = 'http://www.ongsbrasil.com.br/default.asp?Pag=2&Destino=InstituicoesTemplate&CodigoInstituicao='.$this->getId();
        $html = file_get_html($url);
        if($html==""){
            if($this->getContResponse()<10){
                echo "Waiting for a response".NW;
                sleep(10);
                echo "Trying again for a response [".$this->getContResponse()."]".NW;
                $this->addContResponse();
                $this->getHtml($this->getId());
            }else{
                echo "No Response, try again later".NW;
                echo "Exiting".NW;
                exit;
            }
        }
        return $html;
    }

    public function getAll(){
        echo "Getting:".$this->getId().NW;
        $html = $this->getHtml($this->getId());
        $this->getTableInfos($html);
        $this->getActivities($html);
        $this->getCategories($html);
        $this->getFinalidades($html);
        sleep(1);
        echo "Finish:".$this->getId().NW.NW;
    }


    public function getTableInfos($html){
        global $link;
        $tableColumns = $this->getColumns();
        $this->delOng();
        
        // Dados da tabela da ONG
        
        # Nome da ONG
        $name = trim($html->find('h1.h1.text-capitalize',0)->plaintext);
        echo $name.NW;
        # Tabela ONG
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

        $colunas = '';
        $valores = '';
        # Trata os valores pegos e monta o insert
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
    
                echo "\nFound a New Column: ".$campo."\n";
                exit; 
            }
    
        } // end foreach
        $colunas = substr($colunas, 0,-1);
        $valores = substr($valores, 0,-1);

        # verifica a existencia
        $sqlOng = 'SELECT id FROM ongs WHERE id='.$this->getId();
        $resOng = mysqli_query($link, $sqlOng);
        if(mysqli_num_rows($resOng)==0){
            $ins = 'INSERT INTO ongs (id,nome,dataHora, '.$colunas.') VALUES ('.$this->getId().',"'.$name.'",NOW(), '.$valores.');';
            mysqli_query($link, $ins);
        }else{
            $rowOng = mysqli_fetch_array($resOng);
        }

        
    } // end function getInfos



    public function getActivities($html){
        global $link;

        foreach($html->find('.col-md-12.col-sm-12.col-xs-12') as $block) {
            // busca pelo título dos blocos
            $item['title'] = $block->find('h2.h2', 0)->plaintext;

            # Achando um bloco com o nome atividades
            if(!is_null($item['title']) && $item['title']=="Atividades da Organização"){
                // Acha as Atividades
                foreach($block->find('p') as $row) {
                    // limpa a string
                    $atv = str_replace("&nbsp;","",trim($row->plaintext));

                    // Verifica se a atividade já existe
                    $sqlAtv = 'SELECT * FROM atividades WHERE nome="'.$atv.'"';
                    $resAtv = mysqli_query($link, $sqlAtv);
                    if(mysqli_num_rows($resAtv)>0){
                        // Atividade já existente
                        $rowAtv = mysqli_fetch_array($resAtv);
                        $idAtv = $rowAtv['id'];
                    }else{
                        // Nova Atividade
                        $insAtv = 'INSERT INTO atividades (nome) VALUES ("'.$atv.'")';
                        $resAtv = mysqli_query($link, $insAtv);
                        if($resAtv){
                            echo "Nova Atividade Encontrada:".$atv.NW;
                            $idAtv = mysqli_insert_id($link);
                        }else{
                            echo "Erro ao inserir nova atividade: ".$insAtv;
                            exit;
                        }
                    }
                    // Insere o relacionamento 
                    $insAtvOng = 'INSERT INTO atividades_x_ongs (idOng, idAtividade) VALUES ('.$this->getId().','.$idAtv.')';
                    mysqli_query($link, $insAtvOng);
                }
                
            }
        }
    } // end getActivities

    public function getCategories($html){
        global $link;

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
                        $rowCat = mysqli_fetch_array($resCat);
                        $idCat = $rowCat['id'];
                    }else{
                        // Nova categoria
                        $insCat = 'INSERT INTO categorias (nome) VALUES ("'.$cat.'")';
                        $resCat = mysqli_query($link, $insCat);
                        if($resCat){
                            echo "Nova Categoria Encontrada:". $cat.NW;
                            $idCat = mysqli_insert_id($link);
                        }else{
                            echo "Error: ".$insCat;
                            exit;
                        }
                    }
                    // Insere o relacionamento 
                    $insCatOng = 'INSERT INTO categorias_x_ongs (idOng, idCategoria) VALUES ('.$this->getId().','.$idCat.')';
                    $resCatOng = mysqli_query($link, $insCatOng);
                } // end foreach
            } // end if
        } // end foreach
    } // end getCategories

    public function getFinalidades($html){
        global $link;

        foreach($html->find('.col-md-12.col-sm-12.col-xs-12') as $block) {
            // busca pelo título dos blocos
            $item['title'] = $block->find('h2.h2', 0)->plaintext;
            
            // achou sobre as finalidades
            if(!is_null($item['title']) && $item['title']=="Finalidades da Organização"){
                // Acha as finalidades
                foreach($block->find('p') as $row) {
                    // limpa a string
                    $fin = str_replace("&nbsp;","",str_replace("ONG de ","",trim($row->plaintext)));
                    // Verifica se a Finalidade já existe
                    $sqlFin = 'SELECT * FROM finalidades WHERE nome="'.$fin.'"';
                    $resFin = mysqli_query($link, $sqlFin);
                    if(mysqli_num_rows($resFin)>0){
                        // Finalidade já existente
                        $rowFin = mysqli_fetch_array($resFin);
                        $idFin = $rowFin['id'];
                    }else{
                        // Nova Finalidade
                        $insFin = 'INSERT INTO finalidades (nome) VALUES ("'.$fin.'")';
                        $resFin = mysqli_query($link, $insFin);
                        if($resFin){
                            echo "Nova Finalidade Encontrada:". $fin.NW;
                            $idFin = mysqli_insert_id($link);
                        }else{
                            echo "Error: ".$insFin;
                            exit;
                        }
                    }
                    // Insere o relacionamento 
                    $insFinOng = 'INSERT INTO finalidades_x_ongs (idOng, idFinalidade) VALUES ('.$this->getId().','.$idFin.')';
                    $resFinOng = mysqli_query($link, $insFinOng);
                } // end foreach
            } // end if
        } // end foreach
    } // end getFinalidades

    public function getColumns() {
        global $link;
        $tableColumns = array();
        $sql = 'SELECT coluna,texto FROM ongs_colunas;';
        $res = mysqli_query($link, $sql);
        while($row = mysqli_fetch_array($res)){
            $tableColumns[$row['texto']] = $row['coluna'];
        }
        return $tableColumns;
    }

    public function delOng(){
        global $link;
        $del = "DELETE FROM ongs WHERE id=".$this->getId();
        if(!mysqli_query($link, $del)){
            echo "FAILED DELETE".NW;
            exit;
        }
    }

    public function getCredibilidade($html){
        # TODO 2115 CREDIBILIDADE

    }
    
}