=== Blogger.com publisher ===
Tags: replication, blogger.com, publishing
Requires at least: 2.6
Stable tag: trunk
Contributors: GlenOgilvie
Tested up to: 2.7 bleeding

Publishes posts with specific tags and/or categories to blogger.com using the Zend gdata api.

== Description ==

Publishes posts with specific tags and/or categories to blogger.com using the Zend gdata api.

When active, this plugin publishes posts that you write or edit to blogger.com.  It is designed
to allow you to pick a set of categories, and / or tags that will be published to a remote blog.

I use this to publish blog on blogger.com that has only posts about the same topic on it. This is
ideal for me because I want to keep my main wordpress blog private, and low traffic, but still only
write my blog in one place, and keep a copy of all my posts on word press.

Blogger.com provides and API using Google's gdata protocol, this plugin publishes via the gdata php
library provided by the Zend framework.  It uses your google username and password to authenticate.

== Installation ==

1. Upload the Zend directory into your php include path, or your '/wp-content/plugins' directory
2. Upload `gdatablogger.php` to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure the plugin from "Settings->blogger.com" 


== Frequently Asked Questions ==

= How do I know my blogger.com Blog ID =

Go to your blogger.com account, and choose the post option.  Look at the link / address of the page. You'll see it has a BlogID in the address.  The number is your blog ID.

== Screenshots ==

1. blogger.com configuration options.
