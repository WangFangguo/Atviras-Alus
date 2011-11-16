<?
class Entities_Mail extends Zend_Db_Table {
	protected $_name="mail";
	protected $_primary = 'mail_id';
	private $_user;
	public function __construct($user) {
		$this->_user=$user;
		
		parent::__construct(); 
	}
	
	
	function outbox($mail_id=0) {
		if ($mail_id>0) {
			$mail_fields=array('mail.mail_subject','mail.mail_date','mail.mail_body');
		}else{
			$mail_fields=array('mail_subject','mail.mail_id','mail.mail_date');
		}
		$db=$this->getAdapter();
		$select=$db->select()
			->from('users',array('mail_to'=>'group_concat(concat(users.user_name," ",users.user_surname),"")'))
			->join('mail_users','mail_users.user_id=users.user_id',array())
			->join('mail','mail.mail_id = mail_users.mail_id',$mail_fields)
			->where('mail.mail_sender='.$this->_user->user_id)
			->where('mail.mail_deleted = ?', '0');
			if ($mail_id>0) {
				$select->where('mail.mail_id='.$mail_id);
				return  $db->fetchRow($select);
			}else{
				$select->group("mail.mail_id");
				$select->order('mail.mail_date DESC');
				return  $db->fetchAll($select);
			}
		
			
		//select mail.mail_id,mail_sender,mail_date,mail_subject,group_concat(concat(user_name,' ',user_surname),"") as mail_to from users,mail_users join mail on mail_users.mail_id = mail.mail_id where users.user_id=mail_users.user_id group by mail.mail_id
	}
	function getUnreadCount() {
		$db=$this->getAdapter();
	 	$select=$db->select()
		->from('mail_users',array("count"=>"count(mail_users.mail_id)"))
		->where('mail_users.user_id = ?',$this->_user->user_id)
		->where('mail_users.mail_read = ?','0');
		return $db->fetchRow($select);
	}
	function receive($mail_id=0) {
		if ($mail_id>0) {
			$mail_fields=array('mail_subject','mail_sender','mail_date','mail_body');
		}else{
			$mail_fields=array('mail_subject','mail_sender','mail_date');
		}
		 	$db=$this->getAdapter();
		 	$select=$db->select()
			->from('mail_users')
			->join('mail','mail.mail_id = mail_users.mail_id',$mail_fields)
			->join('users','mail.mail_sender=users.user_id',array('mail_from'=>'CONCAT(user_name," ",user_surname)'))
			->where('mail_users.user_id='.$this->_user->user_id);
			if ($mail_id>0) {
				$select->where('mail.mail_id='.$mail_id);
				if ($row=$db->fetchRow($select)) {
					$db->update('mail_users',array('mail_read'=>1),'user_id ='.$this->_user->user_id.' and mail_id='.$mail_id);
					return $row;
				};
			}else{
				$select->order('mail.mail_date DESC');
				return  $db->fetchAll($select);
			}
			
			
			
	}
	function delete($mail,$target) {
		if ($this->_user) {
			switch ($target) {
				case "inbox":
					 $db=$this->getAdapter();
					 if ($db->delete("mail_users","mail_id IN (".implode(",",$mail).") and user_id=".$this->_user->user_id)) {
						 return true;
					 }
							
					break;
				case "outbox":
					$db=$this->getAdapter();
					 if ($db->update("mail",array("mail_deleted"=>1),"mail_id IN (".implode(",",$mail).") and mail_sender=".$this->_user->user_id)) {
						 return true;
					 }
					break;
			}
		}
	}
	function send($recipients,$subject,$body) {
		if ($this->_user) {
		 
			if (count($recipients)>0) {
				 $smtp_recipients=array();
				 $i=0;
				while($i<count($recipients)) {
				$recipients[$i]=trim($recipients[$i]);
				 	if (strlen($recipients[$i])>0) {
				 		$recipients[$i]="'".$recipients[$i]."'";
				 		$i++;
				 	}else{
				 	array_splice($recipients,$i,1);
				 	}
				 }
				
				 $db=$this->getAdapter();
				 $select=$db->select()
				 	->from('users')
				 	->where('user_name IN ('.implode(",",$recipients).')');
				 	print_r( $select->__toString());
				  $recipients=$db->fetchAll($select);
				  if (count($recipients)>0) {
				  	  if ($id=$this->insert(array('mail_sender'=>$this->_user->user_id,'mail_body'=>$body,'mail_subject'=>$subject))) {
				  	  	  for ($i=0;$i<count($recipients);$i++) {
				  	  	  	$recipients[$i]["mail_id"]=$id;
				  	  	  	$db->insert('mail_users',array('user_id'=>$recipients[$i]['user_id'],'mail_id'=>$id));
							$smtp_recipients[]=$recipients[$i];	
				  	  	  }
						
						return $smtp_recipients;     
				  	  }
				  }else{
				  	  new Exception("Nerasta kontaktų");
				  }
				
			}else{
				new Exception("Nerasta kontaktų");
			}
		}
	}
	public static function mail($to=array(),$subject="",$body="") {
		$config = array('auth' => 'login', 'username' => 'noreply@atvirasalus.lt','password' => 'povelniu');
		$tr = new Zend_Mail_Transport_Smtp('mail.atvirasalus.lt',$config);
		$mail = new Zend_Mail('UTF-8');
		$mail->setFrom('noreply@atvirasalus.lt', 'Atviras alus');
		for ($i=0;$i<count($to);$i++) {
			$mail->addBcc($to[$i], '');
		}
		$mail->setSubject($subject);
		$mail->setBodyText($body);
		try{
			$mail->send($tr);
			return true;
		}catch(Zend_Mail_Exception $e){
			return false;
		}
	}
}
?>