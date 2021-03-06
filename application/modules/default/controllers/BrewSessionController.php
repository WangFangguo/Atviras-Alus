<?php

class BrewsessionController extends Zend_Controller_Action {

	public function init() {
		$storage = new Zend_Auth_Storage_Session();
		$user_info = $storage->read();
		$this->use_plato = false;
		$this->show_beta = false;
		if (isset($user_info->user_id) && !empty($user_info->user_id)){
			$db = Zend_Registry::get("db");
			$select = $db->select()
					->from("users_attributes")
					->where("users_attributes.user_id = ?", $user_info->user_id)
					->limit(1);
			$u_atribs= $db->fetchRow($select);
			if ($u_atribs['beta_tester'] == 1) {
				$this->show_beta = true;
			}
			if ($u_atribs['plato'] == 1) {
				$this->use_plato = true;
			}
		}
		$this->view->use_plato = $this->use_plato;
	}

	public function indexAction() {
		$storage = new Zend_Auth_Storage_Session();
		$user_info = $storage->read();
		$this->show_beta = false;
		if (isset($user_info->user_id) && !empty($user_info->user_id)){
			$db = Zend_Registry::get("db");
			$select = $db->select()
					->from("users_attributes")
					->where("users_attributes.user_id = ?", $user_info->user_id)
					->limit(1);
			$u_atribs= $db->fetchRow($select);
			if ($u_atribs['beta_tester'] == 1) {
				$this->show_beta = true;
			}
		}
		
	}

	public function brewerAction() {
		$owner_id = 0;
		$db = Zend_Registry::get('db');
		$this->view->user_id = 0;
		$this->view->user_name = "";
		$storage = new Zend_Auth_Storage_Session();
		$u = $storage->read();
		if (isset($u->user_id)) {
			$this->view->user_id = $u->user_id;
			$this->view->user_name = $u->user_name;
		}
		if ($this->getRequest()->getParam("brewer") > 0) {
			$owner_id = $this->getRequest()->getParam("brewer");
		} else {
			if (isset($u->user_id)) {
				$owner_id = $u->user_id;
				$select = $db->select()
						->from("beer_recipes")
						->where("brewer_id = ?", $u->user_id)
						->order('recipe_name');
				$this->view->user_recipes = $db->fetchAll($select);
			}
		}
		$select = $db->select()
				->from("users")
				->where("user_id =?", $owner_id);
		$this->view->owner_user_info = $db->fetchRow($select);
		$select = $db->select()
				->from("beer_brew_sessions", array("session_caskingdate", "session_secondarydate", "session_primarydate", "session_recipe", "session_fg", "session_og", "session_size", "session_brewer", "session_name", "session_id", 'session_comments' => 'LEFT( session_comments, LOCATE( " ", session_comments, 30 ) )'))
				->join("users", "beer_brew_sessions.session_brewer = users.user_id")
				->joinLeft("beer_recipes", "beer_brew_sessions.session_recipe=beer_recipes.recipe_id", array("recipe_id", "recipe_name"))
				->where("beer_brew_sessions.session_brewer =?", $owner_id)
				->order("session_primarydate DESC");
		$result = $db->fetchAll($select);
		foreach($result as $key=>$val){
			if ((float)$val['session_og'] != 0 && (float)$val['session_fg']!=0){
				$result[$key]['session_abv'] = number_format(($val['session_og'] - $val['session_fg']) * 131, 1)."%";
			} else {
				$result[$key]['session_abv'] = "-";
			}
			
		}
		$this->view->brew_sessions = $result;
	}

	public function historyAction() {

		$db = Zend_Registry::get('db');
		$select = $db->select()
				->from("beer_brew_sessions", array("session_caskingdate", "session_secondarydate", "session_primarydate", "session_recipe", "session_fg", "session_og", "session_size", "session_brewer", "session_name", "session_id", 'session_comments' => 'LEFT( session_comments, LOCATE( " ", session_comments, 30 ) )'))
				->join("users", "beer_brew_sessions.session_brewer = users.user_id")
				->joinLeft("beer_recipes", "beer_brew_sessions.session_recipe=beer_recipes.recipe_id", array("recipe_id", "recipe_name"))
				->where("beer_brew_sessions.session_primarydate !='0000-00-00'")
				->where("session_primarydate <= NOW()")
				->where("recipe_publish = ?", '1')
				->order("session_primarydate DESC");
		$adapter = new Zend_Paginator_Adapter_DbSelect($select);
		$this->view->content = new Zend_Paginator($adapter);
		$this->view->content->setCurrentPageNumber($this->_getParam('page'));

		$this->view->content->setItemCountPerPage(100);
	}

	public function newAction() {
		$db = Zend_Registry::get('db');
		$this->view->user_id = 0;
		$storage = new Zend_Auth_Storage_Session();
		$u = $storage->read();
		if (isset($u->user_id)) {
			$this->view->user_id = $u->user_id;
			$this->view->user_name = $u->user_name;
		}
		$select = $db->select()
				->from("beer_recipes")
				->where("recipe_id=?", $this->getRequest()->getParam("recipe"));
		$this->view->plan = $this->_getParam("plan");
		$this->view->recipe = $db->fetchRow($select);
	}

	public function detailAction() {
		$db = Zend_Registry::get('db');
		$storage = new Zend_Auth_Storage_Session();
		$u = $storage->read();
		if (isset($u->user_id)) {
			$this->view->user_id = $u->user_id;
			$this->view->user_name = $u->user_name;
		}

		$select = $db->select()
				->from("beer_brew_sessions")
				->join("users", "beer_brew_sessions.session_brewer = users.user_id")
				->joinLeft("beer_recipes", "beer_brew_sessions.session_recipe=beer_recipes.recipe_id", array("recipe_id", "recipe_name"))
				->where("beer_brew_sessions.session_id =?", $this->getRequest()->getParam("session"));
		$this->view->brew_sessions = $db->fetchRow($select);
	}

	public function editAction() {
		$db = Zend_Registry::get('db');
		$storage = new Zend_Auth_Storage_Session();
		$u = $storage->read();
		if (isset($u->user_id)) {
			$this->view->user_id = $u->user_id;
			$this->view->user_name = $u->user_name;
		}

		$select = $db->select()
				->from("beer_brew_sessions")
				->join("users", "beer_brew_sessions.session_brewer = users.user_id")
				->joinLeft("beer_recipes", "beer_brew_sessions.session_recipe=beer_recipes.recipe_id", array("recipe_id", "recipe_name"))
				->where("beer_brew_sessions.session_id =?", $this->getRequest()->getParam("session"));
		$this->view->brew_sessions = $db->fetchRow($select);
	}

	public function recipeAction() {
		$this->view->user_id = 0;
		$storage = new Zend_Auth_Storage_Session();
		$u = $storage->read();
		if (isset($u->user_id)) {
			$this->view->user_id = $u->user_id;
			$this->view->user_name = $u->user_name;
		}
		$db = Zend_Registry::get('db');
		$select = $db->select()
				->from("beer_brew_sessions", array("session_caskingdate", "session_secondarydate", "session_primarydate", "session_recipe", "session_fg", "session_og", "session_size", "session_brewer", "session_name", "session_id", 'session_comments' => 'LEFT( session_comments, LOCATE( " ", session_comments, 30 ) )'))
				->join("users", "beer_brew_sessions.session_brewer = users.user_id")
				->joinLeft("beer_recipes", "beer_brew_sessions.session_recipe=beer_recipes.recipe_id", array("recipe_id", "recipe_name"))
				->where("beer_brew_sessions.session_recipe =?", $this->getRequest()->getParam("recipe"))
				->order("session_primarydate DESC");
		$result = $db->fetchAll($select);
		foreach($result as $key=>$val){
			if ((float)$val['session_og'] != 0 && (float)$val['session_fg']!=0){
				$result[$key]['session_abv'] = number_format(($val['session_og'] - $val['session_fg']) * 131, 1)."%";
			} else {
				$result[$key]['session_abv'] = "-";
			}
			
		}
		$this->view->brew_sessions = $result;
		$select = $db->select()
				->from("beer_recipes")
				->join("beer_styles", "beer_styles.style_id = beer_recipes.recipe_style", array("style_class"))
				->where("recipe_id=?", $this->getRequest()->getParam("recipe"));
		$rcp = $db->fetchRow($select);
		if (($rcp['style_class'] == "beer" && $rcp['recipe_abv'] > 9.5) || ($rcp['style_class'] != "beer" && $rcp['recipe_abv'] > 18)){
			$this->_redirect("/");
			exit;
		}
		$this->view->recipe = $rcp;
	}

	public function updateAction() {
		$storage = new Zend_Auth_Storage_Session();
		$u = $storage->read();
		$this->_helper->viewRenderer->setNoRender();
		if (isset($u->user_name)) {
			$db = Zend_Registry::get('db');
			$fields_session = array("session_name" => "session_name", "session_size" => "session_size", "session_og" => "session_og", "session_fg" => "session_fg", "session_comments" => "session_comments", "session_caskingdate" => "session_caskingdate", "session_secondarydate" => "session_secondarydate", "session_primarydate" => "session_primarydate");
			foreach ($fields_session as $key => $value) {
				if (($key == "session_og" || $key == "session_fg") && $this->use_plato === true){
					$_POST[$value] = $this->pl2sg($_POST[$value]);
				}
				$ins[$key] = $_POST[$value];
			}

			$db->update("beer_brew_sessions", $ins, array("session_id=" . $_POST["session_id"], "session_brewer =" . $u->user_id));
		}
		$this->_redirect($_POST['redirect']);
	}

	public function deleteAction() {
		$this->_helper->layout->setLayout('empty');
		$this->_helper->viewRenderer->setNoRender();
		$storage = new Zend_Auth_Storage_Session();
		$u = $storage->read();
		if (isset($u->user_name)) {
			$db = Zend_Registry::get('db');
			$db->delete("beer_brew_sessions", array("session_id=" . $_POST["session_id"], "session_brewer =" . $u->user_id));
			print Zend_Json::encode(array("status" => 0, "data" => array()));
		} else {
			print Zend_Json::encode(array("status" => 1, "errors" => array(array("message" => "Neregistruotas naudotojas", "type" => "authentication"))));
		}
	}
	
	private function pl2sg($p){
		return 1.00001 + 3.8661E-3 * $p + 1.3488E-5 * $p * $p + 4.3074E-8 * $p * $p * $p;
	}

	public function addAction() {
		$storage = new Zend_Auth_Storage_Session();
		$u = $storage->read();
		if (isset($u->user_name)) {
			$db = Zend_Registry::get('db');
			$fields_session = array("session_brewer" => "session_brewer", "session_recipe" => "session_recipe", "session_name" => "session_name", "session_size" => "session_size", "session_og" => "session_og", "session_fg" => "session_fg", "session_comments" => "session_comments", "session_caskingdate" => "session_caskingdate", "session_secondarydate" => "session_secondarydate", "session_primarydate" => "session_primarydate");
			foreach ($fields_session as $key => $value) {
				if (($key == "session_og" || $key == "session_fg") && $this->use_plato === true){
					$_POST[$value] = $this->pl2sg($_POST[$value]);
				}
				$ins[$key] = $_POST[$value];
			}

			$db->insert("beer_brew_sessions", $ins);
			$db->delete("beer_session_plan", "user_id = '".$u->user_id."' AND plan_id='".$_POST['session_plan']."'");
		}
		$this->_helper->viewRenderer->setNoRender();
		$this->_redirect($_POST['redirect']);
	}

}
