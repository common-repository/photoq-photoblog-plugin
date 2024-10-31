<?php 
/**
 * Here we group everything related to PhotoQ error handling
 */


//define PhotoQ error codes
define('PHOTOQ_INFO_MSG', 					1); //general information/feedback to user, not really an error
define('PHOTOQ_EXCEPTION_MESSAGE',			2);
define('PHOTOQ_PHOTO_NOT_FOUND', 			10);
define('PHOTOQ_QUEUED_PHOTO_NOT_FOUND', 	11);
define('PHOTOQ_FILE_UPLOAD_FAILED', 		12);
define('PHOTOQ_SIZE_NOT_DEFINED',			13); //image size with given name is not defined (freeform mode)
define('PHOTOQ_COULD_NOT_MOVE',				14);
define('PHOTOQ_COULD_NOT_CREATE_DIR',		15);
define('PHOTOQ_DIR_NOT_FOUND',				16);
define('PHOTOQ_COULD_NOT_DELETE_PHOTO',		17);
define('PHOTOQ_DELETE_DENY',				18);
define('PHOTOQ_DELETE_NOT_FOUND',			19);
define('PHOTOQ_POST_NOT_FOUND',				20);//post with id 'id' not found
define('PHOTOQ_COULD_NOT_PUBLISH_PHOTO',	21);
define('PHOTOQ_NOTHING_TO_POST',			22);
define('PHOTOQ_BATCH_REGISTER_FAILED', 		30);
define('PHOTOQ_FIELD_EXISTS',				40); //custom field with same name as the one to be added already exists
define('PHOTOQ_VIEW_EXISTS',				50); //custom field with same name as the one to be added already exists
define('PHOTOQ_ERROR_VALIDATION', 			100); //options did not validate properly
define('PHOTOQ_EXPCOMP_ENTRY_EXISTS',		110); //element of an expandable composite option already exists
define('PHOTOQ_IMGSIZE_DEL_FAILED',			112); //could not remove directory of image size
define('PHOTOQ_XML_IMPORT_MISSING_ELEMENT',	201); //required exp comp element that was not there
define('PHOTOQ_XML_DENIED_OPTION',			202); //option was not in list of allowed options

//setup the error stack that is used for error handling in PhotoQ
$photoqErrStack = PEAR_ErrorStack::singleton('PhotoQ');
$photoqErrHandler = new PhotoQ_Error_ErrorHandler($photoqErrStack->getMessageCallback('PhotoQ'));
$photoqErrStack->setMessageCallback(array($photoqErrHandler, 'errorMessageCallback'));
//these are the default messages for above defined errors
$photoQMsgs = array(
    PHOTOQ_PHOTO_NOT_FOUND 				=> __('Post "%title%": The photo "%imgname%" could not be found at "%path%".', 'PhotoQ'),
    PHOTOQ_QUEUED_PHOTO_NOT_FOUND 		=> __('Queued post "%title%": The photo "%imgname%" could not be found at "%path%".', 'PhotoQ'),
    PHOTOQ_COULD_NOT_DELETE_PHOTO		=> __('Could not delete photo "%photo%" from server. Please delete manually.', 'PhotoQ'),
    PHOTOQ_POST_NOT_FOUND 				=> __('The post with ID "%id%" does not seem to exist.', 'PhotoQ'),
    PHOTOQ_FILE_UPLOAD_FAILED			=> __('The file upload failed with the following error: %errMsg%.', 'PhotoQ'),
    PHOTOQ_SIZE_NOT_DEFINED				=> __('The image size "%sizename%" is not defined.', 'PhotoQ'),
    PHOTOQ_COULD_NOT_MOVE				=> __('Unable to move "%source%" to "%dest%".', 'PhotoQ'),
	PHOTOQ_COULD_NOT_CREATE_DIR			=> __('Error when creating "%dir%" directory. Please check your PhotoQ settings.', 'PhotoQ'),
    PHOTOQ_DIR_NOT_FOUND				=> __('The directory "%dir%" does not exist on your server.', 'PhotoQ'),
    PHOTOQ_BATCH_REGISTER_FAILED 		=> __('Error when registering batch process: No photos updated.', 'PhotoQ'),
    PHOTOQ_EXPCOMP_ENTRY_EXISTS			=> __('Please choose another name, an entry with this name already exists.', 'PhotoQ'),
    PHOTOQ_FIELD_EXISTS					=> __('Please choose another name, a meta field with name "%fieldname%" already exists.', 'PhotoQ'),
    PHOTOQ_VIEW_EXISTS					=> __('Please choose another name, a view with name "%viewname%" already exists.', 'PhotoQ'),
    PHOTOQ_IMGSIZE_DEL_FAILED 			=> __('Could not remove image size. The required directories in %imgDir% could not be removed. Please check your settings.','PhotoQ'),
    PHOTOQ_XML_IMPORT_MISSING_ELEMENT	=> __('XML import error: Element %element% was missing in %compName% option.','PhotoQ'),
    PHOTOQ_XML_DENIED_OPTION			=> __('The option %optionname% that you tried to import is not allowed.', 'PhotoQ'),
    PHOTOQ_DELETE_DENY 					=> __('You do not have privileges to delete: %id%', 'PhotoQ'),
    PHOTOQ_DELETE_NOT_FOUND				=> __('Could not find photo to delete: %id%', 'PhotoQ'),
    PHOTOQ_COULD_NOT_PUBLISH_PHOTO		=> __('Publishing Photo did not succeed.', 'PhotoQ'),
    PHOTOQ_NOTHING_TO_POST				=> __('Queue is empty, nothing to post.', 'PhotoQ'),
);
$photoqErrStack->setErrorMessageTemplate($photoQMsgs);
