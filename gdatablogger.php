<?php
/*
* Plugin Name: Blogger.com publisher
* Plugin URI: http://www.linuxsolutions.co.nz/gdatablogger
* Description: Publishes posts with specific tags and/or catagories to blogger.com using the Zend gdata api.
* Version: 0.1
* Author: Glen Ogilvie
* Author URI: http://www.linuxsolutions.co.nz
* */

/*  Copyright (C) 2008  Glen Ogilvie

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

define ("GDATA_ARRAY", 1);
define ("GDATA_STRING", 2);

class gdata {
	private $blogID;
	private $email;
	private $password;		// blogid from blogger.com
	private $categories_to_publish; 	// id of categories to publish
	private $tags_to_publish;
	private $timezone_offset;
	private $meta_key = "gdataId";
	public $gdClient;

	private function load_options() {
		foreach ($this->get_options() as $option) {
			switch ($option->type) {
			case GDATA_STRING: 
				$this->{$option->class_var} = get_option( $option->db_var );
				break;
			case GDATA_ARRAY:
				$this->{$option->class_var} = split(",", get_option( $option->db_var ));
				break;
			}
		}		
	}
	
	public static function get_options() {
		return array ( 
		new gdata_option("Gmail email address:", "email", GDATA_STRING),
		new gdata_option("Gmail password:", "password", GDATA_STRING, "This will be stored in the wordpress options table"),
		new gdata_option("blogger.com blog ID:", "blogID", GDATA_STRING, "Your blog id. Can be found by looking at the create post link on your blogger.com blog, it will look like: ?blogID=[number]"),
		new gdata_option("Categories to publish:", "categories_to_publish", GDATA_ARRAY, "Comma seprated list of Category id numbers - see: Manage->Categories"),
		new gdata_option("Tags to publish:", "tags_to_publish", GDATA_ARRAY, "Comma seprated list of tag id numbers - see: Manage->Tags"),
		new gdata_option("Timezone Offset:", "timezone_offset", GDATA_STRING, "Offset which will be applied to the date of posts when posting to blogger.com")
		);
	}
	
	public function __construct()
	{
			$this->load_options();
	}

	public function authenticate() {
		if ($this->gdClient) return;
		$client = Zend_Gdata_ClientLogin::getHttpClient($this->email, $this->password, 'blogger');
		$this->gdClient = new Zend_Gdata($client);
	}

	function publish_remote($post_id) {
			$post = get_post($post_id);	
			$remost_post_id = get_post_meta($post_id, $this->meta_key, true);
			$content = $this->format_content($post->post_content); 
			$this->authenticate();
			if (empty($remost_post_id)) {
				$remost_post_id = $this->gdatacreatePost($post->post_title, $content, $post->post_date);
				add_post_meta($post_id, $this->meta_key, $remost_post_id, true);
			} else {
				$this->updatePost($remost_post_id,$post->post_title, $content, $post->post_date);
			}
	}
	function format_date($date) {
		$datetime = date_create ($date);
		$datetime->modify($this->timezone_offset);
		return $datetime->format("c");
	// needs to be in format: 2008-07-31T04:58:00.001-07:00 (ISO 8601 date)
	}
	function is_publishable($post_id) {
		$categories =  wp_get_post_categories($post_id);
		if (count(array_intersect($this->categories_to_publish, $categories)) > 0) return true;
		$tags = wp_get_post_tags($post_id);
		foreach ($tags as $tag) {
			if (array_search($tag->term_id,$this->tags_to_publish)) return true;
		}
		return false;
	}

	private function format_content($content) {
		// format the contents the same way wordpress does by default.
		$content = apply_filters('the_content', $content);
        	$content = str_replace(']]>', ']]&gt;', $content);
		return $content;
	}

	public function gdatacreatePost($title, $content, $published, $isDraft=False)
	{
		// We're using the magic factory method to create a Zend_Gdata_Entry.
		// http://framework.zend.com/manual/en/zend.gdata.html#zend.gdata.introdduction.magicfactory
		$entry = $this->gdClient->newEntry();
		$entry->title = $this->gdClient->newTitle(trim($title));
		$entry->content = $this->gdClient->newContent(trim($content));
		$entry->published = $this->gdClient->newPublished($this->format_date($published));
		$entry->content->setType('html'); 
		$uri = "http://www.blogger.com/feeds/" . $this->blogID . "/posts/default";  
		if ($isDraft)
		{
			$control = $this->gdClient->newControl(); 
			$draft = $this->gdClient->newDraft('yes');
			$control->setDraft($draft);  
			$entry->control = $control; 
		}

		$createdPost = $this->gdClient->insertEntry($entry, $uri);
		$idText = split('-', $createdPost->id->text);
		$postID = $idText[2];
		return $postID; 
	}
	
	public function updatePost($postID, $updatedTitle, $updatedContent, $published, $isDraft=false)
	{
		$query = new Zend_Gdata_Query('http://www.blogger.com/feeds/' . $this->blogID . '/posts/default/' . $postID); 
		$postToUpdate = $this->gdClient->getEntry($query);
		$postToUpdate->title->text = $this->gdClient->newTitle(trim($updatedTitle));
		$postToUpdate->content->text = $this->gdClient->newContent(trim($updatedContent));
		// using Gdata/App/Extension/Published.php - Zend_Gdata_App_Extension_Published
		$postToUpdate->published->text = $this->gdClient->newPublished($this->format_date($published));
		if ($isDraft) {
			$draft = $this->gdClient->newDraft('yes'); 
		} else {
			$draft = $this->gdClient->newDraft('no');
		}
		$control = $this->gdClient->newControl();
		$control->setDraft($draft);
		$postToUpdate->control = $control;
		$updatedPost = $postToUpdate->save();
		return $updatedPost; 
    }

}


class gdata_option {
	var $db_var, $class_var, $display_name, $type, $comment;

	function __construct($display, $class_var, $type, $comment = "" ) {
		$this->db_var = "gdata_".$class_var;
		$this->class_var = $class_var;
		$this->type = $type;
		$this->display_name = $display;
		$this->comment = $comment;
		}
}

function gdata_publish($post_id) {
	require_once 'Zend/Loader.php';
	Zend_Loader::loadClass('Zend_Gdata');
	Zend_Loader::loadClass('Zend_Gdata_Query');
	Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
	$gdata = new gdata();
	if ($gdata->is_publishable($post_id)) 
		$gdata->publish_remote($post_id);

}

// action function for above hook
function gdata_add_pages() {
    // Add a new submenu under Options:
    add_options_page('blogger.com publish options', 'blogger.com', 8, 'gdatablogger', 'gdata_options_page');
}

function gdata_options_page() {
    $options = gdata::get_options();
    $hidden_field_name = 'gdata_submit_hidden';

    // See if the user has posted us some information
    // If they did, this hidden field will be set to 'Y'
    if( $_POST[ $hidden_field_name ] == 'Y' ) {
        // Read their posted value
        foreach ($options as $option) {
		// Save the posted value in the database
		update_option( $option->db_var, trim($_POST[ $option->db_var ]) );
	}
        // Put an options updated message on the screen
?>
<div class="updated"><p><strong><?php _e('Options saved.', 'mt_trans_domain' ); ?></strong></p></div>
<?php
    }
    // Now display the options editing screen
    echo '<div class="wrap">';
    echo "<h2>" . __( 'blogger.com publish options', 'mt_trans_domain' ) . "</h2>";
    // options form
 ?>
<form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
<table class="form-table">
<?php  foreach ($options as $option) { ?>
<tr valign="top">
<th scope="row"><label for="<?php echo $option->db_var; ?>"><?php _e($option->display_name, 'mt_trans_domain' ); ?></label></th>
<td><input type="text" name="<?php echo $option->db_var; ?>" value="<?php echo get_option( $option->db_var ); ?>" size="40"></td>
<td><?php echo $option->comment; ?></td>
</tr>
<?php } ?>
</table>
<p class="submit">
<input type="submit" name="Submit" value="<?php _e('Update Options', 'mt_trans_domain' ) ?>" />
</p>
</form>
</div>
<?php

}

// Hook for adding admin menus
add_action('admin_menu', 'gdata_add_pages');
add_action('publish_post', 'gdata_publish');
?>