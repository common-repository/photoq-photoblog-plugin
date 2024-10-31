<?php
class PhotoQSettingsPageHandler extends PhotoQPageHandler
{
	
	protected $_bp;
	protected $_rebuildAll = false;
	protected $_defaultPanel = 'options.php';
	
	public function __construct(){
		parent::__construct();
		$this->_bp = new PhotoQ_Batch_BatchProcessor();
	}
	
	protected function _getRequestedAction(){
		if($this->_oc->wasFormSubmitted()){
			return new PhotoQ_Form_DefaultAction(
				$this->_buildMacroCommandShowingDefaultPanel(
					array(new PhotoQ_Command_SaveOptions($this))
				)
			);
		}else{//custom actions are treated by parent
			return parent::_getRequestedAction();
		}
	}
	
	
	
	protected function _createDefaultAction(){
		return new PhotoQ_Form_DefaultAction(
			$this->_buildMacroCommandShowingDefaultPanel());
	}
	
	protected function _createActionArray(){
		return array(
			new PhotoQ_Form_PostAction(
				'showWatermarkUploadPanel', 'wimpq_options-nonce', 
				new PhotoQ_Command_ShowPanel($this, 'uploadWatermark.php')
			),
			new PhotoQ_Form_PostAction(
				'rebuildAll', 'wimpq_options-nonce',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_EnableRebuildAll($this)))
			),
			new PhotoQ_Form_PostAction(
				'importXML', 'photoqImportXML-nonce',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_ImportXML($this)))
			),
			new PhotoQ_Form_PostAction(
				'uploadWatermark', 'wimpq_options-nonce',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_UploadWatermark($this)))
			),
			new PhotoQ_Form_PostAction(
				'fixPermissions', 'wimpq_options-nonce',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_FixPermissions($this)))
			),
			new PhotoQ_Form_PostAction(
				'addField', 'wimpq_options-nonce',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_AddField($this->_db)))
			),
			new PhotoQ_Form_PostAction(
				'renameField', 'wimpq_options-nonce',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_RenameField($this->_db)))
			),
			new PhotoQ_Form_GetAction(
				'deleteField', 'photoq-deleteField',
				$this->_buildMacroCommandShowingDefaultPanel(array(new PhotoQ_Command_DeleteField($this->_db)))
			)
		);
	}
	
	public function preparePanel(){
				
		if($this->_rebuildAll){
			$changedSizes = $this->_oc->getImageSizeNames();
		}else{
			$changedSizes = $this->_oc->getChangedImageSizeNames();
			if($this->_watermarkHasChanged())
				$changedSizes = $this->_oc->appendImageSizesWithWatermark($changedSizes);
		}
			
		if($this->_oc->shouldRebuild($changedSizes)){
			$this->_setupBatchRebuild($changedSizes);
		}

		//do input validation on options
		$this->_oc->validate();

		//make sure we have freshest data possible.
		$this->_oc->initRuntime();
			
		if($this->_bp->isRegisteredWithDB()){
			$this->_triggerJavaScriptBatchProcessing();
		}

		PhotoQ_Error_ErrorHandler::showAllErrors($this->_errStack);

	}
	
	private function _watermarkHasChanged(){
		return $this->_oc->hasChanged('watermarkOptions') || $this->_newWatermarkUpoaded;
	}
	
	private function _setupBatchRebuild($changedSizes){
		
		//first determine what needs to be rebuilt
		$updateExif = $this->_rebuildAll ? true : $this->_oc->hasChanged('exifTags') || $this->_oc->hasChanged('exifDisplay');
		$addDelTagFromExifArray = $this->_oc->getAddedDeletedTagsFromExif();

		$updateOriginalFolder = $this->_rebuildAll ? true : ($this->_oc->hasChanged('originalFolder')  || $this->_oc->hasChanged('imgdir'));

		$changedViews = $this->_oc->getChangedViewNames($changedSizes, $updateExif, $updateOriginalFolder);
		//if full rebuild was selected we rebuild everything
		if($this->_rebuildAll || $this->_oc->hasChanged('imgdir'))
			$changedViews = $this->_oc->getViewNames();
		
		//these two operations should not take too long so do them outside the batch
		if($publishedPhotoIDs = $this->_db->getAllPublishedPhotoIDs()){
			$oldNewFolderName = $this->_rebuildFileSystem($updateOriginalFolder, $changedSizes, $this->_oc->hasChanged('imgdir'), $this->_oc->getImgDir());

			$batchCommand = new PhotoQ_Command_BatchMacro();
			foreach($publishedPhotoIDs as $id){
				$batchCommand->addCommand(
					new PhotoQ_Command_BatchRebuildPublishedPhoto($id, 
						$changedSizes, $updateExif, $changedViews, 
						$updateOriginalFolder, $oldNewFolderName, 
						$addDelTagFromExifArray[0], $addDelTagFromExifArray[1]
					)
				);
			}
			
			if( $this->_bp->queueCommand($batchCommand)){
				if(!empty($changedSizes))
					$this->_errStack->push(PHOTOQ_INFO_MSG,'info', array(), __('Updating following image sizes:', 'PhotoQ') . ' ' . implode(", ", $changedSizes));
				if(!empty($changedViews))
					$this->_errStack->push(PHOTOQ_INFO_MSG,'info', array(), __('Updating following views:', 'PhotoQ') . ' ' . implode(", ", $changedViews));
				$this->_errStack->push(PHOTOQ_INFO_MSG,'info', array(), __('Updating all published Photos...', 'PhotoQ') );
			}
		}
	}
	
	private function _triggerJavaScriptBatchProcessing(){
		?>
			<script type="text/javascript">
				var batchId =  <?php echo $this->_bp->getId(); ?>;
				var ajaxNonce = "<?php echo wp_create_nonce( 'photoq-batchProcess' ); ?>";
				var ajaxUrl = "<?php echo plugins_url('photoq-photoblog-plugin/whoismanu-photoq-ajax.php'); ?>";
			</script>
		<?php
	}
	
	/**
	 * Display current watermark <img> tag or the string 'None' if there is no watermark.
	 *
	 */
	protected function _showCurrentWatermark(){
		$path = get_option( "wimpq_watermark" );
		if(!$path)
			_e('None', 'PhotoQ');
		else{
			$size = getimagesize($path);
			echo '<img class="watermark" width="'.$size[0].'" height="'.$size[1].'" alt="PhotoQ Watermark" src="'. PhotoQHelper::getRelUrlFromPath($path) .'" />';
		}
	}
	
	/*
	 * display the list of currently used metafields
	 */
	protected function _showMetaFields()
	{
		if($results = $this->_db->getAllFields()){
			$i = 0; //used to alternate styles
			foreach ($results as $field_entry) {

				echo '<tr valign="top"';
				if(($i+1)%2) {echo ' class="alternate"';}
				echo '>';
				if ($_GET['action'] == 'rename' && $_GET['id'] == $field_entry->q_field_id ) {
					echo '<td><p><input type="text" name="field_name" size="15" value="'.$field_entry->q_field_name.'"/></p></td>';
					echo '<td><input type="hidden" name="field_id" size="15" value="'.$field_entry->q_field_id.'"/>&nbsp;</td><td><p><input type="submit" class="button-secondary" name="renameField" value="Rename field &raquo;" /></p></td>';
				}else{
					echo '<td>'.$field_entry->q_field_name.'</td>';
					echo '<td><a href="options-general.php?page=whoismanu-photoq.php&amp;action=rename&amp;id='.$field_entry->q_field_id.'" class="edit">Rename</a></td>';

					$delete_link = 'options-general.php?page=whoismanu-photoq.php&amp;action=deleteField&amp;id='.$field_entry->q_field_id;
					$delete_link = ( function_exists('wp_nonce_url') ) ? wp_nonce_url($delete_link, 'photoq-deleteField' . $field_entry->q_field_id) : $delete_link;
					echo '<td><a href="'.$delete_link.'" class="delete" onclick="return confirm(\'Are you sure?\');">Delete</a></td>';
				}
				echo '</tr>';

				$i++;
			}

		}else{
			echo '<tr valign="top"><td colspan="3">'. __('No fields so far. You can add some if you like.','PhotoQ').'</td></tr>';
		}
	}
	
	public function enableRebuildAll(){
		$this->_rebuildAll = true;
	}
	
	public function importXMLFile($xmlFilename){
		$xmlParser = new PhotoQ_Option_XMLParser($xmlFilename);
		try{
			$xmlParser->importFromFile();
			$this->enableRebuildAll();
		}catch(PhotoQ_Error_Exception $e){
			$e->pushOntoErrorStack();		
		}
	}

	/**
	 * Fix PhotoQ file and folder permissios such that they match the ones from WP.
	 * @return unknown_type
	 */
	function fixPermissions(){
		//get permissions of imgdir = permissions of directories
		$stat = @stat( $this->_oc->getImgDir() );
		$dirPerms = $stat['mode'] & 0007777;  // Get the permission bits.
		
		//get permissions for files
		$filePerms = $stat['mode'] & 0000666;
		
		//change all files inside imgdir
		$topLevelDirs = $this->_getOldImgDirContent($this->_oc->getImgDir());
		foreach($topLevelDirs as $dir)
			$this->_recursiveChmod($dir, $dirPerms, $filePerms);
		
		//change all files inside cache dir
		$this->_recursiveChmod($this->_oc->getCacheDir(), $dirPerms, $filePerms);
		
	}
	
	function _recursiveChmod($dir, $dirPerms, $filePerms){
		@chmod($dir, $dirPerms);
		
		//get all visible files inside dir
		$match = '#^[^\.]#';//exclude hidden files starting with .
		$visibleFiles = PhotoQHelper::getMatchingDirContent($dir, $match);
		
		foreach($visibleFiles as $file){
			//echo $file .'<br/>';
			if(is_dir($file)){
				@chmod($file, $dirPerms);
				$this->_recursiveChmod($file, $dirPerms, $filePerms);	
			}else{
				@chmod($file, $filePerms);	
			}	
		}
	}
	
	
	function _rebuildFileSystem($updateOriginalFolder,$changedSizes, $imgDirChanged, $oldImgDir){
		
		//check whether imgdir changed. if so we have to move all the existing stuff and rebuild the content
		if($imgDirChanged){
			$this->_moveImgDir($oldImgDir, false);
		}

		$oldNewFolderName = new PhotoQ_File_SourceDestinationPair();
		if($updateOriginalFolder){
			$imgDirs = new PhotoQ_File_ImageDirs();
			$oldNewFolderName = $imgDirs->updateOriginalFolderName($oldImgDir, $this->_oc->getValue('hideOriginals'), $this->_oc->getImgDir());
			PhotoQHelper::moveFile($oldNewFolderName);
		}
			
		//remove the image dirs
		foreach ($changedSizes as $changedSize){
			PhotoQHelper::recursiveRemoveDir($this->_oc->getImgDir() . $changedSize . '/');
		}
		PhotoQHelper::debug('oldNewFolderName: ' . print_r($oldNewFolderName,true));
		
		return $oldNewFolderName;
	}
	
	function _moveImgDir($oldImgDir, $includingOriginal = true){
		//move all dirs to the new place
		$newImgDir = $this->_oc->getImgDir();
		$dirs2move = $this->_getOldImgDirContent($oldImgDir, $includingOriginal);
		foreach( $dirs2move as $dir2move){
			$moveTo = $this->_oc->getImgDir().basename($dir2move);
			if(!PhotoQHelper::moveFileIfNotExists(new PhotoQ_File_SourceDestinationPair($dir2move, $moveTo))){
					$this->_errStack->push(PHOTOQ_COULD_NOT_MOVE, 'error', array('source' => $dir2move, 'dest' => $moveTo));
			}//else
			// @todo we might want to use sth like this to make it more flexible for users who 
			//already messed up with their imgdir
			//	PhotoQHelper::mergeDirs($dir2move, $moveTo);
		}
		
		//update the watermark directory database entry
		$oldWatermarkPath = get_option( "wimpq_watermark" );
		if($oldWatermarkPath){
			$oldWMFolder = $oldImgDir.'photoQWatermark/';
			$newWMFolder = $newImgDir.'photoQWatermark/';
			$newWatermarkPath = str_replace($oldWMFolder, $newWMFolder, $oldWatermarkPath);
			update_option( "wimpq_watermark", $newWatermarkPath);
		}
	}
	
	/**
	 * Get content of old imgdir so we know what to move
	 *
	 * @return array
	 */
	function _getOldImgDirContent($oldImgDir, $includingOriginal = true)
	{
		//determine which folders we are allowed to move
		$allowedFolders  = array('qdir','photoQWatermark', 'myPhotoQPresets');
		if($includingOriginal){
			$imgDirs = new PhotoQ_File_ImageDirs();
			$allowedFolders[] = $imgDirs->getCurrentOriginalDirName();
		}
		//only thing allowed to be moved are folders related to photoq
		$allowedFolders = array_merge($allowedFolders, $this->_oc->getImageSizeNames());
		for($i = 0; $i<count($allowedFolders); $i++)
			$allowedFolders[$i] = $oldImgDir . $allowedFolders[$i];
		
		//get all visible files from old img dir
		$match = '#^[^\.]#';//exclude hidden files starting with .
		$visibleFiles = PhotoQHelper::getMatchingDirContent($oldImgDir, $match);
		
		//folders that are in both array will be moved
		return array_intersect($allowedFolders, $visibleFiles);
	}
	
	/**
	 * Only used by updates from pre 1.5
	 * @param $oldImgDir
	 * @return unknown_type
	 */
	function _getOldImgDir($oldImgDir)
	{
		$newImgDir = $this->_oc->getImgDir();
		return str_replace('wp-content', $oldImgDir, $newImgDir);
	}
	
	/**
	 * Handles uploading of a new watermark image.
	 *
	 */
	function uploadWatermark(){
		//watermark images can have different suffixes, but we only want one watermark file at a time.
		//instead of finding them all we just delete and recreate the directory.
		$wmDir = $this->_oc->getImgDir().'photoQWatermark/';
		PhotoQHelper::recursiveRemoveDir($wmDir);
		PhotoQHelper::createDir($wmDir);
		//put uploaded file into watermark directory
		$uploader = new PhotoQ_File_Uploader($wmDir);
		if(!$file = $uploader->import()){
			delete_option('wimpq_watermark');
		}else{
			$pathParts = pathInfo($file);
			$newPath = preg_replace("#".$pathParts['filename']."#", 'watermark', $file);
			PhotoQHelper::moveFile(new PhotoQ_File_SourceDestinationPair($file, $newPath));

			if(get_option('wimpq_watermark'))
				update_option('wimpq_watermark', $newPath);
			else
				add_option('wimpq_watermark', $newPath);

			//this is now done via batch processing
			$this->_errStack->push(PHOTOQ_INFO_MSG,'info', array(),
				__('New Watermark successfully uploaded. Updating image sizes including watermark...'));
		}
		$this->_newWatermarkUpoaded = true;
	}
	
	
}