<?php

class AdminController extends Page
{
    protected $_torLogfile = '/var/log/tor/notices.log';
    protected $_vpnLogfile = '/var/log/openvpn.log';

    protected $_allowed_actions = array('index', 'update', 'get_wan_status', 'toggle_tor', 'display_tor', 'tor_status',
                                        'get_wifi', 'get_stored_wifi', 'wan', 'toggle_vpn', 'display_vpn', 
                                        'upload_vpn', 'delete_vpn',
                                        'toggle_routing', 'routing_status', 'vpn_status');

    public function init()
    {
        if ($_SESSION['logged_in'] != 1)
            $this->_redirect('/user/login');
    }

    public function index()
    {
		$cur_stage = NetAidManager::get_stage();
        $routing_status = NetAidManager::routing_status();

        $vpn_obj = new Ovpn();
        $vpn_options = $vpn_obj->getOptions();
        $cur_vpn = basename($vpn_obj->getCurrent());

        $tor_status = $this->_get_tor_status();
        //$vpn_status = $this->_get_vpn_status();

        $params = array('cur_stage' => $cur_stage, 'wan_ssid' => '',
                        'vpn_options' => $vpn_options, 'cur_vpn' => $cur_vpn,
                        'tor_status' => $tor_status, 'vpn_status' => $vpn_status,
                        'routing_status' => $routing_status);
                        
        $view = new View('admin', $params);
        return $view->display();
    }

    public function update()
    {
		$_SESSION['update_mode'] = 1;
		$this->_redirect('/admin/index');
	}

	public function get_wan_status()
	{
		$request = $this->getRequest();
		$wan_ssid = NetAidManager::wan_ssid().' <i>('.NetAidManager::get_stage().')</i>';
		if ($wan_ssid == 'NETAIDKIT')
			$wan_ssid = _('Wired connection');
		$params = $wan_ssid;
		if ($request->isAjax()) {
			echo $wan_ssid;
			exit(0);
		} else {
			return $params;
		}
	}

    public function upload_vpn()
    {
        $vpn_obj = new Ovpn();
        if ($vpn_obj->handleUpload())
            $this->_addMessage('info', _('VPN config file uploaded.'), 'vpn');
        else
            $this->_addMessage('error', _('File upload failed.'), 'vpn');

        $this->_redirect('/admin/index');
    }

    public function delete_vpn()
    {
        $request = $this->getRequest();
        $file = $request->postvar('file');

        $vpn_obj = new Ovpn();
        if ($vpn_obj->removeFile($file))
            $this->_addMessage('info', _('VPN config file removed.'), 'vpn');
        else
            $this->_addMessage('error', _('Could not remove file.'), 'vpn');

        $this->_redirect('/admin/index');
    }

    public function toggle_tor()
    {
        $request = $this->getRequest();
        $tor_success = NetAidManager::toggle_tor();

		if ($request->isAjax()) {
			if($tor_success) {
				echo 'SUCCESS';
			} else {
				echo 'FAILURE';
			}
			exit;
		} else {
			$this->_redirect('admin/index');
		}
    }

    public function toggle_routing()
    {
        $request = $this->getRequest();
        $mode = $request->postvar('mode');
        $routing_success = NetAidManager::toggle_routing($mode);

		if ($request->isAjax()) {
			echo $routing_success ? "SUCCESS" : "FAILURE";
			exit;
		} else {
			$this->_redirect('admin/index');
		}
    }

    public function routing_status()
    {
        $status = NetAidManager::routing_status();
        die($status);
    }

    public function tor_status()
    {
        $status = $this->_get_tor_status();
        die($status);
    }

    protected function _get_tor_status()
    {
		$client = new NakdClient();
		$output = $client->doCommand('tor','GETINFO status/bootstrap-phase');
		// example output: 250-status/bootstrap-phase=NOTICE BOOTSTRAP PROGRESS=100 TAG=done SUMMARY="Done"
        preg_match_all('/PROGRESS\=(\d{1,3})\\ TAG/', $output[0], $bootstrap);
		$progress = '0';
		if (!empty($bootstrap[1][0])) {
			$progress = $bootstrap[1][0];
		}
		return $progress;
		
		/* DEPRECATED:
        if (file_exists($this->_torLogfile)) {
            $log = file_get_contents($this->_torLogfile);

            preg_match_all('/Bootstrapped (\d{1,3})\%/', $log, $bootstrap);

            $progress = '5';

            if (!empty($bootstrap[1])) {
                $progress = end(array_values($bootstrap[1]));
                if ($progress == '0')
                    $progress = '5';
            }

            return $log.' #  '.$progress;
        */
			
    }

    public function toggle_vpn()
    {
        $request = $this->getRequest();
        $ovpn_obj = new Ovpn();

        if (!empty($request->postvar('file')))
            $ovpn_file = $ovpn_obj->ovpn_root . '/upload/' . basename($request->postvar('file'));

		$current = $ovpn_obj->ovpn_root . '/current.ovpn';
        if ($ovpn_file && file_exists($ovpn_file)) {
            $ovpn_file = escapeshellarg($ovpn_file);
            shell_exec('rm '.$current.'; ln -s '.$ovpn_file.' '.$current);
        }

		if(file_exists($current)) {
			$vpn_success = NetAidManager::toggle_vpn();
		} else {
			$vpn_success = FALSE;
		}
		
		if ($request->isAjax()) {
			if($vpn_success) {
				echo 'SUCCESS';
			} else {
				echo 'FAILURE';
			}
			exit;
		} else {
			$this->_redirect('admin/index');
		}
    }

    public function get_wifi()
    {
        $request = $this->getRequest();

        if ($request->isAjax()) {
            NetAidManager::scan_wifi();
			$wifi_list = NetAidManager::list_wifi();
            $params = array('wifi_list' => $wifi_list);
            $view = new View('wifi_ajax', $params);
            $view->display();
            exit;
        }
    }

    public function get_stored_wifi()
    {
        $request = $this->getRequest();
        if ($request->isAjax()) {
			$wifi_list = NetAidManager::list_stored_wifi();
            $params = array('wifi_list' => $wifi_list);
            $view = new View('wifi_ajax_adv', $params);
            $view->display();
            exit;
        }
    }

    public function wan()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $ssid = $request->postvar('ssid');
            $key  = $request->postvar('key');

            $wan_success  = NetAidManager::setup_wan($ssid, $key);

            if ($request->isAjax()) {
                echo $wan_success ? "SUCCESS" : "FAILURE";
                exit;
            }
        }
    }

    public function vpn_status()
    {
        $status = $this->_get_vpn_status();
        die($status);
    }

    protected function _get_vpn_status()
    {
		$client = new NakdClient();
        $output = $client->doCommand('openvpn','state')[0];

		$result = 'not running';
		if(!isset($output['error'])) {
			if(isset($output['state'])) {
				switch($output['state']) {
						case 'TCP_CONNECT':
							@$t_start = $output['timestamp'];
							$t_sec = time() - $t_start;
							$estimated_sec = 30;
							if ($t_sec > $estimated_sec)
								$progress = 80;
							else {
								$progress = (80 / $estimated_sec) * $t_sec;
							}
							$result = intval($progress);
						break;
						case 'WAIT': $result = 90; break;
						case 'CONNECTED': $result = 100; break;
						default: $result = 0;
						/*
							WAIT -- (Client only) Waiting for initial response
							from server.
							AUTH -- (Client only) Authenticating with server.
							GET_CONFIG -- (Client only) Downloading configuration options
							from server.
							ASSIGN_IP -- Assigning IP address to virtual network
							interface.
							ADD_ROUTES -- Adding routes to system.
							CONNECTED -- Initialization Sequence Completed.
							RECONNECTING -- A restart has occurred.
							EXITING -- A graceful exit is in progress.
						*/
				}
			}
		}
	
        return $result;
    }
    
    public function display_tor()
    {
        $request = $this->getRequest();
		if ($request->isAjax()) {
			$cur_stage = NetAidManager::get_stage();
			$this->_params['cur_stage'] = $cur_stage;
			include ('../nak/webapp/views/admin/tiles/tor.phtml');
			exit;
		} else {
			$this->_redirect('admin/index');
		}
	}
	
    public function display_vpn()
    {
        $request = $this->getRequest();
		if ($request->isAjax()) {
			$cur_stage = NetAidManager::get_stage();
			$this->_params['cur_stage'] = $cur_stage;
			$vpn_obj = new Ovpn();
			$this->_params['vpn_options'] = $vpn_obj->getOptions();
			$this->_params['cur_vpn'] = basename($vpn_obj->getCurrent());
			$ajax = TRUE;
			include ('../nak/webapp/views/admin/tiles/vpn.phtml');
			exit;
		} else {
			$this->_redirect('admin/index');
		}
	}
    
}
