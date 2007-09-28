<?php
/** 
 * Something like igoogle
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @version 0.1
 * @package epesi-base-extra
 * @subpackage dashboard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Dashboard extends Module {
	private $lang;
	private $set_default_js='';
	
	public function body() {
		$this->lang = $this->init_module('Base/Lang');
		Base_ActionBarCommon::add('add','Add applet',$this->create_callback_href(array($this,'applets_list')));
		load_js($this->get_module_dir().'ab.js');
		$tipmod = $this->init_module('Utils/Tooltip');
		print('<table id="dashboard" style="width: 100%"><tr>');
		for($j=0; $j<3; $j++) {
			print('<td id="dashboard_applets_'.$j.'" style="width:33%;min-height:100px;vertical-align:top;">');
			
			$ret = DB::Execute('SELECT id,module_name FROM base_dashboard_applets WHERE col=%d AND user_login_id=%d ORDER BY pos',array($j,Base_UserCommon::get_my_user_id()));
			while($row = $ret->FetchRow()) {
				if(ModuleManager::is_installed($row['module_name'])==-1) {//if its invalid entry
					$this->delete_applets($row['module_name']);
					continue;
				}
					
				$m = $this->init_module($row['module_name'],null,$row['id']);
				$title = call_user_func(array($row['module_name'].'Common', 'applet_caption'));
				$th = $this->init_module('Base/Theme');
				$th->assign('handle_class','handle');
				$th->assign('toggle','<a class="toggle" '.$tipmod->open_tag_attrs($this->lang->ht('Toggle')).'>=</a>');
				$th->assign('remove','<a class="remove" '.$tipmod->open_tag_attrs($this->lang->ht('Remove')).' '.$this->create_confirm_callback_href($this->lang->t('Delete this applet?'),array($this,'delete_applet'),$row['id']).'>x</a>');
				if(method_exists($row['module_name'].'Common', 'applet_settings'))
					$th->assign('configure','<a class="configure" '.$tipmod->open_tag_attrs($this->lang->ht('Configure')).' '.$this->create_callback_href(array($this,'configure_applet'),array($row['id'],$row['module_name'])).'>c</a>');
				$th->assign('content','<div class="content">'.
						$this->get_html_of_module($m,array($this->get_values($row['id'],$row['module_name']), & $title, $row['id']),'applet').
						'</div>');
				$th->assign('caption',$title);
				print('<div class="applet" id="ab_item_'.$row['id'].'">');
				$th->display();
				print('</div>');
			}
			print('</td>');
		}
		print('</tr></table>');
		eval_js('dashboard_activate()');
	}
	
	public function applets_list() {
		$this->lang = $this->init_module('Base/Lang');

		$fc = $this->get_module_variable('first_conf');
		if(isset($fc)) {
			$mod = $this->get_module_variable('mod_conf');
			$ok = null;
			if(!$this->configure_applet($fc,$mod,& $ok)) {
				if(!$ok) 
					self::delete_applet($fc);
				else 
					Base_StatusBarCommon::message(Base_LangCommon::ts($this->get_type(),'Applet added'));
				$this->unset_module_variable('first_conf');
				$this->unset_module_variable('mod_conf');
				return false;
			}
			return true;
		}

		if($this->is_back()) return false;		
		Base_ActionBarCommon::add('back','Back to Dashboard',$this->create_back_href());


		$tipmod = $this->init_module('Utils/Tooltip');
		foreach(ModuleManager::$modules as $name=>$obj) {
			if(method_exists($obj['name'].'Common', 'applet_caption')) {
				$attrs = '';
				if(method_exists($obj['name'].'Common', 'applet_info')) {
					$out = '';
					$ret = call_user_func(array($obj['name'].'Common', 'applet_info'));
					if(is_array($ret)) {
						$out .= '<table>';
						foreach($ret as $k=>$v)
							$out .= '<tr><td>'.$k.'</td><td>'.$v.'</td></tr>';
						$out .= '</table>';
					} elseif(is_string($ret))
						$out = $ret;
					else
						trigger_error('Invalid applet info for module: '.$obj['name'],E_USER_ERROR);
					$attrs .= $tipmod->open_tag_attrs($out).' ';
				}
				$links[$obj['name']] = '<a '.$attrs.$this->create_callback_href(array($this,'add_applet'),$obj['name']).'>'.call_user_func(array($obj['name'].'Common', 'applet_caption')).'</a>';
			}
		}
		
		$theme =  & $this->pack_module('Base/Theme');
		$theme->assign('header', $this->lang->t('Add applet'));
		$theme->assign('links', $links);
		$theme->display('list');
		
		return true;
	}
	
	public function add_applet($mod) {
		DB::Execute('INSERT INTO base_dashboard_applets(user_login_id,module_name) VALUES (%d,%s)',array(Base_UserCommon::get_my_user_id(),$mod));
		$this->set_module_variable('first_conf',DB::Insert_ID('base_dashboard_applets','id'));
		$this->set_module_variable('mod_conf',$mod);
//		$this->set_back_location();
		
	}

	public static function delete_applet($id) {
		DB::Execute('DELETE FROM base_dashboard_settings WHERE applet_id=%d',array($id));
		DB::Execute('DELETE FROM base_dashboard_applets WHERE id=%d AND user_login_id=%d',array($id,Base_UserCommon::get_my_user_id()));
	}

	public static function delete_applets($module) {
		$module = str_replace('/','_',$module);
		$ret = DB::GetAll('SELECT id FROM base_dashboard_applets WHERE module_name=%s',array($module));
		foreach($ret as $row)
			DB::Execute('DELETE FROM base_dashboard_settings WHERE applet_id=%d',array($row['id']));
		DB::Execute('DELETE FROM base_dashboard_applets WHERE module_name=%s',array($module));
	}

	public function configure_applet($id,$mod,& $ok=null) {
		if($this->is_back()) {
			$ok=false;
			return false;
		}
		if(!method_exists($mod.'Common', 'applet_settings')) {
			$ok=true;
			return false;
		}
		
		$this->lang = $this->init_module('Base/Lang');
		$f = &$this->init_module('Libs/QuickForm',$this->lang->ht('Saving settings'),'settings');
		$f->addElement('header',null,$this->lang->t(call_user_func(array($mod.'Common','applet_caption'))));
		
		$menu = call_user_func(array($mod.'Common','applet_settings'));
		if (is_array($menu)) 
			$this->add_module_settings_to_form($menu,$f,$id,$mod);
		else
			trigger_error('Invalid applet settings function: '.$mod,E_USER_ERROR);

		$defaults = HTML_QuickForm::createElement('button','defaults',$this->lang->ht('Restore defaults'), 'onClick="'.$this->set_default_js.'"');
		$submit = HTML_QuickForm::createElement('submit','submit',$this->lang->ht('OK'));
		$cancel = HTML_QuickForm::createElement('button','cancel',$this->lang->ht('Cancel'), $this->create_back_href());
		$f->addGroup(array($defaults, $submit,$cancel));

		if($f->validate()) {
			//$f->process(array(& $this, 'submit_settings'));
			$submited = $f->exportValues();
			$defaults = $this->get_default_values($mod);
			$old = $this->get_values($id,$mod);
			foreach($defaults as $name=>$def_value)
				if($submited[$name]!=$old[$name]) {
					if($submited[$name]==$def_value)
						DB::Execute('DELETE FROM base_dashboard_settings WHERE applet_id=%d AND name=%s',array($id,$name));
					else
						DB::Replace('base_dashboard_settings', array('applet_id'=>$id, 'name'=>$name, 'value'=>$submited[$name]), array('applet_id','name'), true);
				}
			$ok = true;
			return false;
		}
		$ok=null;
		$f->display();
		return true;

	}
	
	private function get_default_values($mod) {
		static $variables;
		if (isset($variables[$mod]))
			return $variables[$mod];

		$variables[$mod] = array();
		if(method_exists($mod.'Common', 'applet_settings')) {
			$menu = call_user_func(array($mod.'Common','applet_settings'));
			foreach($menu as $v) 
				if(isset($v['default']))
					$variables[$mod][$v['name']] = $v['default'];
		}
		return $variables[$mod];
	}
	
	private function get_values($id,$mod) {
		$variables = $this->get_default_values($mod);

		$ret = DB::Execute('SELECT name,value FROM base_dashboard_settings WHERE applet_id=%d',array($id));
		while($v = $ret->FetchRow())
			$variables[$v['name']] = $v['value'];
		
		return $variables;
	}

	private function add_module_settings_to_form($info, &$f, $id, $module){
		$values = $this->get_values($id,$module);
		foreach($info as $v){
			if ($v['type']=='select'){
				$select = array(); 
				foreach($v['values'] as $k=>$x) $select[$k] = $this->lang->ht($x);
				$f -> addElement('select',$v['name'],$this->lang->t($v['label']),$select);
				$this->set_default_js .= 'e = $(\''.$f->getAttribute('name').'\').'.$v['name'].';'.
										'for(i=0; i<e.length; i++) if(e.options[i].value==\''.$v['default'].'\'){e.options[i].selected=true;break;};';
			} elseif ($v['type']=='static' || $v['type']=='header') {
				$f -> addElement($v['type'],$v['name'],$this->lang->t($v['label']),$this->lang->t($v['values']));
			} elseif ($v['type']=='radio') {
				$radio = array();
				$label = $this->lang->t($v['label']);
				foreach($v['values'] as $k=>$x) {
					$f -> addElement('radio',$v['name'],$label,$this->lang->ht($x),$k);
					$label = '';
				}
				$this->set_default_js .= 'e = $(\''.$f->getAttribute('name').'\').'.$v['name'].';'.
										'for(i=0; i<e.length; i++){e[i].checked=false;if(e[i].value==\''.$v['default'].'\')e[i].checked=true;};';
			} elseif ($v['type']=='bool' || $v['type']=='checkbox') {
				$f -> addElement('checkbox',$v['name'],$this->lang->t($v['label']));
				$this->set_default_js .= '$(\''.$f->getAttribute('name').'\').'.$v['name'].'.checked = '.$v['default'].';';
			} elseif ($v['type']=='text' || $v['type']=='textarea') {
				$f -> addElement($v['type'],$v['name'],$this->lang->t($v['label']));
				$this->set_default_js .= '$(\''.$f->getAttribute('name').'\').'.$v['name'].'.value = \''.$v['default'].'\';';
			} else trigger_error('Invalid type: '.$v['type'],E_USER_ERROR);
			if (isset($v['rule'])) {
				$i = 0;
				foreach ($v['rule'] as $r) {
					if (!isset($r['message'])) trigger_error('No error message specified for field '.$v['name'], E_USER_ERROR);
					if (!isset($r['type'])) trigger_error('No error type specified for field '.$v['name'], E_USER_ERROR);
					if ($r['type']=='callback') {
						if (!isset($r['func'])) trigger_error('Invalid parameter specified for rule definition for field '.$v['name'], E_USER_ERROR);
						if(is_string($r['func']))
							$f->registerRule($v['name'].$i.'_rule', 'callback', $r['func']);
						elseif(is_array($r['func']))
							$f->registerRule($v['name'].$i.'_rule', 'callback', $r['func'][1], $r['func'][0]);
						else
							trigger_error('Invalid parameter specified for rule definition for field '.$v['name'], E_USER_ERROR);
						if(isset($r['param']) && $r['param']=='__form__')
							$r['param'] = &$f;
						$f->addRule($v['name'], $this->lang->t($r['message']), $v['name'].$i.'_rule', isset($r['param'])?$r['param']:null);
					} else {
						if ($r['type']=='regex' && !isset($r['param'])) trigger_error('No regex defined for a rule for field '.$v['name'], E_USER_ERROR);
						$f->addRule($v['name'], $this->lang->t($r['message']), $r['type'], isset($r['param'])?$r['param']:null);
					}
					$i++;
				}
			}
		}
		$f -> setDefaults($values);
	}

}

?>