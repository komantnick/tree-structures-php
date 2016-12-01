<?php  
$start = microtime(true);
set_time_limit(3600);
ini_set("memory_limit", "256M");
//функция db- отвечает за соединение с БД
class AdjacencyList{
protected $dbh;
protected $tbl;
private function db(){
$dsn = "mysql:host=localhost;dbname=kurs7;charset=utf8";
$opt = array(
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
);
$user='root';
$pass='';
//$pdo = new PDO($dsn, $user, $pass, $opt);
try{
    $dbh = new PDO($dsn, $user, $pass, $opt);
    json_encode(array('outcome' => true));
}
catch(PDOException $ex){
    die(json_encode(array('outcome' => false, 'message' => 'Unable to connect')));
}
return $dbh;
}
//констуктор использующий паттерн проектирования "Singleton"
public function __construct($table) {
		$this->table = $table;
	    $this->dbh=$this->db();
	}
//получение узла дерева к которму нужно добавить определенный узел
public function get($id) {
		$sql = '
			SELECT * 
			FROM '.$this->table.' 
			WHERE user_id = :id';
		$sth = $this->dbh->prepare($sql);
		$sth->bindParam(':id', $id);
		$sth->execute();
		return $sth->fetch(PDO::FETCH_ASSOC);
	}
	//получение родительского узла
	public function parent_node($id) {
		$node = $this->get($id);
		$parent = $node['parent_id'];
		$sql = '
			SELECT user_id, user_name 
			FROM '.$this->table.' 
			WHERE 
				user_id <= '.$parent.' AND 
				right_number >= '.$right.' AND
				level = '.$level.'
			ORDER BY user_id';
		
		$sth = $this->dbh->query($sql);
		return $sth->fetch(PDO::FETCH_ASSOC);
	}
	public function create($name) {
		$this->dbh->exec('DELETE FROM '.$this->table.'');
		$this->dbh->exec('ALTER TABLE '.$this->table.' AUTO_INCREMENT=1');
		$sql = '
			INSERT 
			INTO '.$this->table.' 
			VALUES(NULL,NULL, :name,1)';
		$sth= $this->dbh->prepare($sql);
		$sth->bindParam(':name', $name);
		$sth->execute();
		echo "Дерево добавлено!";
	}
	//добавление одного узла дерева
	public function add($id,$name){
		$node = $this->get($id);
		$pid = $node['user_id'];
		$insert = '
			INSERT 
			INTO '.$this->table.' 
			SET parent_id='.$pid.', user_name = :name
			';	
		$sth = $this->dbh->prepare($insert);
		$sth->bindParam(':name', $name);
		$sth->execute();
		echo "Узел добавлен!";
		return $this->dbh->lastInsertId();
	}
	//удаление одного узла дерева
	public function delete($id){
	$node = $this->get($id);
    $id=$node['user_id'];
    $delete = '
			DELETE 
			FROM '.$this->table.' 
			WHERE user_id='.$id.'';
		$this->dbh->exec($delete);
	    echo "Узел удален!";
	}
	public function block($id){
	$node = $this->get($id);
    $id=$node['user_id'];
    $block = '
			UPDATE '.$this->table.' 
			SET user_status=0
			WHERE user_id='.$id.'';
		$this->dbh->exec($block);
	    echo "Узел заблокирован!";
	}
	//функция выполнения запросов в БД и получения массивов
	public function querybuilder($sql,$num,$id=TRUE,$parent_node=TRUE){
		switch ($num) {
    case 0:
        $sth = $this->dbh->prepare($sql);
		if(!$parent_node)
			$sth->bindParam(':id', $id);
		$sth->execute();
		$arr = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$arr[$row['user_id']] = $row;
		}
		return $arr;
		case 1:
		$sth = $this->dbh->query($sql);
		$arr = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$arr[$row['user_id']] = $row;
		}
		return $arr;
     }
      
	}
	//получение дерева с помощью рекурсии
	public function gettree($sql,$id,$num,$parent_node=TRUE){
		switch ($num) {
			//вывод детей("вглубь")
    case 0:
    $arr=$this->querybuilder($sql,0,$id,$parent_node);
        $html = '';
        foreach ($arr as $row)
    {
        if ($row['parent_id'] == $id)
        {
            $html .= '<li>' . "\n";
            $html .= '    ' . $row['user_id'].". ".$row['user_name'] . "\n";
            $html .= '    ' .$this->rekurstree($row['user_id']);
            $html .= '</li>' . "\n";
        }
    }
    return $html ? '<ul>' . $html . '</ul>' . "\n" : '';
        
	case 1:		
	$arr=$this->querybuilder($sql,1,$id,FALSE);
	$html = '';
		$h=[];
		foreach ($arr as $row)
        {
        if ($row['user_id'] == $id){
        	$html .= '    ' .$this->parent_branch($row['parent_id']);
         	$html .= '<li>' . "\n";
            $html .= '    ' . $row['user_id'].". ".$row['user_name'] . "\n";
            $html .= '</li>' . "\n";
         	 }
        }
        return $html ? '<ul>' . $html . '</ul>' . "\n" : '';
     
     case 2:
     $arr=$this->querybuilder($sql,1,$id,FALSE);
		//вывод результатов с помощью рекурсии
		$html = '';
		$h=[];
		foreach ($arr as $row)
        {
        if ($row['user_id'] == $id){
        	$html .= '    ' .$this->parent_branch($row['parent_id']);
         	$html .= '<li>' . "\n";
            $html .= '    ' . $row['user_id'].". ".$row['user_name'] . "\n";
            $html .= '</li>' . "\n";
         	 }
        }
         foreach ($arr as $row)
    {
        if ($row['parent_id'] == $id)
        {
        	$html .= '<ul>' . "\n";
            $html .= '<li>' . "\n";
            $html .= '    ' . $row['user_id'].". ".$row['user_name'] . "\n";
            $html .= '    ' .$this->rekurstree($row['user_id']);
            $html .= '</li>' . "\n";
            $html .= '</ul>' . "\n";
        }
    }
    return $html ? '<ul>' . $html . '</ul>' . "\n" : '';

    case 3:
    $arr=$this->querybuilder($sql,1,$id,FALSE);
		//вывод результатов с помощью рекурсии
		$html = '';
		$h=[];
		if ($parent_node==TRUE){
         	$html .= '<li>' . "\n";
            $html .= '    ' . $row['user_id'].". ".$row['user_name'] . "\n";
            $html .= '</li>' . "\n";
        }
         foreach ($arr as $row)
    {
        if ($row['parent_id'] == $id&&$parent_node==TRUE)
        {
        	$html .= '<ul>' . "\n";
            $html .= '<li>' . "\n";
            $html .= '    ' . $row['user_id'].". ".$row['user_name'] . "\n";
            $html .= '</li>' . "\n";
            $html .= '</ul>' . "\n";
        }
        else if ($row['parent_id'] == $id&&$parent_node==FALSE){
            $html .= '<li>' . "\n";
            $html .= '    ' . $row['user_id'].". ".$row['user_name'] . "\n";
            $html .= '</li>' . "\n";
        }
    }
    return $html ? '<ul>' . $html . '</ul>' . "\n" : '';

 
	}
}
	//функция отвечающая за рекурсию для получения полного дерева и подчиненной ветки)
	public function rekurstree($id,$parent_node=TRUE){
		if ($id) {
			$node = $this->get($id);
		    $pid = $node['user_id'];
	    }
           if($parent_node) {
			$sql = '
			SELECT *
			FROM '.$this->table.' 
			ORDER BY user_id';
		} else {
			$sql = '
			SELECT *
			FROM '.$this->table.' 
			WHERE user_id !=:id
			ORDER BY user_id';
		}
		//echo $sql;
		
		return $this->gettree($sql,$id,0,$parent_node);
         
}
//функция для получения полностью построенного дерева
public function tree($parent_node=TRUE){
	return $this->rekurstree(0,$parent_node);
}
// Получение подчиненной ветки начиная с данного узла
public function child_branch($id,$parent_node=TRUE){
	if (!$parent_node){
	return $this->rekurstree($id,$parent_node);
}
else {
	$node = $this->get($id);
	$html="";
	$html .= '<ul>' . "\n";
	$html .= '<li>' . "\n";
    $html .= '    ' . $node['user_id'].". ".$node['user_name'] . "\n";
    $html .= '</li>' . "\n";
    $html.=$this->rekurstree($id,$parent_node);
    $html .= '</ul>' . "\n";
    return $html;
}
}
//функция для получения родительской ветки
public function parent_branch($id) {
	
	$node = $this->get($id);
    $pid = $node['user_id'];
			$sql = '
			SELECT *
			FROM '.$this->table.' 
			ORDER BY user_id';
	return $this->gettree($sql,$id,1,FALSE);	
}
//получение ветки в которой данный узел находится, т.е поулчение всех родителей и потомков
public function branch($id){
	$node = $this->get($id);
    $pid = $node['user_id'];
    $parent = $node['parent_id'];
			$sql = '
			SELECT *
			FROM '.$this->table.' 
			ORDER BY user_id';
	return $this->gettree($sql,$id,2,FALSE);	
		
}
//получение всех подчиненных узлов
	public function child($id, $parent_node = TRUE) {
    $node = $this->get($id);
    $parent = $node['parent_id'];
    $pid=$node['user_id'];
			$sql = '
			SELECT *
			FROM '.$this->table.' 
			ORDER BY user_id';
	return $this->gettree($sql,$id,3,$parent_node);
		
	}
	//перемещение узла
	public function move($id, $id_to) {
		$node = $this->get($id);	
		$node_to = $this->get($id_to);	
		// перенос в текущем узле не реализован
		if($node['user_id'] == $node_to['user_id']) {
			echo '==\n';
			return FALSE;
		}
		// перенос в корень не реализован
		if(!$id_to) {
			echo '0\n';
			return FALSE;
		}
		//переноc
		$sql='UPDATE '.$this->table.' 
				SET parent_id='.$id_to.' 
				WHERE user_id='.$id.'';
		$sth = $this->dbh->query($sql);
		echo "Узел перемещен!";
	}
	public function generateName($length = 8){
  $chars = 'abdefhiknrstyzABDEFGHKNQRSTYZ23456789';
  $numChars = strlen($chars);
  $string = '';
  for ($i = 0; $i < $length; $i++) {
    $string .= substr($chars, rand(1, $numChars) - 1, 1);
  }
  return $string;
}
public function auto_increment(){
      $sql='SHOW TABLE STATUS FROM kurs7 LIKE "'.$this->table.'"';
      $sth = $this->dbh->query($sql);
		$r = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$r = $row['Auto_increment'];
		}
		return $r;
	}
	//тестовые функции для произвольного количества определенных узлов
	//вставка определенного количества узлов
	public function multinsert($id,$num){
      $node = $this->get($id);
    $pid = $node['user_id'];
    $count=$this->auto_increment();
    //вставка элементов
    $i=$count+1;
    $z=$this->generateName(8);
    $max=$num+$count; //максимальное число вставляемых элементов
    $sql="INSERT INTO adjacency_list
    SELECT user_id,parent_id,user_name,user_status FROM(
    SELECT $i user_id, $id parent_id,'$z' user_name,1 user_status FROM DUAL 
	UNION ALL ";
	++$i;
    while ($i<=$max){
	$z=$this->generateName(8); //генерация имен случайным образом
	$rand=rand(0,1);
	if ($rand==1){
	$x=rand($count+1,$i-1);
    }
    else {
    	$x=$id;
    }
	if ($i!=$max){
	$sql.="SELECT $i, $x,'$z','1' FROM DUAL 
	UNION ALL ";
}
else {
    $sql.="SELECT $i, $x,'$z','1' FROM DUAL";
	 }
	++$i;
}
$sql.=")t";
//echo $sql;exit;
$sth = $this->dbh->prepare($sql);
$sth->execute();
	}
public function level3($num){
    $count=$this->auto_increment();
    //вставка элементов
    $i=$count+1;
    $z=$this->generateName(8);
    $query1="SELECT c2.user_id,c1.parent_id FROM adjacency_list
    c1 LEFT OUTER JOIN adjacency_list c2 ON c2.parent_id=c1.user_id WHERE (c1.parent_id is NULL)AND c2.user_id>0 ORDER BY RAND() LIMIT 1;";
$sth = $this->dbh->prepare($query1);
$sth->execute();
while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$x = $row['user_id'];
		}
		
    $max=$num+$count; //максимальное число вставляемых элементов
    $sql="INSERT INTO adjacency_list
    SELECT user_id,parent_id,user_name,user_status FROM(
    SELECT $i user_id, $x parent_id,'$z' user_name,1 user_status FROM DUAL 
	UNION ALL ";
	++$i;
while ($i<=$max){
	$z=$this->generateName(8); //генерация имен случайным образом
	if ($i!=$max){
	$sql.="SELECT $i, $x,'$z','1' FROM DUAL 
	UNION ALL ";
}
else {
    $sql.="SELECT $i, $x,'$z','1' FROM DUAL";
	 }
	++$i;
}
$sql.=")t";
//echo $sql;exit;
$sth = $this->dbh->prepare($sql);
$sth->execute();

	}
	//придание свойств всем детям для данного элемента
	public function test5($id){
		$node = $this->get($id);
        $status = $node['user_status'];
        $status_list=array();
        array_push($status_list,$id);
        $query='SELECT user_id,parent_id,user_status FROM '.$this->table.' ORDER BY user_id';
        $sth = $this->dbh->prepare($query);
        $sth->execute();
        $arr = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$arr[$row['user_id']] = $row;
		}
        foreach ($arr as $row)
    {
        if ($row['parent_id'] == $id)
        {
           array_push($status_list,$row['user_id']);
           $this->test5($row['user_id']);
        }
    }
    $sql='UPDATE '.$this->table.' SET user_status=CASE user_id ';
    foreach ($status_list as $value){
    	$sql.=' WHEN '.$value.' THEN '.$status.' ';
    }
     
    $sql.='ELSE user_status END;';
    return $sth=$this->dbh->exec($sql);
	}
}

$st=new AdjacencyList('adjacency_list');
print_r($st->test5(1));
echo "<br>Данные добавлены!";
//$st->level3(12);
 //print($st-> tree());
$time = microtime(true) - $start;
echo "<br>Время работы скрипта:".$time;
