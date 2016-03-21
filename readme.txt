=== Plugin Name ===
Contributors: acegiak
Donate link: http://acegiak.machinespirit.net/2012/01/25/whisperfollow/
Tags: rss,federation,social,reblog,aggregation
Requires at least: 2.0.2
Tested up to: 3.5
Stable tag: trunk

Use your wordpress blog to aggregate and reblog all friends and sites from across the web.

== Description ==

WhisperFollow adds an indieweb compatible feed reader to your wordpress blog.

It uses your blogroll links as a subscription list. If you don't have the wordpress blogroll installed on your site use the Link Manager Plugin to do so: https://wordpress.org/plugins/link-manager/

If no RSS/Atom feed is provided for a link, WhisperFollow will attempt to parse the page itself looking for Microformats2 H-entry markup.

Whisperfollow creates a page called "following" which when viewed by a user with appropriate permissions displays entries from your aggregated feeds.

You will need composer installed on your system to install this plugin's dependencies.



== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Extract the WhisperFollow folder into `/wp-content/plugins/` directory
2. navigate to the WhisperFollow folder
3. run composer install
4. Activate the plugin through the 'Plugins' menu in WordPress
5. Navigate To yourblog/following

== Frequently Asked Questions ==

= Where do I see everything? =

This plugin creates the page "following" so for the blog "foo.com/bar" the whisperfollow page would be at "foo.com/bar/following".
The page is initally private but if you open it in the page editor and make it public you can add it to menus and things.

= How do I add links? =

You can add things to your blogroll using the links editor in the Wordpress admin section which is recommended.


== Changelog ==

= 2.0.0 =
* refactored everything back into one file
* using composer for dependencies now!
* polling should now be asynchrounous and non-blocking
* pubsubhubbub support and quick follow are dead. at least for now.

= 1.5.1 =
* readded quick follow function

= 1.5.0 =
* added support for attached media in streams
* added search function
* added page skip functions

= 1.4.0 =
* Major Rejiggering to the interface. Now with fancy new infinite scrolling!
* The metadata for reblogs etc has also changed to align with some vague indieweb conspiracy
* now has plugin dependencies indieweb-custom-taxonomy and json-api so get those

= 1.3.0 =
* Many minor bugfixes
* Added Reply-Context metadata to reblog whispers
* Added support for reading MF2 pages as part of ongoing effort to make plugin indieweb friendly

= 1.2.4 =
* Fixed the error preventing whispers to actually be added to the wall created from last bugfix
= 1.2.3 =
* Fixed installation errors created by the logging function >.<

= 1.2.2 =
* Improved Stability and changed logging to Fixed length FIFO to avoid explosions

= 1.2.1 =
* Bugfix. Items from PuSH updates now actually get sent to aggregation


= 1.2.0 =
* PubSubHubbub Subscription robustified. Should now autodetect and subscribe to PuSH hubs.

= 1.1.3 =
* Fixed logging capability.

= 1.1.2 =
* Fixed bugs from wordpress 3.5 preventing aggregation from occurring

= 1.1.1 =
* Fixed new scheduling bug

= 1.1.0 =
* Fixed links for installs without permalinks
* Added test pubsubhubbub subscription!

= 1.0.2 =
* Bugfix to create the proper scheduler time! Now checks for updates in five minute intervals!

= 1.0.1 =
* Bugfix to actually register the update hook with scheduler.

= 1.0 =
* Initial Release

== Upgrade Notice ==

= 1.1.1 =
* Fixed new scheduling bug

= 1.1.0 =
* Fixed links for installs without permalinks
* Added test pubsubhubbub subscription!

= 1.0.2 =
* Bugfix to create the proper scheduler time! Now checks for updates in five minute intervals!

= 1.0.1 =
Now the scheduler works!(hourly) Soon it will work more regularly!