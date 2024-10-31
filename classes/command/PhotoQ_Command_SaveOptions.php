<?php
class PhotoQ_Command_SaveOptions extends PhotoQ_Command_PhotoQCommand
{
	public function execute(){
		$this->_updateController();
		$this->_showMessage(__('Options saved.', 'PhotoQ'));
	}
	
	private function _updateController(){
		$oc = PhotoQ_Option_OptionController::getInstance();
		$oc->update();
	}
	
	private function _showMessage($msg){
		$errStack = PEAR_ErrorStack::singleton('PhotoQ');
		$errStack->push(PHOTOQ_INFO_MSG,'info', array(), $msg);
	}
}