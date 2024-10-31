<?php
class PhotoQ_Util_ScriptLoader
{
	protected $_pageHook;
	
	public function __construct($pageHook){
		$this->_pageHook = $pageHook;
	}
	
	public function registerScriptCallbacksWithWordPress(){
		add_action("admin_print_styles-$this->_pageHook", array($this, 'injectCSS'), 1);
		add_action("admin_print_scripts-$this->_pageHook", array($this, 'injectCommonJSScripts'), 1);
	}
	
	public function injectCSS(){
		wp_enqueue_style('photoQCSS', plugins_url('photoq-photoblog-plugin/css/photoq.css'));
	}
	
	public function injectCommonJSScripts(){
		wp_enqueue_script('mini-postbox', plugins_url('photoq-photoblog-plugin/js/mini-postbox.js'), array('jquery'),'20080808');
	}
}