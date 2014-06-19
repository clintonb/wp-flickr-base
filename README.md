wp-flickr-base
==========

This plugin adds shortcodes for retrieving a single photo or a set of photos from Flickr.

Configuration
-----

1. Create a new Flickr app at https://www.flickr.com/services/ and set your callback URL to the URL of your site's admin section (e.g. http://clintonblackburn.com/wp-admin/)
2. Install the contents into your plugins directory.
3. Activate the plugin.
4. You should now see a *Flickr Options* settings page.  Enter your Flicker API Key/Secret here.


Usage
-----

Shortcode     | Action        | Example    | Demo
------------- | ------------- | ---------- | ------
flickr-download-gallery  | Displays a grid of photos, each with a download link. Clicking on a photo enters a slideshow. | [flickr-download-gallery id="12345"] | http://clintonblackburn.com/clients/joemmys-shots/
flickr-photo  | Displays a single photo | [flickr-photo id="12345"] | --
flickr-photoset | Displays a grid of photos. Clicking on a photo enters a slideshow. | [flickr-photoset id="12345"] |  http://clintonblackburn.com/camelopardalid-meteor-shower/
flickr-photoset-fullscreen | Displays a set of photos as a fullscreen slideshow. | [flickr-photoset-fullscreen id="12345"] | http://clintonblackburn.com/
