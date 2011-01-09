 <?php

##############################################################################
# *                                                                          #
# * 2MOONS                                                                   #
# *                                                                          #
# * @copyright Copyright (C) 2010 By ShadoX from titanspace.de               #
# *                                                                          #
# *	                                                                         #
# *  This program is free software: you can redistribute it and/or modify    #
# *  it under the terms of the GNU General Public License as published by    #
# *  the Free Software Foundation, either version 3 of the License, or       #
# *  (at your option) any later version.                                     #
# *	                                                                         #
# *  This program is distributed in the hope that it will be useful,         #
# *  but WITHOUT ANY WARRANTY; without even the implied warranty of          #
# *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           #
# *  GNU General Public License for more details.                            #
# *                                                                          #
##############################################################################

if ($USER['rights'][str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__)] != 1) exit;
set_time_limit(0);

function exitupdate($LOG){
	$Page	= "";
	if(is_array($LOG['debug'])) {
		foreach($LOG['debug'] as $key => $content) {
			$Page .= $content."<br>";
		}
	}
	
	if(is_array($LOG['update'])) {
		foreach($LOG['update'] as $rev => $content) {
			foreach($content as $file => $status) {
				$Page.= "File ".$file." (Rev. ".$rev."): ".$status."<br>";
			}
		}
	}
		
	if(is_array($LOG['finish'])) {	
		foreach($LOG['finish'] as $key => $content) {
			$Page .= $content."<br>";
		}
	}
	$Page .= "<br><a href='?page=update'>".$LNG['up_weiter']."</a>";

	$template = new template();
	$template->message($Page, false, 0, true);
				
	exit;
}

function ShowUpdatePage()
{
	global $LNG, $CONF, $db;
	if(isset($_REQUEST['version']))
	{
		$Temp	= explode('.', $_REQUEST['version']);
		$Temp	= array_map('intval', $Temp);
		update_config(array('VERSION' => $Temp[0].'.'.$Temp[1].'.'.$Temp[2]), true);
	}
	
	$Patchlevel	= explode(".",$CONF['VERSION']);
	if($_REQUEST['action'] == 'history')
		$Level		= 0;	
	elseif(isset($Patchlevel[2]))
		$Level		= $Patchlevel[2];
	else
		$Level		= 1060;
		
	$opts 			= array('http' => array('method'=> "GET", 'header'=> "Patchlevel: ".$Level."\r\n".$LNG['up_agent']."".$Patchlevel[2].")\r\n"));
			
	$context 		= stream_context_create($opts);
	
	switch($_REQUEST['action'])
	{
		case "download":
			require_once(ROOT_PATH.'includes/libs/zip/zip.lib.'.PHP_EXT);
			$UpdateArray 	= unserialize(@file_get_contents("http://update.xnova.de/index.php?action=getupdate",FALSE,$context));
			if(!is_array($UpdateArray['revs']))
				exitupdate(array('debug' => array('noupdate' => $LNG['up_kein_update'])));
				
			$SVN_ROOT		= $UpdateArray['info']['svn'];
			
			$zipfile 	= new zipfile();
			$TodoDelete	= "";
			$Files		= array('add' => array(), 'edit' => array(), 'del' => array());
			foreach($UpdateArray['revs'] as $Rev => $RevInfo) 
			{
				if(!empty($RevInfo['add']))
				{
					foreach($RevInfo['add'] as $File)
					{	
						if(in_array($File, $Files['add']) || strpos($File, '.') === false)
							continue;
							
						$Files['add'][] = $File;
						
						$zipfile->addFile(@file_get_contents($SVN_ROOT.$File), str_replace("/trunk/", "", $File), $RevInfo['timestamp']);					
					}
				}
				if(!empty($RevInfo['edit']))
				{
					foreach($RevInfo['edit'] as $File)
					{	
						if(in_array($File, $Files['edit']) || strpos($File, '.') === false) 
							continue;
							
							$Files['edit'][] = $File;
							
							$zipfile->addFile(@file_get_contents($SVN_ROOT.$File), str_replace("/trunk/", "", $File), $RevInfo['timestamp']);
						
					}
				}
				if(!empty($RevInfo['del']))
				{
					foreach($RevInfo['del'] as $File)
					{
						if(in_array($File, $Files['del']) || strpos($File, '.') === false)
							continue;
						$Files['del'][] = $File;

						$TodoDelete	.= str_replace("/trunk/", "", $File)."\r\n";
					}
				}
				$LastRev = $Rev;
			}	
			
			if(!empty($TodoDelete))
				$zipfile->addFile($TodoDelete, "!TodoDelete!.txt", $RevInfo['timestamp']);
			
			update_config(array('VERSION' => $Patchlevel[0].".".$Patchlevel[1].".".$LastRev), true);
			// Header f�r Download senden
			$File	= $zipfile->file(); 		
			header("Content-length: ".strlen($File));
			header("Content-Type: application/force-download");
			header('Content-Disposition: attachment; filename="patch_'.$Level.'_to_'.$LastRev.'.zip"');
			header("Content-Transfer-Encoding: binary");

			// Zip File senden
			echo $File; 
			exit;			
		break;
		case "update":
			require_once(ROOT_PATH.'includes/libs/ftp/ftp.class.'.PHP_EXT);
			require_once(ROOT_PATH.'includes/libs/ftp/ftpexception.class.'.PHP_EXT);
			$UpdateArray 	= unserialize(@file_get_contents("http://update.xnova.de/index.php?action=getupdate",FALSE,$context));
			if(!is_array($UpdateArray['revs']))
				exitupdate(array('debug' => array('noupdate' => $LNG['up_kein_update'])));
				
			$SVN_ROOT		= $UpdateArray['info']['svn'];
			$CONFIG 		= array("host" => $CONF['ftp_server'], "username" => $CONF['ftp_user_name'], "password" => $CONF['ftp_user_pass'], "port"     => 21 ); 
			try
			{
				$ftp = FTP::getInstance(); 
				$ftp->connect($CONFIG);
				$LOG['debug']['connect']	= $LNG['up_ftp_ok'];
			}
			catch (FTPException $error)
			{
				$LOG['debug']['connect']	= $LNG['up_ftp_error']."".$error->getMessage();
				exitupdate($LOG);
			}	
						
			if($ftp->changeDir($CONF['ftp_root_path']))
			{
				$LOG['debug']['chdir']	= $LNG['up_ftp_change']."".$CONF['ftp_root_path']."): ".$LNG['up_ftp_ok'];
			}
			else
			{
				$LOG['debug']['chdir']	= $LNG['up_ftp_change']."".$CONF['ftp_root_path']."): ".$LNG['up_ftp_change_error'];
				exitupdate($LOG);
			}
			$Files	= array('add' => array(), 'edit' => array(), 'del' => array());
			foreach($UpdateArray['revs'] as $Rev => $RevInfo) 
			{
				if(!empty($RevInfo['add']))
				{
					foreach($RevInfo['add'] as $File)
					{
						if(in_array($File, $Files['add']))
							continue;	
						$Files['add'][] = $File;
						if($File == "/trunk/updates/update_".$Rev.".sql") {
							$db->multi_query(str_replace("prefix_", DB_PREFIX, @file_get_contents($SVN_ROOT.$File)));
							continue;
						} elseif($File == "/trunk/updates/update_".$Rev.".php") {
							require($SVN_ROOT.$File);
						} else {
							if (strpos($File, '.') !== false) {		
								$Data = fopen($SVN_ROOT.$File, "r");
								if ($ftp->uploadFromFile($Data, str_replace("/trunk/", "", $File))) {
									$LOG['update'][$Rev][$File]	= $LNG['up_ok_update'];

								} else {
									$LOG['update'][$Rev][$File]	= $LNG['up_error_update'];
								}
								fclose($Data);
							} else {
								if ($ftp->makeDir(str_replace("/trunk/", "", $File), 1)) {
									if(PHP_SAPI == 'apache2handler')
										$ftp->chmod(str_replace("/trunk/", "", $File), '0777');
									else
										$ftp->chmod(str_replace("/trunk/", "", $File), '0755');
										
									$LOG['update'][$Rev][$File]	= $LNG['up_ok_update'];
								} else {
									$LOG['update'][$Rev][$File]	= $LNG['up_error_update'];
								}				
							}
						}
					}
				}
				if(!empty($RevInfo['edit']))
				{
					foreach($RevInfo['edit'] as $File)
					{	
						if(in_array($File, $Files['edit']))
							continue;
						$Files['edit'][] = $File;
						if (strpos($File, '.') !== false) {
							if($File == "/trunk/updates/update_".$Rev.".sql")
							{
								$db->multi_query(str_replace("prefix_", DB_PREFIX, @file_get_contents($SVN_ROOT.$File)));
								continue;
							} else {
								$Data = fopen($SVN_ROOT.$File, "r");
								if ($ftp->uploadFromFile($Data, str_replace("/trunk/", "", $File))) {
									$LOG['update'][$Rev][$File]	= $LNG['up_ok_update'];
								} else {
									$LOG['update'][$Rev][$File]	= $LNG['up_error_update'];
								}
								fclose($Data);
							}
						}
					}
				}
				if(!empty($RevInfo['del']))
				{
					foreach($RevInfo['del'] as $File)
					{
						if(in_array($File, $Files['del']))
							continue;
							
						$Files['del'][] = $File;
						if (strpos($File, '.') !== false) {
							if ($ftp->delete(str_replace("/trunk/", "", $File))) {
								$LOG['update'][$Rev][$File]	= $LNG['up_delete_file'];
							} else {
								$LOG['update'][$Rev][$File]	= $LNG['up_error_delete_file'];
							}
						} else {
							if ($ftp->removeDir(str_replace("/trunk/", "", $File), 1 )) {
								$LOG['update'][$Rev][$File]	= $LNG['up_delete_file'];
							} else {
								$LOG['update'][$Rev][$File]	= $LNG['up_error_delete_file'];
							}						
						}
					}
				}
				$LastRev = $Rev;
			}
			$LOG['finish']['atrev'] = $LNG['up_update_ok_rev']." ".$LastRev;
			// Verbindung schlie�en
			ClearCache();
			update_config(array('VERSION' => $Patchlevel[0].".".$Patchlevel[1].".".$LastRev), true);
			exitupdate($LOG);
		break;
		default:
			$template 	= new template();
			
			$RevList	= '';
			$Update		= '';
			$Info		= '';
			
			if(!function_exists('file_get_contents') || !function_exists('fsockopen') || ini_get('allow_url_fopen') == 0) {
				$template->message($LNG['up_error_fsockopen'], false, 0, true);
			} 
			ob_start();
			echo file_get_contents("http://update.xnova.de/index.php?action=update", FALSE, $context);
			$Data 	= ob_get_clean();
			if(false === ($UpdateArray = unserialize($Data))) {
				$template->message($LNG['up_update_server']."<br>".substr(strip_tags($Data), strpos(strip_tags($Data), 'failed to open stream: ') + 23), false, 0, true);
			} else {
				if(is_array($UpdateArray['revs']))
				{
					foreach($UpdateArray['revs'] as $Rev => $RevInfo) 
					{
						if(!(empty($RevInfo['add']) && empty($RevInfo['edit'])) && $Patchlevel[2] < $Rev){
							$Update		= "<tr><th><a href=\"?page=update&amp;action=update\">Update</a>".(function_exists('gzcompress') ? " - <a href=\"?page=update&amp;action=download\">".$LNG['up_download_patch_files']."</a>":"")."</th></tr>";
							$Info		= "<tr><td class=\"c\" colspan=\"5\">".$LNG['up_aktuelle_updates']."</td></tr>";
						}
						
						$edit	= "";
						if(!empty($RevInfo['edit']) || is_array($RevInfo['edit'])){
							foreach($RevInfo['edit'] as $file) {							
								$edit	.= '<a href="http://code.google.com/p/2moons/source/diff?spec=svn'.$Rev.'&r='.$Rev.'&format=side&path='.$file.'" target="diff">'.str_replace("/trunk/", "", $file).'</a><br>';
							}
						}

						$RevList .= "<tr>
						".(($Patchlevel[2] == $Rev)?"<th colspan=5>".$LNG['up_momentane_version']."</th></tr><tr>":((($Patchlevel[2] - 1) == $Rev)?"<th colspan=5>".$LNG['up_alte_updates']."</th></tr><tr>":""))."
						<th>".(($Patchlevel[2] == $Rev)?"<font color=\"red\">":"")."".$LNG['up_revision']."" . $Rev . " ".date("d. M y H:i:s", $RevInfo['timestamp'])." ".$LNG['ml_from']." ".$RevInfo['author'].(($Patchlevel[2] == $Rev)?"</font>":"")."</th></tr>
						<tr><td>".makebr($RevInfo['log'])."</th></tr>
						".((!empty($RevInfo['add']))?"<tr><td>".$LNG['up_add']."<br>".str_replace("/trunk/", "", implode("<br>\n", $RevInfo['add']))."</b></td></tr>":"")."
						".((!empty($RevInfo['edit']))?"<tr><td>".$LNG['up_edit']."<br>".$edit."</b></td></tr>":"")."
						".((!empty($RevInfo['del']))?"<tr><td>".$LNG['up_del']."<br>".str_replace("/trunk/", "", implode("<br>\n", $RevInfo['del']))."</b></td></tr>":"")."
						</tr>";
					}
				}
								
				$template->assign_vars(array(	
					'version'	=> $CONF['VERSION'],
					'RevList'	=> $RevList,
					'Update'	=> $Update,
					'Info'		=> $Info,
				));
					
				$template->show('adm/UpdatePage.tpl');
			}
		break;
	}
}
?>