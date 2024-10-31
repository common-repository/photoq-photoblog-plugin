<?php
class PhotoQQueue implements PhotoQSingleton
{
	
	private static $_singletonInstance;
	
	/**
	 * The list of queued photos
	 *
	 * @var array
	 * @access private
	 */
	var $_queuedPhotos;
	
	/**
	 * Reference to PhotoQDB singleton
	 * @var object PhotoQDB
	 */
	var $_db;

	/**
	 * Reference to PhotoQ_Option_OptionController singleton
	 * @var object PhotoQ_Option_OptionController
	 */
	var $_oc;
	
	/**
	 * Reference to ErrorStack singleton
	 * @var object PEAR_ErrorStack
	 */
	var $_errStack;
	
	/**
	 * PHP5 type constructor
	 */
	private function __construct()
	{
		PhotoQHelper::debug('enter PhotoQQueue::__construct()');
		
		$this->_db = PhotoQDB::getInstance();
		$this->_oc = PhotoQ_Option_OptionController::getInstance();
		//get the PhotoQ error stack for easy access
		$this->_errStack = PEAR_ErrorStack::singleton('PhotoQ');
		
		PhotoQHelper::debug('PhotoQQueue::__construct(): load');
		//get Queue from DB
		$this->load();
		PhotoQHelper::debug('leave PhotoQQueue::__construct()');
	}
		
	public static function getInstance()
	{
		if (!isset(self::$_singletonInstance)) {
			self::$_singletonInstance = new self();
		}
		return self::$_singletonInstance;
	}
	
	function load()
	{
		$this->_queuedPhotos = array();
	
		if($results = $this->_db->getQueueByPosition()){
			foreach ($results as $position => $qEntry) {
				//tags are split by commas surrounded by any kind of space character
				$this->addPhoto(new PhotoQ_Photo_QueuedPhoto($qEntry->q_img_id,
						$qEntry->q_title, $qEntry->q_descr, $qEntry->q_exif, 
						$qEntry->q_imgname, preg_split("/[\s]*,[\s]*/", $qEntry->q_tags),
						$qEntry->q_slug, $qEntry->q_edited, $qEntry->q_fk_author_id, $position, $qEntry->q_date
					));
			}
		}
	}
	
	function addPhoto($photo)
	{
		array_push($this->_queuedPhotos, $photo);
	}
	
	/**
	 * Delete a photo from the queue.
	 *
	 * @param int $id the id of the photo to delete
	 */
	function deletePhotoById($id)
	{
		global $current_user;
		foreach($this->_queuedPhotos as $position => $photo) {
    		if($photo->getId() == $id){
    			//check that user is allowed to delete this one
    			if ( $current_user->id == $photo->getAuthor() ||  current_user_can('delete_others_posts') ){
    			
    				//remove from database
					$this->_db->deleteQueueEntry($id, $position);
        			//remove from queue
    				unset($this->_queuedPhotos[$position]);
        			//remove from server
    				return $photo->delete();
    			}else
    				$this->_errStack->push(PHOTOQ_DELETE_DENY,'error', array('id' => $id));
    		}
    	}
    	$this->_errStack->push(PHOTOQ_DELETE_NOT_FOUND,'error', array('id' => $id));
    	return false;
	}
	
	function deleteAll()
	{
		foreach($this->_queuedPhotos as $position => $photo)
    		$this->deletePhotoById($photo->getId());
	}
	
	
	/**
	 * Returns the length of the queue.
	 *
	 * @return integer	The length of the queue.
	 * @access public
	 */
	function getLength()
	{
		return count($this->_queuedPhotos);
	}
	
	public function hasPhotos(){
		return $this->getLength() > 0;
	}
	
	/**
	 * Returns the photo at position $pos in the queue.
	 * @param $pos int	the position to retrieve
	 * @return object PhotoQ_Photo_QueuedPhoto
	 */
	function getQueuedPhoto($pos)
	{
		return $this->_queuedPhotos[$pos];
	}
	
	function getQueuedPhotoById($id){
		foreach ( array_keys($this->_queuedPhotos) as $position ) {
			$photo = $this->_queuedPhotos[$position];
    		if($photo->getId() == $id){
    			return $photo;
    		}
    	}
    	throw new PhotoQ_Error_PhotoNotFoundException(sprintf(__('Could not find photo with ID: %s', 'PhotoQ'),$id));
	}
	
	function getQueuedUneditedPhotos(){
		$unedited = array();
		foreach ( array_keys($this->_queuedPhotos) as $position ) {
			$photo = $this->_queuedPhotos[$position];
    		if(!$photo->wasEdited()){
    			array_push($unedited, $photo);
    		}
    	}
    	return $unedited;
	}
	
	
	/**
	 * Publish the top of the queue.
	 *
	 * @return boolean
	 */
	function publishTop()
	{
		if($this->getLength() == 0){
			$this->_errStack->push(PHOTOQ_NOTHING_TO_POST, 'error');
			return;
		}
		$topPhoto = $this->_queuedPhotos[0];
		try{
			$postID = $topPhoto->publish();
			$this->_postPublishingActions($topPhoto->getId(),$postID);
			$statusMsg = '<strong>'.__('Your post has been saved.', 'PhotoQ').'</strong> <a href="'. get_permalink( $postID ).'">'.__('View post', 'PhotoQ').'</a> | <a href="post.php?action=edit&amp;post='.$postID.'">'.__('Edit post', 'PhotoQ').'</a>';
			$this->_errStack->push(PHOTOQ_INFO_MSG, 'info', array(), $statusMsg);
		}catch(PhotoQ_Error_Exception $e){
			$e->pushOntoErrorStack();
			$this->_errStack->push(PHOTOQ_COULD_NOT_PUBLISH_PHOTO, 'error');
		}
	}

	/**
	 * Actions that need to be performed after photo is published.
	 * @param $topID
	 * @param $postID
	 * @return unknown_type
	 */
	function _postPublishingActions($topID, $postID){
		$this->_db->deleteQueueEntry($topID, 0);
		//if exif is inlined we already need a rebuild to get the post_tag
		//links needed for the tagsFromExif stuff. These are not available
		//before the post has been posted (and thus the tags registered).
		if($this->_oc->getValue('inlineExif')){
			$photo = $this->_db->getPublishedPhoto($postID);
			if($photo)
				$photo->rebuild(array(), false, array('content'));
		}
	}
	
	/**
	 * Publish several photos from queue at once.
	 */
	function publishMulti()
	{
		$num2Post = $this->_oc->getValue('postMulti');
		if($this->getLength() == 0){
			$this->_errStack->push(PHOTOQ_NOTHING_TO_POST, 'error');
			return;	
		}
		$num2Post = min($this->getLength(), $num2Post);
		
		//we'll increase this timestamp from one post to the next to make sure 
		//that posts are at least spaced by one second otherwise wordpress doesn't 
		//know how to deal with it.
		$postDateFirst = current_time('timestamp');
		
		for ($i = 0; $i<$num2Post; $i++){
			$topPhoto = $this->_queuedPhotos[$i];
			try{
				$postID = $topPhoto->publish($postDateFirst + $i);
				$this->_postPublishingActions($topPhoto->getId(),$postID);
			}catch(PhotoQ_Error_Exception $e){
				$e->pushOntoErrorStack();
				$this->_errStack->push(PHOTOQ_COULD_NOT_PUBLISH_PHOTO, 'error');
			}
		}
		$statusMsg = '<strong>'.__('Your posts have been saved.', 'PhotoQ').'</strong>';
		$this->_errStack->push(PHOTOQ_INFO_MSG, 'info', array(), $statusMsg);
	}
	
	/**
	 * Sorts the queue according to the specified criterion
	 * @param $criterion
	 * @return unknown_type
	 */
	function sort($criterion){
		if($criterion === '-1')
			return;
		$this->_db->sortQueue($criterion);
	}
	
	public function publishViaCronjob(){
		$this->_addFTPUploadsIfCronOptionSet();
		if($this->_hoursSinceLastPost() >= $this->_oc->getValue('cronFreq')){
			$this->_allowUnfilteredHTML();	
			if($this->_oc->getValue('cronPostMulti'))
				$this->publishMulti();
			else
				$this->publishTop();
		}
	}
	
	/**
	 * add ftp dir to queue if corresponding option is set
	 * @return unknown_type
	 */
	private function _addFTPUploadsIfCronOptionSet(){
		if( $this->_oc->onCronImportFTPUploadsToQueue() ){
			$ftpDir = $this->_oc->getFTPDir();
			if (is_dir($ftpDir)) {
				$ftpDirContent = PhotoQHelper::getMatchingDirContent($ftpDir,'#.*\.(jpg|jpeg|png|gif)$#i');
				foreach ($ftpDirContent as $ftpFile)
					$this->uploadPhoto(basename($ftpFile), '', $ftpFile);
				//reload the queue to get newly uploaded photos
				$this->load();
			}
		}
	}
	
	private function _hoursSinceLastPost(){
		$currentTime = strtotime(gmdate('Y-m-d H:i:s', (time() + (get_option('gmt_offset') * 3600))));
	
		$lastTime = $this->_db->getLastPostDate();
		if($lastTime){
			$lastTime = strtotime($lastTime);
		}else{
			PhotoQHelper::debug('cronjob: lastTime was null');
			$lastTime = 0; //somewhere way back in the past, when time started ;-)
		}
		$timeDifferenceSeconds = $currentTime - $lastTime;
		
		return round($timeDifferenceSeconds / 3600);
	}
	
	/* cronjob does not run in admin mode. still, we want to allow unfiltered_html
	 * since some people use this with php exec plugins. so we disable appropriate
	 * filters here.
	 */
	private function _allowUnfilteredHTML(){
		kses_remove_filters();
	}
	

	
}
?>