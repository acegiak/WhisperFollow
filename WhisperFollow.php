<?php
/*
    Plugin Name: WhisperFollow
    Plugin URI: http://acegiak.machinespirit.net/2012/01/25/whisperfollow/
    Description: Follow and reblog multiple sites with simplepie RSS
    Version: 1.3.0
    Author: Ashton McAllan
    Author URI: http://acegiak.machinespirit.net
    License: GPLv2
*/

/*  Copyright 2012 Ashton McAllan (email : acegiak@gmail.com)
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/

/*
 MF2 Parser by Barnaby Walters: https://github.com/indieweb/php-mf2
*/
	require_once('WFCore.php');


    global $whisperfollow_db_version;
$whisperfollow_db_version = "1.0";


function whisperfollow_feed_time() { return 300; }

function whisperfollow_cron_definer($schedules){

	$schedules['fivemins'] = array(
		'interval'=> 300,
		'display'=>  __('Once Every 5 Minutes')
	);

	return $schedules;
}


function date_sort($a,$b) {
			$ad = (int)$a->get_date('U');
			$bd = (int)$b->get_date('U');
              if ($ad == $bd) {
        return 0;
    }
    return ($ad > $bd) ? -1 : 1;
}
    

function whisperfollow_createtable() {
	global $wpdb;
	global $whisperfollow_db_version;

	$table_name = $wpdb->prefix . "whisperfollow";

	$sql = "CREATE TABLE " . $table_name . " (
		id int NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		authorname text DEFAULT '' NOT NULL,
		authorurl text DEFAULT '' NOT NULL,
		authoravurl text DEFAULT '' NOT NULL,
		permalink text DEFAULT '' NOT NULL,
		title text DEFAULT '' NOT NULL,
		content mediumtext DEFAULT '' NOT NULL,
		viewed boolean DEFAULT FALSE NOT NULL,
		UNIQUE KEY id (id)
	);";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);

	add_option("whisperfollow_db_version", $whisperfollow_db_version);
}

function whisperfollow_installdata() {
	$welcome_name = "The Machine-Spirits";
	$welcome_text = "Congratulations, you've installed WhisperFollow.";
	add_whisper('','Install complete',$welcome_text,$welcome_name);
   
}


function whisperfollow_update_db_check() {
	global $whisperfollow_db_version;
	if (get_site_option('whisperfollow_db_version') != $whisperfollow_db_version) {
		whisperfollow_install();
	}
}


function add_whisper($permalink,$title,$content,$authorname='',$authorurl='',$time=0,$avurl=''){
	whisperfollow_log("adding whisper: ".$permalink.": ".$title);
	global $wpdb;
	if($time < 1){
		$time = time();
	}
	$table_name = $wpdb->prefix . "whisperfollow";
	if($wpdb->get_var( "SELECT COUNT(*) FROM ".$table_name." WHERE permalink LIKE \"".$permalink."\";")<1){
		whisperfollow_log("no duplicate found");
		$rows_affected = $wpdb->insert( $table_name,
			array(
				'permalink' => $permalink,
				'title' => $title,
				'content' => $content,
				'authorname' => $authorname,
				'authorurl' => $authorurl,
				'time' => date( 'Y-m-d H:i:s', $time),
				'authoravurl' => $avurl,
				'viewed' =>false,
			 ) );

		if($rows_affected == false){
		
			whisperfollow_log("could not insert whisper into database!");
			die("could not insert whisper into database!");
		}else{
			whisperfollow_log("added ".$title." from ".$authorurl);
		}
	}else{
		whisperfollow_log("duplicate detected");
	}

}

function whisperfollow_install() {
	// Activates the plugin and checks for compatible version of WordPress 
	if ( version_compare( get_bloginfo( 'version' ), '2.9', '<' ) ) {
		deactivate_plugins ( basename( __FILE__ ));     // Deactivate plugin
		wp_die( "This plugin requires WordPress version 2.9 or higher." );
	}

	if ( !wp_next_scheduled( 'whisperfollow_generate_hook' ) ) {            
		wp_schedule_event( time(), 'fivemins', 'whisperfollow_generate_hook' );
	}
	$isthereacat = false;
	foreach (get_categories() as $category){
		if($category->name == "whispers"){
			$isthereacat = true;
			break;
		}
	}
	if(!$isthereacat){
		if(wp_create_category("whispers")<1){
			die("failed to create category \"whispers\"!");
		}
	}

	createthefollowpage();
	flush_rewrite_rules( false );
}
    

function whisperfollow_deactivate() {
	// on deactivation remove the cron job 
	if ( wp_next_scheduled( 'whisperfollow_generate_hook' ) ) {
		wp_clear_scheduled_hook( 'whisperfollow_generate_hook' );
	}
}

     
function createthefollowpage(){
	if(!get_page_by_title( 'following' )){
	$current_user = wp_get_current_user();
     if ( !($current_user instanceof WP_User) )
         wp_die("Couldn't get current user to create follow page");
	$post = array(
			'comment_status' => 'closed', 
			'ping_status' => 'closed', 
			'post_author' => $current_user->ID,
			'post_content' => '[whisperfollow_page]', 
			'post_name' => 'following', 
			'post_status' => 'publish', 
			'post_title' => 'following', 
			'post_type' => 'page' 
		); 
		if(wp_insert_post( $post )<1)
			wp_die("Could not create the followpage");
		
	}else{
		wp_die("followpage already exists");
	}
}

function createthereblog($ftitle,$fcontent){
	$cat = get_term_by('name', 'whispers', 'category');
	if($cat){
		$cats = array($cat->term_id);
	}else{
		$cats= array();
	}
	$post = array(
		'post_author' => $user_ID, //The user ID number of the author.
		'post_content' => $fcontent, //The full text of the post.
		'post_title' => $ftitle, //The title of your post.
		'post_status' => 'publish',
		'post_type' => 'post', //You may want to insert a regular post, page, link, a menu item or some custom post type
		'post_category' => $cats
	); 
	set_post_format(wp_insert_post( $post, $wp_error ),"aside");
	//echo "<p>Created post \"".$ftitle."\"</p>";
}
    
function convertAttributeWhitespace($matches){
	return '="'.str_replace(' ','%20',$matches[1]).'"';
}

	
//START PAGINATION VARIABLES

function whisperfollow_add_rewrite_rules( $wp_rewrite ) 
{
  $new_rules = array( 
     $wp_rewrite->root.'(following)/(\w*)$' => 'index.php?pagename='.
       $wp_rewrite->preg_index(1).'&followpage='.
       $wp_rewrite->preg_index(2),
	 $wp_rewrite->root.'(following)$' => 'index.php?pagename='.
       $wp_rewrite->preg_index(1));

  $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
}


function following_queryvars( $qvars )
{
	$qvars[] = 'followpage';
	return $qvars;
}
 
    
function whisperfollow_shortcode( $atts ) {    
	if ( !empty ($atts) ) {
		foreach ( $atts as $key => &$val ) {
			$val = html_entity_decode($val);
		}
	}
	whisperfollow_page( $atts );       
}

function whisperfollow_newfollow($examineurl){
	include('wp-admin/includes/bookmark.php');
	$linkdata = WFCore_newfollow($examineurl);
	
	$link_id = wp_insert_link( $linkdata );
}



//BEGIN PUBSUBHUBBUB
function whisperfollow_pubsub_parse_request($wp) {
    if (array_key_exists('wfpushend', $wp->query_vars) ){
			if( array_key_exists('hub_challenge', $wp->query_vars)){
				header('HTTP/1.1 200 OK', null, 200);
				echo $wp->query_vars['hub_challenge'];
				whisperfollow_log("hub challenge: ".$wp->query_vars['hub_challenge'],false);
				exit();
			}else{
				whisperfollow_log("Recieved PUSH update!");
				$body = @file_get_contents('php://input');
				$self = rss_selfget($body);
				$hub = rss_hubget($body);
				if(strlen($self) > 0){
					whisperfollow_log("PUSH START: ".substr($self,0,50));
					if(!in_array($self,array_map(create_function('$o', 'return $o->link_rss;'),get_bookmarks( array(	'orderby' => 'name','order'=> 'ASC','category_name'  => 'Blogroll'))))){
						whisperfollow_pubsub_change_subscription("unsubscribe",$self,$hub);
					}else{
						whisperfollow_aggregate($body,true);
					}
				}else{
					whisperfollow_log("PUSH notice without SELF:<br/>".$body,false);
				}
				
				exit();
			}
    }
}
add_action('parse_request', 'whisperfollow_pubsub_parse_request');


function whisperfollow_pubsub_query_vars($vars) {
    $vars[] = 'wfpushend';
	$vars[] = 'hub.challenge';
    return $vars;
}
add_filter('query_vars', 'whisperfollow_pubsub_query_vars');



	
function whisperfollow_subscribe_to_push($feed,$hub){
		whisperfollow_log( "subscribing to PUSH for instant notification!<br/>Feed: ".$feed."<br/>hub: ".$hub);
	$o = get_option('whisperfollow_pushsubs');
	if($o == false){$o = array();}else{$o = explode("|",$o);}
	
	if(strlen($feed)>0){
		if(!in_array($feed,$subscribed)){
			if(whisperfollow_pubsub_change_subscription("subscribe",$feed,$hub)){
				$subscribed[] = $feed;
				whisperfollow_log("PUSH subscribed to \"".$feed."\"");
			}
		}
	}
	update_option( 'whisperfollow_pushsubs', implode("|",$subscribed));
}

//END PUBSUB HUBBBUB


function whisperfollow_aggregator( $args = array() ) {
	whisperfollow_log("aggregation!");
	$bookmarks = get_bookmarks( array(
	'orderby'        => 'name',
	'order'          => 'ASC',
	'category_name'  => 'Blogroll'
	));
	$feed_uris = array();
	foreach($bookmarks as $bookmark){
		if(rand(0,count($bookmarks))<100){
			if(strlen($bookmark->link_rss)>0){
				whisperfollow_log('<br/>checking '.$bookmark->link_name);
				whisperfollow_aggregate( $bookmark->link_rss);
			}else{
				whisperfollow_mf2_read($bookmark->link_url);
			}
		}
	}
	

}

function followinglink(){
	$followinglink = "/index.php?pagename=following&followpage=";
	if ( get_option('permalink_structure') != '' ) { $followinglink = "/following/"; }
	return $followinglink;
}

function whisperfollow_page($items){
	global $wp_query;
	global $wpdb;
	$fpage=0;
	$length = 15;
	if (isset($wp_query->query_vars['followpage']))	{
		echo "followpage: ";
		$pagenum = (string)$wp_query->query_vars['followpage'];
		echo $pagenum;
		if(stristr($pagenum,"debuglog")){
			whisperfollow_log("log viewed");
			echo "<br/>".implode("<br>",array_slice(explode("|",get_option("whisperfollow_log")),0,25));
			return;
		}elseif(stristr($pagenum,"endpoint")){
			$wfbody = @file_get_contents('php://input');
			$feed = whisperfollow_fetch_feed( $wfbody );
			whisperfollow_log("got a pubsubhubbub update from \"".$feed."\"");
			echo "<br/>frickin PuSH endpoint!";
			return;
		}elseif(current_user_can('manage_options')){
			$fpage = $wp_query->query_vars['followpage'];
		}else{
			echo '<p>Only the owner of this page can view their <a href="http://wordpress.org/extend/plugins/whisperfollow">WhisperFollow</a> feed.</p>';
			return;
		}
	}elseif(!current_user_can('manage_options')){
		echo '<p>Only the owner of this page can view their <a href="http://wordpress.org/extend/plugins/whisperfollow">WhisperFollow</a> feed.</p>';
		return;
	}
	if(isset($_POST['follownewaddress'])&&current_user_can('manage_options')){
		whisperfollow_newfollow($_POST['follownewaddress']);
	}
	if(isset($_POST['followtitle'])&&current_user_can('manage_options')){
		createthereblog(html_entity_decode($_POST['followtitle']),html_entity_decode($_POST['followcontent']));
	}
	if(isset($_POST['forcecheck'])&&current_user_can('manage_options')){
		whisperfollow_log("check forced by user");
		whisperfollow_aggregator();
	}
	$items = $wpdb->get_results(
		'SELECT * 
		FROM  `'.$wpdb->prefix . 'whisperfollow` 
		ORDER BY  `id` DESC 
		LIMIT '.($fpage*$length).' , '.$length.';'
	);
	      
	whisperfollow_display($items,'Items');
	echo '<div style="clear: both;">';
	if($fpage > 0){
		echo '<p style="float: left;"><a href="'.site_url().followinglink().($fpage-1).'" >Newer</a></p>';
	}
	echo '<p style="float: right;"><a href="'.site_url().followinglink().($fpage+1).'" >Older</a></p></div>';
	echo '<div style="clear: both;"><form target="" method="POST">New Follow: <input type="TEXT" name="follownewaddress"><input type="SUBMIT" value="go"><br><input type="submit" name="forcecheck" value="forcecheck"></form></div>';
}
    
function whisperfollow_display($items,$time){
	if ( !empty( $items ) ) { 
		foreach ( $items as $item ) {
			echo '<div class="followingpost" style="border:3px solid black;" ><a href="' . $item->permalink.'"><h2>'. $item->title. '</h2></a>'; 
			echo '<div class="rss_show_box" id="'.$item->permalink.'">'. $item->content. '</div>'; 
			echo '<br><span class="feed-source">Source: '.$item->authorname . ' | ' . $item->time. '</span>';
			if(current_user_can('manage_options')){
				echo "<br><button onClick=\"document.getElementById('reply-".urlencode($item->permalink)."').style.display='block'\">Reblog This</button>";
				echo "</div>";
				echo '<div id="reply-'.urlencode($item->permalink).'" style="display:none;"><form target="" method="POST">
				Title<br>
				<input type="text" name="followtitle" value="'.htmlspecialchars($item->authorname.": ".$item->title).'"><br>
				Text:<br>
				<textarea name="followcontent" style="width:100%;height:300px">'.htmlspecialchars("<p><blockquote>".$item->content.'</blockquote>Reblogged from <a rel="in-reply-to" class=u-in-reply-to" href="'.$item->permalink.'">@'.$item->authorname.": ".$item->title.'</a></p>').'</textarea><br>
				<input type="hidden" name="followpermalink" value="'.$item->permalink.'">
				<input type="submit" value="go">
				</form>';
			}
			echo '</div>';
			if (strpos(strtolower ($item->permalink), 'tumblr') !== FALSE || strpos(strtolower ($item->authorname), 'tumblr') !== FALSE){
				echo '
				<button onClick="jQuery(\'#tumblr-'.preg_replace('`\W`','',$item->permalink).'\').attr(\'src\',\''.htmlspecialchars($item->permalink).'\');jQuery(\'#tumblr-'.preg_replace('`\W`','',$item->permalink).'\').toggle()">tumblrreblog</button>
				<iframe id="tumblr-'.preg_replace('`\W`','',$item->permalink).'" style="display:none;width:300px;height:25px;" scrolling="no"></iframe>';
			}
		}

	}
}

function whisperfollow_log($message,$verbose=true){
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	if($verbose && is_plugin_active('whisperfollow/WhisperFollow.php')){
		echo "<p>".$message."</p>";
	}
	$o = get_option('whisperfollow_log');
	if($o == false){
		$log = "";
	}else{
		$log = "|".((string)$o);
	}
	$log = ((string)date('r')).": ".(string)$message.$log;
	update_option('whisperfollow_log',substr($log,0,100000));
}

add_shortcode( 'whisperfollow_page', 'whisperfollow_shortcode');
add_filter('cron_schedules','whisperfollow_cron_definer');

add_filter('query_vars', 'following_queryvars' );
    
register_activation_hook( __FILE__, 'whisperfollow_install' );
register_activation_hook(__FILE__,'whisperfollow_createtable');
register_activation_hook(__FILE__,'whisperfollow_installdata');

register_deactivation_hook( __FILE__, 'whisperfollow_deactivate' );

//add_action('plugins_loaded', 'whisperfollow_update_db_check');
add_action( 'whisperfollow_generate_hook', 'whisperfollow_aggregator' );
add_action('generate_rewrite_rules', 'whisperfollow_add_rewrite_rules');
add_action('admin_init', 'flush_rewrite_rules');

add_action('activated_plugin','save_error');
function save_error(){
    update_option('plugin_error',  ob_get_contents());
}





?>