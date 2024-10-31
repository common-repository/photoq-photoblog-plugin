<?php
class PhotoQ_Command_AddField extends PhotoQ_Command_DatabaseCommand
{
	public function execute(){
		$oc = PhotoQ_Option_OptionController::getInstance();
		$this->_db->insertField(esc_attr($_POST['newFieldName']), $oc->getValue('fieldAddPosted'));
	}
}