<?php
class PhotoQ_Command_RenameField extends PhotoQ_Command_DatabaseCommand
{
	public function execute(){
		$this->_db->renameField(esc_attr($_POST['field_id']), esc_attr($_POST['field_name']));
	}
}