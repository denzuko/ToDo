<?php
App::uses('AppController', 'Controller');
/**
 * Users Controller
 */
class UsersController extends AppController {
	public function beforeFilter() {
		parent::beforeFilter();
		// Allow login and logout for everyone
		$this->Auth->allow('login', 'logout');
		//$this->Security->allowedControllers = '';
	}

/**
 * login method
 *
 * @return void
 */
	public function login() {
		// If they are already logged in, redirect them to the home page
		if($this->Auth->user()) {
			$this->redirect(array('controller' => 'things', 'action' => 'index'));
		}
		if ($this->request->is('post')) {
			$username = strtolower($this->request->data['User']['username']);
			$result = $this->User->find('first',array('conditions' => array('uid' => $username)));
			if (isset($result['User']['userpassword']) && $this->_check_password($result['User']['userpassword'],$this->request->data['User']['password'])) {
				$user = array('User' => array(
					'username' => $username,
					'first_name' => $result['User']['cn'],
					'last_name' => $result['User']['sn'],
					'email' => $result['User']['mail'],
					'groups' => array(),
				));
				$result = $this->User->Group->find('all',array('conditions' => array('memberuid' => $username)));
				foreach ($result as $group) {
					$user['User']['groups'][] = $group['Group']['cn'];
				}
				// User must be in the "members" group to login
				if (in_array('members',$user['User']['groups'])) {
					$this->Auth->login($user);
					$this->redirect($this->referer());
				} else {
					// Account exists, but is not in the right groups
					$this->Session->setFlash(__('Authorization failure. Your account is inactive.'),'alert-error');
				}
			} else {
				$this->Session->setFlash(__('Username or password is incorrect.'));
			}
		}
		// Don't fill the fields back in!
		unset($this->request->data);
	}

/**
 * logout method
 *
 * @return void
 */
	public function logout() {
		$this->redirect($this->Auth->logout());
	}

/**
 * Check to see if a plaintext password matches a hash
 *
 * @param string $hash Hashed password
 * @param string $pass Plaintext password
 * @return bool
 */
	protected function _check_password($hash,$pass) {
		// From LDAP the passwords look like: {SSHA}832/II3YHCEpqC3TZQBOYrZaruXEPzU2
		if (preg_match('/{([^}]+)}(.*)/',$hash,$matches)) {
			$hash = $matches[2];
			$hash_type = strtolower($matches[1]);
		} else {
			$hash_type = null;
		}

		if ($hash_type == 'ssha') {
			// Grab the salt
			$salt = substr(base64_decode($hash),20);
			// Built a test hash using the salt and the plaintext password
			$test_hash = base64_encode(mhash(MHASH_SHA1,$pass.$salt).$salt);
			if (strcmp($hash,$test_hash) == 0) {
				// Awww yeah
				return true;
			} else {
				// Nope
				return false;
			}
		} elseif ($hash_type == 'smd5') {
			// This has not be tested
			$salt = substr(base64_decode($hash),16);
			$test_hash = base64_encode(mhash(MHASH_MD5,$pass.$salt).$salt);
			if (strcmp($hash,$test_hash) == 0) {
				return true;
			} else {
				return false;
			}
		} elseif ($hash_type == 'sha') {
			// TODO: Not yet implimented
			return false;
		} elseif ($hash_type == 'md5') {
			// TODO: Not yet implimented
			return false;
		} else {
			// Plaintext?
			if ($pass == $hash) {
				return true;
			} else {
				return false;
			}
		}
	}

}
