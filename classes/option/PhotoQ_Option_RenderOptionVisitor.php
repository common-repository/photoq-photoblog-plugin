<?php
/**
 * The PhotoQ_Option_RenderOptionVisitor:: is responsible for rendering of the options. It 
 * renders every visited option in HTML.
 *
 * @author  M. Flury
 * @package PhotoQ
 */
class PhotoQ_Option_RenderOptionVisitor extends RO_Visitor_RenderOptionVisitor
{
	
	
	 
	/**
	 * Method called whenever a
	 * PhotoQ_Option_ImageSizeOption is visited. Subclasses should override this and and
	 * define the operation to be performed.
	 *
	 * @param object PhotoQ_Option_ImageSizeOption $dropDownList	Reference to visited option.
	 */
	 function visitPhotoQ_Option_ImageSizeOptionBefore($imageSize)
	 {
	 	//$deleteLink = '';
	 	/*if($imageSize->isRemovable()){
	 		$deleteLink = 'options-general.php?page=whoismanu-photoq.php&amp;action=deleteImgSize&amp;entry='.$imageSize->getName();
	 		$deleteLink = ( function_exists('wp_nonce_url') ) ? wp_nonce_url($deleteLink, 'photoq-deleteImgSize' . $imageSize->getName()) : $deleteLink;
	 		$deleteLink = '<a href="'.$deleteLink.'" class="delete" onclick="return confirm(\'Are you sure?\');">Delete</a>';
	 	}*/
	 	print '<table width="100%" cellspacing="2" cellpadding="5" class="form-table noborder">
	 				<tr valign="top">
	 					<th class="imageSizeName"> ' .$imageSize->getName().'</th>
	 					<td></td>
	 				</tr>';
	 	
	 }
	 
	 /**
	 * Method called whenever a
	 * PhotoQ_Option_ImageSizeOption is visited. Subclasses should override this and and
	 * define the operation to be performed.
	 *
	 * @param object PhotoQ_Option_ImageSizeOption $imageSize	Reference to visited option.
	 */
	 function visitPhotoQ_Option_ImageSizeOptionAfter($imageSize)
	 {
	 	print "</table>";
	 }
	 
	 
	 function visitPhotoQ_Option_ExifTagOptionBefore($option)
	 {
	 	print '<b>'.$option->getExifKey().'</b> ( '.$option->getExifExampleValue().' )<br/>'.PHP_EOL;
	 }
	 
	function visitPhotoQ_Option_RoleOptionBefore($option)
	 {
	 	print $option->getTextBefore();
	 	print $option->getLabel().':'.PHP_EOL;
	 	print '<ul>'.PHP_EOL;
	 	
	 }
	 
	function visitPhotoQ_Option_RoleOptionAfter($option)
	 {
	 	print '</ul>'.PHP_EOL;
	 	print $option->getTextAfter();
	 }
	 
	 
	 function visitPhotoQ_Option_ViewOptionBefore($imageSize)
	 {
	 	print '<table width="100%" cellspacing="2" cellpadding="5" class="form-table noborder">
	 				<tr valign="top">
	 					<th class="viewName"> ' .$imageSize->getName().'</th>
	 					<td></td>
	 				</tr>';
	 	
	 }
	 
	 function visitPhotoQ_Option_ViewOptionAfter($imageSize)
	 {
	 	print "</table>";
	 }
	 
	 	
}