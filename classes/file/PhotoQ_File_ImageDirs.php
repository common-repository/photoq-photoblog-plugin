<?php
class PhotoQ_File_ImageDirs
{
	const THUMB_IDENTIFIER = 'thumbnail';
	const MAIN_IDENTIFIER = 'main';
	const ORIGINAL_IDENTIFIER = 'original';
	const ORIGINAL_IDENTIFIER_DB_NAME = 'wimpq_originalFolder';
	
	private $_currentOriginalDirName = self::ORIGINAL_IDENTIFIER;
	
	public function __construct(){
		//get alternative original identifier if available
		$originalID = get_option(self::ORIGINAL_IDENTIFIER_DB_NAME);
		if($originalID)
			$this->setCurrentOriginalDirName($originalID);	
	}
	
	public function getCurrentOriginalDirName(){
		return $this->_currentOriginalDirName;
	}
	
	public function setCurrentOriginalDirName($newName){
		$this->_currentOriginalDirName = $newName;
	}
	
	public function isOriginalHidden(){
		return $this->_currentOriginalDirName !== self::ORIGINAL_IDENTIFIER;
	}
	
	/**
	 * Change "original" folder name to a random string if desired.
	 *
	 */
	public function updateOriginalFolderName($oldImgDir, $hideOriginals, $imgDir){
		$newName = self::ORIGINAL_IDENTIFIER;
		if($hideOriginals){
			//generate a random name
			$newName .= substr(md5(rand()),0,8);
		}
		$this->setCurrentOriginalDirName($newName);	
		
		//update option plus get old name
		$oldName = get_option(self::ORIGINAL_IDENTIFIER_DB_NAME);
		if($oldName)
			update_option(self::ORIGINAL_IDENTIFIER_DB_NAME, $newName);
		else{
			$oldName = self::ORIGINAL_IDENTIFIER;
			add_option(self::ORIGINAL_IDENTIFIER_DB_NAME, $newName);
		}
		return new PhotoQ_File_SourceDestinationPair($oldImgDir.$oldName, $imgDir.$newName);
	}
	
}