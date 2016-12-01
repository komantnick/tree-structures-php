<?php  
$start = microtime(true);
set_time_limit(3600);
ini_set("memory_limit", "256M");  
$start = microtime(true);
//функция db- отвечает за соединение с БД
class NestedSets{
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
		$left = $node['left_number'];
		$right = $node['right_number'];
		$level = $node['level'] - 1;
		$sql = '
			SELECT user_id, user_name, level 
			FROM '.$this->table.' 
			WHERE 
				left_number <= '.$left.' AND 
				right_number >= '.$right.' AND
				level = '.$level.'
			ORDER BY left_number';
		
		$sth = $this->dbh->query($sql);
		return $sth->fetch(PDO::FETCH_ASSOC);
	}
	//создание дерева из одного элемента(а также обнуление старого дерева)
public function create($name) {
		$this->dbh->exec('DELETE FROM '.$this->table.'');
		$this->dbh->exec('ALTER TABLE '.$this->table.' AUTO_INCREMENT=1');
		$sql = '
			INSERT 
			INTO '.$this->table.' 
			VALUES(NULL, :name, 1, 2,1,1)';
		$sth= $this->dbh->prepare($sql);
		$sth->bindParam(':name', $name);
		$sth->execute();
		echo "Дерево добавлено!";
	}
	//добавление одного узла дерева
public function add($id,$name) {
		$node = $this->get($id);
		$right = $node['right_number'];
		$level = $node['level'];	
		$update = '
			UPDATE '.$this->table.' 
			SET right_number = right_number + 2, 
			left_number = IF(left_number > '.$right.', left_number + 2, left_number) 
			WHERE right_number >= '.$right;
		$this->dbh->exec($update);		
		$insert = '
			INSERT 
			INTO '.$this->table.' 
			SET left_number = '.$right.', right_number = '.$right.' + 1, 
			level = '.$level.' + 1, user_name = :name
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
    $left=$node['left_number'];
    $right=$node['right_number'];
    $delete = '
			DELETE 
			FROM '.$this->table.' 
			WHERE left_number >= '.$left.' AND 
			right_number <= '.$right.'';
	$update = '
			UPDATE '.$this->table.' 
			SET 
			left_number = IF(left_number > '.$left.', left_number - ('.$right.' - '.$left.' + 1), left_number), 
			right_number = right_number - ('.$right.' - '.$left.' + 1) 
			WHERE right_number > '.$right;
		$this->dbh->exec($delete);
		$this->dbh->exec($update);
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
//получение всего дерева
public function tree($parent_node=TRUE) {
		if($parent_node) {
			$sql = '
			SELECT user_id, user_name, level 
			FROM '.$this->table.' 
			ORDER BY left_number';
		} else {
			$sql = '
			SELECT user_id, user_name, level 
			FROM '.$this->table.' 
			WHERE user_id != 1
			ORDER BY left_number';
		}
		return $this->treebuilder($sql,1,0,$parent_node);
	}
		// Получение подчиненной ветки начиная с данного узла
	public function child_branch($id, $parent_node = TRUE) {
		$node = $this->get($id);
		$left = $node['left_number'];
		$right = $node['right_number'];
		if($parent_node) {
			$sql = '
			SELECT user_id, user_name, level 
			FROM '.$this->table.' 
			WHERE 
				left_number >= '.$left.' AND 
				right_number <= '.$right.' 
			ORDER BY left_number';
		} else {
			$sql = '
			SELECT 
				user_id, user_name, level 
			FROM 
				'.$this->table.' 
			WHERE 
				left_number >= '.$left.' 
				AND 
				right_number <= '.$right.' 
				AND
				user_id != :id
			ORDER BY 
				left_number';
		}
		return $this->treebuilder($sql,0,$id,$parent_node);
		
	}
	//получение родительской ветки для данного узла
	public function parent_branch($id) {
		$node = $this->get($id);
		$left = $node['left_number'];
		$right = $node['right_number'];
		
		$sql = '
			SELECT user_id, user_name, level 
			FROM '.$this->table.' 
			WHERE 
				left_number <= '.$left.' AND 
				right_number >= '.$right.' 
			ORDER BY left_number';
		
		return $this->treebuilder($sql,1,$id,TRUE);	
	}

	// получение ветки, в которой находится данный узел
	public function branch($id) {
		$node = $this->get($id);
		$left = $node['left_number'];
		$right = $node['right_number'];
		$sql = '
			SELECT user_id, user_name, level 
			FROM '.$this->table.' 
			WHERE 
				right_number > '.$left.' AND 
				left_number < '.$right.' 
			ORDER BY left_number';
		
		return $this->treebuilder($sql,1,$id,TRUE);	
	}
	//получение всех подчиненных узлов
	public function child($id, $parent_node = TRUE) {
		$node = $this->get($id);
		$left = $node['left_number'];
		$right = $node['right_number'];
		$level = $node['level'] + 1;
		
		if($parent_node) {
			$sql = '
			SELECT user_id, user_name, level 
			FROM '.$this->table.' 
			WHERE 
				left_number >= '.$left.' AND 
				right_number <= '.$right.' AND
				level <= '.$level.'
			ORDER BY left_number';
		} else {
			$sql = '
			SELECT user_id, user_name, level 
			FROM '.$this->table.' 
			WHERE 
				left_number >= '.$left.' AND 
				right_number <= '.$right.' AND
				level <= '.$level.' AND
				user_id != :id
			ORDER BY left_number';
		}
		return $this->treebuilder($sql,0,$id,$parent_node);	
		
		
	}
	//перемещение узла
	public function move($id, $id_to) {
		$node = $this->get($id);
		$node_parent = self::parent_node($id);		
		$node_to = $this->get($id_to);	
		// перенос в текущем узле не реализован
		if($node_parent['user_id'] == $node_to['user_id']) {
			echo '==\n';
			return FALSE;
		}
		// перенос в корень не реализован
		if(!$id_to) {
			echo '0\n';
			return FALSE;
		}
		//перенос
		$left 	= $node['left_number'];
		$right 	= $node['right_number'];
		$level 	= $node['level'];
		$level_up= $node_to['level'];
		$sth = $this->dbh->query('SELECT (right_number- 1) AS right_number FROM '.$this->table.' WHERE user_id = '.$id_to.'');
		$right_near = $sth->fetch(PDO::FETCH_ASSOC)['right_number'];
		$skew_level = $level_up - $level + 1;
		$skew_tree = $right- $left + 1;	
		$sth = $this->dbh->query('SELECT user_id FROM '.$this->table.' WHERE left_number >= '.$left.' AND right_number <= '.$right.'');
		$id_edit = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$id_edit[] = $row['user_id'];
		}
		//объединение элементов массива в строку
		$id_edit = implode(', ', $id_edit);
		if($right_near < $right) {
			//вышестоящие
			$skew_edit = $right_near - $left + 1;
			$sql[0] = '
				UPDATE '.$this->table.' 
				SET right_number = right_number + '.$skew_tree.' 
				WHERE 
					right_number < '.$left.' AND 
					right_number > '.$right_near.'';
			$sql[1] = '
				UPDATE '.$this->table.' 
				SET left_number = left_number + '.$skew_tree.' 
				WHERE 
					left_number < '.$left.' AND 
					left_number > '.$right_near;
			$sql[2] = '
				UPDATE '.$this->table.' 
				SET left_number = left_number + '.$skew_edit.', 
					right_number = right_number + '.$skew_edit.', 
					level = level + '.$skew_level.' 
				WHERE user_id IN ('.$id_edit.')';
			
		} else {
			//нижестоящие
			$skew_edit = $right_near - $left +1 - $skew_tree;
			
			$sql[0] = '
				UPDATE '.$this->table.' 
				SET right_number = right_number - '.$skew_tree.' 
				WHERE 
					right_number > '.$right.' AND 
					right_number <= '.$right_near;
				
			$sql[1] = '
				UPDATE '.$this->table.' 
				SET left_number = left_number - '.$skew_tree.' 
				WHERE 
					left_number > '.$right.' AND 
					left_number <= '.$right_near;
				
			$sql[2] = '
				UPDATE '.$this->table.' 
				SET left_number = left_number + '.$skew_edit.', 
					right_number = right_number + '.$skew_edit.', 
					level = level + '.$skew_level.' 
				WHERE user_id IN ('.$id_edit.')';
		}
		
		$this->dbh->exec($sql[0]);
		$this->dbh->exec($sql[1]);
		$this->dbh->exec($sql[2]);
		echo "Узел перемещен!";
	}
	public function querybuilder($sql,$num,$id,$parent_node){
        switch ($num){
        	case 0:
        	$sth = $this->dbh->prepare($sql);
		if(!$parent_node)
			$sth->bindParam(':id', $id);
		$sth->execute();
		$r = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC))
			$r[$row['user_id']] = $row;
		return $r;
		case 1:
		$sth = $this->dbh->prepare($sql);
		$sth->execute();
		$r = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC))
			$r[$row['user_id']] = $row;
		return $r;


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
public function auto_increment(){
      $sql='SHOW TABLE STATUS FROM kurs7 LIKE "'.$this->table.'"';
      $sth = $this->dbh->query($sql);
		$r = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$r = $row['Auto_increment'];
		}
		return $r;
		
	}
	public function treebuilder($sql,$num,$id,$parent_node){
		switch ($num){
			case 0: //
			$arr=$this->querybuilder($sql,0,$id,$parent_node);
    		$html="";
    		foreach ($arr as $row){
    			$x=$row['level'];
    			$html.=str_repeat("- ".str_repeat(" ",$x-1),$x).$row['user_id'].". ".$row['user_name']."<br>";
    		}
    		return $html;
			case 1: //
    		$arr=$this->querybuilder($sql,1,$id,$parent_node);
    		$html="";
    		foreach ($arr as $row){
    			$x=$row['level'];
    			$html.=str_repeat("- ".str_repeat(" ",$x-1),$x).$row['user_id'].". ".$row['user_name']."<br>";
    		}
    		return $html;
		}
	}
	public function multinsert($id,$num){
		$node = $this->get($id);
		$left = $node['left_number'];
		$right = $node['right_number'];
		$levv = $node['level'];
		$i=0;
$z=$this->generateName(8);
$x=1;

$count=$this->auto_increment();
$sql[0] = '
				UPDATE '.$this->table.' 
				SET left_number = left_number + '.$num.'*2				
				WHERE left_number>='.$right.'';
$sql[1] = '
				UPDATE '.$this->table.' 
				SET right_number = right_number + '.$num.'*2 					
				WHERE right_number>='.$right.'';
	$this->dbh->exec($sql[0]);
	$this->dbh->exec($sql[1]);

$i+=$count;
$x+=$count;
$max=$num+$count;
$l1=$right;
$l2=$right+1;
$level=1+$levv;
$left=array(); //массив левых узлов
$right=array(); //массив правых узлов
$lez=array(); //массив уровней
array_push($left,$l1);
array_push($right,$l2);
array_push($lez,$level);
$k=$i+1;
$sql="INSERT INTO nested_sets(user_id,user_name,left_number,right_number,level,user_status) 
SELECT user_id,user_name,left_number,right_number,level,user_status FROM(";
	++$i;
while ($i<=$max-1){
	$xit=rand($count,$i-1); //генерация чисел внутри дерева
	if ($xit!=$count){
    $d=$xit-$count;
    $level=$lez[$d]+1;
    $l1=$right[$d];
    $l2=$l1+1;
    for ($j = 0; $j<count($left); $j++){
    if ($left[$j]>=$l1) {$left[$j]+=2;}
    }
    for ($j = 0; $j<count($right); $j++){
    if ($right[$j]>=$l1) {$right[$j]+=2;}
    }
    array_push($left,$l1);
    array_push($right,$l2);
    array_push($lez,$level);
  }

    else {
    $level=1+$levv;
    //echo max($right);
    $l1=max($right)+1;
    $l2=$l1+1;
    array_push($left,$l1);
    array_push($right,$l2);
    array_push($lez,$level);
    }
    ++$i;
    if ($i==$max) {
    for ($k=$count;$k<count($lez)+$count;$k++){
    	;
      $z=$this->generateName(8);
      $j=$k+1;
      $d=$k-$count;
      //echo $j; echo $k; echo $d;exit;
        if ($j!==$max&&$k!=$count){
       $sql.="SELECT $j-1, '$z','$left[$d]','$right[$d]','$lez[$d]','1' FROM DUAL 
       UNION ALL ";
        }
        else if ($k==$count){
          $sql.="SELECT $j-1 user_id, '$z' user_name,'$left[$d]' left_number, '$right[$d]' right_number, '$lez[$d]' level,'1' user_status 
           FROM DUAL 
           UNION ALL ";
        }
        else {
       $sql.="SELECT $j-1, '$z','$left[$d]','$right[$d]','$lez[$d]','1' FROM DUAL";
        }
      }
    }
}
$sql.=")t";
//echo $sql;exit;
$sth = $this->dbh->prepare($sql);
$sth -> execute();	
	}
	//вставка некоторого количества элементов третьего уровня
	public function level3($num){
		$i=0;
$z=$this->generateName(8);
$x=1;
//начало усложнения
$count = $this->auto_increment();
//метод быдлокодера
$i+=$count;
$x+=$count;
$max=$num+$count;
$l1=$count*2+1;
$l2=$count*2+2;
$level=1;
$query1=" SELECT user_id,right_number,level FROM nested_sets WHERE level=2 ORDER BY RAND() LIMIT 1;";
$sth = $this->dbh->prepare($query1);
$sth -> execute();
while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$xit[0] = $row;
		}
$right=$xit[0]['right_number'];
$level=$xit[0]['level'];
$xit=$xit[0]['user_id'];
$k=$i+1;
$sql="INSERT INTO nested_sets(user_id,user_name,left_number,right_number,level,user_status) 
SELECT user_id,user_name,left_number,right_number,level,user_status FROM(";
	++$i;
while ($i<=$max){
    $query1="UPDATE nested_sets SET left_number = left_number + 2 WHERE left_number >= $right;
    UPDATE nested_sets SET right_number = right_number + 2 WHERE right_number >= $right;
    ";
$sth = $this->dbh->prepare($query1);
$sth->execute();
      $z=$this->generateName(8);
      $j=$k+1;
      $d=$k-$count;
      $lev=$level+1;
      $right1=$right+1;
       $sql2="SELECT '$i' user_id, '$z' user_name,'$right' left_number,'$right1' right_number,'$lev' level,'1' user_status FROM DUAL";
       /*echo $sql."<br>";
       echo "<br>";*/
       $sql2=$sql.$sql2.")t";
       //echo $sql2;exit;
       $sth = $this->dbh->prepare($sql2);
       $sth -> execute();	
       ++$i;
     // }
   // }
  }	
	}
	public function test5($id){
		$node = $this->get($id);
    $status=$node['user_status'];
    $left = $node['left_number'];
	$right = $node['right_number'];
			$query = '
			SELECT user_id, user_status
			FROM '.$this->table.' 
			WHERE 
				left_number >= '.$left.' AND 
				right_number <= '.$right.' 
			ORDER BY left_number';
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
$st=new NestedSets('nested_sets');
//print_r($st->add(1000,'Бяка'));
//$st->multinsert(1,1000);
print_r($st->tree());

echo "<br>Данные добавлены!";
$time = microtime(true) - $start;
echo "<br>Время работы скрипта:".$time;
//print_r($st->parent_node(5));
//print_r($st->move(5,2));
//print_r($st->child(3));
//print_r($st->child_branch(2));
//print_r($st->tree());
//echo "<br>";

//print_r($st->child_branch(1));

//$st->delete(4);
//token to github:82a9caf248afdc3a031d780e984715de77f06afe

/*$st->create("Номер1");
$st->add(1,'Багс');
$st->add(1,'Багс');
$st->add(1,'Багс');
$st->add(3,'Багс');
$st->add(5,'Багс');*/
//$st->multinsert(6,1000);