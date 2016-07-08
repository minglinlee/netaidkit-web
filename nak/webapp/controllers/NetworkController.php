<?php

class NetworkController extends Page
{
    protected $_allowed_actions = array('index','save','set_stored_wifi');

    public function init()
    {
        if ($_SESSION['logged_in'] != 1)
            $this->_redirect('/user/login');
    }

    public function index()
    {
		$cur_stage = NetAidManager::init_stage();

        $broadcast_hidden_status = NetAidManager::broadcast_hidden_status();

        $params = array('broadcast_hidden_status' => $broadcast_hidden_status);
        $view = new View('network', $params);
        return $view->display();
    }

    public function save()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $broadcast_ssid = $request->postvar('broadcast_ssid');

            $mode = $broadcast_ssid ? 'on' : 'off';
            $success = NetAidManager::toggle_broadcast($mode);

            if ($success)
                $this->_addMessage('info',
                    _('Successfully saved network settings.'), 'network');

            if ($request->isAjax()) {
                echo ($success) ? "SUCCESS" : "FAILURE";
                exit;
            }
        };
    }

    public function set_stored_wifi()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $action = $request->postvar('action');
            switch($action) {
				case 'setauto':
					$ssid = $request->postvar('ssid');
					$properties = array('auto' => $request->postvar('auto'));
					$output = NetAidManager::set_stored_wifi($ssid,$properties);
				break;
				case 'delete':
					$ssid = $request->postvar('ssid');
					$output = NetAidManager::del_stored_wifi($ssid);
				break;
			}

            if ($request->isAjax()) {
				$success = TRUE; // stopgap
                echo ($success) ? "SUCCESS ".$action.' -> '.var_dump($output) : "FAILURE";
                exit;
            }
        };
    }

}
