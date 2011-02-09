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
//Modified on $Date: 2008-02-27 12:34:12 $$Author: hunal $($Revision: 1.17 $)
include('security.php');
require_once('require/function_server.php');

//cas of add new server's diff
if (isset($_POST['valid_server']) and $_POST['valid_server'] != '0')
{

	$i=1;
	foreach ($_POST as $key=>$value){
		if (substr($key, 0, 9) == "checkmass"){
			$mach[$i]=$value;
			$i++;
		}
	}
	if ($mach == "")
	{
		$sql="select h.id ".$_SESSION['groupReq'];
		$res = mysql_query( $sql, $_SESSION["readServer"]);
		while( $valallid = mysql_fetch_array( $res ) ){
			$mach[$i] = $valallid['id'];
			$i++;
		}

	}
	if ($_POST['name_server_new'] != "")
	$name=$_POST['name_server_new'];
	elseif ($_POST['name_server_add'] != "")
	$name=$_POST['name_server_add'];
	elseif ($_POST['name_server_replace'] != "")
	$name=$_POST['name_server_replace'];
	$msg=admin_serveur($_POST['action_server'],$name,$_POST['descr_server'],$mach) ;
	echo "<script>alert('".$msg."');</script>";
}

	if( isset( $_GET["nme"] ) && isset( $_GET["stat"] ) ) {
		$_POST["act_0"] = "on";
		$_POST["chm_0"] = "tele";
		$_POST["lbl_0"] = $l->g(512);
		$_POST["ega_0"] = "ayant";
		$_POST["val_0"] = urldecode( $_GET["nme"] );
		$_POST["val2_0"] = urldecode( $_GET["stat"] );
		$_POST["sub"] = $l->g(30);
		$_POST["max"] = 1;
		if( $_POST["val2_0"] == $l->g(482) ) {
			$_POST["val2_0"] = "stats";
		}
		$_SESSION["OPT"][] = $l->g(512);
	}

	if($_POST["sub"]==$l->g(30)) {
		unset($_SESSION["selectSofts"]);
		unset($_SESSION["selectRegistry"]);
		unset($_SESSION["storedRequest"], $_SESSION["c"],$_SESSION["reqs"],$_SESSION["softs"]);
	}

	printEnTete($l->g(9));
	$req = NULL;

	if( !isset($_SESSION["optCol"]) ) {
		$reqCol = "SHOW COLUMNS FROM accountinfo";
		$resCol = mysql_query($reqCol, $_SESSION["readServer"]) or die(mysql_error($_SESSION["readServer"]));
		while($colname=mysql_fetch_array($resCol)) {
			if( strcasecmp($colname["Field"], TAG_NAME) != 0 )
				$_SESSION["optCol"][] = $colname["Field"] ;
		}
	}

	require("req.class.php");
	$indLigne=0;
	$softPresent = false;
	$cuPresent = -1;
	$leSelect = array_merge( array("h.id"=>"h.id", "deviceid"=>"deviceid"), $_SESSION["currentFieldList"] );

	if( is_array($_SESSION["selectSofts"]) && $_POST["sub"]!=$l->g(30))
		$leSelect = array_merge( $leSelect, $_SESSION["selectSofts"] );

	if( is_array($_SESSION["selectRegistry"]) )
		$leSelect = array_merge( $leSelect, $_SESSION["selectRegistry"] );

	$selFinal ="";

	if($_POST["reset"]==$l->g(41))
	{
		unset($_SESSION["OPT"]);
		unset($_SESSION["reqs"]);
		unset($_SESSION["softs"]);
	}
	else if($_POST["selOpt"])
	{
		$_POST["selOpt"] = urldecode( $_POST["selOpt"] );
		if( $_POST["selOpt"]==$l->g(20) ||  ((! is_array($_SESSION["OPT"]))  ||   ( !in_array($_POST["selOpt"],$_SESSION["OPT"])))) {
			$_SESSION["OPT"][]=stripslashes($_POST["selOpt"]);
		}
	}
	else if($_POST["sub"]==$l->g(30))
	{
		/* Creates a description of the generated query in $_SESSION["queryDescription"]
		$totSofts = 0;
		$totRegs = 0;

		$logName = "";
		$firstLog = true;
		for($cpLog=0;$cpLog<$_POST["max"];$cpLog++)	{
			if( $_POST["val_".$cpLog] != "" && $_POST["act_".$cpLog] == "on" ) {				
				if( ! $firstLog )
					$logName .= " &\n";
				$logName .= addslashes( $_POST["chm_".$cpLog]." ".$_POST["ega_".$cpLog]." ".$_POST["val_".$cpLog] );
				if( $_POST["ega_".$cpLog] == $l->g(203) )
					$logName .= $l->g(582).$_POST["val2_".$cpLog];

				$firstLog = false;
			}
		}
		$_SESSION["queryDescription"] = $logName ;
		*/
		$i=0; $nb=0; 
		$laRequete="";				

		for($i=0;$i<$_POST["max"];$i++)	{

			if( urldecode($_POST["lbl_".$i]) == $l->g(20))
				$_SESSION["softs"][] = array( $_POST["act_".$i], urldecode($_POST["chm_".$i]), $_POST["ega_".$i],
				strtr($_POST["val_".$i],"\"","'"), strtr($_POST["val2_".$i],"\"","'"), $_POST["valreg_".$i] );

			$_SESSION["reqs"][ urldecode($_POST["lbl_".$i]) ] = array( $_POST["act_".$i], urldecode($_POST["chm_".$i]), $_POST["ega_".$i],
			strtr($_POST["val_".$i],"\"","'"), strtr($_POST["val2_".$i],"\"","'"), $_POST["valreg_".$i] );

			if(!isset($_POST["act_".$i]))
				continue;
			$nb++;			
		}

		$from = " hardware h LEFT JOIN accountinfo a ON a.hardware_id=h.id LEFT JOIN bios b ON b.hardware_id=h.id,";
		//$laRequete.=" FROM hardware h,accountinfo a, bios b, ";

		$softTable = false ;
		$logIndex = 1;
		$fromPrelim  ="";
		for($i=0;$i<$_POST["max"];$i++)
		{

			if(!isset($_POST["act_".$i]))
			continue;

			//jokers
			if( $_POST["ega_".$i] != $l->g(410) )
				$_POST["val_".$i] = strtr($_POST["val_".$i], "?*", "_%");

			if( isFieldDate($_POST["chm_".$i]) ) {
				$_POST["val_".$i] = dateToMysql($_POST["val_".$i]);
			}

			if( ($_POST["chm_".$i]=="name") && ($_POST["ega_".$i]==$l->g(129) || $_POST["ega_".$i]==$l->g(410))) {
				$leSelect["s".$logIndex.".name"] = $l->g(20)." $logIndex";
				$_SESSION["selectSofts"]["s".$logIndex.".name"] = $l->g(20)." $logIndex";
			}

			if( ($_POST["chm_".$i]=="regval" || $_POST["chm_".$i]=="regname")&&
				($_POST["ega_".$i]==$l->g(129) || $_POST["ega_".$i]==$l->g(410))) {
				$leSelect["r.regvalue"] = $_POST["val_".$i];
				$from = substr ( $from, 0 , strlen( $from)-1 );
				$from .= " LEFT JOIN registry r ON r.hardware_id=h.id AND r.name='".$_POST["val_".$i]."',";
				$_SESSION["selectRegistry"]["r.regvalue"] = $_POST["val_".$i];
			}

			$regRes = null;
			if( ($_POST["ega_".$i]==$l->g(129)||$_POST["ega_".$i]==$l->g(410)) && $_POST["chm_".$i]=="name" ) {
				//$fromPrelim.=" softwares s".$logIndex.",";
				$from .= " softwares s".$logIndex.",";
				$logIndex++;
			}

			if( ($_POST["chm_".$i]=="smonitor" || $_POST["chm_".$i]=="fmonitor" || $_POST["chm_".$i]=="lmonitor") && ! $monitorTable ) {
				$fromPrelim.=" monitors m,";
				$monitorTable = true;
			}

			if($_POST["chm_".$i]=="free") {
				$fromPrelim.=" drives dr,";
			}

			if(($_POST["chm_".$i]=="ipmask"||$_POST["chm_".$i]=="ipgateway"||$_POST["chm_".$i]=="ipaddr"||$_POST["chm_".$i]=="ipsubnet"||$_POST["chm_".$i]=="macaddr") && !$netTable) {
				$fromPrelim.=" networks n,";
				$netTable=true;
			}
		}

		if($fromPrelim[strlen($fromPrelim)-1]==",")
			$fromPrelim[strlen($fromPrelim)-1]=" ";
		if($from[strlen($from)-1]==",")
			$from[strlen($from)-1]=" ";
		$groupReqBegin = "FROM ".$from;
		if( $fromPrelim != "" )
			$groupReqBegin .= ",".$fromPrelim;

		$groupReqBegin .= " WHERE ";
		for($i=0;$i<$_POST["max"];$i++)
		{				
			if(!isset($_POST["act_".$i]))
				continue;
								
			if( $_POST["act_".$i]="checked" && $_POST["chm_".$i] == "ipdisc" ) {
							
				if( ! empty($laRequete) ) $laRequete .= " AND ";
				if( ! empty($groupReq) ) $groupReq .= " AND ";

				$laRequete.= " h.id ";
				$groupReq.= " h.id ";
				switch( $_POST["val_".$i] ) {
					case "elu":
						$groupReq.= "IN (SELECT hardware_id FROM devices WHERE ivalue=1 AND name='IPDISCOVER') "; 
						$laRequete.= "IN "; 
						$reqIdIpd = "SELECT DISTINCT hardware_id FROM devices WHERE ivalue=1 AND name='IPDISCOVER'"; 
					break;
					case "for":
						$groupReq.= "IN (SELECT hardware_id FROM devices WHERE ivalue=2 AND name='IPDISCOVER') "; 
						$laRequete.= "IN "; 
						$reqIdIpd = "SELECT DISTINCT hardware_id FROM devices WHERE ivalue=2 AND name='IPDISCOVER'"; 
					break;
					case "nelu":
						$groupReq.= "NOT IN (SELECT hardware_id FROM devices WHERE ivalue=1 AND name='IPDISCOVER') "; 
						$laRequete.= "NOT IN "; 
						$reqIdIpd = "SELECT DISTINCT hardware_id FROM devices WHERE ivalue=1 AND name='IPDISCOVER'"; 
					break;
					case "eli":
						$groupReq.= "NOT IN (SELECT hardware_id FROM devices WHERE ivalue=0 AND name='IPDISCOVER') ";
						$laRequete.= "NOT IN "; 
						$reqIdIpd = "SELECT DISTINCT hardware_id FROM devices WHERE ivalue=0 AND name='IPDISCOVER'";
					break;
					case "neli":
						$groupReq.= "IN (SELECT hardware_id FROM devices WHERE ivalue=0 AND name='IPDISCOVER') ";
						$laRequete.= "IN "; 
						$reqIdIpd = "SELECT DISTINCT hardware_id FROM devices WHERE ivalue=0 AND name='IPDISCOVER'";
					break;
				}
				$laRequete .= "('".getGluedIds($reqIdIpd)."')";
				continue;
			}
			
			if( $_POST["act_".$i]="checked" && $_POST["chm_".$i] == "freq" ) {
							
				if( ! empty($laRequete) ) $laRequete .= " AND ";
				if( ! empty($groupReq) ) $groupReq .= " AND ";

				$laRequete.= " h.id ";
				$groupReq .= " h.id ";
				switch( $_POST["val_".$i] ) {
					case "std":
						$groupReq.= "NOT IN (SELECT hardware_id FROM devices WHERE name='FREQUENCY') "; 
						$laRequete.= "NOT IN ";
						$reqIdFre = " (SELECT DISTINCT hardware_id FROM devices WHERE name='FREQUENCY') "; 
					break;
					case "always":
						$groupReq.= "IN (SELECT hardware_id FROM devices WHERE name='FREQUENCY' AND ivalue=0) "; 
						$laRequete.= "IN ";
						$reqIdFre = "  (SELECT DISTINCT hardware_id FROM devices WHERE name='FREQUENCY' AND ivalue=0) "; 
					break;
					case "never":
						$groupReq.= "IN (SELECT hardware_id FROM devices WHERE name='FREQUENCY' AND ivalue=-1) "; 
						$laRequete.= "IN ";
						$reqIdFre = "  (SELECT DISTINCT hardware_id FROM devices WHERE name='FREQUENCY' AND ivalue=-1) "; 
					break;
					case "custom":
						$groupReq.= "IN (SELECT hardware_id FROM devices WHERE name='FREQUENCY' AND ivalue>0)  ";
						$laRequete.= "IN ";
						$reqIdFre = "  (SELECT DISTINCT hardware_id FROM devices WHERE name='FREQUENCY' AND ivalue>0)  ";
					break;					
				}
				$laRequete .= "('".getGluedIds($reqIdFre)."')";
				continue;
			}
			
			if( $_POST["act_".$i]="checked" && $_POST["chm_".$i] == "tele" ) {
							
				if( ! empty($laRequete) ) $laRequete .= " AND ";
				if( ! empty($groupReq) ) $groupReq .= " AND ";					
				$laRequete.= " h.id ";
				$groupReq .= " h.id ";
				
				if( $_POST["ega_".$i] == "ayant" ) {
					$laRequete.= " IN ";
					$groupReq .= " IN ";
				}
				else if( $_POST["ega_".$i] == "nayant" ) {
					$laRequete.= " NOT IN ";
					$groupReq .= " NOT IN ";
				}
				$reqIdDownload = "";
				switch( $_POST["val2_".$i] ) {
					case "suc":
						$groupReq.= "(SELECT d.hardware_id FROM devices d, download_available a, download_enable e
						 WHERE d.name='DOWNLOAD' AND a.name='".$_POST["val_".$i].
						 "' AND d.tvalue like 'SUCCESS%' AND e.fileid=a.fileid AND e.id=d.ivalue UNION 
					     SELECT dh.hardware_id FROM download_history dh, download_available da WHERE dh.pkg_id=da.fileid AND da.name='".$_POST["val_".$i].
						 "')"; 
						$reqIdDownload = "SELECT DISTINCT d.hardware_id FROM devices d, download_available a, download_enable e
						 WHERE d.name='DOWNLOAD' AND a.name='".$_POST["val_".$i].
						 "' AND d.tvalue like 'SUCCESS%' AND e.fileid=a.fileid AND e.id=d.ivalue UNION 
					     SELECT dh.hardware_id FROM download_history dh, download_available da WHERE dh.pkg_id=da.fileid AND da.name='".$_POST["val_".$i].
						 "'"; 
					break;
					case "nsuc":
						$groupReq.= "(SELECT d.hardware_id FROM devices d, download_available a, download_enable e
						 WHERE d.name='DOWNLOAD' AND a.name='".$_POST["val_".$i].
						 "' AND (d.tvalue not like 'SUCCESS%' OR d.tvalue IS NULL) AND e.fileid=a.fileid AND e.id=d.ivalue) "; 
						$reqIdDownload = "SELECT DISTINCT d.hardware_id FROM devices d, download_available a, download_enable e
						 WHERE d.name='DOWNLOAD' AND a.name='".$_POST["val_".$i].
						 "' AND (d.tvalue not like 'SUCCESS%' OR d.tvalue IS NULL) AND e.fileid=a.fileid AND e.id=d.ivalue"; 
					break;
					case "ind":
						$groupReq.= "(SELECT d.hardware_id FROM devices d, download_available a, download_enable e
						 WHERE d.name='DOWNLOAD' AND a.name='".$_POST["val_".$i].
						 "' AND e.fileid=a.fileid AND e.id=d.ivalue UNION SELECT dh.hardware_id FROM download_history dh, download_available da WHERE dh.pkg_id=da.fileid AND da.name='".$_POST["val_".$i].
						 "')";
						$reqIdDownload = "SELECT DISTINCT d.hardware_id FROM devices d, download_available a, download_enable e
						 WHERE d.name='DOWNLOAD' AND a.name='".$_POST["val_".$i].
						 "' AND e.fileid=a.fileid AND e.id=d.ivalue UNION SELECT dh.hardware_id FROM download_history dh, download_available da WHERE dh.pkg_id=da.fileid AND da.name='".$_POST["val_".$i].
						 "'";
					break;
					case "stats":
						$groupReq.= "(SELECT d.hardware_id FROM devices d, download_available a, download_enable e
						 WHERE d.name='DOWNLOAD' AND a.name='".$_POST["val_".$i].
						 "' AND e.fileid=a.fileid AND e.id=d.ivalue AND d.tvalue IS NULL ) ";  
					
						$reqIdDownload = "SELECT DISTINCT d.hardware_id FROM devices d, download_available a, download_enable e
						 WHERE d.name='DOWNLOAD' AND a.name='".$_POST["val_".$i].
						 "' AND e.fileid=a.fileid AND e.id=d.ivalue AND d.tvalue IS NULL";  
					break;
					default: //standard case
						$groupReq.= "(SELECT d.hardware_id FROM devices d, download_available a, download_enable e
						 WHERE d.name='DOWNLOAD' AND a.name='".$_POST["val_".$i].
						 "' AND d.tvalue='".$_POST["val2_".$i]."' AND e.fileid=a.fileid AND e.id=d.ivalue) ";  
						$reqIdDownload = "SELECT DISTINCT d.hardware_id FROM devices d, download_available a, download_enable e
						 WHERE d.name='DOWNLOAD' AND a.name='".$_POST["val_".$i].
						 "' AND d.tvalue='".$_POST["val2_".$i]."' AND e.fileid=a.fileid AND e.id=d.ivalue";  
					break;									
				}
				$laRequete .= "('".getGluedIds($reqIdDownload)."')";				
				continue;
			}
			
			if( $_POST["act_".$i]="checked" && ! (  ($cuPresent != -1 ) && $_POST["chm_".$i] == "cu") )
			{				
				// cas particulier avec LOGICIEL
				if( ($_POST["chm_".$i] == "name" ) ) {
					if( $_POST["ega_".$i] == $l->g(129)||$_POST["ega_".$i]==$l->g(410) )
						$softsEg[] = Array( $_POST["val_".$i], urldecode($_POST["lbl_".$i]), $_POST["ega_".$i] );
					else
						$softsDi[] = Array( $_POST["val_".$i], urldecode($_POST["lbl_".$i]), "" );
					continue ;
				}
				
				// cas particulier avec registry DIFFERENT DE
				if( $_POST["chm_".$i] == "regname" && $_POST["ega_".$i] == $l->g(130) ) {
					$regDiff=Array($_POST["val_".$i],$_POST["valreg_".$i]);
					continue ;
				}
				
				$forceEgal=false;
												
				if($_POST["chm_".$i]=="regname") {
					if( ! empty($laRequete) ) $laRequete .= " AND ";
					if( ! empty($groupReq) ) $groupReq .= " AND ";	
					$laRequete.= "r.hardware_id=h.id ";
					$groupReq .= "r.hardware_id=h.id ";
				}
				$tblIneq = "h";	
				$reqCondition = "";				
				switch($_POST["chm_".$i])
				{
					case "ssn": $reqCondition.="b.ssn";break;
					case "bmanufacturer": $reqCondition.="b.bmanufacturer";break;
					case "bversion": $reqCondition.="b.bversion";break;
					case "smanufacturer": $reqCondition.="b.smanufacturer";break;
					case "smodel": $reqCondition.="b.smodel";break;
					case "ipmask": $reqCondition.="n.hardware_id=h.id AND n.ipmask";break;
					case "ipgateway": $reqCondition.="n.hardware_id=h.id AND n.ipgateway";break;
					case "free": $reqCondition.="dr.hardware_id=h.id AND dr.free";$tblIneq="dr";break;
					case "ipsubnet": $reqCondition.="n.hardware_id=h.id AND n.ipsubnet";break;
					case "regname": 
							if( $_POST["valreg_".$i] != $l->g(265) ) {
								if( $_POST["ega_".$i] != $l->g(410) )
									$_POST["valreg_".$i] = strtr($_POST["valreg_".$i], "?*", "_%");
								
								if( $_SESSION["usecache"] == true && $_POST["ega_".$i] == $l->g(129) ) {
									$glued = getCache( "registry", "regvalue", $_POST["valreg_".$i], $totRegs );
									$reqCondition.="r.regvalue IN('".$glued."') AND ";
								}
								else {
									$comp = $_POST["ega_".$i] == $l->g(129) ? " like '%" : " = '";							   
									$compFin = $_POST["ega_".$i] == $l->g(129) ? "%' " : "' ";
									$reqCondition.="r.regvalue$comp".$_POST["valreg_".$i]."{$compFin}AND ";
								}
							}
							$reqCondition.="r.name";
							$forceEgal=true;
							break;					
					
					case "name": 							
							$reqCondition.="s.hardware_id=h.id AND s.name";
							$softPresent = true;
							if( $_POST["ega_".$i] == $l->g(129)||$_POST["ega_".$i]==$l->g(410) )
								$unSoftnEgal = true ;
							break;			
							
					case "ORDEROWNER": $reqCondition.="a.orderowner";break;
					case "ORDERID": $reqCondition.="a.orderid";break;
					case "PRODUCTID": $reqCondition.="a.productid";break;
					case "BILLDATE": $reqCondition.="a.billnbr";break;
					case "cu": $reqCondition.="a.".TAG_NAME;break;
					case "processors": $reqCondition.="h.processors";break;
					case "memory": $reqCondition.="h.memory";break;
					case "osname": $reqCondition.="h.osname";$forceEgal=false;break;
					case "oscomments": $reqCondition.="h.oscomments";$forceEgal=false;break;
					case "userid": $reqCondition.="h.userid";break;
					case "ipaddr": $reqCondition.="n.hardware_id=h.id AND n.ipaddress";break;
					case "macaddr": $reqCondition.="n.hardware_id=h.id AND n.macaddr";break;
					case "useragent": $reqCondition.="h.useragent";$forceEgal=true;break;
					case "workgroup": $reqCondition.="h.workgroup";$forceEgal=true;break;
					case "userdomain": $reqCondition.="h.userdomain";$forceEgal=true;break;
					case "hname": $reqCondition.="h.name";break;
					case "description": $reqCondition.="h.description";break;
					case "lastdate": $reqCondition.="h.lastdate";break;
					case "smonitor": $reqCondition.="m.hardware_id=h.id AND m.serial";break;
					case "fmonitor": $reqCondition.="m.hardware_id=h.id AND m.manufacturer";break;
					case "lmonitor": $reqCondition.="m.hardware_id=h.id AND m.caption";break;
					case "sversion": $reqCondition.="s1.hardware_id=h.id AND s1.version";break;
					default: $reqCondition.="a.".$_POST["chm_".$i]; break;
				}
				
				if( $_POST["val_".$i] == "" ) {
						switch($_POST["ega_".$i]) {
							case $l->g(410):	
							case $l->g(129): $reqCondition.=" IS NULL "; break;						
							case $l->g(130): 					
							case $l->g(346):
							case $l->g(201): 
							case $l->g(347):
							case $l->g(202): 
							case $l->g(203): 
							default: $reqCondition .=" IS NOT NULL "; break;		
						}
				}
				else {
					if( ! $forceEgal ) {
						switch($_POST["ega_".$i]) {
							case $l->g(410): $reqCondition.=" = ";$forceEgal=true; break;	
							case $l->g(129): $reqCondition.=" LIKE ";$forceLike=true; break;						
							case $l->g(130): $reqCondition.=" NOT LIKE ";$forceLike=true; break;					
							case $l->g(346):
							case $l->g(201): $reqCondition.="<"; $forceEgal=true; break;
							case $l->g(347):
							case $l->g(202): $reqCondition.=">"; $forceEgal=true; break;
							case $l->g(203): $reqCondition.="<'".$_POST["val2_".$i]."' AND $tblIneq.".$_POST["chm_".$i].">"; $forceEgal=true; break;
							//case $l->g(204): $reqCondition.=">'".$_POST["val2_".$i]."' OR h.".$_POST["chm_".$i]."<";break;
							default: $reqCondition.=" LIKE "; $forceLike=true;break;
						}
					}
					else {
						switch($_POST["ega_".$i]) {
							case $l->g(410):	
							case $l->g(129): $reqCondition.=" = ";break;						
							case $l->g(130): 					
							case $l->g(346):
							case $l->g(201): 
							case $l->g(347):
							case $l->g(202): 
							case $l->g(203): 
							default: $reqCondition.=" <> ";break;		
						}
					}
					
					if( $forceEgal || !$forceLike )
						$reqCondition.="'".$_POST["val_".$i]."'";	
					else
						$reqCondition.="'%".$_POST["val_".$i]."%'";					
				}

				if( ! empty($laRequete) ) $laRequete .= " AND ";
				if( ! empty($groupReq) ) $groupReq .= " AND ";	
				$laRequete .= $reqCondition;
				$groupReq .= $reqCondition;
			}			
		}
		
		if( $nb > 0 ) {		
			$laRequeteF=$laRequete;				
			$logIndexEg = 1;
	
			for($ii=0;$ii<sizeof($softsEg);$ii++) {			
				$selFinal .= " AND ";
				if( ! empty($laRequeteF) ) $laRequeteF .= " AND ";
				if( ! empty($groupReq) )   $groupReq   .= " AND ";	
				
				$comp = $softsEg[$ii][2] == $l->g(129) ? " like '%" : " = '";
				$compFin = $softsEg[$ii][2] == $l->g(129) ? "%' " : "' ";
				$groupReq .= " s$logIndexEg.hardware_id=h.id AND s$logIndexEg.name$comp".$softsEg[$ii][0]."$compFin";
				// If cache is used AND 'like' search is used
				if( $_SESSION["usecache"] == true && $softsEg[$ii][2]==$l->g(129) ) {		
					$gluedSofts = getCache( "softwares", "name", $softsEg[$ii][0], $totSofts );
					if ($gluedSofts != ''){
					$laRequeteF .= " s$logIndexEg.hardware_id=h.id AND s$logIndexEg.name IN('$gluedSofts')";
					$selFinal   .= " s$logIndexEg.hardware_id=h.id AND s$logIndexEg.name IN('$gluedSofts')";
					}
					else{
					$laRequeteF .= " s$logIndexEg.hardware_id=h.id AND s$logIndexEg.name IN('SOFT NOT EXIST')";
					$selFinal   .= " s$logIndexEg.hardware_id=h.id AND s$logIndexEg.name IN('SOFT NOT EXIST')";
					}
				}
				else {
					$laRequeteF .= " s$logIndexEg.hardware_id=h.id AND s$logIndexEg.name$comp".$softsEg[$ii][0]."$compFin";
					$selFinal   .= " s$logIndexEg.hardware_id=h.id AND s$logIndexEg.name$comp".$softsEg[$ii][0]."$compFin";
				}
				$logIndexEg++;
			}
			
			if( $_SESSION["usecache"] == true ) {
							
				for($ii=0;$ii<sizeof($softsDi);$ii++) {
					$gluedSofts = "";
					$softsDi[$ii][0] = strtr($softsDi[$ii][0], "?*", "_%");
					$gluedSofts = getCache( "softwares", "name", $softsDi[$ii][0], $totSofts );
					
					if( $gluedSofts != "" ) {
						$reqSid = "SELECT DISTINCT hardware_id FROM softwares WHERE name IN('$gluedSofts')";
						$resSid = mysql_query( $reqSid, $_SESSION["readServer"] );
						while( $valSid = mysql_fetch_array($resSid) ) {
							$idNotIn[] = $valSid["hardware_id"];
						}
					}					
				}				
			}
			else {				
				for($ii=0;$ii<sizeof($softsDi);$ii++) {
					if( ! empty($laRequeteF) ) $laRequeteF .= " AND ";						
					$laRequeteF .= " h.id NOT IN(SELECT DISTINCT(ss.hardware_id) FROM softwares ss WHERE ss.name like '%".$softsDi[$ii][0]."%')";
				}
			}
			
			for($ii=0;$ii<sizeof($softsDi);$ii++) {
				if( ! empty($groupReq) ) $groupReq .=" AND";
				$groupReq .= " h.id NOT IN(SELECT DISTINCT(ss.hardware_id) FROM softwares ss WHERE ss.name like '%".$softsDi[$ii][0]."%')";
			}

			if( $_SESSION["usecache"] == true ) {
				if(sizeof($regDiff)>=1) {				
					$regDiff[1] = strtr($regDiff[1], "?*", "_%");
					$gluedRegs = getCache( "registry", "regvalue", $regDiff[1], $totRegs );
					$reqSid = "SELECT DISTINCT hardware_id FROM registry WHERE name='".$regDiff[0]."' AND regvalue IN('".$gluedRegs."')";
					$resSid = mysql_query( $reqSid, $_SESSION["readServer"] );
					while( $valSid = mysql_fetch_array($resSid) ) {
						$idNotIn[] = $valSid["hardware_id"];
					}					
				}
			}
			else {
				if(sizeof($regDiff)>=1) {
					$valRegR = "AND rr.regvalue = '".$regDiff[1]."'";
					if( ! empty($laRequeteF) ) $laRequeteF .= " AND";
					$laRequeteF .= " h.id NOT IN(SELECT DISTINCT(rr.hardware_id) FROM registry rr WHERE rr.name = '".$regDiff[0]."' $valRegR)";
				}
			}
			
			if(sizeof($regDiff)>=1) {
				$valRegR = " AND rr.regvalue like '%".$regDiff[1]."%'";
				if(  ! empty($groupReq) ) $groupReq .= " AND";
				$groupReq .= " h.id NOT IN(SELECT DISTINCT(rr.hardware_id) FROM registry rr WHERE rr.name = '".$regDiff[0]."' $valRegR)";
			}
			//modif apport�es pour r�soudre bug des recherches qui renvoient toutes les machines
			if( ! empty($laRequeteF))
			$and=" AND ";

			if( (! empty($laRequeteF) || ! empty($reqSid)) && ! empty($mesMachines) ) {
				$laRequeteF .= $and.$mesMachines;
			}
			//fin des modifs
			if( sizeof( $idNotIn ) > 0 ) {
				if(  ! empty($laRequeteF) ) $laRequeteF .=" AND";
				$idNotIn = @array_unique( $idNotIn );
				$gluedId = @implode( "','", $idNotIn );
				$laRequeteF .= " h.id NOT IN('".$gluedId."')";
			}
						
			if( ! empty($laRequeteF) ) $laRequeteF .= " AND ";
			$laRequeteF .= " deviceid<>'_SYSTEMGROUP_' AND deviceid <> '_DOWNLOADGROUP_' ";

			if( ! empty($groupReq) ) $groupReq .= " AND ";
			$groupReq .= " deviceid<>'_SYSTEMGROUP_' AND deviceid <> '_DOWNLOADGROUP_' ";

			$group =  " h.id";
			$lbl="Recherche multicrit�res";
			$lblChmp[0]=NULL;
			$selectPrelim = array("h.id"=>"h.id");
			$linkId = "h.id";
			$whereId = "h.id";
			$countId = "h.id";
			$_SESSION["groupReq"] = $groupReqBegin." " .$groupReq;
			$req=new Req($lbl,$whereId,$linkId,$laRequeteF,$leSelect,$selectPrelim,$from,$fromPrelim,$group,"h.lastdate DESC",$countId,null,true,null,null,null,null,$selFinal);
		}		
	}
	else if($_GET["redo"] || $_GET["c"] || $_GET["av"] || $_GET["page"] || isset($_GET["pcparpage"]) || isset($_GET["newcol"])  )
	{
		$lblChmp[0]=NULL;				
		$req=new Req($_SESSION["storedRequest"]->label,$_SESSION["storedRequest"]->whereId,$_SESSION["storedRequest"]->linkId,$_SESSION["storedRequest"]->where,$leSelect,$_SESSION["storedRequest"]->selectPrelim,
		$_SESSION["storedRequest"]->from,$_SESSION["storedRequest"]->fromPrelim,$_SESSION["storedRequest"]->group,$_SESSION["storedRequest"]->order,$_SESSION["storedRequest"]->countId,null,true,null,null,null,null,$_SESSION["storedRequest"]->selFinal); // Instanciation du nouvel objet de type "Req"		
		//echo $requeteCount[0];
	}
	
	if($_SESSION["debug"]) 
		echo "<br><font color='brown'><b>".$groupReqBegin." " .$groupReq."</b></font><br><br>";
	
	if( $totSofts > MAX_CACHED_SOFTS || $totRegs > MAX_CACHED_REGISTRY )
		echo "<br><center><font color='red'><b>Trop de r�sultats. Merci d'�tre plus sp�cifique.</b></font></center>";
	else if( $req != NULL ) {
		ShowResults($req,true,false,false,true,false,false,false,true,true);
	}
?>
<br>
<table border=0 width=80% align=center><tr align=right><td width=50%>
<form name='optionss' action='index.php?multi=1' method='post'><b><?php echo $l->g(31);?>:&nbsp;&nbsp;&nbsp;</b> 
<select name=selOpt OnChange="optionss.submit();"><?php 

$optArray = array($l->g(34), $l->g(33), $l->g(557), $l->g(20), $l->g(26), $l->g(35),
$l->g(36), $l->g(207), $l->g(25), $l->g(24), $l->g(377), $l->g(65), $l->g(284), $l->g(64), $l->g(554), 
TAG_LBL, $l->g(357), $l->g(46),$l->g(257),$l->g(331),$l->g(209),$l->g(53),$l->g(45), $l->g(312), $l->g(286), $l->g(429), $l->g(512),$l->g(95),$l->g(555),$l->g(556));

//If software is selected, then software version is available
if(is_array($_SESSION["OPT"]) && in_array($l->g(20),$_SESSION["OPT"]))
	$optArray = array_merge( $optArray , array($l->g(19)) );

$optArray  = array_merge( $optArray, $_SESSION["optCol"]);
sort($optArray);
$countHl++;
echo "<option".($countHl%2==1?" class='hi'":"").">".$l->g(32)."</option>"; $countHl++;

foreach( $optArray as $val) {
	if( (!is_array($_SESSION["OPT"]) || !in_array($val,$_SESSION["OPT"])) && $val!="DEVICEID"&& $val!="HARDWARE_ID" || $val==$l->g(20)) {
		$countHl++;
		echo "<option".($countHl%2==1?" class='hi'":"").">$val</option>";
	}
}

?>
</select>
</form></td><td align=left>
<form method=post name=res action=index.php?multi=1><input taborder=2 type=submit name=reset value=<?php echo $l->g(41);?>></form></td>
</td></tr></table>

<?php 
$softVersion = false;
if( @in_array($l->g(19),$_SESSION["OPT"]))
	$softVersion = true;

if($_SESSION["OPT"]!=0)
{	
	echo "<form name=machine action=index.php?multi=1 method=post><table border=1 class= 'Fenetre' WIDTH = '75%' ALIGN = 'Center' CELLPADDING='5'>";
	
	$ligne[] = array( $l->g(34),"ipaddr","hardware","",2,5,"",false,true);
	$ligne[] = array( $l->g(33),"workgroup","hardware","SELECT DISTINCT workgroup FROM hardware",1,1,"",false,true);
	$ligne[] = array( $l->g(557),"userdomain","hardware","SELECT DISTINCT userdomain FROM hardware",1,1,"",false,true);
	
	foreach( $_SESSION["OPT"] as $op )
		if( $op == $l->g(20) ) {
			$ligne[] = array( $l->g(20),"name","softwares","",2,7,"",false,true,!$softVersion);
			if( $softVersion )
				break;
		}
	$ligne[] = array( $l->g(19),"sversion","softwares","",2,1,"",false,true);
	$ligne[] = array( $l->g(26),"memory","hardware","",2,3,"MO",false,false);
	$ligne[] = array( $l->g(35),"hname","hardware","",2,1,"",false,true);
	$ligne[] = array( $l->g(53),"description","hardware","",2,1,"",false,true);
	$ligne[] = array( $l->g(46),"lastdate","hardware","",2,2,"",true);
	$ligne[] = array( $l->g(357),"useragent","hardware","SELECT DISTINCT useragent FROM hardware",1,1,"",false,false);
	$ligne[] = array( $l->g(36),"ssn","bios","",2,1,"",false,true);	
	$ligne[] = array( $l->g(64),"smanufacturer","bios","",2,1,"",false,true);
	$ligne[] = array( $l->g(65),"smodel","bios","",2,1,"",false,true);
	$ligne[] = array( $l->g(284),"bmanufacturer","bios","",2,1,"",false,true);
	$ligne[] = array( $l->g(207),"ipgateway","networks","",2,5,"",false,true);
	$ligne[] = array( $l->g(331),"ipsubnet","networks","",2,5,"",false,true);
	$ligne[] = array( $l->g(95),"macaddr","networks","",2,5,"",false,true);
	$ligne[] = array( $l->g(25),"osname","hardware","SELECT DISTINCT osname FROM ".($_SESSION["usecache"] == true?"hardware_osname_cache":"hardware"),1,1,"",false,false);
	$ligne[] = array( $l->g(286),"oscomments","hardware","SELECT DISTINCT oscomments FROM hardware",2,1,"",false,true);
	$ligne[] = array( $l->g(24),"userid","hardware","",2,1,"",false,true);
	$ligne[] = array( $l->g(377),"processors","hardware","",2,3,"MHZ",false,false);
	$ligne[] = array( $l->g(45),"free","drives","",2,3,"MB",false,false);
	$ligne[] = array( $l->g(257),"regname","hardware","SELECT DISTINCT name FROM ".($_SESSION["usecache"] == true?"registry_name_cache":"registry"),1,6,"",false,true);
	$ligne[] = array( $l->g(554),"smonitor","hardware","",2,1,"",false,true);
	$ligne[] = array( $l->g(555),"fmonitor","hardware","",2,1,"",false,true);
	$ligne[] = array( $l->g(556),"lmonitor","hardware","",2,1,"",false,true);
	$ligne[] = array( $l->g(209),"bversion","bios","",2,1,"",false,true);
	$ligne[] = array( TAG_LBL,"cu","accountinfo","",2,1);

	//HARDCODED OPTIONS
	$ligne[] = array( $l->g(312), "ipdisc");
	$ligne[] = array( $l->g(429), "freq" );
	$ligne[] = array( $l->g(512), "tele" );
	
	foreach($_SESSION["optCol"] AS $col) {
		if($col!="DEVICEID"&&$col!="TAG"&&$col!="HARDWARE_ID") {
			$isDate = isFieldDate($col);
			$ligne[]  =  array( $col,$col,"hardware","accountinfo",2,$isDate ? 2 : 1,"",$isDate,true);
		}
	}
	
	foreach( $ligne as $laLigne) {
		$colATrier[] = $laLigne[0];
	}
	$indLigneSoft = 0;
	sort($colATrier);
	foreach($colATrier as $nomLigne) {
		foreach($ligne as $laLigne) {
			if($laLigne[0] == $nomLigne) {
				afficheLigne($laLigne);
				break;
			}
		}
	}	
	
	$color=$indLigne%2==0?"#F2F2F2":"#FFFFFF";
	echo "<tr bgcolor='$color'><td colspan='3' align='right'><input type='hidden' name='max' value='$indLigne'>";
	
	if($_SESSION["OPT"]!=0)
	{
		echo "<input type=submit taborder=1 name=sub value=".$l->g(30).">";
	}	
	echo "</td></tr></table></form>";
	if($_SESSION["OPT"]!=0)
	{
		echo "<center><i>".$l->g(358)."</i></font></center><br>";
	}	
}

function afficheLigne($ligne)
{	
	global $indLigne,$indLigneSoft,$l,$_POST;	

	$label = $ligne[0];
	$champ = $ligne[1];
	$table = $ligne[2];
	$laRequete = $ligne[3];
	$combo = isset($ligne[4]) ? $ligne[4] : 1 ;
	$type = isset($ligne[5]) ? $ligne[5] : 1 ;
	$leg = isset($ligne[6]) ? $ligne[6] : "" ;
	$isDate = isset($ligne[7]) ? $ligne[7] : false ;
	$allowExact = isset($ligne[8]) ? $ligne[8] : true ;
	$canDisable = isset($ligne[9]) ? $ligne[9] : true ;

	if(is_array($_SESSION["OPT"])) {
		if(!in_array($label,$_SESSION["OPT"]))
			return;
	}
	else
		return;
	
	$color=$indLigne%2==0?"#F2F2F2":"#FFFFFF";
	$suff="_".$indLigne;
	
	if( $type == 7) {// un soft
		echo"<tr bgcolor=$color><td>";
		if( ! $canDisable )
			echo "<input type='hidden' name='act$suff' id='act$suff' value='on'>";
		echo "<input type=checkbox ".($canDisable?"":"disabled checked")." id='act$suff' name='act$suff'".($_SESSION["softs"][$indLigneSoft][0]=="on"||$_POST["selOpt"]==$label?" checked":"").">&nbsp;".$l->g(205)."</input>
			<input type=hidden name='chm$suff' value=$champ>
			<input type=hidden name='lbl$suff' value='".urlencode($label)."'>
		</td><td>$label</td><td>";
		echo "<select OnClick='act$suff.checked=true' name='ega$suff'>";		
		echo "<option".($_SESSION["softs"][$indLigneSoft][2]==$l->g(129)?" selected":"").">".$l->g(129)."</option>";
		if( $allowExact ) echo "<option".(($_SESSION["softs"][$indLigneSoft][2]==$l->g(410) or !isset($_SESSION["softs"][$indLigneSoft][2]))?" selected":"").">".$l->g(410)."</option>";
		echo "<option".($_SESSION["softs"][$indLigneSoft][2]==$l->g(130)?" selected":"").">".$l->g(130)."</option>";
		echo "</select>&nbsp;&nbsp;";
		echo "<input OnClick='act$suff.checked=true' name='val$suff' value=\"".stripslashes($_SESSION["softs"][$indLigneSoft][3])."\">";
		$indLigne++;
		$indLigneSoft++;
		return;
	}

	echo"		
	<tr bgcolor=$color>
		<td>
			<input type=checkbox id='act$suff' name='act$suff'".($_SESSION["reqs"][$label][0]=="on"||$_POST["selOpt"]==$label?" checked":"").">&nbsp;".$l->g(205)."</input>
			<input type=hidden name='chm$suff' value=$champ>
			<input type=hidden name='lbl$suff' value='".urlencode($label)."'>
		</td>
		<td>
			$label
		</td>
		<td>";	
		
	if( $champ == "ipdisc" ) {	
		echo "<select OnClick='act$suff.checked=true' name='val$suff'>
		<option ".($_SESSION["reqs"][$label][3]=="elu"?" selected":"")." value='elu'>".$l->g(502)."</option>
		<option ".($_SESSION["reqs"][$label][3]=="for"?" selected":"")." value='for'>".$l->g(503)."</option>
		<option ".($_SESSION["reqs"][$label][3]=="nelu"?" selected":"")." value='nelu'>".$l->g(504)."</option>
		<option ".($_SESSION["reqs"][$label][3]=="eli"?" selected":"")." value='eli'>".$l->g(505)."</option>
		<option ".($_SESSION["reqs"][$label][3]=="neli"?" selected":"")." value='neli'>".$l->g(506)."</option></select></td></tr>";
		$indLigne++;
		return;	
	}
	else if( $champ == "freq" ) {
		echo "<select OnClick='act$suff.checked=true' name='val$suff'>
		<option ".($_SESSION["reqs"][$label][3]=="std"?" selected":"")." value='std'>".$l->g(488)."</option>
		<option ".($_SESSION["reqs"][$label][3]=="always"?" selected":"")." value='always'>".$l->g(485)."</option>
		<option ".($_SESSION["reqs"][$label][3]=="never"?" selected":"")." value='never'>".$l->g(486)."</option>
		<option ".($_SESSION["reqs"][$label][3]=="custom"?" selected":"")." value='custom'>".$l->g(487)."</option></select></td></tr>";
		$indLigne++;
		return;	
	}
	else if( $champ == "tele" ) {
		
		$resTele = @mysql_query("SELECT distinct NAME FROM download_available ORDER BY NAME", $_SESSION["readServer"]);
		
		if( mysql_num_rows( $resTele ) >0 ) {		
			echo "<select OnClick='act$suff.checked=true' name='ega$suff'>
			<option ".($_SESSION["reqs"][$label][2]=="ayant"?" selected":"")." value='ayant'>".$l->g(507)."</option>
			<option ".($_SESSION["reqs"][$label][2]=="nayant"?" selected":"")." value='nayant'>".$l->g(508)."</option>
			</select>  ".$l->g(498).": <select OnClick='act$suff.checked=true' name='val$suff'>";
			while( $valTele = mysql_fetch_array( $resTele )) {
				echo "<option ".($_SESSION["reqs"][$label][3]==$valTele["NAME"]?" selected":"").">".$valTele["NAME"]."
				</option>";	
			}				
			
			echo "</select> ".$l->g(546).": <select OnClick='act$suff.checked=true' name='val2$suff'>
			<option ".($_SESSION["reqs"][$label][4]=="ind"?" selected":"")." value='ind'>".$l->g(509)."</option>
			<option ".($_SESSION["reqs"][$label][4]=="nsuc"?" selected":"")." value='nsuc'>".$l->g(548)."</option>
			<option ".($_SESSION["reqs"][$label][4]=="suc"?" selected":"")." value='suc'>SUCCESS</option>
			<option ".($_SESSION["reqs"][$label][4]=="stats"?" selected":"")." value='stats'>".$l->g(482)."</option>";
			
			$resState = @mysql_query("SELECT distinct(tvalue) FROM devices WHERE tvalue<>'SUCCESS' AND tvalue IS NOT NULL AND name='DOWNLOAD'", $_SESSION["readServer"]);
			while( $valState = @mysql_fetch_array( $resState )) {
				echo "<option ".($_SESSION["reqs"][$label][4]==$valState["tvalue"]?" selected":"")." value='".$valState["tvalue"]."'>".$valState["tvalue"]."</option>";
			}	 
			
			echo "</select>";
			$indLigne++;
		}
		else {
			echo $l->g(510);	
		}
		return;	
	}
		
		if($type != 4 && $type != 6) {
					echo $_SESSION["reqs"][$label][2];
			echo "<select OnClick='act$suff.checked=true' name='ega$suff'>			
			<option".($_SESSION["reqs"][$label][2]==$l->g(129)?" selected":"").">".$l->g(129)."</option>";
			if( $allowExact ) echo "<option".(($_SESSION["reqs"][$label][2]==$l->g(410) or !isset($_SESSION["reqs"][$label][2]))?" selected":"").">".$l->g(410)."</option>";
			echo "<option".($_SESSION["reqs"][$label][2]==$l->g(130)?" selected":"").">".$l->g(130)."</option>";
	
			if( $isDate) {
				echo "<option".($_SESSION["reqs"][$label][2]==$l->g(346)?" selected":"").">".$l->g(346)."</option><option".($_SESSION["reqs"][$label][2]==$l->g(347)?" selected":"").">".$l->g(347)."</option>"; 
			}
			else if( $type==2||$type==3 )
			{
				echo "<option".($_SESSION["reqs"][$label][2]==$l->g(201)?" selected":"").">".$l->g(201)."</option><option".($_SESSION["reqs"][$label][2]==$l->g(202)?" selected":"").">".$l->g(202)."</option>";
			}
			if ($type==3)
			{
				echo "<option".($_SESSION["reqs"][$label][2]==$l->g(203)?" selected":"").">".$l->g(203)."</option>";//<option".($_POST["ega$suff"]==$l->g(204)?" selected":"").">".$l->g(204)."</option>";		
			}
		}
		else if( $type != 6)
			echo $l->g(129);
		if( $type != 6)
			echo "</select>&nbsp;&nbsp;";
			
	if($combo==1)
	{
		echo "<select OnClick='act$suff.checked=true' name='val$suff'>";	
		$res=mysql_query($laRequete, $_SESSION["readServer"]) or die(mysql_error($_SESSION["readServer"]));
		
		$linSel = "LINUX"   == $_SESSION["reqs"][$label][3] ?" selected":"";
		$winSel = "WINDOWS" == $_SESSION["reqs"][$label][3] ?" selected":"";
		$macOSX = "MacOSX"  == $_SESSION["reqs"][$label][3] ?" selected":""; //add by WES Young
		
		if( $champ=="osname")
			echo "<option value='LINUX' $linSel>LINUX (".$l->g(547).")</option>
				  <option value='WINDOWS' $winSel>WINDOWS (".$l->g(547).")</option>
				  <option value='MacOSX' $macOSX>MacOSX (".$l->g(547).")</option>";//add by WES Young
				 
		while($row=mysql_fetch_array($res))
		{
			if($row[0]=="") continue;	
			$selected = $row[0]== $_SESSION["reqs"][$label][3] ?" selected":"";
			echo "<option$selected>".$row[0]."</option>\n";	
		}
		
		echo "</select>";
	}
	else
	{
		if( $isDate ) {
			echo "<input READONLY ".dateOnClick("val$suff","act$suff")." OnClick='act$suff.checked=true' name='val$suff' id='val$suff' value='"./*dateFromMysql(*/$_SESSION["reqs"][$label][3]/*)*/."'>".datePick("val$suff","act$suff");
		}
		else
			echo "<input OnClick='act$suff.checked=true' name='val$suff' value=\"".stripslashes($_SESSION["reqs"][$label][3])."\">";
		
		if ($type==3) // deux inputs pour "entre machin et truc"
		{
			echo "&nbsp;&nbsp;--&nbsp;&nbsp;<input OnClick='act$suff.checked=true' name='val2$suff' value='".$_SESSION["reqs"][$label][4]."'>";
		}	
	}
	if( $type == 6) {
			echo "<select OnClick='act$suff.checked=true' name='ega$suff'>			
			<option".($_SESSION["reqs"][$label][2]==$l->g(129)?" selected":"").">".$l->g(129)."</option>";
			if( $allowExact ) echo "<option".(($_SESSION["reqs"][$label][2]==$l->g(410) or !isset($_SESSION["reqs"][$label][2]))?" selected":"").">".$l->g(410)."</option>";
			echo "<option".($_SESSION["reqs"][$label][2]==$l->g(130)?" selected":"").">".$l->g(130)."</option></select>";
			/*$reqRes = mysql_query("SELECT DISTINCT(regvalue) FROM registry", $_SESSION["readServer"]) or die(mysql_error($_SESSION["readServer"])); // mesmachines
			echo "&nbsp;&nbsp;".$l->g(224).":&nbsp;&nbsp;*/
			echo "<input OnClick='act$suff.checked=true' name='valreg$suff' value='".($_SESSION["reqs"][$label][5])."'>";
					
			/*while($row=mysql_fetch_array($reqRes))
			{
				if($row[0]=="") continue;	
				$selected = $row[0]== $_SESSION["reqs"][$label][5] ?" selected":"";
				echo "<option$selected>".$row[0]."</option>\n";	
			}*/
			
			echo "</input>";			
	}	
	
	echo "&nbsp;&nbsp;&nbsp;$leg</td></tr>";
	$indLigne++;
}

function getCache( $table, $field, $value, &$count ) {
	$reqCache = "SELECT ".$field." FROM ".$table."_".$field."_cache WHERE ".$field." LIKE '%".$value."%' AND ".$field." IS NOT NULL";	 
	$resCache = mysql_query( $reqCache, $_SESSION["readServer"] );
	$cached = array();
	while( $valCache = mysql_fetch_array( $resCache ) ) {
		if( $count > 200 )
			return NULL;
		$cached[] = $valCache[$field];
		$count++;							
	}
	
	$glued = @implode("','", $cached);
	return $glued;
}

function isFieldDate($nom) {
	if( $nom == "lastdate" )
		return true;
		
	$reqType = "SELECT $nom FROM accountinfo";
	if( $resType = @mysql_query($reqType, $_SESSION["readServer"])) {
		$valType = mysql_fetch_field($resType);
		return ($valType->type == "date");
	}
	return false;
}

		
?>
