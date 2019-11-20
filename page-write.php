<?php
/*
Template Name: Writing Pad
*/


// set blanks
$wTitle = $wEmail = $wFooter = $wTags = $wNotes = $wLicense = $w_thumb_status = $wAccess = '';
$post_id = $revcount = 0;
$is_published = $is_re_edit = $linkEmailed = $wAccessCodeOk = false; 
$errors = array();

// get the parent category for published topics
$published_cat_id = get_cat_ID( 'Published' );


// see if we have an incoming clear the code form variable only on writing form
// ignored if options are not to use it

$wAccessCodeOk = isset( $_POST['wAccessCodeOk'] ) ? true : false;

// check that an access code is in play and it's not been yet passed
if ( !empty( truwriter_option('accesscode') ) AND !$wAccessCodeOk ) {

	// now see if we are to check the access code
	if ( isset( $_POST['truwriter_form_access_submitted'] ) 
	  AND wp_verify_nonce( $_POST['truwriter_form_access_submitted'], 'truwriter_form_access' ) ) {
	    
	   // grab the entered code from  form
		$wAccess = 	stripslashes( $_POST['wAccess'] );

		// Validation of the code
		if ( $wAccess != truwriter_option('accesscode') ) {
			$box_style = '<div class="notify notify-red"><span class="symbol icon-error"></span> ';
			$feedback_msg = '<p><strong>Incorrect Access Code</strong> - try again? Hint: ' . truwriter_option('accesshint') . '</p>'; 	
		} else {
			$wAccessCodeOk = true;
		}
	} else {
		$box_style = '<div class="notify"><span class="symbol icon-info"></span> ';
		$feedback_msg = '<p>An access code is required to use the writing form on ' . get_bloginfo('name') . '</p>';
	} // form check access code
} else {
	// set flag true just to clear all the other gates
	$wAccessCodeOk = true;
} // access code in  play check

// Writing form was submitted and it passes the nonce check
if ( isset( $_POST['truwriter_form_make_submitted'] ) && wp_verify_nonce( $_POST['truwriter_form_make_submitted'], 'truwriter_form_make' )  )  {

 		// grab the variables from the form
 		$wTitle = 					sanitize_text_field( stripslashes( $_POST['wTitle'] ) );
 		$wAuthor = 					( isset ($_POST['wAuthor'] ) ) ? sanitize_text_field( stripslashes($_POST['wAuthor']) ) : 'Anonymous';
 		$wEmail = 					sanitize_text_field( $_POST['wEmail'] );			
 		$wTags = 					sanitize_text_field( $_POST['wTags'] );	
 		$wText = 					wp_kses_post( $_POST['wText'] );
 		$wNotes = 					sanitize_text_field( stripslashes( $_POST['wNotes'] ) );
 		$wFooter = 					sanitize_text_field( stripslashes( $_POST['wFooter'] ) ) ;

		$wHeaderImage_id =			$_POST['wHeaderImage'];
 		$linkEmailed = 				$_POST['linkEmailed'];
 		$post_id = 					$_POST['post_id'];
 		$wCats = 					( isset ($_POST['wCats'] ) ) ? $_POST['wCats'] : array();
 		$wLicense = 				( isset ($_POST['wLicense'] ) ) ? $_POST['wLicense'] : '';
 		$wHeaderImageCaption = 		sanitize_text_field(  $_POST['wHeaderImageCaption']  );
 		$revcount =					$_POST['revcount'] + 1;		


		// upload header image if we got one
		if ($_FILES) {
			foreach ( $_FILES as $file => $array ) {
				$newupload = truwriter_insert_attachment( $file, $post->ID );
				if ( $newupload ) {
					$wHeaderImage_id = $newupload;
					$w_thumb_status = 'Header image uploaded. Choose another to replace it.';
					
					// check image meta?
					$imgmeta = wp_get_attachment_metadata( $wHeaderImage_id ); 

					// add image meta data title to caption
					if ( $imgmeta['image_meta']['title'] ) $wHeaderImageCaption .= ' "' . $imgmeta['image_meta']['title'] . '" ';
					
					// add image meta data copytight to caption
					if ( $imgmeta['image_meta']['copyright'] ) $wHeaderImageCaption .= $imgmeta['image_meta']['copyright'];
					
				}
			}
		}
		
 		// let's do some validation, store an error message for each problem found
 		
 		if ( $wTitle == '' ) $errors[] = '<strong>Title Missing</strong> - please enter an interesting title.'; 	
 		
 		if ( truwriter_word_count( $wText ) < truwriter_option('min_words') ) $errors[] = '<strong>Missing or Insufficient Text</strong> - This site asks that you write at least ' . truwriter_option('min_words') . ' words.';
 		
 		if ( $wHeaderImageCaption == '' ) $errors[] = '<strong>Header Image Caption Missing</strong> - please provide a description or an attribution for your header image. We would like to show that it is either your own image or one that is licensed for re-use.'; 
 		
 		if ( truwriter_option('require_extra_info') == 1  AND $wNotes == '' ) $errors[] = '<strong>Extra Information Missing</strong> - please provide the requested extra information.';
 		
		// test for email only if enabled in options
		if ( truwriter_option('show_email') )   {
		
			// check first for valid email address, blank is ok
			if ( is_email( $wEmail ) OR empty($wEmail) ) {

				// if email is good then check if we are limiting to domains
				if ( !empty(truwriter_option('email_domains'))  AND !truwriter_allowed_email_domain( $wEmail ) ) {
					$errors[] = '<strong>Email Address Not Allowed</strong> - The email address you entered <code>' . $wEmail . '</code> is not from an domain accepted in this site. This site requests that addresses are ones with domains <code>' .  truwriter_option('email_domains') . '</code>. ';
				}
		
			} else {
				// bad email, sam.
				$errors[] = '<strong>Invalid Email Address</strong> - the email address entered <code>' . $wEmail . '</code> is not a valid address. To skip entering an email address, make sure the field is empty. Pleae check and try again. ';
			}
		}
 		
 		if ( count($errors) > 0 ) {
 			// form errors, build feedback string to display the errors
 			$feedback_msg = 'Sorry, but there are a few errors in your entry. Please correct and try again.<ul>';
 			// Hah, each one is an oops, get it? 
 			foreach ($errors as $oops) {
 				$feedback_msg .= '<li>' . $oops . '</li>';
 			}			
 			$feedback_msg .= '</ul>';
 			
 			// updates for display
 			$revcount =	$_POST['revcount'];		
 			$wStatus = 'Form input error';
 			$formclass = 'writeoops';
 			$box_style = '<div class="notify notify-red"><span class="symbol icon-error"></span> ';
 	
 		} else { // good enough, let's set up a post! 
 		
 			// set notifications and display status
			if ( isset( $_POST['wPublish'] ) ) {
				// set to status defined as option
				$post_status = truwriter_option('pub_status');
			
				if ( truwriter_option('pub_status') == 'pending' ) {
					$wStatus = 'Submitted for Review';
					$formclass = 'writedraft';
					$box_style = '<div class="notify notify-green"><span class="symbol icon-tick"></span> ';
				} else {
					$wStatus = 'Published';
					$box_style = '<div class="notify notify-blue"><span class="symbol icon-tick"></span> ';
				}
			
				$wStatus .= ' (version #' . $revcount . ' last saved ' . get_the_date( '', $post_id) . ' '  . get_the_time( '', $post_id) . ')';
			
			} else {
				// stay as draft
				$formclass = 'writedraft';
				$post_status = 'draft';
				$wStatus = 'In Draft (revision #' . $revcount . ' last saved ' . get_the_date( '', $post_id) . ' '  . get_the_time( '', $post_id) . ')';
				$box_style = '<div class="notify notify-green"><span class="symbol icon-tick"></span> ';
			}
 			
  			// the default category for in progress
 			$def_category_id = get_cat_ID( 'In Progress' );
 			
			$w_information = array(
				'post_title' => $wTitle,
				'post_content' => $wText,
				'post_status' => $post_status, 
				'post_category' => 	array( $def_category_id )		
			);
			
			// updates for display
			$wStatus = 'In Draft (revision #' . $revcount . ' last saved ' . get_the_time( '', $post_id) . ')';
			$formclass = 'writedraft';
						
 			// is this a first draft?
			if ( $post_id == 0 ) {
						
				// insert as a new post
				$post_id = wp_insert_post( $w_information );
				
				// store the author as post meta data
				add_post_meta($post_id, 'wAuthor', $wAuthor);
				
				// store the email as post meta data
				add_post_meta($post_id, 'wEmail', $wEmail);				
				
				// add the tags
				wp_set_post_tags( $post_id, $wTags);
			
				// set featured image
				set_post_thumbnail( $post_id, $wHeaderImage_id);
				
				// Add caption to featured image if there is none, this is 
				// stored as post_excerpt for attachment entry in posts table
				
				if ( !get_attachment_caption_by_id( $wHeaderImage_id ) ) {
					$i_information = array(
						'ID' => $wHeaderImage_id,
						'post_excerpt' => $wHeaderImageCaption
					);
					
					wp_update_post( $i_information );
				}
				
				// store the header image caption as post metadata
				add_post_meta($post_id, 'wHeaderCaption', $wHeaderImageCaption);
				
				// store notes for editor
				if ( $wNotes ) add_post_meta($post_id, 'wEditorNotes', $wNotes);

				// store notes for editor
				if ( $wFooter ) add_post_meta($post_id, 'wFooter', nl2br( $wFooter ) );
				
				// user selected license
				if ( truwriter_option( 'use_cc' ) != 'none' ) add_post_meta( $post_id,  'wLicense', $wLicense);
				
				// add a token for editing
				truwriter_make_edit_link( $post_id,  $wTitle );
				
				$feedback_msg = 'We have saved this first version of your writing. You can <a href="'. site_url() . '/?p=' . $post_id . '&preview=true&ispre=1' . '" target="_blank">preview it now</a> (opens in a new window), or make edits and save again. ';
						
				
				// if user provided email address, send instructions to use link to edit
				if ( $wEmail != '' ) {
					truwriter_mail_edit_link( $post_id, 'draft' );
					$linkEmailed = true;
					$feedback_msg .= ' Since you provided an email address, a message has been sent to <strong>' . $wEmail . '</strong>  with a special link that can be used at any time later to edit and publish your writing. '; 
				}
			
									
			 } else { // the post exists, let's update
					
				// make a copy of the category array so we can append the default category ID
				$copy_cats = $wCats;

				// check if we have a publish button click

				if ( isset( $_POST['wPublish'] ) ) {
											
					// roger, we have ignition
					$is_published = true;

					// for status message links		
					$returnlink = site_url();
					$postlink = get_permalink( $post_id );
					
					// set the published category
					$copy_cats[] = $published_cat_id;
									
					// revise status to pending (new ones) 
					$w_information['post_status'] = truwriter_option('pub_status');
										
					if ( truwriter_option('pub_status') == 'pending' ) {
						// theme options for saving as reviewed
						
						$feedback_msg = 'Your writing <strong>"' . $wTitle . '"</strong> is now in the queue for publishing and will appear on <strong>' . get_bloginfo() . '</strong> as soon as it has been reviewed. ';

						if ( $wEmail != ''  ) {
							$feedback_msg .=  'We will notify you by email at <strong>' . $wEmail . '</strong> when it has been published.';
						}
						
						$feedback_msg .= ' Now please <a href="' . $returnlink  . '">clear the writing tool and return to ' . get_bloginfo() . '</a>.';
						
						// set up admin email
						$subject = 'Review newly submitted writing at ' . get_bloginfo();
				
						$message = '<strong>"' . $wTitle . '"</strong> written by <strong>' . $wAuthor . '</strong>  has been submitted to ' . get_bloginfo() . ' for editorial review. You can <a href="'. site_url() . '/?p=' . $post_id . '&preview=true' . '">preview it now</a>.<br /><br /> To  publish simply <a href="' . admin_url( 'edit.php?post_status=pending&post_type=post') . '">find it in the submitted works</a> and change it\'s status from <strong>Draft</strong> to <strong>Publish</strong>';
						
					} else {
						// theme options for saving as published
						
						$feedback_msg = 'Your writing <strong>"' . $wTitle . '"</strong> has been published to <strong>' . get_bloginfo(). '</strong>. You can now exit the writing tool to  <a href="'.  $postlink   . '" >view it now</a> or <a href="' . $returnlink  . '">return to ' . get_bloginfo() . '</a>.';
						
						// set up admin email
						$subject = 'Recently published writing at ' . get_bloginfo();
				
						$message = '<strong>"' . $wTitle . '"</strong> written by <strong>' . $wAuthor . '</strong>  has been published to ' . get_bloginfo() . '. You can <a href="'. site_url() . '/?p=' . $post_id . '&preview=true' . '">view it now</a>,  review / edit if needed, or just enjoy the feeling of being published on your site.';
						
						// if user provided email address, send instructions to use link to edit if not done before
						if ( $wEmail != '' and !$linkEmailed  ) truwriter_mail_edit_link( $post_id, truwriter_option('pub_status') );
					
					} // is_status pending
						

					// Let's do some EMAIL! 
				
					// who gets mail? They do.
					$to_recipients = explode( "," ,  truwriter_option( 'notify' ) );
							
					if ( $wNotes ) $message .= '<br /><br />There are some extra notes from the author:<blockquote>' . $wNotes . '</blockquote>';
				
					// turn on HTML mail
					add_filter( 'wp_mail_content_type', 'set_html_content_type' );
				
					// mail it!
					wp_mail( $to_recipients, $subject, $message);
				
					// Reset content-type to avoid conflicts -- http://core.trac.wordpress.org/ticket/23578
					remove_filter( 'wp_mail_content_type', 'set_html_content_type' );	
																															
				} else {
					// updated but still in draft mode
					
					// if user provided email address, send instructions to use link to edit if not done before
					if ( isset( $wEmail ) and !$linkEmailed  ) truwriter_mail_edit_link( $post_id, 'draft' );
				
					// attach the default category ID
					$copy_cats[] = $def_category_id ;
					
					$feedback_msg = 'Your edits have been updated and are still saved as a draft mode. You can <a href="'. site_url() . '/?p=' . $post_id . 'preview=true&ispre=1' . '"  target="_blank">preview it now</a> (opens in a new window), or make edits, review again, or if you are ready, submit it for publishing. ';
					
					if (  $wEmail != '' )  $feedback_msg .= ' Since you provided an email address, you should have a message that provides instructions on how to return and make edits in a later session.';
					
				} // isset( $_POST['wPublish'] 

				// add the id to our array of post information so we can issue an update
				$w_information['ID'] = $post_id;
				$w_information['post_category'] = $copy_cats;
		 
		 		// update the post
				wp_update_post( $w_information );
				
				// update the tags
				wp_set_post_tags( $post_id, $wTags);
				
				// update featured image
				set_post_thumbnail( $post_id, $wHeaderImage_id);
								
				// Update caption to featured image if it changed
				// stored as post_excerpt for attachment entry in posts table

				if ( get_attachment_caption_by_id( $wHeaderImage_id ) != $wHeaderImageCaption  ) {
					$i_information = array(
						'ID' => $wHeaderImage_id,
						'post_status' => 'draft', 
						'post_excerpt' => $wHeaderImageCaption
					);
					
					wp_update_post( $i_information );
				}

				// store the author's name
				update_post_meta($post_id, 'wAuthor', $wAuthor);

				// update the email as post meta data
				update_post_meta($post_id, 'wEmail', $wEmail);	
																			
				// store the header image caption as post metadata
				update_post_meta($post_id, 'wHeaderCaption', $wHeaderImageCaption);

				// user selected license
				if ( truwriter_option( 'use_cc' ) != 'none' ) update_post_meta( $post_id,  'wLicense', $wLicense);

				// store notes for editor
				if ( $wNotes ) update_post_meta($post_id, 'wEditorNotes', $wNotes);

				// store any end notes
				if ( $wFooter ) update_post_meta($post_id, 'wFooter', nl2br( $wFooter ) );
								
			} // post_id = 0
						 	
		} // count errors	
						
} elseif ( $wAccessCodeOk ) { 
	// first time entry
	// ------------------------ writing form defaults ------------------------

	// defaults from theme options
	$wText =  truwriter_option('def_text'); // pre-fill the writing area
	$wCats = array( truwriter_option('def_cat')); // preload default category

	// set default image
	$wHeaderImage_id = truwriter_option('defheaderimg');
	$wHeaderImageCaption = get_attachment_caption_by_id( $wHeaderImage_id );

	//default license if used
	$wLicense = truwriter_option( 'cc_site' );

	// default is anon, that's how we roll
	$wAuthor = "Anonymous";

	//  notification box style, classes, status
	$box_style = '<div class="notify"><span class="symbol icon-info"></span> ';
	$wStatus = "New, not saved";
	$formclass = 'writenew';

	// default welcome message
	$feedback_msg = truwriter_form_default_prompt();	
	
	// ------------------------ re-edit check ------------------------

	// check for query vars that indicate this is a edit request
	$wid = get_query_var( 'wid' , 0 );   // id of post
	$tk  = get_query_var( 'tk', 0 );    // magic token to check

	if ( ( $wid  and $tk )  ) {
		// re-edit attempt
		$is_re_edit = true;
		$formclass = 'writedraft';	
	} 

	if ( $is_re_edit and !isset( $_POST['truwriter_form_make_submitted'] )) {
		// check for first entry of a re-edit.

		// look up the stored edit key
		$wEditKey = get_post_meta( $wid, 'wEditKey', 1 );

		if (  $tk == $wEditKey ) {
			// keys match, we are GOLDEN

			// default welcome message for a re-edit
			$feedback_msg = truwriter_form_re_edit_prompt();

			$writing = get_post( $wid );
			$wTitle = get_the_title( $wid );
			$wAuthor =  get_post_meta( $wid, 'wAuthor', 1 );
			$wEmail =  get_post_meta( $wid, 'wEmail', 1 );
			$wText = $writing->post_content; 
			$wHeaderImage_id = get_post_thumbnail_id( $wid);
			$box_style = '<div class="notify notify-green"><span class="symbol icon-tick"></span> ';	
			$post_status = get_post_status( $wid );

			// get categories
			$categories = get_the_category( $wid);
			foreach ( $categories as $category ) { 
				$wCats[] = $category->term_id;
			}
			// Get the attachment excerpt as a default caption
			$wHeaderImageCaption = get_attachment_caption_by_id( $wHeaderImage_id );

			// notes
			$wNotes = get_post_meta( $wid, 'wEditorNotes', 1 );

			// license
			$wLicense = get_post_meta( $wid, 'wLicense', 1 );

			// load the tags
			$wTags = implode(', ', wp_get_post_tags( $wid, array( 'fields' => 'names' ) ) );
		
			// revision count
			$revcount = 1;
		
			// post id
			$post_id = $wid;
		
			// status note
			$wStatus = 'Re-edit (revision #' . $revcount . ' last saved ' . get_the_date( '', $wid) . ' ' .  get_the_time( '', $wid) . ')';
	
			} else {
				// attempted re-edit but keys dont match
				$is_re_edit = false;

				// updates for display	
				$errors[] = '<strong>Token Mismatch</strong> - please check the url provided.';
				$wStatus = 'Form input error';
				$formclass = 'writeoops';	
				// default welcome message is error
				$feedback_msg = 'This URL does not match the edit key. Please check the link from your email again, or return to your published writing and click the button at the bottom to send an edit link.';
				$is_published = true;  // not really but it serves to hide the form.
			}
	} 	
}

// end form submmitted check

get_header('write');
?>

<div class="content">		

	<?php if ( have_posts() ) : 
		
		while ( have_posts() ) : the_post(); ?>
	
			<div <?php post_class( 'post single' ); ?>>
		
				<?php if ( has_post_thumbnail() ) : ?>
					
					<div class="featured-media" style="background-image: url( <?php the_post_thumbnail_url( $post->ID, 'post-image' ); ?> );">
			
						<?php 
						
						the_post_thumbnail( 'post-image' );
						
						$image_caption = get_post( get_post_thumbnail_id() )->post_excerpt;
						
						if ( $image_caption ) : 
							?>
												
							<div class="media-caption-container">
								<p class="media-caption"><?php echo $image_caption; ?></p>
							</div>
							
						<?php endif; ?>
						
					</div><!-- .featured-media -->
						
				<?php endif; ?>

											
			<div class="post-header section">
		
				<div class="post-header-inner section-inner">
																									
					<?php the_title( '<h1 class="post-title">', '</h1>' ); ?>
				
				</div><!-- .post-header-inner section-inner -->
														
			</div><!-- .post-header section -->
			    
		    <div class="post-content section-inner medium">
		    
		    	<?php the_content(); ?>
		    		
			    		
		    	<?php echo $box_style . $feedback_msg . '</div>';?>   
		    	
				<div class="clear"></div>
							
				<?php wp_link_pages('before=<p class="page-links">' . __('Pages:','radcliffe') . ' &after=</p>&seperator= <span class="sep">/</span> '); ?>
		    
					
		<?php endwhile; else: ?>
	
			<p><?php _e("Danger, danger Will Robinson, somethng bad happened inside the engine room. Have Scotty radio the bridge and ask for more dilithium crystals", "radcliffe"); ?></p>

		<?php endif; ?>
		
		
	<?php if (!$wAccessCodeOk) : // show the access code form ?>
	
		<form  id="writerform" class="writenew" method="post" action="">
			<fieldset>
				<label for="wAccess">Access Code</label><br />
				<p>Enter the special code to access the writing tool</p>
				<input type="text" name="wAccess" id="wAccess" class="required" value="<?php echo $wAccess?>"  />
			</fieldset>	
		
			<fieldset> 
			<?php wp_nonce_field( 'truwriter_form_access', 'truwriter_form_access_submitted' )?>
			
			<input type="submit" class="pretty-button pretty-button-final" value="Check Code" id="checkit" name="checkit">
			</fieldset>
		</form>
			
	<?php elseif ( !$is_published or $is_re_edit ) : // show form if logged in and it has not been published ?>
		
		<form  id="writerform" class="<?php echo $formclass?>" method="post" action="" enctype="multipart/form-data">
		
		<div class="writestatus">STATUS: <span class="statnow"><?php echo $wStatus?></span></div>
		
		<input name="post_id" type="hidden" value="<?php echo $post_id?>" />
		<input name="revcount" type="hidden" value="<?php echo $revcount?>" />
		<input name="linkEmailed" type="hidden" value="<?php echo $linkEmailed?>" />
		<input name="wAccessCodeOk" type="hidden" value="true" />
		
				<fieldset id="theTitle">
					<label for="wTitle"><?php truwriter_form_item_title() ?></label><br />
					<p><?php truwriter_form_item_title_prompt()?></p>
					<input type="text" name="wTitle" id="wTitle" class="required writerfield" value="<?php echo $wTitle; ?>"  />
				</fieldset>	
			
				<fieldset id="theAuthor">
					<label for="wAuthor"><?php truwriter_form_item_byline() ?></label><br />
					<p><?php truwriter_form_item_byline_prompt() ?></p>
					<input type="text" name="wAuthor" id="wAuthor" class="required writerfield" value="<?php echo $wAuthor; ?>"  />
				</fieldset>	
				
				<fieldset id="theText">
						<label for="wText"><?php truwriter_form_item_writing_area() ?></label>
						<p><?php truwriter_form_item_writing_area_prompt() ?></p>
						
						<p> See details on the formatting tools in the  
<a class="video fancybox.iframe" href="<?php echo get_stylesheet_directory_uri()?>/includes/edit-help.html">editing tool tips</a>.</p>
						<?php
						// set up for inserting the WP post editor
						$settings = array( 
							'textarea_name' => 'wText', 
							'editor_height' => '400', 
							'media_buttons' => FALSE,
						);

						wp_editor(  stripslashes( $wText ), 'wText', $settings );
						
						
						?>
						
						
				</fieldset>


				<?php if (truwriter_option('show_footer') ):?>
				<fieldset id="theFooter">
						<label for="wFooter"><?php truwriter_form_item_footer() ?></label>						
						<p><?php truwriter_form_item_footer_prompt() ?></p>
						<textarea name="wFooter" id="wFooter" class="writerfield" rows="15"  ><?php echo stripslashes( $wFooter );?></textarea>
				</fieldset>
				<?php endif?>
				
				<fieldset id="theHeaderImage">
					<label for="headerImage"><?php truwriter_form_item_header_image() ?></label>
					
						
					<div class="uploader">
						<input id="wHeaderImage" name="wHeaderImage" type="hidden" value="<?php echo $wHeaderImage_id?>" />

						<?php 
						
						if ($wHeaderImage_id) {
							$defthumb = wp_get_attachment_image_src( $wHeaderImage_id, 'thumbnail' );
						} else {
							$defthumb = [];
							$defthumb[] = get_stylesheet_directory_uri() . '/images/default-header-thumb.jpg';
							$wHeaderImageCaption = 'flickr photo by Lívia Cristina https://flickr.com/photos/liviacristinalc/3402221680 shared under a Creative Commons (BY-NC-ND) license';
						}
						
						?>
						<input id="wDefThumbURL" name="wDefThumbURL" type="hidden" value="<?php echo $defthumb[0]?>" />
						<img src="<?php echo $defthumb[0]?>" alt="thumbnail image to represent featured one for this item" id="headerthumb" />

						
						</div>
						
						<p><?php truwriter_form_item_header_image_prompt() ?> <span id="uploadresponse"><?php echo $w_thumb_status?></span><br clear="left"></p>
						<p id="footlocker"></p>
						
						<div id="splotdropzone">
							<input type="file" accept="image/*" name="wUploadImage" id="wUploadImage">
							<p id="dropmessage">Drag file or click to select file to upload</p>
						</div>
						
						<label for="wHeaderImageCaption"><?php truwriter_form_item_header_caption() ?></label>
						<p><?php truwriter_form_item_header_caption_prompt() ?></p>
						<input type="text" name="wHeaderImageCaption" class="writerfield" id="wHeaderImageCaption" value="<?php echo htmlentities( stripslashes( $wHeaderImageCaption ), ENT_QUOTES); ?>" />
				
				</fieldset>						
				
				
				<?php if (truwriter_option('show_cats') ):?>
				
				<fieldset  id="theCats">
					<label for="wCats"><?php truwriter_form_item_categories() ?></label>
					<p><?php truwriter_form_item_categories_prompt() ?></p>
					<?php 
					
					// set up arguments to get all categories that are children of "Published"
					$args = array(
						'child_of'                 => $published_cat_id,
						'hide_empty'               => 0,
					); 
					
					$article_cats = get_categories( $args );

					foreach ( $article_cats as $acat ) {
					
						$checked = ( in_array( $acat->term_id, $wCats) ) ? ' checked="checked"' : '';
						
						echo '<br /><input type="checkbox" name="wCats[]" value="' . $acat->term_id . '"' . $checked . '> ' . $acat->name . ' <em style="font-size:smaller">' . $acat->description . '</em>';
					}
					
					?>
					
				</fieldset>

				<?php endif?>
				
				<?php if (truwriter_option('show_tags') ):?>
				
				<fieldset id="theTags">
					<label for="wTags"><?php truwriter_form_item_tags() ?></label>
					<p><?php truwriter_form_item_tags_prompt() ?></p>
					
					<input type="text" name="wTags" id="wTags" class="writerfield" value="<?php echo $wTags; ?>"  />
				</fieldset>

				<?php endif?>

				<?php if (truwriter_option('show_email') ):?>
				<fieldset id="theEmail">
					<label for="wEmail"><?php truwriter_form_item_email() ?> (optional)</label><br />
					<p><?php truwriter_form_item_email_prompt() ?> 
					<?php 
						if  ( !empty( truwriter_option('email_domains') ) ) {
							echo ' Allowable email addresses must be ones from domains <code>' . truwriter_option('email_domains') . '</code>.';
						}
					?>
					
					</p>
					<input type="text" name="wEmail" id="wTitle" class="writerfield"  value="<?php echo $wEmail; ?>" autocomplete="on" />
				</fieldset>	
				
				<?php endif?>
				

				<?php if ( truwriter_option('require_extra_info') != -1 ):?>
				
				<fieldset id="theNotes">
						<?php $req_state = ( truwriter_option('require_extra_info') == 1 ) ? 'required' : 'optional';?>
						<label for="wNotes"><?php truwriter_form_item_editor_notes(); _e(' (' . $req_state . ')' , 'radcliffe') ?></label>						
						<p><?php truwriter_form_item_editor_notes_prompt()?></p>
						<textarea name="wNotes" class="writerfield" id="wNotes" rows="15"  ><?php echo stripslashes( $wNotes );?></textarea>
				</fieldset>
				<?php endif?>



					<?php if ( truwriter_option( 'use_cc' ) != 'none' ):?>
						<!-- creative commons options -->
						<fieldset  id="theLicense">
				
							<label for="wLicense"><?php truwriter_form_item_license()?></label>
							<?php if ( truwriter_option( 'use_cc' ) == 'site' ) :?>
					
								<p>All writing added to this site will be published under a rights statement like:</p>
								
								<p class="form-control"><?php echo truwriter_license_html( truwriter_option( 'cc_site' ), $wAuthor );?></p>
								<input type="hidden" name="wLicense" id="wLicense" value="<?php echo truwriter_option( 'cc_site' )?>">
								
				
							<?php elseif  ( truwriter_option( 'use_cc' ) == 'user' ) :?>
								
								<p><?php truwriter_form_item_license_prompt()?></p>
								
								<select name="wLicense" id="wLicense" class="form-control">
									<option value="--">Select...</option>
									<?php echo cc_license_select_options( $wLicense )?>
								</select>				
							<?php endif; // -- cc_mode type = site or user?>
						</fieldset>
						<?php endif; // -- cc_mode != none?>

			
				<fieldset>
				
					<?php 
					wp_nonce_field( 'truwriter_form_make', 'truwriter_form_make_submitted' ); 
					?>
					
					<?php if ( $post_id ) :?>
							
						<?php
						// set up button names

						if ( $is_re_edit ) {
							$save_btn_txt = "Update and Publish";
						} else {
							$save_btn_txt = "Publish Now";
						}
					?>
						<input type="submit" class="pretty-button pretty-button-update" value="Update and Save Draft" id="wSubDraft" name="wSubDraft" > Save changes as draft and continue writing.<br /><br />
						
						<input type="submit" class="pretty-button pretty-button-final" value="<?php echo $save_btn_txt?>" id="wPublish" name="wPublish" > All edits complete, publish to site. 
					
					<?php else:?>
					
						<input type="submit" class="pretty-button pretty-button-update" value="Save Draft" id="wSubDraft" name="wSubDraft" > Save your first draft, then preview.
					
					<?php endif?>
					
					
				</fieldset>
			
				<div class="writestatus">STATUS: <span class="statnow"><?php echo $wStatus?></span></div>
		
		</form>
	<?php endif?>
	
	<div class="clear"></div>
			
	</div> <!-- /post -->
		

<div class="clear"></div>	
</div> <!-- /content -->
								
<?php get_footer(); ?>