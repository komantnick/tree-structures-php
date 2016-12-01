<?php
$start = microtime(true);
set_time_limit(3600);
ini_set("memory_limit", "256M");  
$start = microtime(true);
//функция db- отвечает за соединение с БД
class MaterializedPath{
const PATH_SEPARATOR = '/';
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
	//получение пути родительского узла

	//создание дерева из одного элемента(а также обнуление старого дерева)
public function create($name = NULL) {
		$this->dbh->exec('DELETE FROM '.$this->table.'');
		$this->dbh->exec('ALTER TABLE '.$this->table.' AUTO_INCREMENT=1');
		//$mpath=base_convert("/1",10,95);
		$ai=str_pad($this->auto_increment(),7,"0",STR_PAD_LEFT);
		$mpath='/'.$ai;
		$sql = '
			INSERT 
			INTO '.$this->table.' 
			VALUES(NULL,:name,"'.$mpath.'",1)';
		$sth= $this->dbh->prepare($sql);
		$sth->bindParam(':name', $name);
		$sth->execute();
		echo "Дерево добавлено!";
	}
	//поулчение id вставляемого элемента
public function auto_increment(){
      $sql='SHOW TABLE STATUS FROM kurs7 LIKE "'.$this->table.'"';
      $sth = $this->dbh->query($sql);
		$r = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$r = $row['Auto_increment'];
		}
		return $r;
		
	}
public function add($id,$name){
		$node = $this->get($id);
		$par_path=$node['user_path'];
		$num=str_pad($this->auto_increment(),7,"0",STR_PAD_LEFT);
		$pid = $par_path.'/'.$num;
		$insert = '
			INSERT 
			INTO '.$this->table.' 
			SET  user_name = :name, user_path="'.$pid.'"
			';	
		$sth = $this->dbh->prepare($insert);
		$sth->bindParam(':name', $name);
		$sth->execute();
		echo "Узел добавлен!";
		return $this->dbh->lastInsertId();
	}
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
	//получение всех дочерних узлов
public function child_branch($id,$parent_node=TRUE){
	$node = $this->get($id);
    $path=$node['user_path'];
    
    $path='"'.$path.'%"';
    if ($parent_node){
     $sql='SELECT *
			FROM '.$this->table.' 
			WHERE 
				user_path LIKE '.$path.'
			ORDER BY user_path';
    }
    else {
     $sql='SELECT * 
			FROM '.$this->table.' 
			WHERE 
				user_path LIKE '.$path.'
				AND user_id != :id
			ORDER BY user_path';
    }
    return $this->treebuilder($sql,0,$id,$parent_node);

    
    }
public function tree($parent_node=TRUE){
    if ($parent_node){
     $sql='SELECT * 
			FROM '.$this->table.' 
			ORDER BY user_path';
    }
    else {
     $sql='SELECT *
			FROM '.$this->table.' 
			WHERE user_id != :1
			ORDER BY user_path';
    }
    return $this->treebuilder($sql,1,0,$parent_node);
   
    }
    //получение всех родителей
public function parent_branch($id,$parent_node=TRUE){
    	$node = $this->get($id);
    $path='"'.$node['user_path'].'"';
     if ($parent_node){
     $sql='SELECT *
			FROM '.$this->table.' 
			WHERE 
			 '.$path.' LIKE CONCAT(user_path,"%") ORDER BY user_path';
    }
    else {
     $sql='SELECT *
			FROM '.$this->table.' 
			WHERE 
				 '.$path.' LIKE CONCAT(user_path,"%")
				AND user_id != :id
			ORDER BY user_path';
    }
    return $this->treebuilder($sql,0,$id,$parent_node);
    
    }
public function branch($id,$parent_node=TRUE){
    $a=$this->parent_branch($id,true);
    $b=$this->child_branch($id,false);
    $r=array_merge($a,$b);
    return $r;
    }
    public function child($id,$parent_node=TRUE){
    	$node = $this->get($id);
    $path=$node['user_path'];

    $path='"'.$path.'%/%"';
    if ($parent_node){
     $sql='SELECT *
			FROM '.$this->table.' 
			WHERE 
				user_path LIKE '.$path.' AND 
				user_path NOT LIKE 
				CONCAT('.$path.',"/%")
			ORDER BY user_path';
    }
    else {
     $sql='SELECT *
			FROM '.$this->table.' 
			WHERE 
				user_path LIKE '.$path.'
				AND 
				user_path NOT LIKE 
				CONCAT('.$path.',"/%")
				AND user_id != :id
			ORDER BY user_path';
    }
    return $this->treebuilder($sql,0,$id,$parent_node);   
}
public function move($id,$id_to){
    $node = $this->get($id);
    $path=$node['user_path'];
    $path_0='"'.$path.'%"';
    $path='"'.$path.'"';
    $node_to = $this->get($id_to);
    $path_to=$node_to['user_path'];
    $path_to='"'.$path_to.$node['user_id'].'/"';
    // перенос в текущем узле не реализован
		if($path == $path_to) {
			echo '==\n';
			return FALSE;
		}
		if(!$path_to) {
			echo '0\n';
			return FALSE;
		}
    $sql='UPDATE materialized_path SET 
    user_path = REPLACE(user_path, '.$path.', '.$path_to.') WHERE user_path LIKE '.$path_0.'';
	$sth = $this->dbh->query($sql);
	return "Узел перемещен!";
    }
    public function querybuilder($sql,$num,$id,$parent_node){
    switch ($num){
    case 0: //child_branch, parent_branch,child
    $sth = $this->dbh->prepare($sql);
		if(!$parent_node)
			$sth->bindParam(':id', $id);
		$sth->execute();
		
		$r = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC))
			$r[$row['user_id']] = $row;
		return $r;
    
    case 1: //tree
     $sth = $this->dbh->prepare($sql);
	$sth->execute();
		$r = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC))
			$r[$row['user_id']] = $row;
		return $r;
    }
}
public function treebuilder($sql,$num,$id,$parent_node){//функция для построения запроса{
    	switch ($num){
    		case 0: //child_branch
    		$arr=$this->querybuilder($sql,0,$id,$parent_node);
    		$html="";
    		foreach ($arr as $row){
    			$x=substr_count($row['user_path'],'/');
    			$html.=str_repeat("- ".str_repeat(" ",$x-1),$x).ltrim($row['user_id'],'0').". ".$row['user_name']."<br>";
    		}
    		return $html;
    		case 1:
    		$arr=$this->querybuilder($sql,1,$id,$parent_node);
    		$html="";
    		foreach ($arr as $row){
    			$x=substr_count($row['user_path'],'/');
    			$html.=str_repeat("- ".str_repeat(" ",$x-1),$x).ltrim($row['user_id'],'0').". ".$row['user_name']."<br>";
    		}
    		return $html;
    	}
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
    public function multinsert($id,$num){
    	$node = $this->get($id);
        $path_0=$node['user_path'];
$i=0;
$z=$this->generateName(8);
$x=1;
$sql='SELECT COUNT(*) FROM materialized_path';
$sth = $this->dbh->prepare($sql);
$sth -> execute();
$count = $this->auto_increment();
$i+=$count;
$x+=$count;
$mpath=str_pad($this->auto_increment(),7,"0",STR_PAD_LEFT);
$x=$path_0.'/'.$mpath;
//echo "A".$x;exit;
$max=$num+$count;
//echo $i." ".$x; exit;
//конец усложнения
//код вставки 1000 элементов
$path=array();
$sql="INSERT INTO materialized_path(user_id,user_name,user_path,user_status) 
SELECT user_id,user_name,user_path,user_status FROM(
SELECT $i user_id, '$z' user_name,'$x' user_path, '1' user_status FROM DUAL 
	UNION ALL ";
	array_push($path,$x);
	++$i;
while ($i<=$max){
	$rand=rand(0,1);
	if ($rand==1){
	$xit=rand($count+1,$i-1);
	//echo $xit;exit;
    }
    else {
    	$xit=$id;
    }
	if ($xit!=$count){
		if ($rand==1){
	$x=$path[$xit-$count-1].'/'.str_pad($i,7,"0",STR_PAD_LEFT);

	array_push($path,$x);
    }
    else {
    $x=$path_0.'/'.str_pad($i,7,"0",STR_PAD_LEFT);
	array_push($path,$x);
    }
    }
    else {
    	$x=$i;
    	array_push($path,$x);
    }
	$z=$this->generateName(8);
	if ($i!=$max){
	$sql.="SELECT $i, '$z','$x',1 FROM DUAL 
	UNION ALL ";
}
else {
    $sql.="SELECT $i, '$z','$x',1 FROM DUAL";
	 }
	++$i;
}
$sql.=")t";
$sth = $this->dbh->prepare($sql);
$sth -> execute();

	}
	public function level3($num){
$i=0;
$z=$this->generateName(8);
$x=1;
$query1=" SELECT user_id,user_path FROM materialized_path where (LENGTH(user_path) 
- LENGTH(REPLACE(user_path, '/', ''))) / LENGTH('/')=2 ORDER BY RAND() LIMIT 1;";
$sth = $this->dbh->prepare($query1);
$sth->execute();
while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$xit[0] = $row;
		}
$id_0=$xit[0]['user_path'];
$path_0=$xit[0]['user_path'];
$count = $this->auto_increment();
$i+=$count;
$x+=$count;
$mpath=str_pad($this->auto_increment(),7,"0",STR_PAD_LEFT);
$x=$path_0.'/'.$mpath;
$max=$num+$count;
$path=array();
$sql="INSERT INTO materialized_path(user_id,user_name,user_path,user_status) 
SELECT user_id,user_name,user_path,user_status FROM(
SELECT $i user_id, '$z' user_name,'$x' user_path, '1' user_status FROM DUAL 
	UNION ALL ";
	array_push($path,$x);
	++$i;
while ($i<=$max){
    	$xit=$id_0;
    $x=$path_0.'/'.str_pad($i,7,"0",STR_PAD_LEFT);
	array_push($path,$x);
	$z=$this->generateName(8);
	if ($i!=$max){
	$sql.="SELECT $i, '$z','$x',1 FROM DUAL 
	UNION ALL ";
}
else {
    $sql.="SELECT $i, '$z','$x',1 FROM DUAL";
	 }
	++$i;
}
$sql.=")t";
$sth = $this->dbh->prepare($sql);
$sth -> execute();	
	}
		public function test5($id){
		$node = $this->get($id);
    $status=$node['user_status'];
    $path=$node['user_path'];
    $path='"'.$path.'%"';
     $query='SELECT user_id,user_status
			FROM '.$this->table.' 
			WHERE 
				user_path LIKE '.$path.'
			ORDER BY user_path';
	$sth = $this->dbh->prepare($query);
        $sth->execute();
        $arr = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$arr[$row['user_id']] = $row;
		}
		$sql='UPDATE '.$this->table.' SET user_status=CASE user_id ';
        foreach ($arr as $row)
    {
    	$sql.=' WHEN '.$row['user_id'].' THEN '.$status.' ';
        
    }
    $sql.='ELSE user_status END;';
    return $sth=$this->dbh->exec($sql);
	}
 }

$st=new MaterializedPath('materialized_path');
//$st->multinsert(7,1000);
print_r($st->test5(1));
/*$st->multinsert(1,1000);
$st->multinsert(1,1000);
$st->multinsert(1,1000);*/
echo "<br>Данные добавлены!";
$time = microtime(true) - $start;
echo "<br>Время работы скрипта:".$time;
//print_r($st->move(5,4));
/*$st->create("A");
$st->add(1,"A");$st->add(1,"A");$st->add(1,"A");$st->add(1,"A");$st->add(1,"A");$st->add(1,"A");
$st->add(1,"A");$st->add(1,"A");$st->add(1,"A");$st->add(1,"A");$st->add(1,"A");$st->add(1,"A");
$st->add(12,"A");$st->add(12,"A");$st->add(12,"A");$st->add(12,"A");$st->add(12,"A");$st->add(12,"A");$st->add(12,"A");$st->add(12,"A");*/