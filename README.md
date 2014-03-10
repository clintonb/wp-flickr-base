wp-flickr-base
==========

This plugin adds shortcodes for retrieving a single photo or a set of photos from Flickr.

Configuration
-----

1. Install the contents into your plugins directory.
2. Activate the plugin.
3. You should now see a *Flickr Options* settings page.  Enter your Flicker API Key/Secret here.


Usage
-----

Shortcode     | Action        | Example    | Demo
------------- | ------------- | ---------- | ------
flickr-download-gallery  | Displays a grid of photos, each with a download link. Clicking on a photo enters a slideshow. | [flickr-download-gallery id="12345"] | http://clintonblackburn.com/clients/joemmys-shots/
flickr-photo  | Displays a single photo | [flickr-photo id="12345"] | --
flickr-photoset | Displays a grid of photos. Clicking on a photo enters a slideshow. | [flickr-photoset id="12345"] |  http://clintonblackburn.com/san-francisco/
flickr-photoset-fullscreen | Displays a set of photos as a fullscreen slideshow. | [flickr-photoset-fullscreen id="12345"] | http://clintonblackburn.com/
