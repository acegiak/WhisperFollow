<?php
/*
    Plugin Name: WhisperFollow
    Plugin URI: http://acegiak.machinespirit.net/2012/01/25/whisperfollow/
    Description: Follow and reblog multiple sites with simplepie RSS
    Version: 2.0.0
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
    GNU General Public License for more details.o
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/

/*
 MF2 Parser by Barnaby Walters: https://github.com/indieweb/php-mf2
*/

//namespace WhisperFollow;

require  plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once (ABSPATH . WPINC . '/class-feed.php');


use BarnabyWalters\Mf2  as BWMF2;;


    global $whisperfollow_db_version;
$whisperfollow_db_version = "1.0";

$bookmarkLibrary = array();

function date_sort($a,$b) {
			$ad = (int)$a->get_date('U');
			$bd = (int)$b->get_date('U');
              if ($ad == $bd) {
        return 0;
    }
    return ($ad > $bd) ? -1 : 1;
}

function whisperfollow_log($message,$verbose=true){
	error_log($message);
	return $message;

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
	return message;
}

register_activation_hook( __FILE__, 'whisperfollow_install' );
register_activation_hook(__FILE__,'whisperfollow_createtable');
register_activation_hook(__FILE__,'whisperfollow_installdata');

register_deactivation_hook( __FILE__, 'whisperfollow_deactivate' );

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


function whisperfollow_install() {
	// Activates the plugin and checks for compatible version of WordPress 
	if ( version_compare( get_bloginfo( 'version' ), '2.9', '<' ) ) {
		deactivate_plugins ( basename( __FILE__ ));     // Deactivate plugin
		wp_die( "This plugin requires WordPress version 2.9 or higher." );
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
		whisperfollow_log("followpage already exists, check it contains the shortcode");
	}
}

function whisperfollow_curlthings($urls){
	$ret = "";
	// We will download info about 2 YouTube videos:
	// http://youtu.be/XmSdTa9kaiQ and
	// http://youtu.be/6dC-sm5SWiU

	// Init queue of requests
	$queue = new \cURL\RequestsQueue;
	// Set default options for all requests in queue
	$queue->getDefaultOptions()
	    ->set(CURLOPT_TIMEOUT, 5)
	    ->set(CURLOPT_RETURNTRANSFER, true);
	// Set function to be executed when request will be completed
	$queue->addListener('complete', function (\cURL\Event $event) {
		whisperfollow_handleCurlComplete($event);
	});

	foreach($urls as $url){
		$request = new \cURL\Request($url);
		
		$queue->attach($request);
	}


	// Execute queue
	while ($queue->socketPerform()) {
	    //echo  '*';
	    $queue->socketSelect();
	}
	return $ret;
}

function whisperfollow_handleCurlComplete($event){
	global $bookmarkLibrary;
	$response = $event->response;
	//$json = $response->getContent(); // Returns content of response
	//$feed = json_decode($json, true);
	//error_log("CURLRESPONSE:".print_r($response,true) . "\n");

	$data = array_values($event->request->getOptions()->toArray());
	//error_log("CURL COMPLETE:".print_r($data[0],true));

	$bookmark = $bookmarkLibrary[$data[0]];
	if(strlen($bookmark->link_rss)>0){
		whisperfollow_handleRSSATOM($response->getContent(),$bookmark);
	}else{
		whisperfollow_handleMF2($response->getContent(),$bookmark);
	}
}

function whisperfollow_handleMF2($feedcontent,$bookmark){
	$page = $bookmark->link_url;
	whisperfollow_log("<br/>MF2 Parsing ".$page."<br/>");

	//error_log("MF2 Parsing ".$page);
	try{
		//$mfhtml = curldo($page);
		//error_log("MF2HTML".$page.": ".print_r($mfhtml,true));
		$output = MF2\parse($feedcontent,$page);
		//error_log("MF2".$page.": ".preg_replace("`\s+`"," ",print_r($output,true)));
		
		$feeditem = BWMF2\findMicroformatsByType($output,'h-feed',true);
		$children = BWMF2\findMicroformatsByType($output,'h-entry',true);

		foreach($children as $child){
				$citation = $child['properties']['in-reply-to'][0];
				$content = $child['properties']['content'][0]['html'];
				if(isset($citation['properties']) && isset($citation['properties']['content'])){
					if(is_array($citation['properties']['content'])){
						$content = '<div class="p-in-reply-to h-cite"><blockquote class="p-content">'.$citation['properties']['content'][0]['html'].'</blockquote>Reblogged from <a href="'.$citation['properties']['url'][0].'" class="u-url">'.$citation['properties']['name'][0].'</div>'.$content;
					}
				}
				whisperfollow_log("<br/>got ".$child['properties']['name'][0]." from ".$bookmark->link_name."<br/>");
				//error_log("MF2: got ".$child['properties']['name'][0]." from ".$bookmark->link_name."");

				add_whisper($child['properties']['url'][0],$child['properties']['name'][0],$content,$bookmark->link_name,$feeditem['properties']['url'][0]?:$page,date('U',strtotime($child['properties']['published'][0])),$bookmark->link_image);
			
			
		}
	}catch(Exception $e){
		whisperfollow_log("Exception occured: ".$e->getMessage());
		//error_log("MF2 parsing Exception occured: ".$e->getMessage());
	}
		
}



function whisperfollow_handleRSSATOM($feedcontent,$bookmark){
	$feed = new SimplePie();
	$feed->set_raw_data($feedcontent);
	$feed->init();
	$feed->handle_content_type();

	if ( $feed->error() ){
		$errstring = $feed->error();
		if(stristr($errstring,"XML error")){
			whisperfollow_log('simplepie-error-malfomed: '.$errstring.'<br/><code>'.htmlspecialchars ($url).'</code>');
		}elseif(strlen($errstring) >0){
			whisperfollow_log('simplepie-error: '.$errstring);
		}
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
			$content = html_entity_decode ($item->get_description());
				foreach ($item->get_enclosures() as $enclosure)
				{
					if(strlen($enclosure->get_link()) > 0 && strlen($enclosure->get_type()) > 0){
						if(stristr($enclosure->get_type(),"audio")){
						$content .= "<p><audio controls><source src=\"".$enclosure->get_link()."\" type=\"".$enclosure->get_type()."\" autoplay=\"false\" preload=\"none\"></audio> - <a href=\"".$enclosure->get_link()."\">LINK</a></p>";
						}else if(stristr($enclosure->get_type(),"video")){
						$content .= "<p><video controls><source src=\"".$enclosure->get_link()."\" type=\"".$enclosure->get_type()."\" autoplay=\"false\" preload=\"none\"></video> - <a href=\"".$enclosure->get_link()."\">LINK</a></p>";
						}else{
						$content .= "<p><embed src=\"".$enclosure->get_link()."\" type=\"".$enclosure->get_type()."\" autoplay=\"false\" preload=\"none\"> - <a href=\"".$enclosure->get_link()."\">LINK</a></p>";
						}
					}
				}
			add_whisper($item->get_permalink(),$item->get_title(),$content,$bookmark->link_name,$bookmark->link_url,$item->get_date("U"),$bookmark->link_image);
		}catch(Exception $e){
			whisperfollow_log("Exception occured: ".$e->getMessage());
		}
	}
}


function whisperfollow_aggregator( $args = array() ) {
	global $bookmarkLibrary;
	whisperfollow_log("aggregation!");
	$bookmarks = get_bookmarks( array(
	'orderby'        => 'name',
	'order'          => 'ASC',
	'category_name'  => 'Blogroll'
	));
	$feed_uris = array();
	while(rand(0,count($bookmarks)/10)>0){
		$bookmark = $bookmarks[rand(0,count($bookmarks))];
		
			if(strlen($bookmark->link_rss)>0){
				whisperfollow_log('<br/>checking '.$bookmark->link_name);
				$bookmarkLibrary[$bookmark->link_rss] = $bookmark;
				whisperfollow_curlthings( array($bookmark->link_rss));
			}else{
				whisperfollow_log('<br/>checking '.$bookmark->link_name);
				$bookmarkLibrary[$bookmark->link_url] = $bookmark;
				whisperfollow_curlthings( array($bookmark->link_url));
			}
		
	}
	

}




add_filter( 'cron_schedules', 'whisperfollow_cron_definer' );


function whisperfollow_cron_definer($schedules){

	$schedules['fivemins'] = array(
		'interval'=> 300,
		'display'=>  __('Once Every 5 Minutes')
	);

	return $schedules;
}



add_action( 'wp', 'whisperfollow_setup_schedule' );
/**
 * On an early action hook, check if the hook is scheduled - if not, schedule it.
 */
function whisperfollow_setup_schedule() {
	if ( ! wp_next_scheduled( 'whisperfollow_generate_hook' ) ) {
		wp_schedule_event( time(), 'fivemins', 'whisperfollow_generate_hook');
	}
}


add_action( 'whisperfollow_generate_hook', 'whisperfollow_aggregator' );
function whisperfollow_feed_time() { return 300; }


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
		
			whisperfollow_log("could not insert whisper into database! ".$permalink." : ".$title);
			//die("could not insert whisper into database!");
		}else{
			whisperfollow_log("added ".$title." from ".$authorurl);
		}
	}else{
		whisperfollow_log("duplicate detected");
	}

}



function whisperfollow_page( $atts ) {
//whisperfollow_aggregator();
	return whisperfollow_ajax_display();
}
add_shortcode( 'whisperfollow_page', 'whisperfollow_page' );

function whisperfollow_api_init() {
	global $whisperfollow_api_whisper;

	$whisperfollow_api_whisper = new WhisperFollow_API_Whisper();
	$whisperfollow_api_follow = new WhisperFollow_API_Follow();
	add_filter( 'json_endpoints', array( $whisperfollow_api_whisper, 'register_routes' ) );
	add_filter( 'json_endpoints', array( $whisperfollow_api_follow, 'register_routes' ) );
}
add_action( 'wp_json_server_before_serve', 'whisperfollow_api_init' );

class WhisperFollow_API_Whisper {
	public function register_routes( $routes ) {
		$routes['/whisperfollow/whispers'] = array(
			array( array( $this, 'get_posts'), WP_JSON_Server::READABLE ),
		);
		$routes['/whisperfollow/whispers/(?P<id>\d+)'] = array(
			array( array( $this, 'get_post'), WP_JSON_Server::READABLE ),

		);

		// Add more custom routes here

		return $routes;
	}
	public function get_posts( $filter = array(), $context = 'view', $type = 'post', $page = 1, $offset = 0, $search = "") {
		global $wpdb; 
		$length = 10;
		$where = "";
		if($offset > 0){
			$where = "where `id` <= ".$offset;
		}
		if(strlen($search) > 0){
			if(strlen($where) <= 0){
				$where = "where";
			}else{
				$where .= " and";
			}
			$where .= " (`authorname` like '%".$search."%' or `authorurl` like '%".$search."%' or `content` like '%".$search."%')";
		}
		$query = 'SELECT * FROM  `'.$wpdb->prefix . 'whisperfollow` '.$where.' ORDER BY  `time` DESC LIMIT '.(($page-1)*$length).' , '.$length.';';
		error_log("Json query: ".$query);
		$items = $wpdb->get_results(
			$query
		);
		return $items;
	}
	public function get_post( $id, $context = 'view' ) {
		global $wpdb; 
		$length = 10;
		$where = "";
		$items = $wpdb->get_row(
		'SELECT * 
		FROM  `'.$wpdb->prefix . 'whisperfollow` WHERE `id` = '.$id.';'
		);
		return $items;
	}

	// ...
}

class WhisperFollow_API_Follow {
	public function register_routes( $routes ) {
		$routes['/whisperfollow/follows'] = array(
			array( array( $this, 'new_post'), WP_JSON_Server::CREATABLE | WP_JSON_Server::ACCEPT_JSON )
		);

		// Add more custom routes here

		return $routes;
	}
	public function new_post( $data ) {

	require_once('wp-admin/includes/bookmark.php');

		$notes = array();
		if(isset($data['twitter'])){
			$notes['twitter'] = $data['twitter'];
		}
		if(isset($data['tumblr'])){
			$notes['tumblr'] = "<a href='http://".$data['tumblr'].".tumblr.com'>".$data['tumblr']."</a>";
		}
		$data['link_notes'] = json_encode($notes);
		if(isset($data['link_id'])){
			unset($data['link_id']);
		}
		if(!isset($data['link_url'])){
			return whisperfollow_log("error: no url defined for new follow");
		}
		$lookup =  WFCore_newfollow($data['link_url']);

		$data['link_url']  = $lookup['link_url'];
		
		if(!isset($data['link_name']) && isset($lookup['link_name'])){
			$data['link_name']  = $lookup['link_name'];
		}
		if(!isset($data['link_rss']) && isset($lookup['link_rss'])){
			$data['link_rss']  = $lookup['link_rss'];
		}
		if(!isset($data['link_image']) && isset($lookup['link_image'])){
			$data['link_image']  = $lookup['link_image'];
		}
		
		$link_id = wp_insert_link( $data );

	
		if($link_id <1){
			$r =  json_ensure_response(whisperfollow_log( "there was a problem adding the link", false));
			
			$r->set_status( 400 );
			return $r;
		}else{
			whisperfollow_log( "added new follow: ".$data['link_name'], false);
			$r =  json_ensure_response($data);
			
			$r->set_status( 201 );
			return $r;
		}
	}

	// ...
}
function whisperfollow_ajax_display(){
return <<<'EOD'

<div class="whispercontrols"><label for="whispersearch">Search:</label><input type="text" name="whispersearch" id="whispersearch"><br/><label for="whisperpage">Page:</label><input type="text" name="whisperpage" id="whisperpage"><br/><form target="" method="POST">
<!--New Follow: <input type="TEXT" name="follownewaddress"><br>Search:<input type="TEXT" name="followsearch"><input type="SUBMIT" value="go"><br>
<input type="submit" name="forcecheck" value="forcecheck"></form>
<input type="button" value="new" onclick="toggleNewFollow()">--!>
<div id="whispernewfollow">
<label for="whispernewurl">url*:</label>
<input type="text" id="whispernewurl" name="whispernewurl">
<label for="whispernewname">name:</label>
<input type="text" id="whispernewname" name="whispernewname">
<label for="whispernewrss">rss:</label>
<input type="text" id="whispernewrss" name="whispernewrss">
<label for="whispernewicon">icon:</label>
<input type="text" id="whispernewicon" name="whispernewicon">
<label for="whispernewtwitter">twitter:</label>
<input type="text" id="whispernewtwitter" name = "whispernewtwitter">
<label for="whispernewtumblr">tumblr:</label>
<input type="text" id="whispernewtumblr" name = "whispernewtumblr">
<input type="button" onclick="newfollow()" value="follow"><div id="newfollowstatus"></div>
</div>
</div>

<div id="wfd"></div>
<input type="button" id="wfdnext" value="load" onclick="dothething()"><code>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
<script>
function toggleNewFollow(){
	$("#whispernewfollow").toggle();
}
function newfollow(){
                var content = {	}
		
		if($("#whispernewurl").val().length >0){
			content['link_url'] = $("#whispernewurl").val();
		}
		if($("#whispernewname").val().length >0){
			content['link_name'] = $("#whispernewname").val();
		}
		if($("#whispernewicon").val().length >0){
			content['link_image'] = $("#whispernewicon").val();
		}
		if($("#whispernewrss").val().length >0){
			content['link_rss'] = $("#whispernewrss").val();
		}
		if($("#whispernewtwitter").val().length >0){
			content['twitter'] = $("#whispernewtwitter").val();
		}
		if($("#whispernewtumblr").val().length >0){
			content['tumblr'] = $("#whispernewtumblr").val();
		}

	
						
		var options = {
						data:JSON.stringify(content),
						url:'../wp-json/whisperfollow/follows',
						type:'POST',
						dataType:'json',
						contentType: 'application/javascript',
						complete:function(xhr,status){
							console.log(xhr);
							if(status == "success"){
								$("#newfollowstatus").append("<p>Subscribed to: "+xhr['responseJSON']['link_name']+"</p>");
							}else{
								$("#newfollowstatus").append("<p>An error occured</p>");
							}
							console.log(status);

						}
					}
		$.ajax(options);
}
function htmlEncode(value){
  //create a in-memory div, set it's inner text(which jQuery automatically encodes)
  //then grab the encoded contents back out.  The div never exists on the page.
  return $('<div/>').text(value).html();
}

function htmlDecode(value){
  return $('<div/>').html(value).text();
}
function safety(instr){
	var mini = $('<div>'+instr+"</div>");
	$('script',mini).wrapAll('<div class="scriptescaper scriptescaped">');
	$('div.scriptescaper',mini).html(function(){var v = htmlEncode($(this).html());console.log(v); return v;});
	return mini.html();
}

function dothething(){
        if(typeof window.whispersloading === "undefined" || window.whispersloading == false){
        whispersloading = true;
        $("#wfdnext").val("loading...");
        if(typeof window.whisperpagecount === "undefined"){
            window.whisperpagecount = 0;
        }
        if(typeof window.listedwhispers === "undefined"){
            window.listedwhispers = [];
        }
        if(typeof window.whisperoffset === "undefined"){
            window.whisperoffset = "";
        }
        if(typeof window.whispersearchterm === "undefined"){
            window.whispersearchterm = "";
        }
	if($("#whispersearch").val().length >0){
		window.whispersearchterm = "&search="+encodeURIComponent($("#whispersearch").val());
	}
        window.whisperpagecount += 1;
        console.log("button were pressed "+window.whisperpagecount.toString());
        $.getJSON( "../wp-json/whisperfollow/whispers?page="+window.whisperpagecount.toString()+window.whisperoffset+window.whispersearchterm, function( data ) {
           var tempmax = 0;
           $.each( data, function( key, val ) {
             if(window.listedwhispers.indexOf(val.permalink) < 0){
                 $("#wfd").append("<div id='whisper"+val.id+"' class='whisper'><div class='whispertitle'><a class='whisperpermalink' href='"+val.permalink+"'>"+val.title+"</a></div><div class='whispercontent'>"+safety(val.content)+"</div><div>Source: <a class='whisperauthor' href='"+val.authorurl+"' alt='"+val.authorname+"'><img class='whisperauthorav' src='"+val.authoravurl+"'> "+val.authorname+"</a><span class='whispertime'>"+val.time+"</span></div><input type='button' onclick='reblog("+val.id+")' value='reblog'></div>" );
		console.log("id check:"+val.id.toString());
                 if(val.id > tempmax){
                     tempmax = val.id;
			console.log("set to new max");
                 }
                 window.listedwhispers[window.listedwhispers.length] = val.permalink;
             }
           });
           blockQuoteExpander();
           if(window.whisperoffset == ""){
               window.whisperoffset = "&offset="+tempmax.toString();
           }
           window.whispersloading = false;
           $("#wfdnext").val("load more");

		
	$("div.scriptescaper").dblclick(function(){$(this).removeClass("scriptescaped");$(this).addClass("scriptunescaped");$(this).html(function(){return $("<div/>").html($(this).html()).text();});});
        });
    }

}
win = $(window),
doc = $(document);
doc.ready(function(){
	$("#whispersearch").on('input propertychange paste', function() {
		$("#wfd").empty();

		if($("#whispersearch").val().length <= 0){
			window.whispersearchterm = "";
		}
		window.whisperpagecount = 0;
           	window.listedwhispers = [];
           	$("#wfdnext").val("load");
	});
	$("#whisperpage").on('input propertychange paste', function() {
		$("#wfd").empty();
		window.whisperpagecount = parseInt($("#whisperpage").val())-1;
           	window.listedwhispers = [];
           	$("#wfdnext").val("load");
	});


	$("#whispernewfollow").hide();
});
win.scroll(function(){
    if( isScrolledIntoView($("#wfdnext")) ) {
        dothething();
    }
});

function isScrolledIntoView(elem)
{
    var docViewTop = $(window).scrollTop();
    var docViewBottom = docViewTop + $(window).height();

    var elemTop = $(elem).offset().top;
    var elemBottom = elemTop + $(elem).height();

    return ((elemBottom <= docViewBottom) && (elemTop >= docViewTop));
}

function reblog(id){
    window.reblogchild = window.open("../wp-admin/post-new.php","newpostwindow","height=768,width=1024,scrollbars=1");
    $(window.reblogchild).load(function() {

        console.log("window ready");

	var whispertitle = $("#whisper"+id+" .whispertitle").text();
	whispertitle = whispertitle.substring(0, 125)+(whispertitle.length > 125?"...":"");
        $("#title",window.reblogchild.document).val(whispertitle);
        $('#cite_name',window.reblogchild.document).val(whispertitle);
        $('#cite_summary',window.reblogchild.document).val($("#whisper"+id+" .whispercontent").html());
        $('#cite_url',window.reblogchild.document).val($("#whisper"+id+" .whisperpermalink").attr("href"));
        $('#author_name',window.reblogchild.document).val($("#whisper"+id+" .whisperauthor").text());
        $('#author_url',window.reblogchild.document).val($("#whisper"+id+" .whisperauthor").attr("href"));
        $('#author_photo',window.reblogchild.document).val($("#whisper"+id+" .whisperauthorav").attr("src"));
        $("#taxonomy-kind li label:contains('Repost') input",window.reblogchild.document).prop('checked', true);
        $("#categorychecklist li label:contains('whispers') input",window.reblogchild.document).prop('checked', true);
   });
}
</script></code>



EOD;

}
?>