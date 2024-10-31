<?php
class PhotoQ_Command_BatchRebuildPublishedPhoto
extends PhotoQ_Command_BatchAtomic
implements PhotoQ_Command_Batchable
{
	
	private $_photoId;
	
	private $_changedSizes;
	private $_updateExif;
	private $_changedViews;
	private $_updateOriginalFolder;
	private $_oldFolder;
	private $_newFolder;
	private $_addedTags;
	private $_deletedTags;
	
	public function __construct($photoId, 
		$changedSizes, $updateExif, $changedViews, $updateOriginalFolder, 
		PhotoQ_File_SourceDestinationPair $srcDest, $addedTags, $deletedTags
	){
		$this->_photoId = $photoId;
		$this->_changedSizes = $changedSizes;
		$this->_updateExif = $updateExif;
		$this->_changedViews = $changedViews;
		$this->_updateOriginalFolder = $updateOriginalFolder;
		$this->_oldFolder = $srcDest->getSource();
		$this->_newFolder = $srcDest->getDestination();
		$this->_addedTags = $addedTags;
		$this->_deletedTags = $deletedTags;
	}
	
	protected function _executeAtom(){
		//if we are changing original dir it is normal to get some photo not found errors
		//we therefore silence these in this case
		$errStack = PEAR_ErrorStack::singleton('PhotoQ');
		if($this->_updateOriginalFolder)
			$errStack->pushCallback(array('PhotoQ_Error_ErrorHandler', 'mutePHOTOQ_PHOTO_NOT_FOUND'));
		
		//get photo
		$db = PhotoQDB::getInstance();
		$photo = $db->getPublishedPhoto($this->_photoId);
		//rebuild it		
		if($photo)
			$photo->rebuild($this->_changedSizes, $this->_updateExif, 
				$this->_changedViews, $this->_updateOriginalFolder, 
				$this->_oldFolder, $this->_newFolder, $this->_addedTags, 
				$this->_deletedTags);
	}
	
}