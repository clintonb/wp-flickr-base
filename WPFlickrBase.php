<?php
/*
Plugin Name: wp-flickr-base
Plugin URI: https://github.com/clintonb/wp-flickr-base
Description: Basic Flickr API accessor and slideshow generator
Version: 0.1.0
Author: Clinton Blackburn
Author URI: https://clintonblackburn.com
License: GPL2
*/

function getItem($array, $key, $default = "")
{
    return isset($array[$key]) ? $array[$key] : $default;
}

if (!class_exists('WPFlickrBase')) {
    require_once('FlickrWrapper.php');

    class WPFlickrBase
    {
        protected FlickrWrapper $fw;
        static protected string $option_name = 'wp-flickr-base';
        static protected string $table_name = 'flickr_wrapper_cache';
        static protected string $options_page_name = 'wp_flickr_base_options';
        static protected string $cache_cleared_qs_param = 'cache_cleared';

        function __construct()
        {
            // Get the API credentials from the database
            $options = $this->get_option();
            $api_key = $options['flickr_api_key'];
            $api_secret = $options['flickr_api_secret'];
            $userId = $options['flickr_api_user_id'];
            $accessToken = $options['flickr_api_access_token'];
            $accessTokenSecret = $options['flickr_api_access_token_secret'];

            // Initialize the Flickr API accessor
            $this->fw = new FlickrWrapper($api_key, $api_secret, $userId, $accessToken, $accessTokenSecret);

            // Enable caching
            $connection = self::get_connection();
            $table = self::get_cache_table();
            $this->fw->cache_enable($connection, $table, 600);

            // Add shortcode handlers
            $this->add_shortcodes();

            // Add filters
            $this->add_filters();

            // Register, and enqueue, scripts and styles
            add_action('wp_enqueue_scripts', array($this, 'register_scripts_and_styles'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_and_styles'));

            // Register Settings
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_menu', array($this, 'add_admin_page'));

            // Register AJAX action for Flickr authorization
            add_action('wp_ajax_wpfb_gallery_auth', array($this, 'flickr_auth_init'));

            // Register AJAX action for clearing the cache
            add_action('wp_ajax_wpfb_clear_cache', array($this, 'wpfb_clear_cache'));
        }

        private function get_option() {
            $option_values = get_option(self::$option_name);
            $default_values = array(
                    'flickr_api_key' => '',
                    'flickr_api_secret' => '',
                    'flickr_api_user_id' => NULL,
                    'flickr_api_access_token' => NULL,
                    'flickr_api_access_token_secret' => NULL,
            );
            return shortcode_atts($default_values, $option_values);
        }

        function get_post_image_url($post_id)
        {
            $url = null;
            $photoset_id = $this->get_post_photoset_id($post_id);

            if (!empty($photoset_id)) {
                $url = $this->fw->get_photoset_primary_photo_url($photoset_id, "medium");
            }

            return $url;
        }

        function flickr_post_image_html($html, $post_id, $post_image_id)
        {
            if (empty($html)) {
                $url = $this->get_post_image_url($post_id);

                if (!empty($url)) {
                    $html = "<img src='{$url}' class='wp-post-image img-polaroid'/>";
                }
            }

            return $html;
        }

        function get_post_metadata($null, $post_id, $key = '', $single = false)
        {
            // If using the Facebook Open Graph plugin set the featured image URL to the custom Flickr image.
            // See https://wordpress.org/plugins/wonderm00ns-simple-facebook-open-graph-tags/.
            if ($key == '_webdados_fb_open_graph_specific_image') {
                return $this->get_post_image_url(get_the_ID());
            }

            return null;
        }

        protected function add_filters()
        {
            add_filter('post_thumbnail_html', array($this, 'flickr_post_image_html'), 10, 3);
            add_filter('get_post_metadata', array($this, 'get_post_metadata'), 10, 4);
        }

        public function add_admin_page()
        {
            add_options_page('Flickr Options', 'Flickr Options', 'manage_options', self::$options_page_name, array($this, 'options_do_page'));
        }

        public function notify_cache_cleared()
        {
            ?>
            <div class="notice notice-success">
                <p><?php _e('Cache cleared!'); ?></p>
            </div>
            <?php
        }

        public function options_do_page()
        {
            ?>
            <div class="wrap">
                <h2>Flickr Options</h2>

                <?php
                // NOTE (CCB): My attempts to use add_action('admin_notices', array($this, 'notify_cache_cleared'))
                // failed. The callback never seemed to be called. Hence, this "hack".
                if (isset($_GET[self::$cache_cleared_qs_param])) {
                    $this->notify_cache_cleared();
                }

                $options = $this->get_option();
                ?>

                <p>
                <ol>
                    <li>Enter your API key and secret.</li>
                    <li>Click "Save Changes" to save the key and secret, and reset the API token.</li>
                    <li>Click "Grant Access" to reauthenticate with Flickr. You will be redirected to this page after
                        authentication, and the API token should be set.
                    </li>
                </ol>
                </p>

                <form method="post" action="options.php">
                    <?php settings_fields(self::$options_page_name); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Flickr API Key:</th>
                            <td><input type="text" name="<?php echo self::$option_name ?>[flickr_api_key]"
                                       value="<?php echo $options['flickr_api_key']; ?>"/></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Flickr API Secret:</th>
                            <td><input type="text" name="<?php echo self::$option_name ?>[flickr_api_secret]"
                                       value="<?php echo $options['flickr_api_secret']; ?>"/></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Flickr User ID:</th>
                            <td><?php echo $options['flickr_api_user_id']; ?></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Flickr API Access Token:</th>
                            <td>
                                <?php echo $options['flickr_api_access_token']; ?>
                                <input type="button" class="button-primary" value="Grant Access"
                                       onclick="document.location.href='<?php echo get_admin_url() . 'admin-ajax.php?action=wpfb_gallery_auth'; ?>';"/>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Flickr API Access Token Secret:</th>
                            <td>
                                <?php echo $options['flickr_api_access_token_secret']; ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Cache:</th>
                            <td>
                                <input type="button" class="button-primary" value="Reset"
                                       onclick="document.location.href='<?php echo get_admin_url() . 'admin-ajax.php?action=wpfb_clear_cache'; ?>';"/>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>"/>
                    </p>
                </form>
            </div>
            <?php
        }

        function flickr_auth_read()
        {
            if (isset($_GET['oauth_verifier']) && isset($_GET['oauth_token'])) {
                $token = $this->fw->retrieveAccessToken($_GET['oauth_verifier'], $_GET['oauth_token']);
                $accessToken = $token->getAccessToken();
                $accessTokenSecret = $token->getAccessTokenSecret();

                $this->fw->setAccessToken($accessToken, $accessTokenSecret);
                $userId = $this->fw->getUserId();

                $options = $this->get_option();
                $options['flickr_api_access_token'] = $accessToken;
                $options['flickr_api_access_token_secret'] = $accessTokenSecret;
                $options['flickr_api_user_id'] = $userId;
                update_option(self::$option_name, $options);

                // Remove the querystring parameters so we don't attempt to save the data again.
                wp_safe_redirect($this->callbackUrl());
                wp_die();
            }
        }

        function callbackUrl() {
            return admin_url('options-general.php?page=' . self::$options_page_name);
        }

        function flickr_auth_init()
        {
            $this->fw->auth_clear_token();
            $this->fw->auth($this->callbackUrl(), 'read');
            wp_die();
        }

        public function admin_init()
        {
            register_setting(self::$options_page_name, self::$option_name, array($this, 'validate_options'));
            $this->flickr_auth_read();
        }

        public function validate_options($input)
        {
            $valid = array();
            $valid['flickr_api_key'] = sanitize_text_field($input['flickr_api_key']);
            $valid['flickr_api_secret'] = sanitize_text_field($input['flickr_api_secret']);
            $valid['flickr_api_user_id'] = sanitize_text_field($input['flickr_api_user_id']);
            $valid['flickr_api_access_token'] = sanitize_text_field($input['flickr_api_access_token']);
            $valid['flickr_api_access_token_secret'] = sanitize_text_field($input['flickr_api_access_token_secret']);

            if (strlen($valid['flickr_api_key']) == 0) {
                add_settings_error(
                    'flickr_api_key',
                    'flickr_api_key_texterror',
                    'Please enter a valid Flickr API key.',
                    'error'
                );
            }

            if (strlen($valid['flickr_api_secret']) == 0) {
                add_settings_error(
                    'flickr_api_secret',
                    'flickr_api_secret_texterror',
                    'Please enter a valid Flickr API secret.',
                    'error'
                );
            }

            return $valid;
        }

        protected function add_shortcodes()
        {
            add_shortcode('flickr-download-gallery', array($this, 'shortcode_flickr_download_gallery'));
            add_shortcode('flickr-photo', array($this, 'shortcode_flickr_photo'));
            add_shortcode('flickr-photoset', array($this, 'shortcode_flickr_photoset'));
            add_shortcode('flickr-photoset-fullscreen', array($this, 'shortcode_flickr_photoset_fullscreen'));
            add_shortcode('flickrset', array($this, 'shortcode_flickr_photoset'));
        }

        public function register_scripts_and_styles()
        {
            // Primary stylesheet
            wp_register_style('wp-flickr-base', plugins_url('/css/wp-flickr-base.css', __FILE__));

            // Photoswipe
            wp_register_script('klass', plugins_url('/photoswipe/lib/klass.min.js', __FILE__));
            wp_register_script('photoswipe-jquery', plugins_url('/photoswipe/code.photoswipe.jquery-3.0.5.min.js', __FILE__), array('jquery', 'klass'));

            // Lazy Load
            wp_register_script('lazy-load', plugins_url('/js/jquery.lazyload.min.js', __FILE__), array('jquery'));
        }

        public function enqueue_scripts_and_styles()
        {
            wp_enqueue_style('wp-flickr-base');
            wp_enqueue_script('photoswipe-jquery');
            wp_enqueue_script('jquery-masonry');
            wp_enqueue_script('lazy-load');
        }

        protected function get_post_photoset_id($post_id = NULL)
        {
            if (empty($post_id)) {
                global $post;
                if (!empty($post)) {
                    $post_id = $post->ID;
                } else {
                    // In the Loop
                    $post_id = get_the_ID();
                }
            }

            return get_post_meta($post_id, 'flickr_photoset_id', true);
        }

        public function shortcode_flickr_download_gallery($atts)
        {
            $attributes = shortcode_atts(array(
                'id' => $this->get_post_photoset_id()
            ), $atts);
            $id = $attributes['id'];

            if (empty($id)) {
                return "Please provide a photo_id.";
            }

            return $this->portfolio_slideshow($id, false, true);
        }

        public function shortcode_flickr_photo($atts)
        {
            $fw = $this->fw;

            $attributes = shortcode_atts(array(
                'id' => $fw->get_photoset_primary_photo($this->get_post_photoset_id())
            ), $atts);
            $id = $attributes['id'];

            if (empty($id)) {
                return "Please provide a photo_id.";
            }

            $url = $fw->get_photo_url($id);
            return $this->display_image($url);
        }

        public function shortcode_flickr_photoset($atts)
        {
            $attributes = shortcode_atts(array(
                'id' => $this->get_post_photoset_id()
            ), $atts);
            $id = $attributes['id'];

            if (empty($id)) {
                return "Please provide a photoset_id.";
            }

            return $this->portfolio_slideshow($id);
        }

        public function shortcode_flickr_photoset_fullscreen($atts)
        {
            $attributes = shortcode_atts(array(
                'id' => $this->get_post_photoset_id()
            ), $atts);
            $id = $attributes['id'];

            if (empty($id)) {
                return "Please provide a photoset_id.";
            }

            return $this->portfolio_slideshow($id, true);
        }

        function display_image($url, $caption = "")
        {
            if (empty($url)) {
                echo "No url provided to display_image().";
                return "";
            }

            return <<<HTML
        <ul class="thumbnails">
            <li class="">
                <div class="thumbnail">
                    <img src="{$url}" alt="{$caption}">
                </div>
                <div class="caption">{$caption}</div>
            </li>
        </ul>
HTML;
        }

        function portfolio_slideshow($photoset_id, $fullscreen = false, $download = false)
        {
            $data = $this->fw->get_photoset($photoset_id);
            if(empty($data)) {
                return NULL;
            }

            return $this->create_photoswipe($data, $fullscreen, $download);
        }

        function create_photoswipe($data, $fullscreen = false, $download = false, $lazy_load = false)
        {
            if ($fullscreen) {
                return $this->create_photoswipe_target($data);
            }

            $id = 'id_' . uniqid();
            $items = "";
            foreach ($data as $image) {
                $attrs = "";
                $img_alt = $image['title'];
                $details = "";

                if ($download) {
                    $lazy_load = true;
                    $url = str_replace(".jpg", "_d.jpg", $image['orig_url']);
                    $attrs = "data-original-url=\"{$url}\"";
                    $details = <<<DETAILS
                    <div class="details">
						<div class="title">{$image['title']}</div>
						<div class="download" title="Download image">
							<a href="{$url}">
								<i class="icon-download-alt icon-white"></i>
							</a>
						</div>
					</div>
DETAILS;
                }

                $img = "<img class=\"ps-thumbnail\" src=\"{$image['thumb_url']}\" alt=\"{$img_alt}\" />";

                if ($lazy_load) {
                    $img = "<img class=\"lazy ps-thumbnail\" data-original=\"{$image['thumb_url']}\" src=\"https://placehold.it/{$image['width']}x{$image['height']}&text=Scroll+down+to+load.\" alt=\"{$img_alt}\" />";
                }

                $items .= <<<ITEM
            <li class="span3">
                <div class="thumbnail">
                    <a class="ps-trigger" href="{$image['url']}" title="{$image['title']}" data-description="{$image['description']}" {$attrs}>
                        {$img}
                    </a>
                    {$details}
                </div>
            </li>
ITEM;
            }

            $psOptions = array('allowUserZoom: true');
            $psEventHandlers = '';
            $downloadToolbar = '';
            $count_js = "";

            // Custom JS to build a multi-line caption
            $caption_js = <<<JS
            function(el){
              var title = el.getAttribute('title').trim();
              var description = (el.getAttribute('data-description') || "").trim();

              if(!description){
                return title;
              }

              var captionEl = document.createElement('div');
              captionEl.appendChild(document.createTextNode(title));
              captionEl.appendChild (document.createElement('br'));
              captionEl.appendChild(document.createTextNode(description));
              return captionEl;
            }
JS;
            array_push($psOptions, "getImageCaption: {$caption_js}");

            if ($download) {
                $count = count($data);
                $count_str = $count . ' image' . (($count != 1) ? 's' : '');
                $count_js = <<<COUNT_JS
                    var title = jQuery(".page-header .title");
			        title.html(title.html() + ' <span class="image-count">({$count_str})</span>');
COUNT_JS;

                $downloadToolbar = "<div class=\"ps-toolbar-download\" style=\"padding-top: 12px;\" title=\"Download image\"><i class=\"icon-download-alt\"></i></div>";

                // Add options
                array_push($psOptions, "getImageMetaData: function(el){ return { orig_url: el.getAttribute('data-original-url') }; }");

                // Add event handlers
                $psEventHandlers = <<<EVT
        instance.addEventHandler(PhotoSwipe.EventTypes.onShow, function(e){
            elDownload = window.document.querySelectorAll('.ps-toolbar-download')[0];
        });

        instance.addEventHandler(PhotoSwipe.EventTypes.onToolbarTap, function(e){
            if (e.toolbarAction === PhotoSwipe.Toolbar.ToolbarAction.none && (e.tapTarget === elDownload || Util.DOM.isChildOf(e.tapTarget, elDownload))){
                var currentImage = instance.getCurrentImage();
                window.open(currentImage.metaData.orig_url);
            }
        });
EVT;
            }
            array_push($psOptions, "getToolbar: function(){return '<div class=\"ps-toolbar-close\" style=\"padding-top: 12px;\" title=\"Close slideshow\"><i class=\"icon-remove\"></i></div><div class=\"ps-toolbar-play\" style=\"padding-top: 12px;\" title=\"Start slideshow\"><i class=\"icon-play\"></i></div><div class=\"ps-toolbar-previous\" style=\"padding-top: 12px;\" title=\"Previous image\"><i class=\"icon-arrow-left\"></i></div><div class=\"ps-toolbar-next\" style=\"padding-top: 12px;\" title=\"Next image\"><i class=\"icon-arrow-right\"></i></div>{$downloadToolbar}';}");

            $psOptions = implode(', ', $psOptions);

            return <<<HTML
        <ul id="{$id}" class="thumbnails">
            {$items}
        </ul>

        <script type="text/javascript">
            (function(window, Util, PhotoSwipe){
                jQuery(document).ready(function(){
                    {$count_js}

                    var instance = jQuery("#{$id} a.ps-trigger").photoSwipe({
                        {$psOptions}
                    });

                    {$psEventHandlers}

                    var container = jQuery('#{$id}');

                    // Lazy load
                    jQuery("img.lazy", container).lazyload();

                    // Masonry
                    container.imagesLoaded( function(){
                      container.masonry({
                        itemSelector : '.span3',
                        containerStyle: { position: 'relative' }
                      });
                    });
                });
            }(window, window.Code.Util, window.Code.PhotoSwipe));
        </script>
HTML;
        }

        function create_photoswipe_target($data)
        {
            $id = 'id_' . uniqid();
            $urls = array_map(function ($image) {
                return "{ url: '{$image['url']}', caption: ''}";
            }, $data);
            $urls_concat = implode(', ', $urls);

            return <<<HTML
            <div id="{$id}" class="photoswipe-target"></div>

            <script type="text/javascript">
                (function(window, Util, PhotoSwipe){

                    Util.Events.domReady(function(e){

                        var instance;

                        instance = PhotoSwipe.attach(
                            [
                                {$urls_concat}
                            ],
                            {
                                target: window.document.querySelectorAll('#{$id}')[0],
                                preventHide: true,
                                autoStartSlideshow: true,
                                slideSpeed: 500,
                                enableMouseWheel: false,
                                captionAndToolbarShowEmptyCaptions: false,
                                getImageSource: function(obj){
                                    return obj.url;
                                },
                                getImageCaption: function(obj){
                                    return obj.caption;
                                },
                                getToolbar: function(){
                                    return '<div class=\"ps-toolbar-play\" style=\"padding-top: 12px;\"><i class=\"icon-play\"></i></div><div class=\"ps-toolbar-previous\" style=\"padding-top: 12px;\"><i class=\"icon-arrow-left\"></i></div><div class=\"ps-toolbar-next\" style=\"padding-top: 12px;\"><i class=\"icon-arrow-right\"></i></div>';
                                }
                            }
                        );
                        instance.show(0);

                    });


                }(window, window.Code.Util, window.Code.PhotoSwipe));
            </script>
HTML;
        }

        static function get_cache_table()
        {
            global $wpdb;
            return $wpdb->prefix . self::$table_name;
        }

        static private function get_connection()
        {
            return sprintf('mysql://%s:%s@%s/%s', DB_USER, DB_PASSWORD, DB_HOST, DB_NAME);
        }

        static function install()
        {
            // Create table
            $table = self::get_cache_table();
            $sql = "CREATE TABLE IF NOT EXISTS `$table` (
                `request` CHAR( 35 ) NOT NULL ,
                `response` MEDIUMTEXT NOT NULL ,
                `expiration` DATETIME NOT NULL ,
                INDEX ( `request` ))";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        static function uninstall()
        {
            // Delete the options
            delete_option(self::$option_name);

            // Delete the cache table
            global $wpdb;
            $table = self::get_cache_table();
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        static function clear_cache()
        {
            global $wpdb;
            $table = self::get_cache_table();
            $wpdb->query("TRUNCATE TABLE $table");
        }

        /**
         * Clears the cache and redirects to the options page.
         */
        function wpfb_clear_cache()
        {
            $this->clear_cache();
            $qs = http_build_query(array(
                'page' => self::$options_page_name,
                self::$cache_cleared_qs_param => 1
            ));
            wp_safe_redirect(admin_url("options-general.php?{$qs}"));
            wp_die();
        }
    }
}

$fb = new WPFlickrBase();

register_activation_hook(__FILE__, array('WPFlickrBase', 'install'));
register_deactivation_hook(__FILE__, array('WPFlickrBase', 'uninstall'));
