<?php

class IdeaController extends Zend_Controller_Action {

	public function init() {
		
	}

	public function indexAction() {
		
	}

	public function getfileAction() {
		$this->_helper->layout->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);
		$idea_id = $this->getRequest()->getParam('idea');
		$file_id = $this->getRequest()->getParam('file');
		if ($file_id < 1 || $file_id > 3 || !is_numeric($file_id)) {
			exit;
		}
		$db = Zend_Registry::get('db');
		$select = $db->select()
				->from("idea_items", array("idea_file_" . $file_id . " as file"))
				->where("idea_items.idea_id = ?", $idea_id)
				->limit(1);
		$result = $db->fetchRow($select);

		$path = $_SERVER['DOCUMENT_ROOT'] . "/ideas/" . $idea_id . "/";
		$fullPath = $path . $file_id . "_" . $result['file'];
		if (file_exists($fullPath)) {
			$fsize = filesize($fullPath);
			$path_parts = pathinfo($fullPath);
			switch ($path_parts['extension']) {
				case "pdf": $ctype = "application/pdf";
					break;
				case "exe": $ctype = "application/octet-stream";
					break;
				case "zip": $ctype = "application/zip";
					break;
				case "doc": $ctype = "application/msword";
					break;
				case "xls": $ctype = "application/vnd.ms-excel";
					break;
				case "ppt": $ctype = "application/vnd.ms-powerpoint";
					break;
				case "gif": $ctype = "image/gif";
					break;
				case "png": $ctype = "image/png";
					break;
				case "jpeg":
				case "jpg": $ctype = "image/jpg";
					break;
				default: $ctype = "application/force-download";
			}
			header('Content-Description: File Transfer');
			header("Content-type: " . $ctype);
			header('Content-Disposition: attachment; filename="' . $result['file'] . '"');
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header("Content-length: " . filesize($fullPath));
			ob_end_flush();
			ob_flush();
			flush();
			ob_start();
			readfile($fullPath);
			exit;
		}
	}

	public function voteAction() {
		$db = Zend_Registry::get('db');
		$storage = new Zend_Auth_Storage_Session();
		$user_info = $storage->read();
		$this->view->user_info = $user_info;
		$this->_helper->layout->setLayout('empty');
		if (isset($_POST['idea_id'])) {
			$me = -1;
			if (isset($user_info->user_id) && !empty($user_info->user_id))
				$me = $user_info->user_id;

			if ($me != -1) {
				$db->delete("idea_votes", array("user_id = '" . $me . "'", "idea_id = '" . $_POST['idea_id'] . "'"));
				switch ($_POST['vote_value']) {
					case "m":
						$v_val = -1;
						break;
					case "p":
						$v_val = 1;
						break;
					default:
						$v_val = 0;
				}
				$db->insert("idea_votes", array(
					"idea_id" => $_POST['idea_id'],
					"user_id" => $me,
					"vote_value" => $v_val,
				));
				$select = $db->select()
						->from("idea_votes", array('SUM(vote_value) AS suma'))
						->where("idea_votes.idea_id = ?", $_POST['idea_id']);
				$result = $db->fetchRow($select);
				$db->update("idea_items", array("idea_vote_sum" => $result['suma']), array("idea_id = '" . $_POST['idea_id'] . "'"));
			}



			$select = $db->select()
					->from("idea_items")
					->join("users", "users.user_id=idea_items.user_id", array("user_name", "user_email"))
					->joinLeft("idea_votes", "idea_votes.idea_id=idea_items.idea_id AND idea_votes.user_id='" . $me . "'", array("vote_value"))
					->where("idea_items.idea_id = ?", $_POST['idea_id'])
					->limit(1);
			$result = $db->fetchRow($select);
			$select = $db->select()
					->from("idea_votes")
					->where("idea_votes.idea_id = ?", $_POST['idea_id']);
			$votes = $db->fetchAll($select);
			$result['total_votes'] = sizeof($votes);
			if ($votes == 0) {
				$result['up_size'] = 0;
				$result['down_size'] = 0;
				$result['neutral_size'] = 0;
			} else {
				$t_up = 0;
				$t_down = 0;
				$t_neutral = 0;
				foreach ($votes as $vote) {
					if ($vote['vote_value'] == "1")
						$t_up++;
					if ($vote['vote_value'] == "0")
						$t_neutral++;
					if ($vote['vote_value'] == "-1")
						$t_down++;
				}
				if ($t_up == 0) {
					$result['up_size'] = 0;
				} else {
					$result['up_size'] = round(100 * $t_up / sizeof($votes));
				}
				if ($t_neutral == 0) {
					$result['neutral_size'] = 0;
				} else {
					$result['neutral_size'] = round(100 * $t_neutral / sizeof($votes));
				}
				if ($t_down == 0) {
					$result['down_size'] = 0;
				} else {
					$result['down_size'] = round(100 * $t_down / sizeof($votes));
				}
				$this->view->idea = $result;
			}
		} else {
			$this->_helper->viewRenderer->setNoRender(true);
			print "error";
		}
	}

	public function listAction() {
		$storage = new Zend_Auth_Storage_Session();
		$this->view->user_info = $storage->read();
		$me = -1;
		if (isset($this->view->user_info->user_id) && !empty($this->view->user_info->user_id)) {
			$me = $this->view->user_info->user_id;
		}
		$type = $this->getRequest()->getParam("type");
		if (empty($type))
			$type = "new";
		$this->view->type = $type;
		$db = Zend_Registry::get('db');
		$select = $db->select()
				->from("idea_items")
				->join("users", "users.user_id=idea_items.user_id", array("user_name", "user_email"))
				->joinLeft("idea_votes", "idea_votes.idea_id=idea_items.idea_id AND idea_votes.user_id='" . $me . "'", array("vote_value"));
		if ($type == "finished") {
			$select->where("idea_items.idea_status = ?", "1");
		} else {
			$select->where("idea_items.idea_status = ?", "0");
		}
		if ($type == "top") {
			$select->order("idea_vote_sum DESC");
		} else {
			$select->order("idea_posted DESC");
		}
		$result = $db->fetchAll($select);
		foreach ($result as $key => $row) {
			$select = $db->select()
					->from("idea_votes")
					->where("idea_votes.idea_id = ?", $row['idea_id']);
			$votes = $db->fetchAll($select);
			$result[$key]['total_votes'] = sizeof($votes);
			if ($votes == 0) {
				$result[$key]['up_size'] = 0;
				$result[$key]['down_size'] = 0;
				$result[$key]['neutral_size'] = 0;
			} else {
				$t_up = 0;
				$t_down = 0;
				$t_neutral = 0;
				foreach ($votes as $vote) {
					if ($vote['vote_value'] == "1")
						$t_up++;
					if ($vote['vote_value'] == "0")
						$t_neutral++;
					if ($vote['vote_value'] == "-1")
						$t_down++;
				}
				if ($t_up == 0) {
					$result[$key]['up_size'] = 0;
				} else {
					$result[$key]['up_size'] = round(100 * $t_up / sizeof($votes));
				}
				if ($t_neutral == 0) {
					$result[$key]['neutral_size'] = 0;
				} else {
					$result[$key]['neutral_size'] = round(100 * $t_neutral / sizeof($votes));
				}
				if ($t_down == 0) {
					$result[$key]['down_size'] = 0;
				} else {
					$result[$key]['down_size'] = round(100 * $t_down / sizeof($votes));
				}
			}
		}
		//@todo: reikia paginatoriaus idėjų sąrašui
		$this->view->ideas = $result;
	}

	public function listnewAction() {
		$this->_forward("list", null, null, array("type" => "new"));
	}

	public function listtopAction() {
		$this->_forward("list", null, null, array("type" => "top"));
	}

	public function listfinishedAction() {
		$this->_forward("list", null, null, array("type" => "finished"));
	}

	public function viewAction() {
		$idea_id = $this->getRequest()->getParam("idea");

		$storage = new Zend_Auth_Storage_Session();
		$this->view->user_info = $storage->read();
		$me = -1;
		if (isset($this->view->user_info->user_id) && !empty($this->view->user_info->user_id)) {
			$me = $this->view->user_info->user_id;
		}
		$db = Zend_Registry::get('db');
		$select = $db->select()
				->from("idea_items")
				->join("users", "users.user_id=idea_items.user_id", array("user_name", "user_email"))
				->joinLeft("idea_votes", "idea_votes.idea_id=idea_items.idea_id AND idea_votes.user_id='" . $me . "'", array("vote_value"));
		$select->where("idea_items.idea_id = ?", $idea_id);
		$result = $db->fetchRow($select);
		$select = $db->select()
				->from("idea_votes")
				->where("idea_votes.idea_id = ?", $idea_id);
		$votes = $db->fetchAll($select);
		$result['total_votes'] = sizeof($votes);
		if ($votes == 0) {
			$result['up_size'] = 0;
			$result['down_size'] = 0;
			$result['neutral_size'] = 0;
		} else {
			$t_up = 0;
			$t_down = 0;
			$t_neutral = 0;
			foreach ($votes as $vote) {
				if ($vote['vote_value'] == "1")
					$t_up++;
				if ($vote['vote_value'] == "0")
					$t_neutral++;
				if ($vote['vote_value'] == "-1")
					$t_down++;
			}
			if ($t_up == 0) {
				$result['up_size'] = 0;
			} else {
				$result['up_size'] = round(100 * $t_up / sizeof($votes));
			}
			if ($t_neutral == 0) {
				$result['neutral_size'] = 0;
			} else {
				$result['neutral_size'] = round(100 * $t_neutral / sizeof($votes));
			}
			if ($t_down == 0) {
				$result['down_size'] = 0;
			} else {
				$result['down_size'] = round(100 * $t_down / sizeof($votes));
			}
		}
		$this->view->idea = $result;
	}

}