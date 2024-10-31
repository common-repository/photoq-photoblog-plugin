<?php
class PhotoQ_File_ServerCopier extends PhotoQ_File_Importer
{
	
	private $_sourcePath;
	
	public function __construct($destinationDir, $sourcePath){
		$this->_sourcePath = $sourcePath;
		parent::__construct($destinationDir);
	}
	
	public function import(){
		$newPath = $this->getDestinationDir() . '/' . basename($this->_sourcePath);
		//move file if we have permissions, otherwise copy file
		//suppress warnings if original could not be deleted due to missing permissions
		if(!@PhotoQHelper::moveFileIfNotExists(new PhotoQ_File_SourceDestinationPair($this->_sourcePath, $newPath))){
			$errMsg = sprintf(__('Unable to move %1$s to %2$s', 'PhotoQ'), $this->_sourcePath, $newPath);
			$this->_errStack->push(PHOTOQ_FILE_UPLOAD_FAILED,'error', array('errMsg' => $errMsg));
			return false;
		}
		return $newPath;
	}
	
	
}