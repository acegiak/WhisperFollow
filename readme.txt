=== Plugin Name ===
Contributors: acegiak
Donate link: http://acegiak.machinespirit.net/2012/01/25/whisperfollow/
Tags: rss,federation,social,reblog,aggregation
Requires at least: 2.0.2
Tested up to: 3.5
Stable tag: trunk

Use your wordpress blog to aggregate and reblog all friends and sites from across the web.

== Description ==

WhisperFollow turns your wordpress blog into a federated social web client.
In it's current form it aggregates RSS feeds in a page on your blog called "following" which it creates.
The links it aggregates are the ones from your blogroll with rss feed data.
Reblogs are automatically in the "whispers" category which can be excluded from pages if you like using plugins like [Simply-Exclude](http://wordpress.org/extend/plugins/simply-exclude/).

This plugin depends on the plugins indieweb-custom-taxonomy and json-api If you havent got them, you'll have issues.
== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `WhisperFollow.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Navigate To yourblog/following

== Frequently Asked Questions ==

= Where do I see everything? =

This plugin creates the page "following" so for the blog "foo.com/bar" the whisperfollow page would be at "foo.com/bar/following".
The page is initally private but if you open it in the page editor and make it public you can add it to menus and things.

= How do I add links? =

You can add things to your blogroll using the links editor in the Wordpress admin section.


== Changelog ==

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