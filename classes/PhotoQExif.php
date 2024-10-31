<?php

/**
 * This class deals with EXIF meta data embedded in the photos.
 *
 */
class PhotoQExif
{
	
	/**
	 * Get associative array with exif info from a photo
	 *
	 * @param string $path	Path to the photo.
	 * @return array		Exif info in associative array.
	 */
	public function readExif($path)
	{
		$iptc = PhotoQExif::_readIPTC($path);
		
		//include and call the exifixer script
		require_once realpath(PHOTOQ_PATH.'lib/exif/exif.php');
		$fullexif = read_exif_data_raw($path, 0);
		//we now retain only the useful (whatever it means ;-) ) info
		$ifd0 = PhotoQExif::_filterUseless($fullexif['IFD0']);
		$subIfd = PhotoQExif::_filterUseless($fullexif['SubIFD']);
		$makerNote = $subIfd['MakerNote'];
		unset($subIfd['MakerNote']);
		$gps = PhotoQExif::_filterUseless($fullexif['GPS']);
		
		//bring all the arrays to single dimension
		$ifd0 = PhotoQHelper::flatten($ifd0);
		$subIfd = PhotoQHelper::flatten($subIfd);
		$makerNote = PhotoQHelper::flatten($makerNote);
		$gps = PhotoQHelper::flatten($gps);
		
		//and finally merge all of them into a single array
		$exif = array_merge($iptc, $ifd0, $subIfd, $makerNote, $gps);
		
		
		//update discovered tags
		PhotoQExif::_discoverTags($exif);
		
		return $exif;
	}
	
	/**
	 * Creates the formatted exif list. Only tags selected in PhotoQ 
	 * and that are present in the current photo are displayed. 
	 * TagsFromExif are shown as links to the corresponding tag pages.
	 * @param $exif	the full exif data array of this post
	 * @param $tags the exif tags that are selected in photoq
	 * @param $tagsFromExif	the exif tags that were chosen as post_tags via tagFromExif
	 * @return string	formatted exif outpout in form of unordered html list
	 */
	public function getFormattedExif($exif, $tags, $tagsFromExif, $displayNames, $displayOptions){
		if(!empty($tags) && !is_array($tags)){
			//is it a comma separated list?
			$tags = array_unique(explode(',',$tags));
		}
		if(!is_array($tags) || count($tags) < 1 ){
			//still nothing?
			$result = '';
		}else{
			$result = $displayOptions['before'];//'<ul class="photoQExifInfo">';
			$foundOne = false; //we don't want to print <ul> if there is no exif in the photo
			foreach($tags as $tag){
				if(array_key_exists($tag, $exif)){
					$foundOne = true;
					if(empty($displayOptions['elementFormatting']))//go with default
						$displayOptions['elementFormatting'] = '<li class="photoQExifInfoItem"><span class="photoQExifTag">[key]:</span> <span class="photoQExifValue">[value]</span></li>';
							
					$displayName = $tag;
					//do we need to display a special name
					if(!empty($displayNames[$tag]))
						$displayName = $displayNames[$tag];
					
					$value = $exif[$tag];
					
					//do we need a tag link?
					if(in_array($tag, $tagsFromExif)){
						//yes, so try to get an id and then the link
						$term = get_term_by('name', $value, 'post_tag');
						if($term)
							$value = '<a href="'.get_tag_link($term->term_id).'">'.$value.'</a>';
					}

					$result .= PhotoQHelper::formatShorttags($displayOptions['elementFormatting'], array('key' => $displayName, 'value' => $value));
					$result .= $displayOptions['elementBetween'];
				}
			}
			//remove last occurrence of elementBetween
			$result = preg_replace('/'.preg_quote($displayOptions['elementBetween']).'$/','',$result);
			$result .= $displayOptions['after'];//'</ul>';
			
			
			if(!$foundOne)
				$result = '';
		}
		return $result;
	}


	private function _discoverTags($newTags){
		$oldTags = get_option( "wimpq_exif_tags" );
		if($oldTags !== false){
			$discovered = array_merge($oldTags, $newTags);
			ksort($discovered, SORT_STRING);
			update_option( "wimpq_exif_tags", $discovered);
		}else
			add_option("wimpq_exif_tags", $newTags);
			
	}
	
	/**
	 * Recursively removes entries containing ':unknown' in key from input array.
	 *
	 * @param array $in the input array
	 * @return array	the filtered array
	 */
	private function _filterUseless($in){
		$out = array();
		if(is_array($in)){
			foreach ($in as $key => $value){
				if(strpos($key,'unknown:') === false && !in_array($key,PhotoQExif::_getUselessTagNames()))
					if(is_array($value))
						$out[$key] = PhotoQExif::_filterUseless($value);
					else
						$out[$key] = PhotoQExif::_sanitizeExifValue($value);
			}
		}
		return $out;
	}

	/**
	 * This return a list of tags that are either not implemented correctly in exifixer,
	 * that are added by exifixer and not needed or that contain no useful information (e.g. 
	 * only offsets inside the TIFF header or info i deem unlikely to be useful to my users).
	 *
	 * @return unknown
	 */
	private function _getUselessTagNames()
	{
		return array(
		'Bytes',
		'CFAPattern',
		'ComponentsConfiguration',	
		'CustomerRender',			
		'ExifInteroperabilityOffset',
		'ExifOffset',
		'GPSInfo',
		'KnownMaker',
		'MakerNoteNumTags',
		'OwnerName',
		'RAWDATA',
		'Unknown',
		'UserCommentOld',
		'VerboseOutput',
		'YCbCrPositioning'
		);
	}
	
	private function _sanitizeExifValue($value)
	{
		return preg_replace('#[^(a-zA-Z0-9_\s\.\:\/\,\;\-)]#','',$value);
	}
	
	/**
	 * Reads the IPTC metadata from the file with the path given
	 * @param $path
	 * @return unknown_type
	 */
	private function _readIPTC($path)
	{
		$result = array();

		//done according to wp-admin/includes/image.php:wp_read_image_metadata() with
		//exception of additional remove of problematic chars.
		if ( is_callable('iptcparse') ) {
			@getimagesize($path, $info);
			if ( !empty($info['APP13']) ) {
				$iptc = @iptcparse($info['APP13']);
				$iptcTags = PhotoQExif::_getIPTCTags();
				foreach ($iptcTags as $key => $value) {
					if (!empty($iptc[$key][0]))
						$result[$value] = PhotoQExif::_removeProblematicChars(utf8_encode(trim(implode(", ", $iptc[$key]))));
				}	
			}
		}
		return $result;
	}
	
	/**
	 * Removes characters like french accents or german umlauts, as well as ' that created problems at least on
	 * my machine.
	 * @param string $in
	 * @return string
	 */
	private function _removeProblematicChars($in){
		$out = preg_replace('/[\']/', ' ', $in);
		$out = iconv('UTF-8', 'ASCII//TRANSLIT', $out); // Ž e.g. becomes e'
		return preg_replace('/[\']/', '', $out);
	}
	
	/**
	 * Returns list of IPTC-NAA IIM fields and their identifier
	 * @return unknown_type
	 */
	private function _getIPTCTags()
	{
		//List taken from http://www.ozhiker.com/electronics/pjmt/library/list_contents.php4?show_fn=IPTC.php
		// Application Record 
		return array(
			'2#000' => 'Record Version',
			'2#003' => 'Object Type Reference',
			'2#005' => 'Object Name (Title)',
			'2#007' => 'Edit Status',
			'2#008' => 'Editorial Update',
			'2#010' => 'Urgency',
			'2#012' => 'Subject Reference',
			'2#015' => 'Category',
			'2#020' => 'Supplemental Category',
			'2#022' => 'Fixture Identifier',
			'2#025' => 'Keywords',
			'2#026' => 'Content Location Code',
			'2#027' => 'Content Location Name',
			'2#030' => 'Release Date',
			'2#035' => 'Release Time',
			'2#037' => 'Expiration Date',
			'2#035' => 'Expiration Time',
			'2#040' => 'Special Instructions',
			'2#042' => 'Action Advised',
			'2#045' => 'Reference Service',
			'2#047' => 'Reference Date',
			'2#050' => 'Reference Number',
			'2#055' => 'Date Created',
			'2#060' => 'Time Created',
			'2#062' => 'Digital Creation Date',
			'2#063' => 'Digital Creation Time',
			'2#065' => 'Originating Program',
			'2#070' => 'Program Version',
			'2#075' => 'Object Cycle',
			'2#080' => 'By-Line (Author)',
			'2#085' => 'By-Line Title (Author Position)',
			'2#090' => 'City',
			'2#092' => 'Sub-Location',
			'2#095' => 'Province/State',
			'2#100' => 'Country/Primary Location Code',
			'2#101' => 'Country/Primary Location Name',
			'2#103' => 'Original Transmission Reference',
			'2#105' => 'Headline',
			'2#110' => 'Credit',
			'2#115' => 'Source',
			'2#116' => 'Copyright Notice',
			'2#118' => 'Contact',
			'2#120' => 'Caption/Abstract',
			'2#122' => 'Caption Writer/Editor',
			'2#125' => 'Rasterized Caption',
			'2#130' => 'Image Type',
			'2#131' => 'Image Orientation',
			'2#135' => 'Language Identifier',
			'2#150' => 'Audio Type',
			'2#151' => 'Audio Sampling Rate',
			'2#152' => 'Audio Sampling Resolution',
			'2#153' => 'Audio Duration',
			'2#154' => 'Audio Outcue',
			'2#200' => 'ObjectData Preview File Format',
			'2#201' => 'ObjectData Preview File Format Version',
			'2#202' => 'ObjectData Preview Data'
		);
	}

	
	
}
?>