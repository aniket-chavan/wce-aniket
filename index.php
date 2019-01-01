<?php
$ACCESS_PWD='';
$DBDEF=array(
		'user'=>"",
		'pwd'=>"", 
		'db'=>"", 
		'host'=>"",
		'port'=>"",
		'chset'=>"utf8",
	);
$IS_COUNT=false;
$DUMP_FILE=dirname(__FILE__).'/pmadump';
if (function_exists('date_default_timezone_set')) date_default_timezone_set('UTC');
//hello aniket
$VERSION='1.9.170730';
$MAX_ROWS_PER_PAGE=50;
$D="\r\n";
$BOM=chr(239).chr(187).chr(191);
$SHOW_DB="SHOW DATABASES";
$SHOW_TABLE="SHOW TABLE STATUS";
$DB=array();

$self=$_SERVER['PHP_SELF'];

session_set_cookie_params(0, null, null, false, true);
session_start();
ini_set('display_errors',0);
error_reporting(E_ALL ^ E_NOTICE);

if (get_magic_quotes_gpc()){
  $_COOKIE=array_map('killmq',$_COOKIE);
  $_REQUEST=array_map('killmq',$_REQUEST);
}

if ($_REQUEST['login'])
{
	if ($_REQUEST['pwd']!=$ACCESS_PWD)
	{
	    $err_msg="Invalid password. Try again";
	}else{
    	$_SESSION['is_logged']=true;
    	loadcfg();
	}
}

if ($_REQUEST['logoff']){
  $_SESSION = array();
  savecfg();
  session_destroy();
  $url=$self;
  if (!$ACCESS_PWD) $url='/';
  header("location: " . $url . "myadmin/");
  exit;
}

if (!$_SESSION['is_logged']){
  if (!$ACCESS_PWD) {
    $_SESSION['is_logged']=true;
    loadcfg();
  }else{
    print_login();
    exit;
  }
}

if ($_REQUEST['savecfg']){
  
  savecfg();
}

loadsess();

if ($_REQUEST['showcfg']){
  print_cfg();
  exit;
}

$SQLq=trim(b64d($_REQUEST['q']));
$page=$_REQUEST['p']+0;
if ($_REQUEST['refresh'] && $DB['db'] && preg_match('/^show/',$SQLq) ) $SQLq=$SHOW_TABLE;

if (db_connect('nodie')){
  $time_start=microtime_float();

  if ($_REQUEST['pi']){
    ob_start();$html=ob_get_clean();preg_match("/<body[^>]*>(.*?)<\/body>/is",$html,$m);
    $sqldr='<div class="pi">'.$m[1].'</div>';
  }else{
   		if ($DB['db']){
		if ($_REQUEST['shex']){
		 print_export();
		}elseif ($_REQUEST['doex']){
		 do_export();
		}elseif ($_REQUEST['shim']){
		 print_import();
		}elseif ($_REQUEST['doim']){
		 do_import();
		}elseif ($_REQUEST['dosht']){
		 do_sht();
		}elseif (!$_REQUEST['refresh'] || preg_match('/^select|show|explain|desc/i',$SQLq) ){
		 if ($SQLq)
		 do_sql($SQLq);
    }
   }else{
    if ( $_REQUEST['refresh'] ){
       do_sql($SHOW_DB);
    }elseif ($_REQUEST['crdb']){
      do_sql('CREATE DATABASE `'.$_REQUEST['new_db'].'`');do_sql($SHOW_DB);
    }elseif ( preg_match('/^(?:show\s+(?:databases|status|variables|process)|create\s+database|grant\s+)/i',$SQLq) ){
       do_sql($SQLq);
    }else{
       $err_msg="Select Database first";
       if (!$SQLq) do_sql($SHOW_DB);
    }
   }
  }
  $time_all=ceil((microtime_float()-$time_start)*10000)/10000;

  print_screen();
}else{
  print_cfg();
}

function do_sql($q){
 global $dbh,$last_sth,$last_sql,$reccount,$out_message,$SQLq,$SHOW_TABLE;
 $SQLq=$q;

 if (!do_multi_sql($q)){
    $out_message="Error: ".mysqli_error($dbh);
 }else{
    if ($last_sth && $last_sql){
       $SQLq=$last_sql;
       if (preg_match("/^select|show|explain|desc/i",$last_sql)) {
          if ($q!=$last_sql) $out_message="Results of the last select displayed:";
          display_select($last_sth,$last_sql);
       } else {
         $reccount=mysqli_affected_rows($dbh);
         $out_message="Done.";
         if (preg_match("/^insert|replace/i",$last_sql)) $out_message.=" Last inserted id=".get_identity();
         if (preg_match("/^drop|truncate/i",$last_sql)) do_sql($SHOW_TABLE);
       }
    }
 }
}

function display_select($sth,$q){
 global $dbh,$DB,$sqldr,$reccount,$is_sht,$is_sm;
 $rc=array("o","e");
 $dbn=ue($DB['db']);
 $sqldr='';

 $is_shd=(preg_match('/^show\s+databases/i',$q));
 $is_sht=(preg_match('/^show\s+tables|^SHOW\s+TABLE\s+STATUS/',$q));
 $is_show_crt=(preg_match('/^show\s+create\s+table/i',$q));

 if ($sth===FALSE or $sth===TRUE) return;

 $reccount=mysqli_num_rows($sth);
 $fields_num=mysqli_field_count($dbh);

 $w='';
 if ($is_sm) $w='sm ';
 if ($is_sht || $is_shd) {
	$w='wa';
	$url="?db=$dbn";
	$sqldr.="<div>";
    if ($is_shd) $sqldr.=" <label>Create new database: <input type='text' name='new_db' placeholder='type db name here'></label> <input type='submit' name='crdb' value='Create'>";
   $sqldr.="<br></div>";
 }
 if ($is_sht){
   $abtn="<div>
 <input type='submit' value='Drop' onclick=\"if(ays()){sht('drop')}else{return false}\">
 <input type='submit' value='Truncate' onclick=\"if(ays()){sht('trunc')}else{return false}\"></div>";
   $sqldr.=$abtn."<input type='hidden' name='dosht' value=''>";
 }

 $sqldr.="<div class='table-responsive' style='margin-top:1%;'><table class='table table-striped table-inverse'>";
 $headers="<thead><tr>";
 if ($is_sht) $headers.="<td><input type='checkbox' name='cball' value='' onclick='chkall(this)'></td>";
 for($i=0;$i<$fields_num;$i++){
    if ($is_sht && $i>0) break;
    $meta=mysqli_fetch_field($sth);
    $headers.="<th><div>".hs($meta->name)."</div></th>";
 }
 if ($is_shd) $headers.="<th>show triggers</th>";
 if ($is_sht) $headers.="<th>Engine</th><th>Rows</th><th>Data Size</th><th>Show Table</th><th>Structure</th><th>drop</th><th>truncate</th>";
 $headers.="</tr></thead>";
 $sqldr.=$headers;
 $swapper=false;
 while($row=mysqli_fetch_row($sth)){
   $sqldr.="<tbody><tr class='".$rc[$swp=!$swp]."' onclick='tc(this)'>";
   $v=$row[0];
   if ($is_sht){
     $vq='`'.$v.'`';
     $url='?'.$xurl."&db=$dbn&t=".b64u($v);
     $sqldr.="<td><input type='checkbox' name='cb[]' value=\"".hs($vq)."\"></td>"
     ."<td><a href=\"$url&q=".b64u("select * from $vq")."\">".hs($v)."</a></td>"
     ."<td>".hs($row[1])."</td>"
     ."<td align='right'>".hs($row[4])."</td>"
     ."<td align='right'>".hs($row[6])."</td>"
     ."<td><a href=\"$url&q=".b64u("show create table $vq")."\">Show Query</a></td>"
     ."<td><a href=\"$url&q=".b64u("explain $vq")."\">Structure</a></td>"
     ."<td><a href=\"$url&q=".b64u("drop table $vq")."\" onclick='return ays()'>Drop</a></td>"
     ."<td><a href=\"$url&q=".b64u("truncate table $vq")."\" onclick='return ays()'>Truncate</a></td>";
   }elseif ($is_shd){
     $url="?db=".ue($v);
     $sqldr.="<td><a href=\"$url&q=".b64u("SHOW TABLE STATUS")."\">".hs($v)."</a></td>"
     ."<td><a href=\"$url&q=".b64u("show triggers")."\">Trigger/s</a></td>";
   }else{
     for($i=0;$i<$fields_num;$i++){
      $v=$row[$i];
      if (is_null($v)) $v="<i>NULL</i>";
      elseif (preg_match('/[\x00-\x09\x0B\x0C\x0E-\x1F]+/',$v)){#all chars <32, except \n\r(0D0A)
       $vl=strlen($v);$pf='';
       if ($vl>16 && $fields_num>1){#show full dump if just one field
         $v=substr($v, 0, 16);$pf='...';
       }
       $v='BINARY: '.chunk_split(strtoupper(bin2hex($v)),2,' ').$pf;
      }else $v=hs($v);
      if ($is_show_crt) $v="<pre>$v</pre>";
      $sqldr.="<td><div>$v".(!strlen($v)?"<br>":'')."</div></td>";
     }
   }
   $sqldr.="</tr></tbody>";
 }
 $sqldr.="</table></div>\n".$abtn;
}

function print_header(){
 global $err_msg,$VERSION,$DB,$dbh,$self,$is_sht,$SHOW_TABLE;
 $dbn=$DB['db'];
?>
<!DOCTYPE html>
<html>
<head><title>Walchand College of Engineering, Sangli</title>
<meta charset="utf-8">
<link rel='stylesheet' href='css/main.css' type='text/css'/>
<link rel='stylesheet' href='css/bootstrap.min.css' type='text/css'/>
<link rel='stylesheet' href='css/bootstrap-theme.min.css' type='text/css'/>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.min.js"></script>						
		
<script type="text/javascript">

var LSK='pma_',LSKX=LSK+'max',LSKM=LSK+'min',qcur=0,LSMAX=32;

function $(i){return document.getElementById(i)}
function frefresh(){
 var F=document.DF;
 F.method='get';
 F.refresh.value="1";
 F.GoSQL.click();
}
function go(p,sql){
 var F=document.DF;
 F.p.value=p;
 if(sql)F.q.value=sql;
 F.GoSQL.click();
}
function ays(){
 return confirm('Are you sure to continue?');
}
function chksql(){
 var F=document.DF,v=F.qraw.value;
 if(/^\s*(?:delete|drop|truncate|alter)/.test(v)) if(!ays())return false;
 if(lschk(1)){
  var lsm=lsmax()+1,ls=localStorage;
  ls[LSK+lsm]=v;
  ls[LSKX]=lsm;
  if(!ls[LSKM])ls[LSKM]=1;
  var lsmin=parseInt(ls[LSKM]);
  if((lsm-lsmin+1)>LSMAX){
   lsclean(lsmin,lsm-LSMAX);
  }
 }
 return true;
}
function tc(tr){
 if (tr.className=='s'){
  tr.className=tr.classNameX;
 }else{
  tr.classNameX=tr.className;
  tr.className='s';
 }
}
function lschk(skip){
 if (!localStorage || !skip && !localStorage[LSKX]) return false;
 return true;
}
function lsmax(){
 var ls=localStorage;
 if(!lschk() || !ls[LSKX])return 0;
 return parseInt(ls[LSKX]);
}
function lsclean(from,to){
 ls=localStorage;
 for(var i=from;i<=to;i++){
  delete ls[LSK+i];ls[LSKM]=i+1;
 }
}
function q_prev(){
 var ls=localStorage;
 if(!lschk())return;
 qcur--;
 var x=parseInt(ls[LSKM]);
 if(qcur<x)qcur=x;
 $('qraw').value=ls[LSK+qcur];
}
function q_next(){
 var ls=localStorage;
 if(!lschk())return;
 qcur++;
 var x=parseInt(ls[LSKX]);
 if(qcur>x)qcur=x;
 $('qraw').value=ls[LSK+qcur];
}
function after_load(){
 var F=document.DF;
 var p=F['v[pwd]'];
 if (p) p.focus();
 qcur=lsmax();

 F.addEventListener('submit',function(e){
  if(!F.qraw)return;
  if(!chksql()){e.preventDefault();return}
  $('q').value=btoa(encodeURIComponent($('qraw').value).replace(/%([0-9A-F]{2})/g,function(m,p){return String.fromCharCode('0x'+p)}));
 });
 var res=$('res');
 if(res)res.addEventListener('dblclick',function(e){
  if(!$('is_sm').checked)return;
  var el=e.target;
  if(el.tagName!='TD')el=el.parentNode;
  if(el.tagName!='TD')return;
  if(el.className.match(/\b\lg\b/))el.className=el.className.replace(/\blg\b/,' ');
  else el.className+=' lg';
 });
}
function logoff(){
 if(lschk()){
  var ls=localStorage;
  var from=parseInt(ls[LSKM]),to=parseInt(ls[LSKX]);
  for(var i=from;i<=to;i++){
   delete ls[LSK+i];
  }
  delete ls[LSKM];delete ls[LSKX];
 }
}
function cfg_toggle(){
 var e=$('cfg-adv');
 e.style.display=e.style.display=='none'?'':'none';
}
function qtpl(s){
 $('qraw').value=s.replace(/%T/g,'`<?php echo $_REQUEST['t']?b64d($_REQUEST['t']):'tablename'?>`');
}

<?php if($is_sht){?>
function chkall(cab){
 var e=document.DF.elements;
 if (e!=null){
  var cl=e.length;
  for (i=0;i<cl;i++){var m=e[i];if(m.checked!=null && m.type=="checkbox"){m.checked=cab.checked}}
 }
}
function sht(f){
 document.DF.dosht.value=f;
}
<?php }?>
</script>

	</head>
	<body onload="after_load()">
		<form method="post" name="DF" id="DF" action="<?php eo($self)?>" enctype="multipart/form-data">
			<input type="hidden" name="refresh" value="">
			<input type="hidden" name="p" value="">

			 <nav class="navbar navbar-inverse">
				<div class="container-fluid">
					<div class="navbar-header">
					<a class="navbar-brand" href="index.php"><b>Welcome to WCE</a>
					</div>
						<ul class="nav navbar-nav">
						<?php if ($_SESSION['is_logged'] && $dbh){ ?>
						<li><a href="?<?php eo('q='.b64u("show databases"))?>">Databases</a><li>
						<li>
							<select style="margin-top:6%;" id="selectdb" name="db" onChange="frefresh()">
								<option value='*'> - select/refresh -</option>
								<option value=''> - show all -</option>
								<?php echo get_db_select($dbn);
									//$url="?db=".ue($v);    															
									//header("location : \"$url&q=".b64u("SHOW TABLE STATUS"));
								?>
							</select>
						<li>						
						<?php if($dbn){ $z="<li><a id='cssbuttongo' href='".hs($self."?db=".ue($dbn)); ?>
						<?php echo $z.'&q='.b64u($SHOW_TABLE)?>'>show tables</a></li>
						
						<?php }
						?>

						</ul>
						<ul class="nav navbar-nav navbar-right">			
						<li><?php if ($_SESSION['is_logged']){?><a href="?logoff=1" onclick="logoff()">Logout</a> <?php }}?></li>
						</ul>
				</div>
			 </nav> 
		<?php
		}

		function print_screen(){
		 global $out_message, $SQLq, $err_msg, $reccount, $time_all, $sqldr, $page, $MAX_ROWS_PER_PAGE, $is_limited_sql, $last_count, $is_sm;

		 $nav='';
		 if ($is_limited_sql && ($page || $reccount>=$MAX_ROWS_PER_PAGE) ){
		  $nav="<div class='nav'>".get_nav($page, 10000, $MAX_ROWS_PER_PAGE, "javascript:go(%p%)")."</div>";
		 }

		 print_header();
		?>
			<div class="container-fluid">
				<div class="row">
					<?php echo "<div class='col-md-7'>" .$nav.$sqldr.$nav . "</div>"; ?>
					<div class="col-md-5" style="padding-top:5%;">							
						<label for="qraw">SQL-query (or multiple queries separated by ";"):</label>&nbsp;<button type="button" class="qnav" onclick="q_prev()">&lt;</button><button type="button" class="qnav" onclick="q_next()">&gt;</button><br>
						<textarea id="qraw" class="form-control" rows="5" placeholder="Write Your Query..."></textarea><br>
						<input type="hidden" name="q" id="q" value="<?php b64e($SQLq);?>">
						<input type="submit" class="btn btn-success" name="GoSQL" value="Go">
						<input type="button" class="btn btn-danger" name="Clear" value="Clear" onclick="$('qraw').value='';">
						<?php if(!empty($_REQUEST['db'])){ ?>
						<div style="float:right">
							<input type="button" class="btn btn-success" value="Select" class="sbtn" onclick="qtpl('SELECT *\nFROM %T\nWHERE 1')">
							<input type="button" class="btn btn-info" value="Insert" class="sbtn" onclick="qtpl('INSERT INTO %T (`column`, `column`)\nVALUES (\'value\', \'value\')')">
							<input type="button" class="btn btn-warning" value="Update" class="sbtn" onclick="qtpl('UPDATE %T\nSET `column`=\'value\'\nWHERE 1=0')">
							<input type="button" class="btn btn-danger" value="Delete" class="sbtn" onclick="qtpl('DELETE FROM %T\nWHERE 1=0')">
						</div>
						<br class="clear">
						<?php } ?>							
					</div>					
				</row>
			</div>
		<?php
		 print_footer();
		}

		function print_footer(){
		?>
		</form>

		<div class="footer-bottom navbar-fixed-bottom">
			<div class="container">
				<div class="row">
					<div class="col-md-12 widget">&copy; 2017-2018 <a href="http://www.walchandsangli.ac.in" target="_blank">WCE, Sangli</a> 
				    </div>
				</div>
			</div>
		</div>
	</body>
</html>
<?php
}

function print_login(){
 print_header();
?>
<center>
<h3>Access protected by password</h3>
	<div style="width:400px;border:1px solid #999999;background-color:#eeeeee">
		<label>Password: <input type="password" name="pwd" value=""></label>
		<input type="hidden" name="login" value="1">
		<input type="submit" value=" Login ">
	</div>
</center>
<?php
 print_footer();
}


function print_cfg(){
 global $DB,$err_msg,$self;
 print_header();
?>

<div class="col-md-3 col-md-offset-4" style="border: 2px solid aqua; border-radius: 25px;padding:2%;padding-top:0;margin-top:5%; background-color: #333;">
      <form>
        <h2 style="color:#F4A460;">Please sign in</h2>
        <label for="inputEmail" class="sr-only">Username</label>
        <input style="margin-bottom:2%;" type="text" name="v[user]" value="" class="form-control" placeholder="Username" autofocus required />
        <label for="inputPassword" class="sr-only">Password</label>
        <input style="margin-bottom:2%;" type="password" name="v[pwd]" value="" class="form-control" placeholder="Password">
		<div style="text-align:right"><a href="#" class="ajax" onclick="cfg_toggle()">advanced settings</a></div>
		<div id="cfg-adv" style="display:none;">
			<label class="sr-only">DB name</label><input style="margin-bottom:2%;" class="form-control" type="text" name="v[db]" value="<?php eo($DB['db'])?>" placeholder="Database">
			<label class="sr-only">HostName</label> <input style="margin-bottom:2%;" class="form-control" type="text" name="v[host]" value="<?php eo($DB['host'])?>" placeholder="Hostname">
			<label class="sr-only">Port</label><input style="margin-bottom:2%;" class="form-control" type="text" name="v[port]" value="<?php eo($DB['port'])?>" placeholder="Port No">
		</div>
		<input type="hidden" name="savecfg" value="1">
        <button class="btn btn-lg btn-primary btn-block" type="submit" value="Apply">Sign in</button>
      </form>
    </div> 
<?php
 print_footer();
}


//* utilities
function db_connect($nodie=0){
 global $dbh,$DB,$err_msg;

 if ($DB['port']) {
    $dbh=mysqli_connect($DB['host'],$DB['user'],$DB['pwd'],'',(int)$DB['port']);
 } else {
    $dbh=mysqli_connect($DB['host'],$DB['user'],$DB['pwd']);
 }
 if (!$dbh) {
    $err_msg='Cannot connect to the database because: '.mysqli_connect_error();
    if (!$nodie) die($err_msg);
 }

 if ($dbh && $DB['db']) {
  $res=mysqli_select_db($dbh, $DB['db']);
  if (!$res) {
     $err_msg='Cannot select db because: '.mysqli_error($dbh);
     if (!$nodie) die($err_msg);
  }else{
     if ($DB['chset']) db_query("SET NAMES ".$DB['chset']);
  }
 }
 return $dbh;
}

function db_checkconnect($dbh1=NULL, $skiperr=0){
 global $dbh;
 if (!$dbh1) $dbh1=&$dbh;
 if (!$dbh1 or !mysqli_ping($dbh1)) {
    db_connect($skiperr);
    $dbh1=&$dbh;
 }
 return $dbh1;
}

function db_disconnect(){
 global $dbh;
 mysqli_close($dbh);
}

function dbq($s){
 global $dbh;
 if (is_null($s)) return "NULL";
 return "'".mysqli_real_escape_string($dbh,$s)."'";
}

function db_query($sql, $dbh1=NULL, $skiperr=0, $resmod=MYSQLI_STORE_RESULT){
 $dbh1=db_checkconnect($dbh1, $skiperr);
 $sth=mysqli_query($dbh1, $sql, $resmod);
 if (!$sth && $skiperr) return;
 if (!$sth) die("Error in DB operation:<br>\n".mysqli_error($dbh1)."<br>\n$sql");
 return $sth;
}

function db_array($sql, $dbh1=NULL, $skiperr=0, $isnum=0){#array of rows
 $sth=db_query($sql, $dbh1, $skiperr, MYSQLI_USE_RESULT);
 if (!$sth) return;
 $res=array();
 if ($isnum){
   while($row=mysqli_fetch_row($sth)) $res[]=$row;
 }else{
   while($row=mysqli_fetch_assoc($sth)) $res[]=$row;
 }
 mysqli_free_result($sth);
 return $res;
}

function db_row($sql){
 $sth=db_query($sql);
 return mysqli_fetch_assoc($sth);
}

function db_value($sql,$dbh1=NULL,$skiperr=0){
 $sth=db_query($sql,$dbh1,$skiperr);
 if (!$sth) return;
 $row=mysqli_fetch_row($sth);
 return $row[0];
}

function get_identity($dbh1=NULL){
 $dbh1=db_checkconnect($dbh1);
 return mysqli_insert_id($dbh1);
}

function get_db_select($sel=''){
 global $DB,$SHOW_DB;
 if (is_array($_SESSION['sql_sd']) && $_REQUEST['db']!='*'){//check cache
    $arr=$_SESSION['sql_sd'];
 }else{
   $arr=db_array($SHOW_DB,NULL,1);
   if (!is_array($arr)){
      $arr=array( 0 => array('Database' => $DB['db']) );
    }
   $_SESSION['sql_sd']=$arr;
 }
 return @sel($arr,'Database',$sel);
}

function chset_select($sel=''){
 global $DBDEF;
 $result='';
 if ($_SESSION['sql_chset']){
    $arr=$_SESSION['sql_chset'];
 }else{
   $arr=db_array("show character set",NULL,1);
   if (!is_array($arr)) $arr=array(array('Charset'=>$DBDEF['chset']));
   $_SESSION['sql_chset']=$arr;
 }

 return @sel($arr,'Charset',$sel);
}

function sel($arr,$n,$sel=''){
 foreach($arr as $a){
#   echo $a[0];
   $b=$a[$n];
   $res.="<option value='".hs($b)."' ".($sel && $sel==$b?'selected':'').">".hs($b)."</option>";
 }
 return $res;
}

function microtime_float(){
 list($usec,$sec)=explode(" ",microtime());
 return ((float)$usec+(float)$sec);
}

function get_nav($pg, $all, $PP, $ptpl, $show_all=''){
  $n='&nbsp;';
  $sep=" $n|$n\n";
  if (!$PP) $PP=10;
  $allp=floor($all/$PP+0.999999);

  $pname='';
  $res='';
  $w=array('Less','More','Back','Next','First','Total');

  $sp=$pg-2;
  if($sp<0) $sp=0;
  if($allp-$sp<5 && $allp>=5) $sp=$allp-5;

  $res="";

  if($sp>0){
    $pname=pen($sp-1,$ptpl);
    $res.="<a href='$pname'>$w[0]</a>";
    $res.=$sep;
  }
  for($p_p=$sp;$p_p<$allp && $p_p<$sp+5;$p_p++){
     $first_s=$p_p*$PP+1;
     $last_s=($p_p+1)*$PP;
     $pname=pen($p_p,$ptpl);
     if($last_s>$all){
       $last_s=$all;
     }
     if($p_p==$pg){
        $res.="<b>$first_s..$last_s</b>";
     }else{
        $res.="<a href='$pname'>$first_s..$last_s</a>";
     }
     if($p_p+1<$allp) $res.=$sep;
  }
  if($sp+5<$allp){
    $pname=pen($sp+5,$ptpl);
    $res.="<a href='$pname'>$w[1]</a>";
  }
  $res.=" <br>\n";

  if($pg>0){
    $pname=pen($pg-1,$ptpl);
    $res.="<a href='$pname'>$w[2]</a> $n|$n ";
    $pname=pen(0,$ptpl);
    $res.="<a href='$pname'>$w[4]</a>";
  }
  if($pg>0 && $pg+1<$allp) $res.=$sep;
  if($pg+1<$allp){
    $pname=pen($pg+1,$ptpl);
    $res.="<a href='$pname'>$w[3]</a>";
  }
  if ($show_all) $res.=" <b>($w[5] - $all)</b> ";

  return $res;
}

function pen($p,$np=''){
 return str_replace('%p%',$p, $np);
}

function killmq($value){
 return is_array($value)?array_map('killmq',$value):stripslashes($value);
}

function savecfg(){
 $v=$_REQUEST['v'];
 $_SESSION['DB']=$v;
 unset($_SESSION['sql_sd']);

 if ($_REQUEST['rmb']){
    $tm=time()+60*60*24*30;
    newcookie("conn[db]",  $v['db'],$tm);
    newcookie("conn[user]",$v['user'],$tm);
    newcookie("conn[pwd]", $v['pwd'],$tm);
    newcookie("conn[host]",$v['host'],$tm);
    newcookie("conn[port]",$v['port'],$tm);
    newcookie("conn[chset]",$v['chset'],$tm);
 }else{
    newcookie("conn[db]",  FALSE,-1);
    newcookie("conn[user]",FALSE,-1);
    newcookie("conn[pwd]", FALSE,-1);
    newcookie("conn[host]",FALSE,-1);
    newcookie("conn[port]",FALSE,-1);
    newcookie("conn[chset]",FALSE,-1);
 }
}

function newcookie($n,$v,$e){$x;return setcookie($n,$v,$e,$x,$x,!!$x,!$x);}

function loadcfg(){
 global $DBDEF;

 if( isset($_COOKIE['conn']) ){
    $_SESSION['DB']=$_COOKIE['conn'];
 }else{
    $_SESSION['DB']=$DBDEF;
 }
 if (!strlen($_SESSION['DB']['chset'])) $_SESSION['DB']['chset']=$DBDEF['chset'];
}

function loadsess(){
 global $DB, $is_sm;

 $DB=$_SESSION['DB'];

 $rdb=$_REQUEST['db'];
 if ($rdb=='*') $rdb='';
 if ($rdb) {
    $DB['db']=$rdb;
 }
 if($_REQUEST['GoSQL']) $_SESSION['is_sm']=$_REQUEST['is_sm']+0;
 $is_sm=$_SESSION['is_sm']+0;
}

function do_export_table($t='',$tt='',$MAXI=838860){
 global $D,$ex_issrv;
 @set_time_limit(600);

 if($_REQUEST['s']){
  $sth=db_query("show create table `$t`");
  $row=mysqli_fetch_row($sth);
  $ct=preg_replace("/\n\r|\r\n|\n|\r/",$D,$row[1]);
  ex_w("DROP TABLE IF EXISTS `$t`;$D$ct;$D$D");
 }

 if ($_REQUEST['d']&&$tt!='VIEW'){//no dump for views
  $exsql='';
  ex_w("/*!40000 ALTER TABLE `$t` DISABLE KEYS */;$D");
  $sth=db_query("select * from `$t`",NULL,0,MYSQLI_USE_RESULT);
  while($row=mysqli_fetch_row($sth)){
    $values='';
    foreach($row as $v) $values.=(($values)?',':'').dbq($v);
    $exsql.=(($exsql)?',':'')."(".$values.")";
    if (strlen($exsql)>$MAXI) {
       ex_w("INSERT INTO `$t` VALUES $exsql;$D");$exsql='';
    }
  }
  mysqli_free_result($sth);
  if ($exsql) ex_w("INSERT INTO `$t` VALUES $exsql;$D");
  ex_w("/*!40000 ALTER TABLE `$t` ENABLE KEYS */;$D$D");
 }
 if (!$ex_issrv) flush();
}

function ex_hdr($ct,$fn){
 global $ex_issrv;
 if ($ex_issrv) return;
 header("Content-type: $ct");
 header("Content-Disposition: attachment; filename=\"$fn\"");
}
function ex_start($ext){
 global $ex_isgz,$ex_gz,$ex_tmpf,$ex_issrv,$ex_f,$DUMP_FILE;
 if ($ex_isgz){
    $ex_tmpf=($ex_issrv?export_fname($DUMP_FILE,true).$ext:tmp_name()).'.gz';
    if (!($ex_gz=gzopen($ex_tmpf,'wb9'))) die("Error trying to create gz tmp file");
 }else{
    if ($ex_issrv) {
      if (!($ex_f=fopen(export_fname($DUMP_FILE,true).$ext,'wb'))) die("Error trying to create dump file");
    }
 }
}
function ex_w($s){
 global $ex_isgz,$ex_gz,$ex_issrv,$ex_f;
 if ($ex_isgz){
    gzwrite($ex_gz,$s,strlen($s));
 }else{
    if ($ex_issrv){
        fwrite($ex_f,$s);
    }else{
        echo $s;
    }
 }
}
function ex_end(){
 global $ex_isgz,$ex_gz,$ex_tmpf,$ex_issrv,$ex_f;
 if ($ex_isgz){
    gzclose($ex_gz);
    if (!$ex_issrv){
      readfile($ex_tmpf);
      unlink($ex_tmpf);
    }
 }else{
    if ($ex_issrv) fclose($ex_f);
 }
}

function print_import(){
 global $self,$DB,$DUMP_FILE;
 print_header();
?>
<center>
<h3>Import DB</h3>
<div class="frm">
<div><label><input type="radio" name="it" value="" checked> import by uploading <b>.sql</b> or <b>.gz</b> file:</label>
 <input type="file" name="file1" value="" size=40><br>
</div>
<div><label><input type="radio" name="it" value="sql"> import from file on server:<br>
 <?php eo($DUMP_FILE.'.sql')?></label></div>
<div><label><input type="radio" name="it" value="gz"> import from file on server:<br>
 <?php eo($DUMP_FILE.'.sql.gz')?></label></div>
<input type="hidden" name="doim" value="1">
<input type="submit" value=" Import " onclick="return ays()"><input type="button" value="Cancel" onclick="window.location='<?php eo($self.'?db='.ue($DB['db']))?>'">
</div>
<br><br><br>
</center>
<?php
 print_footer();
 exit;
}

function do_import(){
 global $err_msg,$out_message,$dbh,$SHOW_TABLE,$DUMP_FILE;
 $err_msg='';
 $it=$_REQUEST['it'];

 if (!$it){
    $F=$_FILES['file1'];
    if ($F && $F['name']){
       $filename=$F['tmp_name'];
       $pi=pathinfo($F['name']);
       $ext=$pi['extension'];
    }
 }else{
    $ext=($it=='gz'?'sql.gz':'sql');
    $filename=$DUMP_FILE.'.'.$ext;
 }

 if ($filename && file_exists($filename)){
  if ($ext!='sql'){
     $tmpf=tmp_name();
     if (($gz=gzopen($filename,'rb')) && ($tf=fopen($tmpf,'wb'))){
        while(!gzeof($gz)){
           if (fwrite($tf,gzread($gz,8192),8192)===FALSE){$err_msg='Error during gz file extraction to tmp file';break;}
        }
        gzclose($gz);fclose($tf);$filename=$tmpf;
     }else{$err_msg='Error opening gz file';}
  }
  if (!$err_msg){
   if (!do_multi_sql('', $filename)){
      $err_msg='Import Error: '.mysqli_error($dbh);
   }else{
      $out_message='Import done successfully';
      do_sql($SHOW_TABLE);
      return;
  }}

 }else{
    $err_msg="Error: Please select file first";
 }
 print_import();
 exit;
}

function do_multi_sql($insql,$fname=''){
 @set_time_limit(600);

 $sql='';
 $ochar='';
 $is_cmt='';
 $GLOBALS['insql_done']=0;
 while ($str=get_next_chunk($insql,$fname)){
    $opos=-strlen($ochar);
    $cur_pos=0;
    $i=strlen($str);
    while ($i--){
       if ($ochar){
          list($clchar, $clpos)=get_close_char($str, $opos+strlen($ochar), $ochar);
          if ( $clchar ) {
             if ($ochar=='--' || $ochar=='#' || $is_cmt ){
                $sql.=substr($str, $cur_pos, $opos-$cur_pos );
             }else{
                $sql.=substr($str, $cur_pos, $clpos+strlen($clchar)-$cur_pos );
             }
             $cur_pos=$clpos+strlen($clchar);
             $ochar='';
             $opos=0;
          }else{
             $sql.=substr($str, $cur_pos);
             break;
          }
       }else{
          list($ochar, $opos)=get_open_char($str, $cur_pos);
          if ($ochar==';'){
             $sql.=substr($str, $cur_pos, $opos-$cur_pos+1);
             if (!do_one_sql($sql)) return 0;
             $sql='';
             $cur_pos=$opos+strlen($ochar);
             $ochar='';
             $opos=0;
          }elseif(!$ochar) {
             $sql.=substr($str, $cur_pos);
             break;
          }else{
             $is_cmt=0;if ($ochar=='/*' && substr($str, $opos, 3)!='/*!') $is_cmt=1;
          }
       }
    }
 }

 if ($sql){
    if (!do_one_sql($sql)) return 0;
    $sql='';
 }
 return 1;
}

function get_next_chunk($insql, $fname){
 global $LFILE, $insql_done;
 if ($insql) {
    if ($insql_done){
       return '';
    }else{
       $insql_done=1;
       return $insql;
    }
 }
 if (!$fname) return '';
 if (!$LFILE){
    $LFILE=fopen($fname,"r+b") or die("Can't open [$fname] file $!");
 }
 return fread($LFILE, 64*1024);
}

function get_open_char($str, $pos){
 if ( preg_match("/(\/\*|^--|(?<=\s)--|#|'|\"|;)/", $str, $m, PREG_OFFSET_CAPTURE, $pos) ) {
    $ochar=$m[1][0];
    $opos=$m[1][1];
 }
 return array($ochar, $opos);
}

function get_close_char($str, $pos, $ochar){
 $aCLOSE=array(
   '\'' => '(?<!\\\\)\'|(\\\\+)\'',
   '"' => '(?<!\\\\)"',
   '/*' => '\*\/',
   '#' => '[\r\n]+',
   '--' => '[\r\n]+',
 );
 if ( $aCLOSE[$ochar] && preg_match("/(".$aCLOSE[$ochar].")/", $str, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
    $clchar=$m[1][0];
    $clpos=$m[1][1];
    $sl=strlen($m[2][0]);
    if ($ochar=="'" && $sl){
       if ($sl % 2){ #don't count as CLOSE char if number of slashes before ' ODD
          list($clchar, $clpos)=get_close_char($str, $clpos+strlen($clchar), $ochar);
       }else{
          $clpos+=strlen($clchar)-1;$clchar="'";#correction
       }
    }
 }
 return array($clchar, $clpos);
}

function do_one_sql($sql){
 global $last_sth,$last_sql,$MAX_ROWS_PER_PAGE,$page,$is_limited_sql,$last_count,$IS_COUNT;
 $sql=trim($sql);
 $sql=preg_replace("/;$/","",$sql);
 if ($sql){
    $last_sql=$sql;$is_limited_sql=0;
    $last_count=NULL;
    if (preg_match("/^select/i",$sql) && !preg_match("/limit +\d+/i", $sql)){
       if ($IS_COUNT){
          $sql1='select count(*) from ('.$sql.') ___count_table';
          $last_count=db_value($sql1,NULL,'noerr');
       }
       $offset=$page*$MAX_ROWS_PER_PAGE;
       $sql.=" LIMIT $offset,$MAX_ROWS_PER_PAGE";
       $is_limited_sql=1;
    }
    $last_sth=db_query($sql,0,'noerr');
    return $last_sth;
 }
 return 1;
}

function do_sht(){
 global $SHOW_TABLE;
 $cb=$_REQUEST['cb'];
 if (!is_array($cb)) $cb=array();
 $sql='';
 switch ($_REQUEST['dosht']){
  case 'exp':$_REQUEST['t']=join(",",$cb);print_export();exit;
  case 'drop':$sq='DROP TABLE';break;
  case 'trunc':$sq='TRUNCATE TABLE';break;
  case 'opt':$sq='OPTIMIZE TABLE';break;
 }
 if ($sq){
  foreach($cb as $v){
   $sql.=$sq." $v;\n";
  }
 }
 if ($sql) do_sql($sql);
 do_sql($SHOW_TABLE);
}

function to_csv_row($adata){
 global $D;
 $r='';
 foreach ($adata as $a){
   $r.=(($r)?",":"").qstr($a);
 }
 return $r.$D;
}
function qstr($s){
 $s=nl2br($s);
 $s=str_replace('"','""',$s);
 return '"'.$s.'"';
}

function get_rand_str($len){
 $result='';
 $chars=preg_split('//','ABCDEFabcdef0123456789');
 for($i=0;$i<$len;$i++) $result.=$chars[rand(0,count($chars)-1)];
 return $result;
}

function check_xss(){
 global $self;
 if ($_SESSION['XSS']!=trim($_REQUEST['XSS'])){
    unset($_SESSION['XSS']);
    header("location: $self");
    exit;
 }
}

function rw($s){
 echo hs(var_dump($s))."<br>\n";
}

function tmp_name() {
  if ( function_exists('sys_get_temp_dir')) return tempnam(sys_get_temp_dir(),'pma');

  if( !($temp=getenv('TMP')) )
    if( !($temp=getenv('TEMP')) )
      if( !($temp=getenv('TMPDIR')) ) {
        $temp=tempnam(__FILE__,'');
        if (file_exists($temp)) {
          unlink($temp);
          $temp=dirname($temp);
        }
      }
  return $temp ? tempnam($temp,'pma') : null;
}

function hs($s){
  return htmlspecialchars($s, ENT_COMPAT|ENT_HTML401,'UTF-8');
}
function eo($s){
  echo hs($s);
}
function ue($s){
  return urlencode($s);
}

function b64e($s){
  return base64_encode($s);
}
function b64u($s){
  return ue(base64_encode($s));
}
function b64d($s){
  return base64_decode($s);
}
?>
