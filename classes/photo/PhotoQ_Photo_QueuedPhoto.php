<?php
class PhotoQ_Photo_QueuedPhoto extends PhotoQ_Photo_Photo
{
	 
	private $_edited; 
	private $_authorID;
	private $_position;
	private $_slug;
	private $_captureDate;
	
	function __construct($id, $title, $descr, $exif, $imgname, $tags, 
					$slug, $edited, $authorID, $position, $date)
	{
		
		$this->_edited = $edited;
		$this->_position = $position;
		$this->_captureDate = $date;
		$this->_slug = $slug;
		$this->_authorID = $authorID;
		
		$oc = PhotoQ_Option_OptionController::getInstance();
		$path = $oc->getQDir() . $imgname;
		
		parent::__construct($id, $title, $descr, $exif, $path, $imgname, $tags);
		
	}
	
	protected function _determineYearMonthDir(){
		return mysql2date('Y_m', current_time('mysql')) . "/";
	}
	
	/**
	 * Getter for the position field
	 * @return int
	 */
	function getPosition(){
		return $this->_position;
	}
	
	/**
	 * Getter for the captureDate field
	 * @return int
	 */
	function getCaptureDate(){
		return $this->_captureDate;
	}
	
	/**
	 * Getter for the edited field
	 * @return boolean
	 */
	function wasEdited(){
		return $this->_edited;
	}
	
	function getSlug(){
		return $this->_slug;
	}
	
	function getAuthor(){
		global $user_ID;
		
		$postAuthor = $this->_authorID;
		
		if ( empty($postAuthor) )
			$postAuthor = $user_ID;
			
		//we still didn't get an author -> set it to default
		if ( empty($postAuthor) )
			$postAuthor = $this->_oc->getValue('qPostAuthor');
		return $postAuthor;
	}
	
	
	/**
	 * Return list of ids of selected categories
	 * @return array
	 */
	function getSelectedCats(){
		$selectedCats = array();
		//first check for common info
		if ( isset($_POST['post_category']) )
			$selectedCats = $_POST['post_category'][0];
		else
			$selectedCats = $this->_db->getCategoriesByImgId($this->_id);
		 
		return $selectedCats;
	}
	
	/**
	 * Get the customfield with specified id. Overrides the parent 
	 * function because here custom fields are still in the photoq DB.
	 * @param $name the name of the field to fetch
	 * @param $id the id of the field to fetch
	 * @return unknown_type
	 */
	function getField($name, $id = 0){
		return $this->_db->getFieldValue($this->_id, $id);
	}

	/**
	 * Shows the edit/enter info form for one photo.
	 *
	 * @param mixed $this	The photo to be edited.
	 */
	function showPhotoEditForm()
	{
		global $current_user;
		//if we have post values (common info) we take those instead of db value.
		$descr = attribute_escape($_POST['img_descr']) ? attribute_escape(stripslashes($_POST['img_descr'])) : $this->getDescription();
		$tags = attribute_escape($_POST['tags_input']) ? attribute_escape(stripslashes($_POST['tags_input'])) : $this->getTagString();
		$selectedAuthor = attribute_escape($_POST['img_author']) ? attribute_escape(stripslashes($_POST['img_author'])) : $this->getAuthor();
		$fullSizeUrl = PhotoQHelper::getRelUrlFromPath($this->getPath());
		
		
		$authors = get_editable_user_ids( $current_user->id ); 				
		
	?>
		
		<div class="main info_group">
			<div class="info_unit">
				<a class="img_link" href="<?php echo $fullSizeUrl; ?>" 
					title="Click to see full-size photo" target="_blank">
					<?php 
						echo $this->getAdminThumbImgTag(
							new PhotoQ_Photo_Dimension(
								$this->_oc->getValue('photoQAdminThumbs-Width'), 
								$this->_oc->getValue('photoQAdminThumbs-Height')
							)
						);
					?>
				</a>
			</div>
			<div class="info_unit"><label><?php _e('Title','PhotoQ') ?>:</label><br /><input type="text" name="img_title[]" size="30" value="<?php echo $this->getTitle(); ?>" /></div>
			<div class="info_unit"><label><?php _e('Description','PhotoQ') ?>:</label><br /><textarea style="font-size:small;" name="img_descr[]" cols="30" rows="3"><?php echo $descr; ?></textarea></div>
			
			<?php //this makes it retro-compatible
				if(function_exists('get_tags_to_edit')): ?>
			<div class="info_unit"><label><?php _e('Tags (separate multiple tags with commas: cats, pet food, dogs)', 'PhotoQ'); ?>:</label><br /><input type="text" name="tags_input[]" class="tags-input" size="50" value="<?php echo $tags; ?>" /></div>
			<?php endif; ?>
			
			<div class="info_unit"><label><?php _e('Slug','PhotoQ') ?>:</label><br /><input type="text" name="img_slug[]" size="30" value="<?php echo $this->getSlug(); ?>" /></div>
			<div class="info_unit"><label><?php _e('Post Author','PhotoQ') ?>:</label><?php wp_dropdown_users( array('include' => $authors, 'name' => 'img_author[]', 'multi' => true, 'selected' => $selectedAuthor) ); ?></div>
			<input type="hidden" name="img_id[]" value="<?php echo $this->getId(); ?>" />
		</div>
		<?php PhotoQHelper::showMetaFieldList($this->getId()); ?>
		<div class="wimpq_cats info_group"><?php PhotoQHelper::showCategoryCheckboxList($this->getId(), $this->_oc->getValue('qPostDefaultCat'), $this->getSelectedCats()); ?></div>
		<div class="clr"></div>
	<?php
		
	}
	
	
	
	
	
	/**
	 * Publish the Photo. Creates the resized images, inserts post data into database
	 *
	 * @return integer	The ID of the post created.
	 */
	function publish($timestamp = 0)
	{
		
		PhotoQHelper::debug('enter publish()');
		
		//create the resized images and move them into position
		foreach($this->_sizes as $size){
			$size->makeFilenameUnique();
			$this->rebuildSize($size);
		}
		
		PhotoQHelper::debug('thumbs created');
		
		//generate the post data and add it to database
		$postData = $this->_generatePostData($timestamp);
		if (!$postID = wp_insert_post($postData)) { //post did not succeed
			$this->cleanUpAfterError();
			throw new PhotoQ_Error_Exception(__('Could not insert post into database.', 'PhotoQ'));
		}
		
		PhotoQHelper::debug('post inserted');
		
		//insert description
		add_post_meta($postID, PhotoQ_Photo_Photo::DESCR_FIELD_NAME, $this->_descr, true);
		
		//insert full exif
		add_post_meta($postID, PhotoQ_Photo_Photo::EXIF_FULL_FIELD_NAME, $this->_exif, true);
		
		//insert formatted exif
		add_post_meta($postID, PhotoQ_Photo_Photo::EXIF_FIELD_NAME, $this->getNiceExif(), true);
		
		//insert sizesFiled
		add_post_meta($postID, PhotoQ_Photo_Photo::SIZES_FIELD_NAME, $this->generateSizesField(), true);
		
		//add path variable
		add_post_meta($postID, PhotoQ_Photo_Photo::PATH_FIELD_NAME, $this->_sizes[PhotoQ_File_ImageDirs::ORIGINAL_IDENTIFIER]->getPath(), true);
	
		//insert custom views
		foreach($this->_oc->getViewNames() as $currentViewName){
			if(!in_array($currentViewName, $this->_DEFAULT_VIEWS)){
				add_post_meta($postID, $currentViewName, $this->generateContent($currentViewName), true);
			}
		}
		
		//handle the other fields
		$fields = $this->_db->getAllFields();
		foreach ($fields as $field) {
			$fieldValue = $this->_db->getFieldValue($this->_id, $field->q_field_id);
			add_post_meta($postID, $field->q_field_name, $fieldValue, true);
		}
		
		//increment the counter of photos posted through photoq
		$postedSinceLastReminder = get_option('wimpq_posted_since_reminded');
		if($postedSinceLastReminder != NULL)
			update_option('wimpq_posted_since_reminded',$postedSinceLastReminder+1);
		else{
			add_option('wimpq_posted_since_reminded', 1);
			add_option('wimpq_reminder_threshold', 50);
			add_option('wimpq_last_reminder_reset', time());
		}	
		PhotoQHelper::debug('leave publish()');
		
		return $postID;
					
	}
	
	
	function _raisePhotoNotFoundError(){
		$this->_errStack->push(PHOTOQ_QUEUED_PHOTO_NOT_FOUND,'error', array('title' => $this->_title, 'imgname' => $this->_imgname, 'path' => $this->_originalPath));
	}
	
	
	
	
	
	
	
	function _generatePostData($timestamp){
		
		$post_author = $this->getAuthor();
		$post_status = $this->_oc->getValue('qPostStatus');
		$post_title = $this->_title;
	
		//if a timestamp is given we set the post_date
		
		if($this->_oc->getValue('dateFromExif') && $this->_exif['DateTimeOriginal'])
			$timestamp = strtotime($this->_exif['DateTimeOriginal']);
		if($timestamp)
			$post_date = gmdate('Y-m-d H:i:s', $timestamp);	
		
		
		//the slug
		$post_name =  $this->slug;
	
		//the tags
		$tags_input =  rtrim($this->getTagString() . ',' . $this->getTagsFromExifString(),',');
	
		//category stuff
		$post_category = $this->_db->getCategoriesByImgId($this->_id);
	
		// Make sure we set a valid category
		if (0 == count($post_category) || !is_array($post_category)) {
			$post_category = array($this->_oc->getValue('qPostDefaultCat'));
		}

		$varNames = array();
		if($this->_oc->isManaged('content')){
			$post_content = $this->generateContent();
			array_push($varNames,'post_content');
		}
		if($this->_oc->isManaged('excerpt')){
			$post_excerpt = $this->generateContent('excerpt');
			array_push($varNames,'post_excerpt');
		}
			
		
		$postData = compact($varNames, 'post_category','post_title','post_name','post_author', 'post_status', 'tags_input', 'post_date');
		//to safely insert values into db
		$postData = add_magic_quotes($postData);
		
		
		return $postData;
		
	}
	
	


}
