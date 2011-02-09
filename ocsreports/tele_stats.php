<?php 
//====================================================================================
// OCS INVENTORY REPORTS
// Copyleft Pierre LEMMET 2005
// Web: http://ocsinventory.sourceforge.net
//
// This code is open source and may be copied and modified as long as the source
// code is always made freely available.
// Please refer to the General Public Licence http://www.gnu.org/ or Licence.txt
//====================================================================================
//Modified on $Date: 2008-06-18 13:26:31 $$Author: airoine $($Revision: 1.15 $)

require('fichierConf.class.php');
require_once("preferences.php");
include('security.php');
if($_SESSION["lvluser"]==SADMIN){
	if( isset($_GET["delsucc"]) ) {		
		$resSupp = mysql_query("DELETE FROM devices WHERE name='DOWNLOAD' AND tvalue LIKE 'SUCCESS%' AND
		ivalue IN (SELECT id FROM download_enable WHERE fileid='".$_GET["stat"]."') AND hardware_id NOT IN (SELECT id FROM hardware WHERE deviceid='_SYSTEMGROUP_')", $_SESSION["writeServer"]);
	}
	else if( isset($_GET["deltout"]) ) {		
		$resSupp = mysql_query("DELETE FROM devices WHERE name='DOWNLOAD' AND tvalue IS NOT NULL AND  
		ivalue IN (SELECT id FROM download_enable WHERE fileid='".$_GET["stat"]."') AND hardware_id NOT IN (SELECT id FROM hardware WHERE deviceid='_SYSTEMGROUP_')", $_SESSION["writeServer"]);
	}
	else if( isset($_GET["delnotif"]) ) {		
		$resSupp = mysql_query("DELETE FROM devices WHERE name='DOWNLOAD' AND tvalue IS NULL AND 
		ivalue IN (SELECT id FROM download_enable WHERE fileid='".$_GET["stat"]."') AND hardware_id NOT IN (SELECT id FROM hardware WHERE deviceid='_SYSTEMGROUP_')", $_SESSION["writeServer"]);
	}
}
if ($_POST['selOpt'] == "GROUP" or $_GET['option']=="GROUP"){
$sql_group="select hardware_id from groups_cache where group_id='".$_GET['group']."'";
$res_group = mysql_query($sql_group, $_SESSION["readServer"]) or die(mysql_error($_SESSION["readServer"]));
$machines_group="(";
	while ($item_group = mysql_fetch_object($res_group)){
		$machines_group.= $item_group->hardware_id.",";	
	}
	$machines_group=" IN ".substr($machines_group,0,-1).")";		
}
if ($_SESSION["mesmachines"] != ""){
	$sql_mesMachines="select hardware_id from accountinfo a where ".$_SESSION["mesmachines"];
	$res_mesMachines = mysql_query($sql_mesMachines, $_SESSION["readServer"]) or die(mysql_error($_SESSION["readServer"]));
	$mesmachines="(";
	while ($item_mesMachines = mysql_fetch_object($res_mesMachines)){
		$mesmachines.= $item_mesMachines->hardware_id.",";	
	}
	$mesmachines=" IN ".substr($mesmachines,0,-1).")";	
	
}
$sqlStats="SELECT COUNT(id) as 'nb', tvalue as 'txt' 
			FROM devices d, download_enable e 
			WHERE e.fileid='".$_GET["stat"]."'
 				AND e.id=d.ivalue 
				AND name='DOWNLOAD' 
				AND hardware_id NOT IN (SELECT id FROM hardware WHERE deviceid='_SYSTEMGROUP_')";
if (isset($machines_group))
	$sqlStats.= " AND hardware_id".$machines_group;
if (isset($mesmachines))				
	$sqlStats.= " AND hardware_id".$mesmachines;	
$sqlStats.= " GROUP BY tvalue ORDER BY nb DESC";
$resStats = mysql_query($sqlStats, $_SESSION["readServer"]);
 	$tot = 0;
	$quartiers = array();
	$coul = array( 0x0091C3, 0xFFCB03  ,0x33CCCC, 0xFF9900,  0x969696,  0x339966, 0xFF99CC, 0x99CC00);
	$coulHtml = array( "0091C3", "FFCB03"  ,"33CCCC", "FF9900",  "969696",  "339966", "FF99CC", "99CC00");
	$i = 0;
	while( $valStats = mysql_fetch_array( $resStats ) ) {
		$tot += $valStats["nb"];
		if( $valStats["txt"] =="" )
			$valStats["txt"] = $l->g(482);
		$quartiers[] = array( $valStats["nb"], $coul[ $i ], $valStats["txt"]." (".$valStats["nb"].")" );
		$legende[] = array( "color"=>$coulHtml[ $i ], "name"=>$valStats["txt"], "count"=>$valStats["nb"] );
		$i++;
		if( $i > sizeof( $coul ) )
			$i=0;
	}

	$sort = array();
	$index = 0;
	for( $count=0; $count < (sizeof( $quartiers )); $count++ ) {
		if( $count%2==0) {
			$sort[ $count ] = $quartiers[ $index ];
			//echo "sort[ $count ] = quartiers[ $index ];<br>";
			$index++;
		}
		else {
			$sort[ $count ] = $quartiers[ sizeof( $quartiers ) - $index ];			
		}		
	}

if( @mysql_num_rows( $resStats ) == 0 ) {
	echo "<center>".$l->g(526)."</center>";
	die();	
}

if( ! function_exists( "imagefontwidth") ) {
	echo "<br><center><font color=red><b>ERROR: GD for PHP is not properly installed.<br>Try uncommenting \";extension=php_gd2.dll\" (windows) by removing the semicolon in file php.ini, or try installing the php4-gd package.</b></font></center>";
	die();
}
else if( isset($_GET["generatePic"]) ) {	
	camembert($sort);
}
else {
	?>
	<html>
	<head>
	<TITLE>OCS Inventory Stats</TITLE>
	<META HTTP-EQUIV="Pragma" CONTENT="no-cache">
	<META HTTP-EQUIV="Expires" CONTENT="-1">
	<LINK REL='StyleSheet' TYPE='text/css' HREF='css/ocsreports.css'>
	</HEAD>
	<?php 
	$sqlStats="SELECT COUNT(DISTINCT HARDWARE_ID) as 'nb' FROM devices d, download_enable e WHERE e.fileid='".$_GET["stat"]."'
	AND e.id=d.ivalue AND name='DOWNLOAD' AND hardware_id NOT IN (SELECT id FROM hardware WHERE deviceid='_SYSTEMGROUP_') ";
	if (isset($mesmachines))				
	$sqlStats.= " AND hardware_id".$mesmachines;
	if (isset($machines_group))
	$sqlStats.= " AND hardware_id".$machines_group;
	
	$resStats = mysql_query($sqlStats, $_SESSION["readServer"]);
	
	$resName = mysql_query("SELECT name FROM download_available WHERE fileid='".$_GET["stat"]."'", $_SESSION["readServer"]);
	$valName = mysql_fetch_array( $resName );

	$valStats = mysql_fetch_array( $resStats );
	
	echo "<body OnLoad='document.title=\"".urlencode($valName["name"])."\"'>";
	printEnTete( $l->g(498)." <b>".$valName["name"]."</b> (".$l->g(296).": ".$_GET["stat"]." )");
	echo "<br><center><img src='tele_stats.php?generatePic=1&stat=".$_GET["stat"]."&group=".$_GET["group"]."&option=".$_POST['selOpt']."'></center>";
	if($_SESSION["lvluser"]==SADMIN){
		echo "<table class='Fenetre' align='center' border='1' cellpadding='5' width='50%'><tr BGCOLOR='#C7D9F5'>";
		echo "<td width='33%' align='center'><a href='tele_stats.php?delsucc=1&stat=".$_GET["stat"]."'><b>".$l->g(483)."</b></a></td>";	
		echo "<td width='33%' align='center'><a href='tele_stats.php?deltout=1&stat=".$_GET["stat"]."'><b>".$l->g(571)."</b></a></td>";	
		echo "<td width='33%' align='center'><a href='tele_stats.php?delnotif=1&stat=".$_GET["stat"]."'><b>".$l->g(575)."</b></a></td>";
		echo "</tr></table><br><br>";
	}
	if ($_GET['group']){
	echo "<form name='refresh' method=POST><div align=center>AFFICHAGE DES STATISTIQUES SUR <select name=selOpt OnChange='refresh.submit();'>
				<option value='ALL'";
	if ($_POST['selOpt'] == "ALL")
	echo " selected ";
	echo ">Toutes les machines ayant ce paquet</option>
				<option value='GROUP'";
	if ($_POST['selOpt'] == "GROUP")
	echo " selected ";
	echo ">Les machines pr�sentes dans le groupe</option></select>
		</div></form>";
	}
	echo "<table class='Fenetre' align='center' border='1' cellpadding='5' width='50%'>
	<tr BGCOLOR='#C7D9F5'><td width='30px'>&nbsp;</td><td align='center'><b>".$l->g(81)."</b></td><td align='center'><b>".$l->g(55)."</b></td></tr>";
	foreach( $legende as $leg ) {
		echo "<tr><td bgcolor='#".$leg["color"]."'>&nbsp;</td><td>".$leg["name"]."</td><td><a href='index.php?multi=1&nme=".urlencode($valName["name"])."&stat=".urlencode($leg["name"])."'>".$leg["count"]."</a></td></tr>";
	}
	echo "<tr bgcolor='#C7D9F5'><td bgcolor='white'>&nbsp;</td><td><b>".$l->g(87)."</b></td><td><b>".$valStats["nb"]."</b></td></tr>";
	echo "</table><br><br>";
	?>
	</BODY>
	</HTML>
	<?php 
}

   function camembert($arr)
   {    
      $size=2; /* taille de la police, largeur du caract�re */
      $ifw=imagefontwidth($size);                            
       
      $w=850; /* largeur de l'image */
      $h=400; /* hauteur de l'image */
      $a=200; /* grand axe du camembert */
      $b=$a/2; /* 60 : petit axe du camembert */
      $d=$a/2; /* 60 : "�paisseur" du camembert */
      $cx=$w/2-1; /* abscisse du "centre" du camembert */
      $cy=($h-$d)/2; /* 95 : ordonn�e du "centre" du camembert */
       
      $A=138+80; /* grand axe de l'ellipse "englobante" */
      $B=102+80; /* petit axe de l'ellipse "englobante" */
      $oy=-$d/2; /* -30 : du "centre" du camembert � celui de l'ellipse "englobante"*/
    
      $img=imagecreate($w,$h);
      $bgcolor=imagecolorallocate($img,0xCD,0xCD,0xCD);    
      imagecolortransparent($img,$bgcolor);
      $black=imagecolorallocate($img,0,0,0);
                                /* calcule la somme des donn�es */
      for ($i=$sum=0,$n=count($arr);$i<$n;$i++) $sum+=$arr[$i][0];    
       
      /* fin des pr�liminaires : on peut vraiment commencer! */
      for ($i=$v[0]=0,$x[0]=$cx+$a,$y[0]=$cy,$doit=true;$i<$n;$i++) {                                                        
         for ($j=0,$k=16;$j<3;$j++,$k-=8) $t[$j]=($arr[$i][1]>>$k) & 0xFF;
                                /* d�termine les "vraies" couleurs */
         $color[$i]=imagecolorallocate($img,$t[0],$t[1],$t[2]);
                                /* calcule l'angle des diff�rents "secteurs" */
         $v[$i+1]=$v[$i]+round($arr[$i][0]*360/$sum);    
                                                           
         if ($doit) { /* d�termine les couleurs "ombr�es" */
            $shade[$i]=imagecolorallocate($img,max(0,$t[0]-50),max(0,$t[1]-50),max(0,$t[2]-50));
                                                           
            if ($v[$i+1]<180) { /* calcule les coordonn�es des diff�rents parall�logrammes */
               $x[$i+1]=$cx+$a*cos($v[$i+1]*M_PI/180);        
               $y[$i+1]=$cy+$b*sin($v[$i+1]*M_PI/180);    
            }                                        
            else {
               $m=$i+1;
               $x[$m]=$cx-$a; /* c'est comme si on rempla�ait $v[$i+1] par 180� */
               $y[$m]=$cy;    
               $doit=false; /* indique qu'il est inutile de continuer! */
            }
         }
      }
       
      /* dessine la "base" du camembert */
      for ($i=0;$i<$m;$i++) imagefilledarc($img,$cx,$cy+$d,2*$a,2*$b,$v[$i],$v[$i+1],$shade[$i],IMG_ARC_PIE);
       
      /* dessine la partie "verticale" du camembert */                                                        
      for ($i=0;$i<$m;$i++) {                        
         $area=array($x[$i],$y[$i]+$d,$x[$i],$y[$i],$x[$i+1],$y[$i+1],$x[$i+1],$y[$i+1]+$d);
         imagefilledpolygon($img,$area,4,$shade[$i]);            
      }
       
      /* dessine le dessus du camembert */
      for ($i=0;$i<$n;$i++) imagefilledarc($img,$cx,$cy,2*$a,2*$b,$v[$i],$v[$i+1],$color[$i],IMG_ARC_PIE);
    
      /*imageellipse($img,$cx,$cy-$oy,2*$A,2*$B,$black);    // dessine l'ellipse "englobante" */
       
      /* dessine les "fl�ches" et met en place le texte */
      for ($i=0,$AA=$A*$A,$BB=$B*$B;$i<$n;$i++) if ($arr[$i][0]) {
         $phi=($v[$i+1]+$v[$i])/2;
                                /* intersection des "fl�ches" avec l'ellipse "englobante" */
         $px=$a*3*cos($phi*M_PI/180)/4;        
         $py=$b*3*sin($phi*M_PI/180)/4;        
                                /* �quation du 2�me degr� avec 2 racines r�elles et distinctes */    
         $U=$AA*$py*$py+$BB*$px*$px;
         $V=$AA*$oy*$px*$py;                        
         $W=$AA*$px*$px*($oy*$oy-$BB);    
                                /* calcule le pourcentage � afficher */
         $value=number_format(100*$arr[$i][0]/$sum,2,",","")."%";
                                /* �crit le texte � droite */    
         if ($phi<=90 || $phi>270) {
            $root=(-$V+sqrt($V*$V-$U*$W))/$U;
            imageline($img,$px+$cx,$py+$cy,$qx=$root+$cx,$qy=$root*$py/$px+$cy,$black);
            imageline($img,$qx,$qy,$qx+10,$qy,$black);        
           
            imagestring($img,$size,$qx+14,$qy-12,$arr[$i][2],$black);
            imagestring($img,$size,$qx+14,$qy-2,$value,$black);
         }
         else { /* �crit le texte � gauche */
            $root=(-$V-sqrt($V*$V-$U*$W))/$U;
            imageline($img,$px+$cx,$py+$cy,$qx=$root+$cx,$qy=$root*$py/$px+$cy,$black);
            imageline($img,$qx,$qy,$qx-10,$qy,$black);        
                        
            imagestring($img,$size,$qx-12-$ifw*strlen($arr[$i][2]),$qy-12,$arr[$i][2],$black);
            imagestring($img,$size,$qx-12-$ifw*strlen($value),$qy-2,$value,$black);
        }
     }
   
     header("Content-type: image/png");
     imagepng($img);
     imagedestroy($img);
  }
  
?>
