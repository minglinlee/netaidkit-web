<?php

class SetupController extends Page
{
    protected $_allowed_actions = array('index', 'ap', 'wan', 'disconnected');

    public function index()
    {
        $this->_redirect('/setup/ap');
    }

    public function ap()
    {
        $cur_stage = NetAidManager::get_stage();

        if ($cur_stage != 'reset' && $cur_stage != 'wansetup')
            $this->_redirect('/admin/index');

		if($cur_stage == 'wansetup')
            $this->_redirect('/setup/wan');

        $request = $this->getRequest();
        if ($request->isPost()) {
            $ssid              = $request->postvar('ssid');
            $key               = $request->postvar('key');
            $key_confirm       = $request->postvar('key_confirm');
            $adminpass         = $request->postvar('adminpass');
            $adminpass_confirm = $request->postvar('adminpass_confirm');
            $distresspass      = $request->postvar('distresspass');

            $valid = $this->ap_validate($ssid, $key, $adminpass, $key_confirm, $adminpass_confirm);

            if ($valid) {
                $pass_success = NetAidManager::set_adminpass($adminpass);
                $ap_success = NetAidManager::setup_ap($ssid, $key);
				$success = ($ap_success && $pass_success);
				if ($success) {
					$this->_addMessage('info', _('Access Point successfully set up.'), 'wan');
				}
            } else {
                $this->_addFormData('ssid', $ssid, 'ap');
                $this->_addFormData('key', $key, 'ap');
                $this->_addFormData('key_confirm', $key_confirm, 'ap');
                $this->_addFormData('adminpass', $adminpass, 'ap');
                $this->_addFormData('adminpass_confirm', $adminpass_confirm, 'ap');
            }

            if ($request->isAjax()) {
                echo ($valid && $success) ? "SUCCESS" : "FAILURE";
                exit;
            }
        }

        $view = new View('setup_ap');
        return $view->display();
    }

    protected function ap_validate($ssid, $key, $adminpass, $key_confirm, $adminpass_confirm)
    {
        $valid = true;

        if (!($ssid && $key && $adminpass && $key_confirm && $adminpass_confirm)) {
            $valid = false;
            $this->_addMessage('error', _('All fields are required.'), 'ap');

            if (empty($ssid))
                $this->_addFormError('ssid', 'ap');

            if (empty($key))
                $this->_addFormError('key', 'ap');

            if (empty($adminpass))
                $this->_addFormError('adminpass', 'ap');

            if (empty($key_confirm))
                $this->_addFormError('key_confirm', 'ap');

            if (empty($adminpass_confirm))
                $this->_addFormError('adminpass_confirm', 'ap');
        }

        if ($key != $key_confirm) {
            $valid = false;
            $this->_addMessage('error', _('Wireless key does not match.'), 'ap');
            $this->_addFormError('key', 'ap');
            $this->_addFormError('key_confirm', 'ap');
        }

        $keylen = strlen($key);
        if ($keylen && (($keylen < 8) || ($keylen > 63))) {
            $valid = false;
            $this->_addMessage('error', _('Invalid key length, must be between 8 and 63 characters.'), 'ap');
            $this->_addFormError('key', 'ap');
            $this->_addFormError('key_confirm', 'ap');
        }

        if ($adminpass != $adminpass_confirm) {
            $valid = false;
            $this->_addMessage('error', _('Admin password does not match.'), 'ap');
            $this->_addFormError('adminpass', 'ap');
            $this->_addFormError('adminpass_confirm', 'ap');
        }

        $passlen = strlen($adminpass);
        if ($passlen < 8) {
            $valid = false;
            $this->_addMessage('error', _('Admin password must be at least 8 characters.'), 'ap');
            $this->_addFormError('adminpass', 'ap');
            $this->_addFormError('adminpass_confirm', 'ap');
        }

        return $valid;
    }

    public function wan()
    {
      //if (NetAidManager::get_inetstat()) {
      //        NetAidManager::set_stage('online');
      //        $this->_addMessage('info', _('Setup complete.'), 'setup');
      //}

      $request = $this->getRequest();
      if ($request->isPost()) {
        $ssid = $request->postvar('ssid');
        $key  = $request->postvar('key');
        $enctype  = $request->postvar('encryption');

        $wan_success  = NetAidManager::setup_wan($ssid, $key, $enctype);

        /* DEPRECATED:
        $cur_stage = NetAidManager::get_stage();

        if ($cur_stage != 'offline') {
            //NetAidManager::set_stage('offline');
            $this->_addMessage('info', _('Setup complete.'), 'setup');
        } else {
            //NetAidManager::set_stage('offline');
            $this->_addMessage('info', _('Setup complete. However, a connection could not be established.'), 'setup');
        }
        */
        if ($request->isAjax()) {
            // DEBUG: echo $ssid.' | '.$key.' | '.$enctype;
            echo $wan_success ? "SUCCESS" : "FAILURE";
            exit;
        }
        
      } else {

        $cur_stage = NetAidManager::get_stage();
        if ($cur_stage != 'reset' && $cur_stage != 'wansetup')
            $this->_redirect('/admin/index');

        // DEPRECATED:
        //$wifi_list = NetAidManager::scan_wifi();
        //$wifi_list = NetAidManager::list_wifi();
        $params = array('wifi_list' => $wifi_list);
        $view = new View('setup_wan', $params);
        return $view->display();
      }
    }

    public function disconnected()
    {
        echo 'disconnected';
    }
}
