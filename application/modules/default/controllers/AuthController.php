<?php
  class AuthController extends Zend_Controller_Action
  {
      var $translate;
      public function init()
      {
          $this->view->errors = array();
        
      }
      public function homeAction()
      {
          $storage = new Zend_Auth_Storage_Session();
          $data = $storage->read();
          if (!$data) {
              $this->_redirect('auth/login');
          }
          $this->view->username = $data->username;
      }
      public function loginAction()
      {
	
      	  $this->errors=array();
          if ($this->getRequest()->isPost()) {
          	$form = new Form_Login();
              if ($form->isValid($_POST)) {
                  $data = $form->getValues();
                  if ($user_data=Entities_AUTH::dologin($data['user_email'],md5($data['user_password']),isset($_POST['remember']))) {  
                  	  
                  }else{
                  	  $this->errors[] = array("type"=>"system","message"=>"Neteisingas slaptažodis ar vartotojo vardas");
                  }
              }else{
              	 $err_codes=new Entities_FormErrors();
              	 foreach ($form->getErrors() as $key => $error) {
                  if (count($error) > 0) {
                  	  $this->errors[] =  array("type"=>"form","message"=>$form->getElement($key)->getLabel() ." - ". $err_codes->getError($error[0]));
                  }
              }
           
              }
          }else{
          	$this->errors[] = array("type"=>"system","message"=>"Neteisingas slaptažodis ar vartotojo vardas");
          }
        $this->_helper->layout->setLayout('empty');
	$this->_helper->viewRenderer->setNoRender(true);
          if (count($this->errors)) {
           	print Zend_Json::encode(array("status"=>1,"errors"=>$this->errors));
          }else{
          	
          	print Zend_Json::encode(array("status"=>0,"data"=>array("user_name"=>$user_data->user_name)));
          }
      }
      public function logoutAction()
      {	 
       	  setcookie("user_email",null,time()-1209600,"/",".atvirasalus.lt");
          $storage = new Zend_Auth_Storage_Session();
          $storage->clear();
          $this->_redirect('/index');
      }
      function generatePassword()
      {
          $new_pass = str_shuffle('abcdefghijklmnopqrstuvwxyz1234567890');
          return substr($new_pass, 0, 6);
      }
  
      public function activateAction()
      {
          $db = Zend_Registry::get('db');
          $emailhash = $this->getRequest()->getParam('emailhash');
          if ($result = $db->fetchRow("SELECT * FROM users WHERE MD5(user_email) ='" . $emailhash . "'")) {
          	    $new_pass = $this->generatePassword();
                    $updated = $db->update('users', array("user_password" => md5($new_pass),"user_active" => 1), array("user_email = '" . $result['user_email'] . "'","user_active = '0'"));
                      if ($updated) {
                      	      //bb
                      	   $db->insert('bb_usermeta',array('user_id'=>$result['user_id'],'meta_key'=>'bb_capabilities','meta_value'=>'a:1:{s:6:"member";b:1;}'));
                           if ($this->sendPasswordEmail($result, $new_pass)) {
                           	   print "Profilis aktyvuotas. Jūsų e. pašto adresu nusiųsti prisijungimo duomenys.";
                           };
                          $this->_helper->viewRenderer->setNoRender();
                      }else{  
                      	      print "Profilis  aktyvus, <a href='#' href='#' onclick='showLogin()'> prisijunkite</a>";
                      }
          } else {
             print "Profilis nerastas";
          }
          $this->_helper->viewRenderer->setNoRender();
      }
      public function rememberAction()
      {

          $form = new Form_Password();
          $this->view->errors = array();
          if ($this->getRequest()->isPost()) {
              if ($form->isValid($_POST)) {
                  $db = Zend_Registry::get('db');
                  $user_email = $this->_request->getParam("user_email");
                 
                  if ( $result = $db->fetchRow("SELECT * FROM users WHERE user_email ='" . $user_email . "'")) {
                      $new_pass = $this->generatePassword();
                      $updated = $db->update('users', array("user_password" => md5($new_pass)), "user_email = '" . $user_email . "'");
                     
                      if ($updated) {
                           if ($this->sendPasswordEmail($result, $new_pass)) {
                           	  print "Į Jūsų e. paštą nusiųsti prisijungimo duomenys.";
                           };
                          $this->_helper->viewRenderer->setNoRender();
                      }
                  } else {
                      $this->view->errors[] =  array("type"=>"form","message"=>"Nurodytu e. pašto adresu registruoto vartotojo sistemoje nėra"); 
                  }
              }
          }
          $err_codes=new Entities_FormErrors();
              foreach ($form->getErrors() as $key => $error) {
                  if (count($error) > 0) {
                  	  $this->view->errors[] =  array("type"=>"form","message"=>$form->getElement($key)->getLabel() ." - ". $err_codes->getError($error[0]));
                  }
              }
          $this->view->form = $form;
      }
      public function registerAction()
      {
      	//$this->_helper->layout->setLayout('auth');
	$succes=false;

      	  if (isset($_POST['user_email'])) {
          $form = new Form_Register();
          if ($form->isValid($_POST)) {
              $db = Zend_Registry::get('db');
              $_rq = $this->_request;
              $result = $db->fetchAll("SELECT * FROM users WHERE user_email ='" . strtolower($_rq->getPost('user_email')) . "' or user_name='".$_rq->getPost('user_name')."'");
              if (count($result) > 0) {
                  //exit 
                  //throw error email allready registered
                  $this->view->errors[] = array("type"=>"system","message"=>'Vartotojas tokiu vardu arba tokiu e. pašto adresu jau egzistuoja');
              } else {
                  $user_data = array( 'user_name' => $_rq->getPost('user_name'), 'user_email' => strtolower($_rq->getPost('user_email')));
                 
                     
                      	      if ($this->sendInvitationEmail($user_data)) { 
                      	      if (@$db->insert('users', $user_data)) {
				      $succes=true;
                      	      	      $this->view->success=true;
                      	      };
                  
                  } else {
                      $this->view->errors[] =array("type"=>"system","message"=>'Sistemos klaida. Registracija nepavyko');
                  }
              }
          } else {
              $err_codes=new Entities_FormErrors();
              foreach ($form->getErrors() as $key => $error) {
                  if (count($error) > 0) {
                  	  $this->view->errors[] =  array("type"=>"form","message"=>$form->getElement($key)->getLabel() ." - ". $err_codes->getError($error[0]));
                  }
              }
          }
          }
        	if ($succes==false) {
             	 $this->view->form = new Form_Register($_POST);
		}
        
          // action body
      }
      
      private function sendInvitationEmail($user_data)
      {
      	 
      	  if ($tpl=Entities_Settings::getTemplate('confirm_registration')) { 
		$tpl_vars=array('user_hash'=>  md5($user_data['user_email']));
		$body=Entities_MicroTemplate::render($tpl["template_body"],$tpl_vars);
		$smtp_recipients = array($user_data['user_email']);
		$subject = $tpl["template_subject"];
		if (Entities_Mail::mail($smtp_recipients, $subject, $body)) {
			return true;
		} 
          }
      }
      private function sendPasswordEmail($user_data, $new_pass)
      {
	if ($tpl=Entities_Settings::getTemplate('lost_password')) {
	      $tpl_vars=array('user_email'=>$user_data['user_email'],'user_password'=> $new_pass);
	      $body=Entities_MicroTemplate::render($tpl["template_body"],$tpl_vars);
	      $smtp_recipients = array($user_data['user_email']);
	      $subject = $tpl["template_subject"];
	  if (Entities_Mail::mail($smtp_recipients, $subject, $body)) {
	      return true;
	  }
	}
      }
     
  }
?>