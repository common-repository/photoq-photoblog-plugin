<?php
class PhotoQ_Util_GarbageCollector implements PhotoQHookable
{	
	/**
	 * To hook the appropriate callback functions 
	 * (action hooks) into WordPress Plugin API.
	 */
	public function hookIntoWordPress(){
		$oc = PhotoQ_Option_OptionController::getInstance();
		if($oc->getValue('deleteImgs'))
			add_action('delete_post', array($this, 'actionCleanUp'));
	}
	
	/** 
	 * sink function executed whenever a post is deleted. 
	 * Takes post id as argument. Deletes the corresponding image 
	 * and thumb files from server if post is deleted.
	 */
	public function actionCleanUp($id){
		if(PhotoQHelper::isPhotoPost($id)){
			$post = get_post($id);
			$photo = new PhotoQ_Photo_PublishedPhoto(
				$post->ID, $post->title
			);
			$photo->delete();
		}
	}

	
}