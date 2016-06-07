<?php

class NetAidManager
{
    static public function setup_ap($ssid, $key)
    {
        if (empty($ssid) || empty($key))
            return false;

        $client = new NakdClient();
        $output = $client->doCommand('configure_ap', array('ssid' => $ssid, 'key' => $key));
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

    static public function list_wifi()
    {
        $client = new NakdClient();
        $output = $client->doCommand('wlan_list');

        $wifi_list = array();
        if(is_array($output)) {
			foreach($output as $i => $wifi) {
				$ssid = $wifi['ssid'];
				$enctype = $wifi['encryption'];
				if ($enctype == 'none')
					$enctype = 'Open';
				$wifi_list[$ssid] = $enctype;
			}
			asort($wifi_list);
		}
		$wifi_list=array(_('Wired connection')=>'Wired')+$wifi_list; // add wired connection
        return $wifi_list;
    }

    static public function setup_wan($ssid, $key)
    {
		if($ssid != _('Wired connection')) {
			if (empty($ssid))
				return false;			
			$wifi_list = NetAidManager::list_wifi();
			$enctype = $wifi_list[$ssid];
			$client = new NakdClient();
			$output = $client->doCommand('wlan_connect', array('ssid' => $ssid, 'key' => $key, 'encryption' => $enctype, 'store' => TRUE));
		} else {	# reset uplink wifi
			$output = shell_exec('uci set wireless.@wifi-iface[0].disabled=1 && uci set wireless.@wifi-iface[0].ssid="" && uci set wireless.@wifi-iface[0].encryption="" && uci set wireless.@wifi-iface[0].key="" && uci commit wireless && wifi');
			sleep(3);
		}
		
        return true;
    }

	// DEPRECATED
    //static public function go_online()
    //{
    //    self::set_stage('online');
    //    return true;
    //}

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
        $output = $client->doCommand('stage_info');
		if(!isset($output['name']) && file_exists(ROOT_DIR . '/data/pass')) $output = array( 'name' => 'offline' );
		if(!isset($output['name']) && !file_exists(ROOT_DIR . '/data/pass')) $output = array( 'name' => 'reset' );
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
        if ($cur_stage == 'reset')
			header('Location: /setup/ap');
		return $cur_stage;
	}
	
    static public function set_stage($stage)
    {
        if (empty($stage))
            return false;

        $client = new NakdClient();
        $output = $client->doCommand('stage_set',$stage);
		sleep(3);
        return true;
    }

    static public function toggle_tor()
    {
        $cur_stage = self::get_stage();
        if ($cur_stage == 'tor') {
            $mode = 'off';
            self::set_stage('online');
        } elseif ($cur_stage == 'online' || $cur_stage == 'reset') {
            $mode = 'on';
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
            $mode = 'off';
            self::set_stage('online');
        } elseif ($cur_stage == 'online' || $cur_stage == 'reset') {
            $mode = 'on';
            self::set_stage('vpn');
        } else {
            return false;
        }
        return true;
    }

    static public function wan_ssid()
    {
        $client = new NakdClient();
        $output = $client->doCommand('wlan_current','WLAN');
        // DEBUG: var_dump($client->doCommand('stage_info'));
        if($output['disabled']) {
			return _('<span style="color: red;">Disconnected</span>');
		} else {
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

    static public function toggle_routing($mode)
    {
        if ($mode != 'on')
            $mode = 'off';
		$cur_stage = self::get_stage();
		if($cur_stage != 'vpn' && $cur_stage != 'tor') {
			if($mode == 'on') {
				self::set_stage('offline');
			} else {
				self::set_stage('online');
			}
		}
        return true;
    }

    static public function toggle_broadcast($mode)
    {
        if ($mode != 'on')
            $mode = 'off';

        $client = new NakdClient();
        //$output = $client->doCommand('broadcst', array($mode));
        $output = FALSE;	//stub

        return true;
    }

    static public function routing_status()
    {
        $setting = shell_exec('uci show firewall.@forwarding[0].enabled');
        $mode = substr($setting, -3, 1);
        return $mode;
    }

    static public function broadcast_hidden_status()
    {
        $setting = shell_exec('uci show wireless.@wifi-iface[1].hidden');
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
        return ($output['local']==true && $output['internet']==false ? true : false);
    }

    static public function release_info() {
        return file_get_contents('/etc/nak-release');
    }
}
