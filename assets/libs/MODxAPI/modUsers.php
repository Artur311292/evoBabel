<?php
require_once('MODx.php'); 

class modUsers extends MODxAPI{

    protected $default_field = array(
        'user'=>array(
            'username',
            'password',
            'cachepwd'
        ),
        'attribute'=>array(
            'fullname',
            'role',
            'email',
            'phone',
            'mobilephone',
            'blocked',
            'blockeduntil',
            'blockedafter',
            'logincount',
            'lastlogin',
            'thislogin',
            'failedlogincount',
            'sessionid',
            'dob',
            'gender',
            'country',
            'state',
            'zip',
            'fax',
            'photo',
            'comment'
        )
    );

    protected function findUser($data){
        switch(true){
            case (is_int($data) || ((int)$data>0 && (string)intval($data)===$data)): $find= 'attribute.internalKey'; break;
            case filter_var($data, FILTER_VALIDATE_EMAIL): $find = 'attribute.email'; break;
            case is_scalar($data): $find = 'user.username'; break;
            default: $find = false;
        }
        return $find;
    }
	public function edit($id){
        $this->close();
        $this->newDoc = false;

        if(!$find=$this->findUser($id))  { return false; } //@TODO: log error

        $result = $this->query("
            SELECT * from {$this->makeTable('web_user_attributes')} as attribute
            LEFT JOIN {$this->makeTable('web_users')} as user ON user.id=attribute.internalKey
            WHERE {$find}='{$this->modx->db->escape($id)}'
        ");
        $this->field = $this->modx->db->getRow($result);

        $this->id = $this->get('internalKey');
        unset($this->field['id']);
        unset($this->field['internalKey']);
        return $this;
	}

    public function set($key,$value){
        if(is_scalar($value) && is_scalar($key) && !empty($key)){
            switch($key){
                case 'password': {
                    $value = $this->getPassword($value);
                    break;
                }
            }
            $this->field[$key] = $value;
        }
        return $this;
    }

	public function getPassword($pass){
        return md5($pass);
    }

    public function save($fire_events = null,$clearCache = false){
        if ($this->get('email')=='' || $this->get('username')=='' || $this->get('password')=='') {
            $this->log['EmptyPKField'] =  'Email, username or password is empty <pre>'.print_r($this->toArray(),true).'</pre>';
            return false;
        }

        if(!$this->checkUnique('web_users','username')){
            $this->log['UniqueUsername'] =  'username not unique <pre>'.print_r($this->get('username'),true).'</pre>';
            return false;
        }

        if(!$this->checkUnique('web_user_attributes','email','internalKey')){
            $this->log['UniqueEmail'] =  'Email not unique <pre>'.print_r($this->get('email'),true).'</pre>';
            return false;
        }

        /*$this->invokeEvent('OnBeforeDocFormSave',array (
            "mode" => $this->newDoc ? "new" : "upd",
            "id" => $this->id ? $this->id : ''
        ),$fire_events);*/

        $fld = $this->toArray();
        foreach($this->default_field['user'] as $key){
            $this->Uset($key,'user');
            unset($fld[$key]);
        }
        if (!empty($this->set['user'])){
            if($this->newDoc){
                $SQL = "INSERT into {$this->makeTable('web_users')} SET ".implode(', ', $this->set['user']);
            }else{
                $SQL = "UPDATE {$this->makeTable('web_users')} SET ".implode(', ', $this->set['user'])." WHERE id = ".$this->id;
            }
            $data = $this->query($SQL);
        }

        if($this->newDoc) {
			$this->id = $this->modx->db->getInsertId();
		}


        foreach($this->default_field['attribute'] as $key){
            $this->Uset($key,'attribute');
            unset($fld[$key]);
        }
        if (!empty($this->set['attribute'])){
            if($this->newDoc){
                $this->set('internalKey',$this->id)->Uset('internalKey','attribute');
                $SQL = "INSERT into {$this->makeTable('web_user_attributes')} SET ".implode(', ', $this->set['attribute']);
            }else{
                $SQL = "UPDATE {$this->makeTable('web_user_attributes')} SET ".implode(', ', $this->set['attribute'])." WHERE  internalKey = ".$this->getID();
            }
            $this->query($SQL);
        }

        foreach($fld as $key=>$value){
            if ($value=='') continue;
                $this->query("UPDATE {$this->makeTable('web_user_settings')} SET `setting_value` = '{$value}' WHERE `webuser` = '{$this->id}' AND `setting_name` = '{$key}';");
                if (mysql_affected_rows()==0){
                    $this->query("INSERT into {$this->makeTable('web_user_settings')} SET `webuser` = {$this->id},`setting_name` = '{$key}',`setting_value` = '{$value}';");
                }
        }

        /*$this->invokeEvent('OnDocFormSave',array (
            "mode" => $this->newDoc ? "new" : "upd",
            "id" => $this->id
        ),$fire_events);*/

        if($clearCache){
            $this->clearCache($fire_events);
        }
        return $this->id;
    }
	public function delete($ids,$fire_events = null){
        if($this->edit($ids)){
            $flag = $this->query("
          DELETE user,attribute FROM {$this->makeTable('web_user_attributes')} as attribute
            LEFT JOIN {$this->makeTable('web_users')} as user ON user.id=attribute.internalKey
            WHERE attribute.internalKey='{$this->modx->db->escape($this->getID())}'");
            $this->query("DELETE FROM {$this->makeTable('web_user_settings')} WHERE webuser='{$this->getID()}'");
        }else{
            $flag=false;
        }
        $this->close();
        return $flag;
	}

    public function authUser($id=0,$fulltime=true,$cookieName = 'WebLoginPE'){
        $flag = false;
        if(!$this->getID() && $id) $this->edit($id);
        if($this->getID()){
            //$this->logOut($cookieName);
            $this->SessionHandler('start', $cookieName, $fulltime);
            $flag = true;
        }
        return $flag;
    }

    public function checkBlock($id=0){
        $tmp = clone $this;
        if($id && $tmp->getID()!=$id){
            $tmp->edit($id);
        }
        unset($tmp);
        //@TODO валидация блокировок
        return false;
    }
    public function testAuth($id,$password,$blocker){
        $tmp = clone $this;
        if($id && $tmp->getID()!=$id){
            $tmp->edit($id);
        }
        $flag = false;
        if(
            ($tmp->getID() && $tmp->get('password')==$this->getPassword($password)) &&
            (!$blocker || ($blocker && !$tmp->checkBlock($id)))
        ){
            $flag = true;
        }
        unset($tmp);
        return $flag;
    }
    public function AutoLogin($fulltime=true,$cookieName = 'WebLoginPE'){
        $flag = false;
        if(isset($_COOKIE[$cookieName])){
            $cookie = explode('|', $_COOKIE[$cookieName],2);
            if(isset($cookie[0],$cookie[1]) && strlen($cookie[0])==32 && strlen($cookie[1])==32){
                $this->close();
                $q=$this->modx->db->query("SELECT id FROM ".$this->makeTable('web_users')." WHERE md5(username)='{$this->modx->db->escape($cookie[0])}'");
                $id = $this->modx->db->getValue($q);
                if($this->edit($id) && $this->getID() && $this->get('password') == $cookie[1] && $this->testAuth($this->getID(),$cookie[1],true)){
                    $flag = $this->authUser($this->getID(),$fulltime,$cookieName);

                }
            }
        }
        return $flag;
    }

    public function logOut($cookieName = 'WebLoginPE'){
         $this->SessionHandler('destroy',$cookieName);
    }

    /**
     * SessionHandler
     * Starts the user session on login success. Destroys session on error or logout.
     *
     * @param string $directive ('start' or 'destroy')
     * @return void
     * @author Raymond Irving
     * @author Scotty Delicious
     *
     * remeber может быть числом в секундах
     */
    protected function SessionHandler($directive, $cookieName, $remember=true){
			switch($directive){
				case 'start':{
					if($this->getID()){
						$_SESSION['webShortname'] = $this->get('username');
						$_SESSION['webFullname'] = $this->get('fullname');
						$_SESSION['webEmail'] = $this->get('email');
						$_SESSION['webValidated'] = 1;
						$_SESSION['webInternalKey'] = $this->getID();
						$_SESSION['webValid'] = base64_encode($this->get('password'));
						$_SESSION['webUser'] = base64_encode($this->get('username'));
						$_SESSION['webFailedlogins'] = $this->get('failedlogincount');
						$_SESSION['webLastlogin'] = $this->get('lastlogin');
						$_SESSION['webnrlogins'] = $this->get('logincount');
						$_SESSION['webUserGroupNames'] = '';
						if($remember){
							$cookieValue = md5($this->get('username')).'|'.$this->get('password');
							$cookieExpires = time() + (is_bool($remember) ? (60 * 60 * 24 * 365 * 5) : (int)$remember);
							setcookie($cookieName, $cookieValue, $cookieExpires, '/');
						}
					}
					break;
				}
				case 'destroy':{
					if (isset($_SESSION['mgrValidated'])){
						unset($_SESSION['webShortname']);
						unset($_SESSION['webFullname']);
						unset($_SESSION['webEmail']);
						unset($_SESSION['webValidated']);
						unset($_SESSION['webInternalKey']);
						unset($_SESSION['webValid']);
						unset($_SESSION['webUser']);
						unset($_SESSION['webFailedlogins']);
						unset($_SESSION['webLastlogin']);
						unset($_SESSION['webnrlogins']);
						unset($_SESSION['webUsrConfigSet']);
						unset($_SESSION['webUserGroupNames']);
						unset($_SESSION['webDocgroups']);

						setcookie($cookieName, '', time()-60, '/');
					}else{
						if (isset($_COOKIE[session_name()])){
							setcookie(session_name(), '', '/');
						}
						setcookie($cookieName, '', time()-60, '/');
						session_destroy();
					}
					break;
				}
			}
        return $this;
    }
}