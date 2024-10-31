<?php
class PhotoQManagePageHandler extends PhotoQPageHandler
{
	protected $_queue;
	protected $_defaultPanel = 'manage.php';
	
	protected function _initialize(){
		$this->_queue = PhotoQQueue::getInstance();
		parent::_initialize();
	}
	
	protected function _createDefaultAction(){
		return new PhotoQ_Form_DefaultAction(
			$this->_buildMacroCommandShowingDefaultPanel());
	}
	
	protected function _createActionArray(){
		return array(
			new PhotoQ_Form_PostAction(
				'add_entry', 'photoq-manageQueue',
				new PhotoQ_Command_ShowPhotoUploadPanel($this)
			),
			new PhotoQ_Form_PostAction(
				'ftp_upload', 'photoq-ftpImport',
				new PhotoQ_Command_ShowFTPPhotoUploadPanel($this)
			),
			new PhotoQ_Form_PostAction(
				'edit_batch', 'photoq-editBatch',
				new PhotoQ_Command_EditBatch($this)
			),
			new PhotoQ_Form_PostAction(
				'batch_upload', 'photoq-uploadBatch',
				new PhotoQ_Command_ProcessBatchUpload($this)
			),
			new PhotoQ_Form_PostAction(
				'save_batch', 'photoq-saveBatch',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_SaveBatch($this)))
			),
			new PhotoQ_Form_GetAction(
				'delete', 'photoq-deleteQueueEntry',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_DeletePhoto($this->_queue)))
			),
			new PhotoQ_Form_GetAction(
				'rebuild', 'photoq-rebuildPost',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_RebuildPost($this)))
			),
			new PhotoQ_Form_GetAction(
				'nothanks', 'photoq-noThanks',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_ResetReminderCounters($this)))
			),
			new PhotoQ_Form_GetAction(
				'alreadydid', 'photoq-noThanks',
				$this->_buildMacroCommandShowingDefaultPanel(
					array(
						new PhotoQ_Command_IncreaseReminderThreshold($this),
						new PhotoQ_Command_ResetReminderCounters($this)
					)
				)
			),
			new PhotoQ_Form_PostAction(
				'post_first', 'photoq-manageQueue',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_PublishTop($this->_queue)))
			),
			new PhotoQ_Form_PostAction(
				'post_multi', 'photoq-manageQueue',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_PublishMulti($this->_queue)))
			),
			new PhotoQ_Form_PostAction(
				'clear_queue', 'photoq-manageQueue',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_ClearQueue($this->_queue)))
			),
			new PhotoQ_Form_PostAction(
				'sort_queue', 'photoq-manageQueue',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_SortQueue($this->_queue)))
			)
		);
	}
	
	public function preparePanel(){
		//refresh the queue as it might have changed because of above operations
		$this->_queue->load();
			
		$this->_showDonationReminder();
		if(isset($status)) 
			$status->show();		
		PhotoQ_Error_ErrorHandler::showAllErrors($this->_errStack);
	}
	
	/**
	 * If more than 50 photos have been posted since the last time the reminder has been shown and if more than 100 days
	 * have elapsed since then, the reminder is shown.
	 * @return unknown_type
	 */
	private function _showDonationReminder(){
		$postedSinceLastReminder = get_option('wimpq_posted_since_reminded');
		$reminderThreshold = get_option('wimpq_reminder_threshold');
		if($reminderThreshold < 50)
			$reminderThreshold = 50;
		$then = get_option('wimpq_last_reminder_reset');
		$now = time();
		if($postedSinceLastReminder > $reminderThreshold && $now - $then > 100 * 86400){
			require_once(PHOTOQ_PATH.'panels/reminder.php');
		}
	}
	
	//these are the receiving callback functions
	public function showPhotoUploadPanel(){
		$this->_createDirIfNotExists($this->_oc->getQDir());
		//show errors if any
		PhotoQ_Error_ErrorHandler::showAllErrors($this->_errStack);
		require_once(PHOTOQ_PATH.'panels/upload.php');
	}
	
	public function editBatch(){
		PhotoQHelper::debug('handleManagePage: load edit-batch panel');
		if($this->_isFTPUpload()){
			$this->_uploadFTPPhotos();		
		}
		require_once(PHOTOQ_PATH.'panels/edit-batch.php');
		PhotoQHelper::debug('handleManagePage: edit-batch panel loaded');
	}
	
	private function _uploadFTPPhotos(){
		foreach ($_POST['ftpFiles'] as $ftpFile){
			$photo = new PhotoQ_Photo_UnsavedPhoto(
				new PhotoQ_File_ServerCopier($this->_oc->getQDir(), $ftpFile),
				basename($ftpFile), $this->_oc->getValue('qPostDefaultTags')
			);
			$photo->saveToQueue();
		}
		//refresh the queue
		$this->_queue->load();
	}
	
	public function processBatchUpload(){
		$photo = new PhotoQ_Photo_UnsavedPhoto(
			new PhotoQ_File_Uploader($this->_oc->getQDir()),
			$_FILES['Filedata']['name'], $this->_oc->getValue('qPostDefaultTags')
		);
		$photo->saveToQueue();
		if(!$_POST['batch_upload']){
			//show errors if any
			PhotoQ_Error_ErrorHandler::showAllErrors($this->_errStack);
			//refresh the queue as a photo was added
			$this->_queue->load();
			require_once(PHOTOQ_PATH.'panels/edit-batch.php');
		}
	}
	
	
	public function saveBatch(){
		//uploaded file info is stored in arrays
		$no_upl = count(PhotoQHelper::arrayAttributeEscape($_POST['img_title']));
		for ($i = 0; $i<$no_upl; $i++) {
			$this->_db->updateQueue(
				esc_attr($_POST['img_id'][$i]), esc_attr($_POST['img_title'][$i]), 
				$_POST['img_descr'][$i], esc_attr($_POST['tags_input'][$i]), 
				esc_attr($_POST['img_slug'][$i]), esc_attr($_POST['img_author'][$i]), 
				$this->_oc->getValue('qPostDefaultCat'), $i);
		}
	}
	
	/**
	 * Are we currently doing an ftp upload?
	 * @return boolean
	 */
	protected function _isFTPUpload(){
		return !is_multisite() && ( isset($_POST['ftpFiles']) || isset($_POST['ftp_upload']));
	}
	
	protected function showFTPFileList(){
		$ftpDir = $this->_oc->getFTPDir();
		echo '<p>' . sprintf(__('Import the following photos from: %s', 'PhotoQ'), "<code> $ftpDir </code>") . '</p>';
		if (!is_dir($ftpDir)) {
			$this->_errStack->push(PHOTOQ_DIR_NOT_FOUND, 'error', array('dir' => $ftpDir));
			//show errors if any
			PhotoQ_Error_ErrorHandler::showAllErrors($this->_errStack);
		}else{
			$ftpDirContent = PhotoQHelper::getMatchingDirContent($ftpDir,'#.*\.(jpg|jpeg|png|gif)$#i');
				foreach ($ftpDirContent as $file)
					echo '<input type="checkbox" name="ftpFiles[]" value="'. $file .'" checked="checked" /> '.basename($file).'<br/>';
		
			}
	}
	
	function rebuildPost(){
		$postID = esc_attr($_GET['id']);
		$photo = $this->_db->getPublishedPhoto($postID);
		if($photo){
			$photo->rebuild($this->_oc->getImageSizeNames(), true, $this->_oc->getViewNames());
			$this->_errStack->push(PHOTOQ_INFO_MSG,'info', array(), __("Photo post with id $postID rebuilt.", 'PhotoQ'));
		}
	}
	
	
}