<?php
class Entities_AUTH
{
	static function dologin($user_email,$user_password,$remember_me=false) {
		  $auth = Zend_Auth::getInstance();
               $db = Zend_Registry::get('db');
                  $authAdapter = new Zend_Auth_Adapter_DbTable($db, 'users', 'user_email', 'user_password', '? AND user_active = "1"');
                  $authAdapter->setIdentity(strtolower($user_email))->setCredential($user_password);
                  $result = $auth->authenticate($authAdapter);
              
                  if ($result->isValid()) {   
                  	  if ($remember_me) {
                      	      setcookie("user_email",$user_email,time()+1209600,"/",".atvirasalus.lt"); 
                      	      setcookie("user_password",$user_password,time()+1209600,"/",".atvirasalus.lt");
                      	      setcookie("remember",'1',time()+1209600,"/",".atvirasalus.lt");
                      	  }else{
                      	      setcookie("user_email",$user_email,time()+21600,"/",".atvirasalus.lt"); 
                      	      setcookie("user_password",$user_password,time()+21600,"/",".atvirasalus.lt");
                      	      setcookie("remember",'0',time()+21600,"/",".atvirasalus.lt");
                      	    
                      	  }   
                      	  
                      $storage = new Zend_Auth_Storage_Session();  
                      $storage->write($authAdapter->getResultRowObject());                      
                      $db->update("users",array("user_lastlogin"=>new Zend_Db_Expr("NOW()")), array("user_email = '" . $user_email . "'")); 
                      return $storage->read();
                  } else {
                  
                  	  return false;
                    
                    
                  }
	}
}
?>