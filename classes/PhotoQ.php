<?php
/**
 * Creates all the components needed by PhotoQ and hooks them up with WordPress.
 * @author  M.Flury
 * @package PhotoQ
 */
class PhotoQ
{
	const VERSION = '1.9b';
	
	public function __construct()
	{
		PhotoQHelper::debug('-----------start plugin-------------');
		
		$this->_setupPluginLocalization();
		
		$handlers = $this->_createWordPressCallbackHandlers();
		$this->_registerHandlersWithWordPress($handlers);
				
		PhotoQHelper::debug('leave __construct()');
	}
	
	private function _setupPluginLocalization(){
		load_plugin_textdomain('PhotoQ', '', 'photoq-photoblog-plugin/lang');
	}
	
	private function _createWordPressCallbackHandlers(){
		$handlers = array();
		
		$handlers[] = new PhotoQAdminPages();
		
		$oc = PhotoQ_Option_OptionController::getInstance();
		$adminThumbDimension = new PhotoQ_Photo_Dimension(
			$oc->getValue('showThumbs-Width'),
			$oc->getValue('showThumbs-Height')
		);
		$handlers[] = new PhotoQDashboard($adminThumbDimension);
		$handlers[] = new PhotoQEditPostsDisplay($adminThumbDimension);
		
		$handlers[] = new PhotoQAjaxHandler();
		$handlers[] = new PhotoQCustomRequestHandler();
		
		$handlers[] = new PhotoQ_Util_Upgrader();
		$handlers[] = new PhotoQContextualHelp();
		$handlers[] = new PhotoQFavoriteActions();
		
		$handlers[] = new PhotoQWordPressEditor();
		$handlers[] = new PhotoQ_Util_GarbageCollector();
		
		return $handlers;
	}
	
	private function _registerHandlersWithWordPress(array $handlers){
		foreach($handlers as $handler){
			$this->_hookIntoWordPress($handler);
		}
	}
	
	private function _hookIntoWordPress(PhotoQHookable $handler){
		$handler->hookIntoWordPress();	
	}

	/**
	 * Called by cronjob file. Allows automatic publishing of top photos
	 * in queue via cronjob. Can be replaced by the custom request handler 'cronjob'.
	 */
	public function cronjob()
	{	
		PhotoQHelper::debug('enter cronjob()');
		$queue = PhotoQQueue::getInstance();
		$queue->publishViaCronjob();
		PhotoQHelper::debug('leave cronjob()');
	}

}