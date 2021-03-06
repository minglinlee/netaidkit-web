<?php

class ClientController extends Page
{
    protected $_allowed_actions = array('locale', 'set_locale', 'translate');

    public function locale() {
        if (I18n::settings_get_language() !== false ||
            I18n::settings_get_autodetect()) {

            $cur_stage = NetAidManager::get_stage();
            if ($cur_stage != 'setup' && $cur_stage != 'wansetup') {
              $this->_redirect('/admin/index');
            } else {
              $this->_redirect('/setup/index');
            }
        }

        $params = array();
        $view = new View('setup_lang', $params);
        return $view->display();
    }

    public function set_locale() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $lang = $request->postvar('language');
            if (empty($lang))
                return;

            if ($lang == 'auto') { 
                I18n::settings_set_autodetect();
            } else {
                // sanitized in I18n class
                I18n::settings_set_language($lang);
            }
        }
    }
    
	
    public function translate() {
        $request = $this->getRequest();
        if ($request->isPost()) {
			$params = $request->postvar('q');
			$view = new View('ajax', $params);
			$view->display();
			exit;
		}
    }    
}
