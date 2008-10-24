<?php
/**
 * Simple mail client
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package apps-mail
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_MailClientCommon extends ModuleCommon {
	public static function user_settings() {
		if(Acl::is_user()) return array('Mail accounts'=>'account_manager','Mail settings'=>array(
					array('name'=>'default_dest_mailbox','label'=>'Messages from epesi users deliver to', 'type'=>'select', 'values'=>array('both'=>'Private message and contact mail', 'mail'=>'Mail only', 'pm'=>'Private message only'), 'default'=>'both'),
			)
		);
		return array();
	}

	public static function account_manager_access() {
		return Acl::is_user();
	}

	public static function applet_caption() {
		return "Mail indicator";
	}

	public static function applet_info() {
		return "Checks if there is new mail";
	}

	public static function applet_settings() {
		$ret = DB::GetAll('SELECT id,mail FROM apps_mailclient_accounts WHERE user_login_id=%d',array(Acl::get_user()));
		$conf = array(array('type'=>'header','label'=>'Choose accounts'));
		if(empty($ret))
			$ret[] = array('id'=>Apps_MailClientCommon::create_internal_mailbox(), 'mail'=>'#internal');

		foreach($ret as $row)
			if($row['mail']==='#internal')
				$conf[] = array('name'=>'account_'.$row['id'], 'label'=>Base_LangCommon::ts('Apps_MailClient','Private messages'), 'type'=>'checkbox', 'default'=>1);
			else
				$conf[] = array('name'=>'account_'.$row['id'], 'label'=>$row['mail'], 'type'=>'checkbox', 'default'=>0);
		if(count($conf)==1)
			return array(array('type'=>'static','label'=>'No accounts configured, go Home->My settings->Mail accounts'));
		return $conf;
	}

	public static function menu() {
		return array('Mail client'=>array());
	}

	public static function admin_caption() {
		return 'Mail client settings';
	}

	////////////////////////////////////////////////////
	// scan mail dir, etc

	public static function create_mailbox_dir($id) {
		//TODO: check protocol, on imap get lsub and create dirs
		$acc_dir = self::Instance()->get_data_dir().$id.'/';
		mkdir($acc_dir,0777,true);
		$dirs = array('Inbox','Sent','Trash','Drafts');
		file_put_contents($acc_dir.'.dirs',implode(",",$dirs));
		foreach($dirs as $d)
			mkdir($acc_dir.$d);
	}

	public static function create_internal_mailbox($user=null) {
		if($user===null) $user = Acl::get_user();
		DB::Execute('INSERT INTO apps_mailclient_accounts(user_login_id,mail,login,password,incoming_server,incoming_protocol) VALUES(%d,\'#internal\',\'\',\'\',\'\',2)',array($user));
		$id = DB::Insert_ID('apps_mailclient_accounts','id');
		Apps_MailClientCommon::create_mailbox_dir($id);
		return $id;
	}

	//gets mailbox dir
	public static function get_mailbox_dir($id) {
		$acc_dir = self::Instance()->get_data_dir().$id.'/';
		if(!file_exists($acc_dir))
			trigger_error('Invalid mailbox id: '.$id,E_USER_ERROR);
		return $acc_dir;
	}

	//gets array of folders in mailbox
	public static function get_mailbox_structure($id,$mdir='') {
		$st = array();
		$mbox_dir = self::get_mailbox_dir($id);
		$cont = @file_get_contents($mbox_dir.$mdir.'.dirs');
		if($cont===false) return array();
		$cont = explode(",",$cont);
		foreach($cont as $f) {
			$path = $mdir.$f;
			if(is_dir($mbox_dir.$path) && is_readable($mbox_dir.$path) && is_writable($mbox_dir.$path))
				$st[$f] = self::get_mailbox_structure($id,$path.'/');
		}
		return $st;
	}

	//gets next message id
	public static function get_next_msg_id($id,$dir) {
		$mailbox = self::get_mailbox_dir($id).$dir;
		$nid = @file_get_contents($mailbox.'.mid');
		if($nid===false || !is_numeric($nid)) {
			$nid = -1;
			$files = scandir($mailbox);
			foreach($files as $f) {
				if(is_numeric($f) && $nid<$f)
					$nid=$f;
			}
		}
		$nid++;
		file_put_contents($mailbox.'.mid',$nid);
		return $nid;
	}

	//drops message to specified mailbox
	public static function drop_message($mailbox_id,$dir,$subject,$from,$to,$date,$body,$read=false) {
		ini_set('include_path','modules/Apps/MailClient/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
		require_once('Mail/mime.php');

		$msg_id = self::get_next_msg_id($mailbox_id,$dir);
		if($msg_id===false) return false;

		$mime = new Mail_Mime();
		$headers = array();
        $headers['From'] = $from;
        $headers['To'] = $to;
	 	$headers['Subject'] = $subject;
		$headers['Date'] = $date;
		$mime->headers($headers);
		$mime->setHTMLBody($body);
		$mbody = $mime->getMessage();
		$mailbox = self::get_mailbox_dir($mailbox_id).$dir;
		file_put_contents($mailbox.$msg_id,$mbody);
		Apps_MailClientCommon::append_msg_to_index($mailbox_id,$dir,$msg_id,$subject,$from,$to,$date,strlen($mbody),$read);

		return true;
	}

/*
	private function _get_mail_dir_structure() {
		$mdir = $this->_get_mail_dir();
		$accounts = DB::GetAll('SELECT * FROM apps_mailclient_accounts WHERE user_login_id=%d',array(Acl::get_user()));
		$st = array();
		$st[] = array('name'=>'internal','label'=>Base_LangCommon::ts('Apps_MailClient','Private messages'),'sub'=>$this->_get_mail_account_structure($mdir.'internal/'));
		foreach($accounts as $v) {
			$name = $v['mail'];
			$path = $mdir.self::mailname2dirname($name);
			$ref = false;
			if($v['incoming_protocol']==0) {///pop3
				$imap = false;
				$sub = $this->_get_mail_account_structure($path.'/');
			} else { //imap
				$sub = array();
				if(function_exists('imap_open')) {
					list($imap,$ref) = self::imap_open($v);
					if ($imap!==false) {
						if(!file_exists($path)) { //download directory structure
							$sub = self::imap_refresh_folders($imap,$ref,$v['mail']);
							if($sub===false) { //cannot download folders
								$name = '<span style="color:red" '.Utils_TooltipCommon::open_tag_attrs(implode(', ',imap_errors()),false).'>'.$name.'</span>';
							}
						} else {
							$sub = $this->_get_mail_account_structure($path.'/');
						}
					} else { //cannot connect
						$name = '<span style="color:red" '.Utils_TooltipCommon::open_tag_attrs(implode(', ',imap_errors()),false).'>'.$name.'</span>';
					}
				} else { //imap not supported
					$imap = false;
					$name = '<span style="color:red" '.Utils_TooltipCommon::open_tag_attrs('php_imap library not installed',false).'>'.$name.'</span>';
				}
			}
			$acc = array('name'=>$name,'sub'=>$sub, 'imap'=>$imap,'id'=>$v['id']);
			if($imap!==false && $ref!==false) $acc['imap_ref']=$ref;
			$st[] = $acc;
		}
		return $st;
	}

	public static function imap_open($v) {
		if(!is_array($v))
			$v = DB::GetRow('SELECT * FROM apps_mailclient_accounts WHERE id=%d',array($v));

		$ssl = $v['incoming_ssl'];
		$host = explode(':',$v['incoming_server']);
		if(isset($host[1])) $port=$host[1];
			else {
				if($ssl)
					$port = '993';
				else
					$port = '143';
			}
		$host = $host[0];
		$user = $v['login'];
		$pass = $v['password'];

		$imap_ref = '{'.$host.':'.$port.'/imap'.($ssl?'/ssl/novalidate-cert':'').'}';
		$imap = @imap_open($imap_ref, $user,$pass, OP_HALFOPEN);
		return array(& $imap, $imap_ref, $v['mail']);
	}

	public static function imap_refresh_folders($id,$ref=null,$account=null) {
		if($ref===null) {
			list($imap,$ref,$account) = self::imap_open($id);
		} else {
			$imap = $id;
		}
		$sub = false;
		if ($imap!==false) {
			if(is_array($list = imap_lsub($imap, $ref, "*"))) {
				$imap_ref_len = strlen($ref);
				$sub = array();
				$dir = self::Instance()->get_data_dir().Acl::get_user().'/'; //mail data dir
				if(!file_exists($dir)) mkdir($dir);
				$acc_dir = $dir.self::mailname2dirname($account).'/';
				if(!file_exists($acc_dir))
					mkdir($acc_dir);
		    	foreach ($list as $val) {
					$box = imap_utf7_decode($val);
					$box = substr($box,$imap_ref_len);
					if(!file_exists($acc_dir.$box))
						mkdir($acc_dir.$box,0777,true);

					$x = explode('/',$box);
					$y = & $sub;
					foreach($x as $v) {
						if(!isset($y[$v])) $y[$v] = array('label'=>$v, 'name'=>$box, 'sub'=>array());
							$y = & $y[$v]['sub'];
			    	}
				}
			}
			imap_close($imap);
		}
		return $sub;
	}

 */
	public static function build_index($id,$dir) {
		ini_set('include_path','modules/Apps/MailClient/PEAR'.PATH_SEPARATOR.ini_get('include_path'));
		require_once('Mail/mimeDecode.php');
		$boxpath = self::get_mailbox_dir($id).$dir;
		$out = @fopen($boxpath.'.idx','w');
		if($out==false) return false;
		$files = scandir($boxpath);
		$c = 0;
		$max = 0;
		foreach($files as $f) {
			if(!is_numeric($f)) continue;
			$message = @file_get_contents($boxpath.$f);
			if($message===false) continue;
			$decode = new Mail_mimeDecode($message, "\r\n");
			$structure = $decode->decode();
			if(!isset($structure->headers['from']) || !isset($structure->headers['to']) || !isset($structure->headers['date']))
				continue;
			fputcsv($out, array($f,isset($structure->headers['subject'])?substr($structure->headers['subject'],0,256):'no subject',substr($structure->headers['from'],0,256),substr($structure->headers['to'],0,256),substr($structure->headers['date'],0,64),substr(strlen($message),0,64),'0'));
			$c++;
			if($f>$max) $max=$f;
		}
		fclose($out);
		file_put_contents($boxpath.'.num',$c.','.$c);
		file_put_contents($boxpath.'.mid',$max);
		return true;
	}

	public static function mark_all_as_read($id,$dir) {
		$box = self::get_mailbox_dir($id).$dir;
		$in = @fopen($box.'.idx','r');
		if($in==false) return false;
		$ret = array();
		while (($data = fgetcsv($in, 700)) !== false) { //teoretically max is 640+integer and commas
			$num = count($data);
			if($num!=7) continue;
			$data[6]=1;
			$ret[] = $data;
		}
		fclose($in);

		$out = @fopen($box.'.idx','w');
		if($out==false) return false;
		$c = 0;
		foreach($ret as $d) {
			fputcsv($out, $d);
			$c++;
		}
		fclose($out);
		file_put_contents($box.'.num',$c.',0');
	}

	public static function get_index($id,$dir) {
		$box = self::get_mailbox_dir($id).$dir;
		if(!file_exists($box.'.idx')) self::build_index($id,$dir);
		$in = @fopen($box.'.idx','r');
		if($in===false) return false;
		$ret = array();
		while (($data = fgetcsv($in, 700)) !== false) { //teoretically max is 640+integer and commas
			$num = count($data);
			if($num!=7) continue;
			$ret[$data[0]] = array('from'=>$data[2], 'to'=>$data[3], 'date'=>$data[4], 'subject'=>$data[1], 'size'=>$data[5],'read'=>$data[6]);
		}
		fclose($in);
		return $ret;
	}

	public static function remove_msg($mailbox_id, $dir, $id) {
		if(!self::remove_msg_from_index($mailbox_id,$dir,$id)) return false;

		$boxpath = self::get_mailbox_dir($mailbox_id).$dir;
		@unlink($boxpath.$id);

		return true;
	}

	public static function remove_msg_from_index($mailbox_id,$dir,$id) {
		$idx = self::get_index($mailbox_id,$dir);

		if($idx===false || !isset($idx[$id])) return false;
		unset($idx[$id]);

		$box = Apps_MailClientCommon::get_mailbox_dir($mailbox_id).$dir;
		$out = @fopen($box.'.idx','w');
		if($out==false) return false;

		$c = 0;
		$ur = 0;
		foreach($idx as $id=>$d) {
			fputcsv($out, array($id,substr($d['subject'],0,256),substr($d['from'],0,256),substr($d['to'],0,256),substr($d['date'],0,64),substr($d['size'],0,64),$d['read']));
			if(!$d['read']) $ur++;
			$c++;
		}
		fclose($out);
		file_put_contents($box.'.num',$c.','.$ur);
		return true;
	}

	public static function move_msg($box, $dir, $box2, $dir2, $id) {
		$boxpath = self::get_mailbox_dir($box).$dir;
		$boxpath2 = self::get_mailbox_dir($box2).$dir2;
		$msg = @file_get_contents($boxpath.$id);
		if($msg===false) return false;

		$id2 = self::get_next_msg_id($box2,$dir2);
		if($id2===false) return false;

		file_put_contents($boxpath2.$id2,$msg);
		$idx = self::get_index($box,$dir);
		$idx = $idx[$id];
		if(!self::append_msg_to_index($box2,$dir2,$id2,$idx['subject'],$idx['from'],$idx['to'],$idx['date'],$idx['size'],$idx['read']))
			return false;

		if(!self::remove_msg($box,$dir,$id)) return false;

		if($dir=='Trash/') {		
			$trashpath = $boxpath.'.del';
			$in = @fopen($trashpath,'r');
			if($in!==false) {
				$ret = array();
				while (($data = fgetcsv($in, 700)) !== false) {
					$num = count($data);
					if($num!=2 || $data[0]==$id) continue;
					$ret[] = $data;
				}
				fclose($in);
				$out = @fopen($trashpath,'w');
				if($out!==false) {
					foreach($ret as $v)
						fputcsv($out,$v);
					fclose($out);
				}
			}
		}
		return $id2;
	}

	public static function append_msg_to_index($box,$dir, $id, $subject, $from, $to, $date, $size,$read=false) {
		$mailbox = self::get_mailbox_dir($box).$dir;
		$num = @file_get_contents($mailbox.'.num');
		if($num===false) {
			self::build_index($box,$dir);
			$num = @file_get_contents($mailbox.'.num');
			if($num===false) return false;
		}
		$num = explode(',',$num);
		if(count($num)!=2) return false;
		$out = @fopen($mailbox.'.idx','a');
		if($out==false) return false;
		fputcsv($out,array($id, substr($subject,0,256), substr($from,0,256), substr($to,0,256), substr($date,0,64), substr($size,0,64),$read?'1':'0'));
		fclose($out);
		file_put_contents($mailbox.'.num',($num[0]+1).','.($num[1]+($read?0:1)));
		return true;
	}

	public static function read_msg($box,$dir, $id) {
		$idx = self::get_index($box,$dir);

		if($idx===false || !isset($idx[$id])) return false;
		if($idx[$id]['read']) return true;
		$idx[$id]['read'] = '1';

		$box = Apps_MailClientCommon::get_mailbox_dir($box).$dir;

		$num = @file_get_contents($box.'.num');
		if($num===false) return false;
		$num = explode(',',$num);
		if(count($num)!=2) return false;

		$out = @fopen($box.'.idx','w');
		if($out==false) return false;

		foreach($idx as $id=>$d)
			fputcsv($out, array($id,substr($d['subject'],0,256),substr($d['from'],0,256),substr($d['to'],0,256),substr($d['date'],0,64),substr($d['size'],0,64),$d['read']));
		fclose($out);
		file_put_contents($box.'.num',$num[0].','.($num[1]-1));
		return true;
	}

	public static function mime_header_decode($string) {
		if(!function_exists('imap_mime_header_decode')) return $string;
	    $array = imap_mime_header_decode($string);
	    $str = "";
	    foreach ($array as $key => $part) {
	        $str .= $part->text;
	    }
	    return $str;
	}

	public static function addressbook_rp_mail($e){
		return CRM_ContactsCommon::contact_format_default($e,true);
	}
}

?>
