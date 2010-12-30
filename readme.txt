=== Content Scheduler ===
Contributors: freakingid
Donate link: http://structureweb.co/donate/
Tags: automation, expire, expires, expiring, notification, notify, schedule, scheduling, sticky
Requires at least: 2.9
Tested up to: 3.0.4
Stable tag: 0.9.1

Schedule Posts and Pages to automatically expire and change at a certain time, and provide notification of expiration.

== Description ==

Content Scheduler lets you schedule Posts and Pages to automatically expire at a certain time.

= Expiration Options =

You control what happens upon expiration, including:

* Change status to Pending, Draft, or Private
* Unstick Posts
* Change Categories
* Move to the Trash

= Notification Options =

Content Scheduler can also notify you:

* When expiration occurs
* A specific number of hours before expiration occurs

This reminder helps you keep content fresh, providing a reminder that content is out of date and needs updated or replaced. Content Scheduler lets you use notification tools without making any changes to content upon expiration, if you'd like.

== Installation ==

To install Content Scheduler:

1. Upload the "content-scheduler" directory and all its contents to your `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Visit the 'Content Scheduler' options through the 'Settings' menu.

== Frequently Asked Questions ==

= Where did you get the datepicker used in this plugin? =

That's the ["Any+Time" date / time picker:](http://www.ama3.com/anytime/) 

= Why isn't the datepicker showing up for me? =

1. Make sure you have enabled the datepicker in the plugin's settings.
1. You may have another plugin installed that uses JavaScript in the backend that conflicts with the Any+Time datepicker. Try disabling other plugins, one at a time, and checking for the datepicker working.

= Does Content Scheduler work with Network / Multisite installations? =

Maybe. All the code is in there to make it work, but we need to do more testing before claiming it is working. This is the main reason the plugin is currently at version 0.9 instead of 1.0.

= My content doesn't seem to be expiring. What should I do? =

1. Check the plugin setting's "expiration period" and make sure you have waited at least that long before checking your content for expiration.
1. Make sure you have actually visited a page on your website after the post's expected expiration date. WordPress only fires off scheduled tasks when people actually visit the site. Continuing to refresh in the backend only to see if things have changed will not work.
1. Check your WordPress installation Timezone, and use one of the timezone strings. That is, when set to "UTC -6," our testing team found WordPress was going to wait several hours before beginning to check schedules. However, setting timezone to "America/Chicago" (the same timezone) fixed the problem. We're still checking on the reason for this.
1. Try simply deactivating the plugin and reactivating it, then testing again.

== Screenshots ==

1. The Content Scheduler options screen, where you determine what happens when the expiration date is reached.
2. Content Scheduler can optionally display the expiraton date and status in a column where your Posts and Pages are listed.
3. Scheduling content expiration uses a small, unobtrusive box on your Post and Pages edit screens.

== Changelog ==

= 0.9.1 =
* Added the "Expiration period" option on the settings screen. This allows users to tell WordPress how often Content Scheduler expiration times should be checked.

= 0.9 =
* First public release.

== Upgrade Notice ==

= 0.9.1 =
* Added the "Expiration period" option on the settings screen. This allows users to tell WordPress how often Content Scheduler expiration times should be checked.

= 0.9 =
Version 0.9 is the first public release.
