<?php
class PhotoQ_Command_DeleteField extends PhotoQ_Command_DatabaseCommand
{
	public function execute(){
		$this->_db->removeField(esc_attr($_GET['id']));
	}
}