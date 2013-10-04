<?php
/*
    Plugin Name: WhisperFollow
    Plugin URI: http://www.machinespirit.net/acegiak
    Description: Follow and reblog multiple sites with simplepie RSS
    Version: 1.2.5
    Author: Ashton McAllan
    Author URI: http://www.machinespirit.net/acegiak
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

	require_once('pubsubhubbubclient.php');


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

function getRSSLocation($html, $location){
	if(!$html or !$location){
		return false;
	}else{
		#search through the HTML, save all <link> tags
		# and store each link's attributes in an associative array
		preg_match_all('/<link\s+(.*?)\s*\/?>/si', $html, $matches);
		$links = $matches[1];
		$final_links = array();
		$link_count = count($links);
		for($n=0; $n<$link_count; $n++){
			$attributes = preg_split('/\s+/s', preg_replace_callback('`="(.*?)"`','convertAttributeWhitespace',$links[$n]));
			foreach($attributes as $attribute){
				$att = preg_split('/\s*=\s*/s', $attribute, 2);
				if(isset($att[1])){
					$att[1] = preg_replace('/([\'"]?)(.*)\1/', '$2', $att[1]);
					$final_link[strtolower($att[0])] = $att[1];
				}
			}
			$final_links[$n] = $final_link;
		}
		#print_r($final_links);
		#now figure out which one points to the RSS file
		for($n=0; $n<$link_count; $n++){
			if(strpos(strtolower($final_links[$n]['rel']), 'alternate') !== false){
				if(strtolower($final_links[$n]['type']) == 'application/rss+xml'){
					$href = $final_links[$n]['href'];
				}
				if(!$href and strtolower($final_links[$n]['type']) == 'application/atom+xml'){
					#kludge to make the first version of this still work
					$href = $final_links[$n]['href'];
				}
				if(!$href and strtolower($final_links[$n]['type']) == 'text/xml'){
					#kludge to make the first version of this still work
					$href = $final_links[$n]['href'];
				}
				
				if($href){
					if(strstr($href, "http://") !== false){ #if it's absolute
						$full_url = $href;
					}else{ #otherwise, 'absolutize' it
						$url_parts = parse_url($location);
						#only made it work for http:// links. Any problem with this?
						$full_url = "http://$url_parts[host]";
						if(isset($url_parts['port'])){
							$full_url .= ":$url_parts[port]";
						}
						if($href{0} != '/'){ #it's a relative link on the domain
							$full_url .= dirname($url_parts['path']);
							if(substr($full_url, -1) != '/'){
								#if the last character isn't a '/', add it
								$full_url .= '/';
							}
						}
						$full_url .= $href;
					}
					return $full_url;
				}
			}
		}
		return false;
	}
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



function curldo($url){
	$curl_handle=curl_init();
	curl_setopt($curl_handle,CURLOPT_URL,$url);
	curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);
	$buffer = curl_exec($curl_handle);
	curl_close($curl_handle);
	return $buffer;
}



function rss_imageget($fulltext){
	$dom = new DOMDocument();
	$dom->loadXML($fulltext);
	$image = $dom->getElementsByTagName('image');
	if(!$head->length >0){
		return "";
	}else{
		$image = $image->item(0);
		$url = $image->getElementsByTagName('url');
		if($url->length >0){
			foreach ($url as $item) {
				if(strlen($item->nodeValue) > 0){
					return $item->nodeValue;
				}
				return '';
			}
		}else{
			return "";
		}
	}
}



function html_titleget($fulltext){
	$dom = new DOMDocument();
	@$dom->loadHTML($fulltext);
	$head = $dom->getElementsByTagName('head');
	if(!$head->length >0){
		return "";
	}else{
		$head = $head->item(0);

		$title = $head->getElementsByTagName('title');
		if($title->length >0){
			$title = $title->item(0)->nodeValue;
			return $title;
		}else{
			return "";
		}
	}
}



function rss_linkget($fulltext){
	$dom = new DOMDocument();
	$dom->loadXML($fulltext);
	$head = $dom->getElementsByTagName('link');
	echo "<br/>links now: ";
	foreach ($head as $item) {
		echo $item->nodeValue . "\n";
		if(strlen(trim($item->nodeValue)) > 0){
			$r = $item->nodeValue;
			return $r;
		}
	}
	return '';
}



function rss_hubget($fulltext){
	$dom = new DOMDocument();
	$dom->loadXML($fulltext);
	$head = $dom->getElementsByTagName('link');
	echo "<br/>hubs now: ";
	foreach ($head as $item) {
		if($item->hasAttribute("href")){
			echo $item->getAttribute("href");
			if($item->getAttribute("rel") == "hub"){
				return $item->getAttribute("href");
			}
		}
	}
	return '';
}

function rss_selfget($fulltext){
	$dom = new DOMDocument();
	$dom->loadXML($fulltext);
	$head = $dom->getElementsByTagName('link');
	echo "<br/>self now: ";
	foreach ($head as $item) {
		if($item->hasAttribute("href")){
			echo $item->getAttribute("href");
			if($item->getAttribute("rel") == "self"){
				return $item->getAttribute("href");
			}
		}
	}
	echo '<br/>no self found in links';
	
	$head = $dom->getElementsByTagName('status');
	echo "<br/>self now: ";
	foreach ($head as $item) {
		if($item->hasAttribute("feed")){
			return $item->getAttribute("feed");
		}
	}
	return '';
}



function whisperfollow_newfollow($examineurl){
	include('wp-admin/includes/bookmark.php');
	if(!preg_match('`(http|https)://.*`',$examineurl)){
	$examineurl = 'http://'.$examineurl;
	}
	$buffer = curldo($examineurl);
	if (empty($buffer)){
	echo "Error: Could not access ".$examineurl;
	}
	$hub = '';
	$image='';
	if(preg_match("`(\<![^\>]*\>|)\<(rss|atom|feed) `i",$buffer)){
		echo $examineurl." is a feed!";
		$followurl = rss_linkget($buffer);
		$hub = rss_hubget($buffer);
		$image = rss_imageget($buffer);
		$fulltext = curldo($followurl);
		$followtitle = html_titleget($fulltext);
		$followrss = $examineurl;


	}else{
		$discovery = getRSSLocation($buffer,$examineurl);
		if($discovery){
			$rssgot = curldo($discovery);
			$followurl = $examineurl;
			$followtitle = html_titleget($buffer);
			$followrss = $discovery;
			$hub = rss_hubget($rssgot);
			$image = rss_imageget($rssgot);
		}else{
			echo "there are no feeds here!";
			return;
		}

	}
	$linkdata = array(
	"link_url" => $followurl, // varchar, the URL the link points to
	"link_name"	=> $followtitle, // varchar, the title of the link
	"link_image" => $image, // varchar, a URL of an image
	"link_target" => '_blank', // varchar, the target element for the anchor tag
	"link_rss" => $followrss, // varchar, a URL of an associated RSS feed
	);
	$link_id = wp_insert_link( $linkdata );
	if(strlen($hub) > 0){
		whisperfollow_subscribe_to_push($followrss,$hub);
	}
	
	if($link_id <1){
		echo "there was a problem adding the link";
	}else{
		echo "subscribed to ".$followtitle."!";
	}
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


function whisperfollow_pubsub_http($url, $post_string) {
        
	// add any additional curl options here
	$options = array(CURLOPT_URL => $url,
					 CURLOPT_USERAGENT => "PubSubHubbub-Subscriber-PHP/1.0",
					 CURLOPT_RETURNTRANSFER => true);
					 
	if ($post_string) {
		$options[CURLOPT_POST] = true;
		$options[CURLOPT_POSTFIELDS] = $post_string;
	}

	$ch = curl_init();
	curl_setopt_array($ch, $options);

	$response = curl_exec($ch);
	$info = curl_getinfo($ch);
	whisperfollow_log("PuSH Request (".$url."): ".print_r($options,true));

	whisperfollow_log("PuSH Response: ".$response."<br/>\n".print_r($info,true));
	// all good -- anything in the 200 range 
	if (substr($info['http_code'],0,1) == "2") {
		return $response;
	}
	return false;   
}
	
function whisperfollow_pubsub_change_subscription($mode, $topic_url, $hub) {
	if (!isset($topic_url))
		throw new Exception('Please specify a topic url');
		
	 // lightweight check that we're actually working w/ a valid url
	 if (!preg_match("|^https?://|i",$topic_url)) 
		throw new Exception('The specified topic url does not appear to be valid: '.$topic_url);
	
	// set the mode subscribe/unsubscribe
	$data = array("hub.mode"=>$mode,"hub.callback"=>site_url().'/index.php?wfpushend=endpoint',"hub.verify"=>"sync","hub.topic"=>$topic_url);
	//$data = "hub.mode=".$mode."&hub.callback=".urlencode(site_url()).'/index.php?wfpushend=true'."&hub.topic=".urlencode($topic_url);
	
	// make the http post request and return true/false
	// easy to over-write to use your own http function
	whisperfollow_pubsub_http($hub,$data);
}

	
	
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
		if(strlen($bookmark->link_rss)>0&&rand(0,count($bookmarks))<100){
			whisperfollow_log('<br/>checking '.$bookmark->link_name);
			$feed_uris[] = $bookmark->link_rss;
		}
	}
	
	whisperfollow_aggregate($feed_uris);

}

function whisperfollow_aggregate($feeds,$pushed=false){
	whisperfollow_log("Aggregating feeds:".count(feeds));
	if ( !empty( $feeds ) ) {
		
		//whisperfollow_log('<br/>feeds: '.print_r($feeds,true));
		
	
		add_filter( 'wp_feed_cache_transient_lifetime', 'whisperfollow_feed_time' );
		$feed = whisperfollow_fetch_feed( $feeds );
		if(is_wp_error($feed)){
			whisperfollow_log($feed->get_error_message());
			trigger_error($feed->get_error_message());
			whisperfollow_log("Feed read Error: ".$feed->get_error_message());
		}
		$feed->enable_cache(false);
		$feed->strip_htmltags(false);   
		
		//whisperfollow_log("<br/>Feed object:");
		//whisperfollow_log(print_r($feed,true));
		$items = $feed->get_items();

		//whisperfollow_log(substr(print_r($items,true),0,500));
		//whisperfollow_log("<br/>items object:");
		usort($items,'date_sort');
		foreach ($items as $item){
			try{
				whisperfollow_log("<br/>got ".$item->get_title()." from ". $item->get_feed()->get_title()."<br/>");
				add_whisper($item->get_permalink(),$item->get_title(),html_entity_decode ($item->get_description()),$item->get_feed()->get_title(),$item->get_feed()->get_link(),$item->get_date("U"));
			}catch(Exception $e){
				whisperfollow_log("Exception occured: ".$e->getMessage());
			}
		}
		
		remove_filter( 'wp_feed_cache_transient_lifetime', 'whisperfollow_feed_time' );
	}
	whisperfollow_log('No feed defined');
	
}

function whisperfollow_fetch_feed($url) {

	require_once (ABSPATH . WPINC . '/class-feed.php');

	$feed = new SimplePie();
	if(is_array($url)||preg_match("`^http://.*?`",$url)){
		whisperfollow_log("Url is array");
		$feed->set_feed_url($url);
		$feed->set_cache_class('WP_Feed_Cache');
		$feed->set_file_class('WP_SimplePie_File');
		$feed->set_cache_duration(30);
		$feed->enable_cache(false);
		$feed->set_useragent('Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.77 Safari/535.7');//some people don't like us if we're not a real boy
	}else{
	
		whisperfollow_log("Url is not array");
		$feed->set_raw_data($url);
	}
	$feed->init();
	$feed->handle_content_type();
	
	//whisperfollow_log("Feed:".print_r($feed,true));

	if ( $feed->error() )
		$errstring = implode("\n",$feed->error());
		//if(strlen($errstring) >0){ $errstring = $feed['data']['error'];}
		if(stristr($errstring,"XML error")){
			whisperfollow_log('simplepie-error-malfomed: '.$errstring.'<br/><code>'.htmlspecialchars ($url).'</code>');
		}elseif(strlen($errstring) >0){
			whisperfollow_log('simplepie-error: '.$errstring);
		}else{
			//whisperfollow_log('simplepie-error-empty: '.print_r($feed,true).'<br/><code>'.htmlspecialchars ($url).'</code>');
		}
	return $feed;
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