<?php

class NetAidManager
{
    static public function setup_ap($ssid, $key)
    {
        if (empty($ssid) || empty($key))
            return false;

        $client = new NakdClient();
        $output = $client->doCommand('configure_ap', array('ssid' => $ssid, 'key' => $key, 'hidden' => FALSE, 'disabled' => FALSE, 'encryption' => 'psk2'));
        return true;
    }

    static public function ap_name()
    {
        $client = new NakdClient();
        $output = $client->doCommand('wlan_current','AP');
        return $output['ssid'];
    }

    static public function scan_wifi()
    {
        $client = new NakdClient();
        $output = $client->doCommand('wlan_scan');
        return $output;
    }

	/*	DEPRECATED:
    static public function list_wifi()
    {
        $client = new NakdClient();
        $output = $client->doCommand('wlan_list');
        $strength_list = array();
        $wifi_list = array();
        if(is_array($output)) {
			foreach($output as $i => $wifi) {
				$ssid = $wifi['ssid'];
				$enctype = $wifi['encryption'];
				if ($enctype == 'none')
					$enctype = 'Open';
				$strength = round(($wifi['quality']/$wifi['quality_max'])*1000)/10;
				$strength_list[$strength] = array('ssid'=>$ssid,'encryption'=>$enctype,'strength'=>$strength);
			}
			krsort($strength_list);
			foreach($strength_list as $strength => $wifi) {
				$wifi_list[$wifi['ssid']] = $wifi;
			}
		}
		$wifi_list=array(_('Wired connection')=>array('encryption'=>'Wired','strength'=>100))+$wifi_list; // add wired connection
        return $wifi_list;
    }
    */

    static public function list_stored_wifi()
    {
        $client = new NakdClient();
        $output = $client->doCommand('wlan_list_stored');
        $wifi_list = array();
        if(is_array($output)) {
			foreach($output as $i => $wifi) {
				$ssid = $wifi['ssid'];
				$enctype = $wifi['encryption'];
				if ($enctype == 'none')
					$enctype = 'Open';
				$wifi_list[$ssid] = array('encryption'=>$enctype,'auto'=>$wifi['auto']);
			}
			asort($wifi_list);
		}
        return $wifi_list;
    }
    
	static public function set_stored_wifi($ssid,$properties)
	{
		$properties['ssid']=$ssid;
		
        $client = new NakdClient();
        $output = $client->doCommand('wlan_modify_stored',$properties);
        
		return var_dump($properties);
	}
    
	static public function del_stored_wifi($ssid)
	{
		$properties['ssid']=$ssid;
		
        $client = new NakdClient();
        $output = $client->doCommand('wlan_forget',$properties);
        
		return var_dump($properties);
	}

    static public function setup_wan($ssid, $key, $enctype)
    {
      $client = new NakdClient();
      if($ssid != _('Wired connection')) {
        if (empty($ssid))
          return false;			
        if($enctype='psk-mixed') { $enctype = 'psk2'; }
        $output = $client->doCommand('wlan_connect', array('ssid' => $ssid, 'key' => $key, 'encryption' => $enctype, 'store' => TRUE, 'auto' => TRUE));
      } else {	# reset uplink wifi
        $output = $client->doCommand('wlan_disconnect'); // <- here you'll have to disable global autoconnect, too, and let user re-enable it
      }
        return true;
    }

    static public function set_adminpass($adminpass)
    {
        if (empty($adminpass) || strlen($adminpass) < 8)
            return false;
        $passfile = ROOT_DIR . '/data/pass';
        $admin_hash = password_hash($adminpass, PASSWORD_BCRYPT);
        return file_put_contents($passfile, $admin_hash);
    }

    static public function check_adminpass($loginpass)
    {
        $passfile = ROOT_DIR . '/data/pass';
        if (!file_exists($passfile))
            throw new Exception('Password file missing.');
        $admin_hash = file_get_contents($passfile);
        return password_verify($loginpass, $admin_hash);
    }

    static public function get_stage()
    {
        $client = new NakdClient();		
        $output = $client->doCommand('stage_current');
        if($output['name']=='setup') {
          if(file_exists(ROOT_DIR . '/data/pass')) $output = array( 'name' => 'wansetup' );
          if(file_exists(ROOT_DIR . '/data/configured')) $output = array( 'name' => 'default' );
        }
        return $output['name'];
    }

    static public function get_inetstat()
    {
        $client = new NakdClient();
        $output = $client->doCommand('connectivity');
		return $output['internet'];
    }

	static public function init_stage()
	{
        $cur_stage = NetAidManager::get_stage();
        if ($cur_stage == 'setup') header('Location: /setup/ap');
		return $cur_stage;
	}
	
    static public function set_stage($stage)
    {
        if (empty($stage))
            return false;

        $client = new NakdClient();
        $output = $client->doCommand('stage_set',$stage);
        return true;
    }

    static public function toggle_tor()
    {
        $cur_stage = self::get_stage();
        if ($cur_stage == 'tor') {
            self::set_stage('offline');
        } elseif ($cur_stage == 'online' || $cur_stage == 'offline' ||
                  $cur_stage == 'setup' || $cur_stage == 'wansetup') {
            self::set_stage('tor');
        } else {
            return false;
        }
        return true;
    }

    static public function toggle_vpn()
    {
        $cur_stage = self::get_stage();
        if ($cur_stage == 'vpn') {
            self::set_stage('offline');
        } elseif ($cur_stage == 'online' || $cur_stage == 'offline' ||
                  $cur_stage == 'setup' || $cur_stage == 'wansetup') {
            self::set_stage('vpn');
        } else {
            return false;
        }
        return true;
    }

    static public function wan_ssid()
    {
        $client = new NakdClient();
        $connection = $client->doCommand('connectivity');
        // DEBUG: var_dump($client->doCommand('stage_info'));
        // DEBUG: var_dump($output);
        if(!$connection['local']) {
			return _('<span style="color: red;">Disconnected</span>');
		} else {
			$output = $client->doCommand('wlan_current','WLAN');
			if ($output == 0)
				return _('Wired connection');
			else
				return $output['ssid'];
		}
    }

    static public function do_update($image_file)
    {

        $pid = pcntl_fork();
        if ($pid == -1) {
             die('could not fork');
        } else if ($pid) {
            //parent
        } else {
            $client = new NakdClient();
            $output = $client->doCommand('doupdate', array($image_file));
        }
    }

    static public function routing_status()
    {
        $client = new NakdClient();		
        $stage_req = $client->doCommand('stage_status');
		$cur_stage = self::get_stage();
		if((isset($stagereq['name']) && $stagereq['name']=='online') || ($cur_stage != 'offline' && $cur_stage != 'setup' && $cur_stage != 'tor' && $cur_stage != 'vpn')) {
			$mode = TRUE;
		} else {
			$mode = FALSE;
		}
        return $mode;
    }

    static public function toggle_routing($mode)
    {
		$cur_stage = self::get_stage();
		if($cur_stage != 'vpn' && $cur_stage != 'tor') {
			if($mode == 'on' && $cur_stage == 'offline') {
				self::set_stage('online');
			} else {
				self::set_stage('offline');
			}
		}
        return true;
    }

	// FIXME!
    static public function toggle_broadcast($mode)
    {
        if ($mode != 'on')
            $mode = 'off';

        // 			 $client = new NakdClient();
        // DISABLED: $output = $client->doCommand('broadcst', array($mode));
        $output = FALSE;	//stub

        return true;
    }

	// FIXME!
    static public function broadcast_hidden_status()
    {
        // $setting = shell_exec('uci show wireless.@wifi-iface[1].hidden');
        $mode = substr($setting, -3, 1);
        return $mode;
    }

    static public function factory_reset()
    {
        unlink(ROOT_DIR . '/data/configured');
        self::set_stage('reset');
        return shell_exec('/nak/scripts/reset.sh');
    }
    
    static public function detect_portal() {
        $client = new NakdClient();
        $output = $client->doCommand('connectivity');
        return ($output['local']==TRUE && $output['internet']==FALSE ? TRUE : FALSE);
    }

    static public function release_info() {
        return file_get_contents('/etc/nak-release');
    }
}
