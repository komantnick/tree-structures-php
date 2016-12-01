<?php
class ClosureTable{
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
public function __construct($table_0,$table_1) {
		$this->table_0 = $table_0;
		$this->table_1 = $table_1;
	    $this->dbh=$this->db();
	}
	public function get($id) {
		$sql = '
			SELECT * 
			FROM '.$this->table_0.' p LEFT JOIN '.$this->table_1.' t ON 
			p.user_id=t.user_id 
			WHERE p.user_id = :id';
		$sth = $this->dbh->prepare($sql);
		$sth->bindParam(':id', $id);
		$sth->execute();
		return $sth->fetch(PDO::FETCH_ASSOC);
	}
	public function countarr($arr,$id){
		$x=0;
        foreach ($arr as $val){
        	if ($val['user_id']==$id) $x++;
        }
        return $x;
	}
	public function rekurstree($parent_node,$pid=TRUE){
		if ($parent_node) {
			$node = $this->get($parent_node);
		    $pid = $node['user_id'];
	    }
           if($pid||!$parent_node) {
			$sql = '
			SELECT *
			FROM '.$this->table_0.' AS p JOIN '.$this->table_1.' AS t
			ON p.user_id=t.user_id WHERE t.level<=2 
			ORDER BY p.user_id';
		} else {
			$sql = '
			SELECT *
			FROM '.$this->table_0.' 
			WHERE user_id !=:id AND  t.level<=2
			ORDER BY user_id';
		}
		$sth = $this->dbh->prepare($sql);
		if(!$parent_node)
			$sth->bindParam(':id', $id);
		$sth->execute();
		$arr = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$arr[$row['num_id']] = $row;
		}
         $html = '';
         if (!$parent_node) $parent_node=1;
        foreach ($arr as $row)
    {
    	echo $row['parent_id']."<br/>";
        if ($row['parent_id'] == $parent_node&&$row['parent_id'] != $row['user_id'])
        {
            
    	    $html .= '<ul>' . "\n";
            $html .= '<li>' . "\n";
            $html .= '    ' . $row['user_id'].". ".$row['user_name'] . "\n";
            $html .= '    ' .$this->rekurstree($row['user_id']);
            $html .= '</li>' . "\n";
            $html .= '</ul>' . "\n";
        }
        else {
        	if ($this->countarr($arr,$row['user_id'])==1&&$parent_node==1){
             
            $html .= '<li>' . "\n";
            $html .= '    ' . $row['user_id'].". ".$row['user_name'] . "\n";
            $html .= '</li>' . "\n";
        	}
        }
    }
    return $html ? '<ul>' . $html . '</ul>' . "\n" : '';
}
	//создание дерева из одного элемента(а также обнуление старого дерева)
	public function create($name = NULL) {
		$this->dbh->exec('DELETE FROM '.$this->table_0.'');
		$this->dbh->exec('ALTER TABLE '.$this->table_0.' AUTO_INCREMENT=1');
		$this->dbh->exec('DELETE FROM '.$this->table_1.'');
		$this->dbh->exec('ALTER TABLE '.$this->table_1.' AUTO_INCREMENT=1');
		$sql = '
			INSERT 
			INTO '.$this->table_0.' 
			VALUES(NULL, :name,1);';
		$sql .= '
			INSERT 
			INTO '.$this->table_1.' 
			VALUES(NULL, 1,1,1);';
		$sth= $this->dbh->prepare($sql);
		$sth->bindParam(':name', $name);
		$sth->execute();
		return "Запись добавлена!";
	}
	public function auto_increment(){
      $sql='SHOW TABLE STATUS FROM kurs7 LIKE "'.$this->table_0.'"';
      $sth = $this->dbh->query($sql);
		$r = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$r = $row['Auto_increment'];
		}
		return $r;
	}
	public function add($id,$name){
		$user= $this->get($id);
		if (!$user){} else {
			$id=$user['user_id'];
		}
		$num=$this->auto_increment();
		$insert = '
			INSERT 
			INTO '.$this->table_0.' 
			SET  user_name = :name;
			';	
			$insert.='INSERT 
			INTO '.$this->table_1.' 
			SELECT NULL,'.$num.',t.parent_id,t.level+1
            FROM '.$this->table_1.' AS t
            WHERE t.user_id='.$id.' UNION ALL 
            SELECT NULL,'.$num.','.$num.',1;';
		$sth = $this->dbh->prepare($insert);
		$sth->bindParam(':name', $name);
		$sth->execute();
		return  "Узел добавлен!";
		
	}
	public function delete($id){
	$node = $this->get($id);
    $id=$node['user_id'];
    $delete = '
			DELETE 
			FROM '.$this->table_0.' 
			WHERE user_id='.$id.';';
			$delete .= '
			DELETE 
			FROM '.$this->table_1.' 
			WHERE user_id IN  
			(SELECT user_id FROM '.$this->table_1.' 
             WHERE parent_id=:id
			 )
			;';
		$sth = $this->dbh->prepare($delete);
		$sth->bindParam(':id', $id);
		$sth->execute();
	    echo "Узел удален!";
	}
	public function block($id){
	$node = $this->get($id);
    $id=$node['user_id'];
    $block = '
			UPDATE '.$this->table_0.' 
			SET user_status=0
			WHERE user_id='.$id.'';
		$this->dbh->exec($block);
	    echo "Узел заблокирован!";
	}
    public function child_branch($id,$parent_node=TRUE){
	$node = $this->get($id);
    $id=$node['user_id'];
    if ($parent_node){
     $sql='SELECT * FROM '.$this->table_0.' p
     JOIN '.$this->table_1.' t ON (p.user_id = t.user_id)
     WHERE t.parent_id = :id AND t.level=2';
    }
    else {
     $sql='SELECT * FROM '.$this->table_0.' p
     JOIN '.$this->table_1.' t ON (p.user_id = t.user_id)
     WHERE t.parent_id = :id
				AND t.user_id != :id AND t.level=2';
    }
    return $this->gettree($sql,$id,0,$parent_node);
    
    }
    public function child($id,$parent_node=TRUE){
	$node = $this->get($id);
    $id=$node['user_id'];
    if ($parent_node){
     $sql='SELECT * FROM '.$this->table_0.' p
     JOIN '.$this->table_1.' t ON (p.user_id = t.user_id)
     WHERE t.parent_id = :id AND t.level<=2';
    }
    else {
     $sql='SELECT * FROM '.$this->table_0.' p
     JOIN '.$this->table_1.' t ON (p.user_id = t.user_id)
     WHERE t.parent_id = '.$id.'
	 AND t.user_id != :id AND t.level<=2';
    }
    return $this->gettree($sql,$id,1,$parent_node);
    }
    public function parent_branch($id,$parent_node=TRUE){
	$node = $this->get($id);
    $id=$node['user_id'];
    if ($parent_node){
     $sql='SELECT p.*,t.user_id AS user_load ,t.parent_id,t.level 
     FROM '.$this->table_0.' AS p
     JOIN '.$this->table_1.' AS t ON (p.user_id = t.parent_id) 
     WHERE t.user_id = :id ORDER BY t.level DESC';
    }
    else {
     $sql='SELECT p.*,t.user_id AS user_load,t.parent_id,t.level 
     FROM '.$this->table_0.' p
     JOIN '.$this->table_1.' t ON (p.user_id = t.parent_id) 
     WHERE t.user_id = '.$id.'
				AND t.user_id != :id ORDER BY t.level DESC';
    }
    return $this->gettree($sql,$id,2,$parent_node);
    }
    public function tree($parent_node=TRUE){
         return $this->rekurstree(0,$parent_node);
    }
   public function branch($id){
   	$a=$this->parent_branch($id,TRUE);
   	$b=$this->child_branch($id,FALSE);
   	$r=array_merge($a,$b);
   	return $r;
   }
   //перемещение в другой узел
   public function move($id,$id_to){
   	$node = $this->get($id);
   	$node_to = $this->get($id_to);
   	$id=$node['user_id'];	
   	$id_to=$node_to['user_id'];	
		if($id == $id_to) {
			echo '==\n';
			return FALSE;
		}
		if(!$id_to) {
			echo '0\n';
			return FALSE;
		}
		$delete = '
			DELETE 
			FROM '.$this->table_1.' 
			WHERE '.$this->table_1.'.user_id IN (SELECT * FROM (SELECT user_id FROM '.$this->table_1.'
            where parent_id=:id) t)

            AND '.$this->table_1.'.parent_id IN (SELECT * FROM (SELECT parent_id FROM 
            '.$this->table_1.'
            where user_id=:id AND parent_id!=user_id) p)
			';
		$sth = $this->dbh->prepare($delete);
		$sth->bindParam(':id', $id);
		$sth->execute();      
	    $insert = '
	        INSERT INTO '.$this->table_1.' 
	        SELECT NULL,t.user_id,p.parent_id,p.level+1
	        FROM '.$this->table_1.' AS p
	        CROSS JOIN '.$this->table_1.' AS t
	        WHERE p.user_id=:id_to AND t.parent_id=:id
			';
			$sth = $this->dbh->prepare($insert);
		$sth->bindParam(':id', $id);
		$sth->bindParam(':id_to', $id_to);
		$sth->execute();
   }
   public function querybuilder($sql,$id,$num,$parent_node=TRUE){
		switch ($num) {
			case 0: 
            $sth = $this->dbh->prepare($sql);
		$sth->bindParam(':id', $id);
		$sth->execute();
		$r = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$r[$row['user_id']] = $row;
		}
		return $r;
			}
		}
   public function gettree($sql,$id,$num,$parent_node=TRUE){
		switch ($num) {
			case 0: 
			$arr = $this->querybuilder($sql,$id,0,$parent_node);
         $html = '';
         if (!$parent_node) $parent_node=1;
        foreach ($arr as $row)
    {
        if ($row['parent_id'] == $id&&$row['parent_id'] != $row['user_id']&&$row['level']<=2)
        {        
    	    $html .= '<ul>' . "\n";
            $html .= '<li>' . "\n";
            $html .= '    ' . $row['user_id'].". ".$row['user_name'] . "\n";
            $html .= '    ' .$this->child_branch($row['user_id']);
            $html .= '</li>' . "\n";
            $html .= '</ul>' . "\n";
        }
        else {
        	if ($parent_node==1&&$row['level']<=2){
             
            $html .= '<li>' . "\n";
            $html .= '    ' . $row['user_id'].". ".$row['user_name'] . "\n";
            $html .= '</li>' . "\n";
        	}
        }
    }
    return $html ? '<ul>' . $html . '</ul>' . "\n" : '';
    case 1:
    $arr = $this->querybuilder($sql,$id,0,$parent_node);
         $html = '';
         if (!$parent_node) $parent_node=1;
        foreach ($arr as $row)
    {
        if ($row['level'] == 2)
        {
            
    	    $html .= '<ul>' . "\n";
            $html .= '<li>' . "\n";
            $html .= '    ' . $row['user_id'].". ".$row['user_name'] . "\n";
            $html .= '</li>' . "\n";
            $html .= '</ul>' . "\n";
        }
                else {
        	if ($row['level']=1){
             
            $html .= '<li>' . "\n";
            $html .= '    ' . $row['user_id'].". ".$row['user_name'] . "\n";
            $html .= '</li>' . "\n";
        	}
        }
    }
    return $html ? '<ul>' . $html . '</ul>' . "\n" : '';
    case 2:
    $arr = $this->querybuilder($sql,$id,0,$parent_node);
         $html = '';
         if (!$parent_node) $parent_node=1;
         foreach ($arr as $row)
    {
        $x=$row['level'];
    	$html.=str_repeat("- ".str_repeat(" ",$x-1),$x).$row['user_id'].". ".$row['user_name']."<br>";
    }
    return $html ? '<ul>' . $html . '</ul>' . "\n" : '';
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
$z=$this->generateName(8);
$count=$this->auto_increment();
$i=$count;
$max=$num+$count;
$xit=0;
$user=array();
$parent=array();
$l=array();
$sql='INSERT INTO '.$this->table_0.' (user_id,user_name,user_status) 
SELECT user_id,user_name,user_status FROM(
SELECT '.$i.' user_id, "'.$z.'" user_name, 1 user_status FROM DUAL 
	UNION ALL ';
	$sql2='INSERT INTO '.$this->table_1.' (num_id,user_id,parent_id,level) ';
$sql_pull='SELECT num_id,user_id,parent_id,level FROM(SELECT NULL num_id, '.$i.' user_id, t.parent_id parent_id, t.level+1 level FROM
'.$this->table_1.' AS t WHERE t.user_id='.$id.'
UNION ALL SELECT NULL,'.$i.','.$i.',1 FROM DUAL  ';
$quer=$sql_pull.')t';
$sth=$this->dbh->prepare($quer);
$sth->execute();
while($row = $sth->fetch(PDO::FETCH_ASSOC)){
	array_push($user,$row['user_id']);
    array_push($parent,$row['parent_id']);
    array_push($l,$row['level']);
	}
	print_r($user);
$sql2.=$sql_pull.'UNION ALL ';
	++$i;
while ($i<=$max-1){
	$xit=rand($count-1,$i-1);  
	if ($xit<$count){
		$id_0=$xit;
		$node = $this->get($id_0);
        $id_0=$node['user_id'];
	}
	else {
		$id_0=$xit;
	}
	$z=$this->generateName(8);
	if ($i!=$max-1){
	$sql.='SELECT '.$i.', "'.$z.'",1 FROM DUAL 
	UNION ALL ';
	//если узел занесен в бд
	if ($id_0<$count){
		$sqll='SELECT num_id,user_id,parent_id,level FROM(';
	$sql_pull='SELECT NULL num_id, '.$i.' user_id, t.parent_id parent_id, t.level+1 level FROM
'.$this->table_1.' AS t WHERE t.user_id='.$id_0.'
UNION ALL SELECT NULL num_id,'.$i.' user_id,'.$i.' parent_id,1 level FROM DUAL  ';
$quer=$sqll.$sql_pull.')t';
$sth=$this->dbh->prepare($quer);
$sth->execute();
while($row = $sth->fetch(PDO::FETCH_ASSOC)){
	array_push($user,$row['user_id']);
    array_push($parent,$row['parent_id']);
    array_push($l,$row['level']);
	}
$sql2.=$sql_pull.'UNION ALL ';

}
//если узел не знаенесен в бд
else {
	$num=0;
	$sql_pull_start="SELECT num_id,user_id,parent_id,level FROM(";
	$sql_pull='';
	foreach ($user as $value){
		if ($value==$id_0){
		
		$sql_pull.='SELECT NULL num_id, '.$i.' user_id,'.$parent[$num].' parent_id,'.$l[$num].'+1 level
		FROM DUAL UNION ALL ';
	}
     $num++;
	}
		$sql_pull.='SELECT NULL, '.$i.','.$i.',1
		FROM DUAL ';
		$quer=$sql_pull_start.$sql_pull.')t';
		$sth=$this->dbh->prepare($quer);
$sth->execute();
while($row = $sth->fetch(PDO::FETCH_ASSOC)){
	array_push($user,$row['user_id']);
    array_push($parent,$row['parent_id']);
    array_push($l,$row['level']);
    
	}
$sql2.=$sql_pull.'UNION ALL ';
	}
}
else {
    $sql.='SELECT '.$i.',"'.$z.'",1 FROM DUAL ';
    if ($id_0<$count){
    $sql2.='SELECT NULL, '.$i.', t.parent_id , t.level+1 FROM
    '.$this->table_1.' AS t WHERE t.user_id='.$id_0.'
    UNION ALL SELECT NULL,'.$i.','.$i.',1 FROM DUAL ';
}
else {
	$num=0;

	$sql_pull_start="SELECT num_id,user_id,parent_id,level FROM(";
	$sql_pull='';
	foreach ($user as $value){
		if ($value==$id_0){		
		$sql_pull.='SELECT NULL num_id, '.$i.' user_id,'.$parent[$num].' parent_id,'.$l[$num].'+1 level
		FROM DUAL UNION ALL ';
	}
     $num++;
	}
		$sql_pull.='SELECT NULL num_id, '.$i.' user_id,'.$i.' parent_id,1 level
		FROM DUAL';
		$quer=$sql_pull_start.$sql_pull.')t';
$sth=$this->dbh->prepare($quer);
$sth->execute();
while($row = $sth->fetch(PDO::FETCH_ASSOC)){
	array_push($user,$row['user_id']);
    array_push($parent,$row['parent_id']);
    array_push($l,$row['level']);
	}
$sql2.=$sql_pull;


}
}
++$i;
}
	$sql.=")t";
$sql2.=")t";
$sql=$sql.';'.$sql2;
$sth = $this->dbh->prepare($sql);
$sth->execute();
}
	public function level3($num){
$z=$this->generateName(8);
$count=$this->auto_increment();
$i=$count;
$max=$num+$count;
$user=array();
$parent=array();
$l=array();
$query1='SELECT t.user_id
			FROM '.$this->table_0.' p LEFT JOIN '.$this->table_1.' t ON 
			p.user_id=t.user_id 
			WHERE t.level=2 ';
$sth = $this->dbh->prepare($query1);
$sth -> execute();
while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$xit[0] = $row;
		}
$id_0=$xit[0]['user_id'];
$sql='INSERT INTO '.$this->table_0.' (user_id,user_name,user_status) 
SELECT user_id,user_name,user_status FROM(
SELECT '.$i.' user_id, "'.$z.'" user_name, 1 user_status FROM DUAL 
	UNION ALL ';
	$sql2='INSERT INTO '.$this->table_1.' (num_id,user_id,parent_id,level) ';
$sql_pull='SELECT num_id,user_id,parent_id,level FROM(SELECT NULL num_id, '.$i.' user_id, t.parent_id parent_id, t.level+1 level FROM
'.$this->table_1.' AS t WHERE t.user_id='.$id_0.'
UNION ALL SELECT NULL,'.$i.','.$i.',1 FROM DUAL  ';
$quer=$sql_pull.')t';
$sth=$this->dbh->prepare($quer);
$sth->execute();
while($row = $sth->fetch(PDO::FETCH_ASSOC)){
	array_push($user,$row['user_id']);
    array_push($parent,$row['parent_id']);
    array_push($l,$row['level']);
	}
$sql2.=$sql_pull.'UNION ALL ';
	++$i;
while ($i<=$max-1){
	
	$z=$this->generateName(8);
	if ($i!=$max-1){
	$sql.='SELECT '.$i.', "'.$z.'",1 FROM DUAL 
	UNION ALL ';
	//если узел занесен в бд
	$num=0;
	$sql_pull='';
	foreach ($user as $value){
		$sql_pull.='SELECT NULL num_id, '.$i.' user_id,'.$parent[$num].' parent_id,'.$l[$num].'+1 level
		FROM DUAL UNION ALL ';
     $num++;
	}
		$sql_pull.='SELECT NULL, '.$i.','.$i.',1
		FROM DUAL ';
$sql2.=$sql_pull.'UNION ALL ';
}
else {
    $sql.='SELECT '.$i.',"'.$z.'",1 FROM DUAL ';
	$num=0;
	$sql_pull='';
	foreach ($user as $value){	
		$sql_pull.='SELECT NULL num_id, '.$i.' user_id,'.$parent[$num].' parent_id,'.$l[$num].'+1 level
		FROM DUAL UNION ALL ';
     $num++;
	}
		$sql_pull.='SELECT NULL num_id, '.$i.' user_id,'.$i.' parent_id,1 level
		FROM DUAL';
$sql2.=$sql_pull;
}
++$i;
}
	$sql.=")t";
$sql2.=")t";
$sql=$sql.';'.$sql2;
$sth = $this->dbh->prepare($sql);
$sth->execute();
	}
	public function test5($id){
		$node = $this->get($id);
        $status = $node['user_status'];
        $status_list=array();
        array_push($status_list,$id);
        $query='SELECT * FROM '.$this->table_0.' p
     JOIN '.$this->table_1.' t ON (p.user_id = t.user_id)
     WHERE t.parent_id = :id';
        $sth = $this->dbh->prepare($query);
        $sth->bindParam(':id', $id);
        $sth->execute();
        $arr = [];
		while($row = $sth->fetch(PDO::FETCH_ASSOC)){
			$arr[$row['user_id']] = $row;
		}
    $sql='UPDATE '.$this->table_0.' SET user_status=CASE user_id ';
        foreach ($arr as $row)
    {
    	$sql.=' WHEN '.$row['user_id'].' THEN '.$status.' ';
        
    }
    $sql.='ELSE user_status END;';
    return $sth=$this->dbh->exec($sql);
	}
}