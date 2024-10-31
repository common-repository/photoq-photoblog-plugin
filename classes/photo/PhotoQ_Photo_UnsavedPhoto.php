<?php
class PhotoQ_Photo_UnsavedPhoto
{
	private $_importStrategy;
	
	private $_title;
	private $_description = '';
	private $_tags;
	private $_slug = '';
	
	private $_oc;
	private $_errStack;
	private $_db;
	
	public function __construct(PhotoQ_File_Importer $importStrategy, $title, $tags){
		$this->_importStrategy = $importStrategy;
		$this->_title = $title;
		$this->_tags = $tags;
		$this->_oc = PhotoQ_Option_OptionController::getInstance();
		$this->_errStack = PEAR_ErrorStack::singleton('PhotoQ');
		$this->_db = PhotoQDB::getInstance();
	}
	
	//uploads a photo, creates thumbnail and puts it to the end of the queue
	function saveToQueue()
	{	
		if (!$path = $this->_importStrategy->import())
			return false;
				
		//get exif meta data
		$exif = PhotoQExif::readExif($path);
		
		PhotoQHelper::debug('saveToQueue: exif read');
		
		$dateTime = $this->_getValidDateTime($exif);
		$exifDescr = $exif['ImageDescription'];
		
		
		// use EXIF image description if none was provided
		if ($this->_oc->getValue('descrFromExif'))
			$this->_description = $exifDescr;
			
		if(!empty($exifDescr) && $this->_oc->getValue('autoTitleFromExif'))
			$this->_title = $exifDescr;
		
		//add IPTC keywords to default tags
		$this->_tags .= $exif['Keywords'];
					
		$exif = serialize($exif);
	
	    $filename = basename($path);
		
	    $postAuthor = $this->_getPostAuthor();
	    
		//make nicer titles
		$titleGenerator = new PhotoQTitleGenerator($this->_oc->getValue('autoTitleRegex'), 
			$this->_oc->getValue('autoTitleNoCaps'), $this->_oc->getValue('autoTitleNoCapsShortWords'),
			$this->_oc->getValue('autoTitleCaps')
		);
		$this->_title = $titleGenerator->generateAutoTitleFromFilename($this->_title);
				
		$queue = PhotoQQueue::getInstance();
		//add photo to queue
		if(!$this->_db->insertQueueEntry($this->_title, $filename, $queue->getLength(), 
			$this->_slug, $this->_description, $this->_tags, $exif, $dateTime, $postAuthor)
		){
			return false;
		}	
		//get the id assigned to this entry
		$imgID = mysql_insert_id();
	
		PhotoQHelper::debug('saveToQueue: post added to DB. ID: '.$imgID);

		$this->_db->insertPostCategories($imgID, $this->_oc->getValue('qPostDefaultCat'));
		$this->_db->insertPostFieldMeta($imgID);
		
		$this->_errStack->push(PHOTOQ_INFO_MSG,'info', array(),
				sprintf(_c('Successfully uploaded. \'%1$s\' added to queue at position %2$d.|filename postion', 'PhotoQ'), $filename, $queue->getLength() + 1));
		
		return true;
	}
	
	private function _getValidDateTime($exif){	
		$dateTime = $exif['DateTimeOriginal'];
		if ( empty($exif['DateTimeOriginal']) || '0000:00:00 00:00:00' == $dateTime){
			$dateTime = current_time('mysql');
		}
		return $dateTime;
	}
	
	private function _getPostAuthor(){
		global $user_ID;	
		if(empty($user_ID))
			return $this->_oc->getValue('qPostAuthor');
		else
			return $user_ID;
	}
}