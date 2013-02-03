<?php
/*
Plugin Name: wp-flickr-base
Plugin URI: http://clintonblackburn.com/wp-flickr-base
Description: Basic Flickr API accessor and slideshow generator
Version: 0.1
Author: Clinton Blackburn
Author URI: http://clintonblackburn.com
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
        protected $fw;
        static protected $option_name = 'wp-flickr-base';
        static protected $table_name = 'flickr_wrapper_cache';

        function __construct()
        {
            $api_key = "";
            $api_secret = "";
            $api_token = "";

            // Get the API credentials from the database
            $options = get_option(self::$option_name);
            if (!empty($options)) {
                $api_key = getItem($options, 'flickr_api_key');
                $api_secret = getItem($options, 'flickr_api_secret');
                $api_token = getItem($options, 'flickr_api_token');
            }

            // Initialize the Flickr API accessor
            $this->fw = new FlickrWrapper($api_key, $api_secret, $api_token);

            // Enable caching
            $connection = self::get_connection();
            $table = self::get_cache_table();
            $this->fw->cache_enable($connection, $table, 600);

            // Add shortcode handlers
            $this->add_shortcodes();

            // Register, and enqueue, scripts and styles
            add_action('wp_enqueue_scripts', array($this, 'register_scripts_and_styles'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_and_styles'));

            // Register Settings
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_menu', array($this, 'add_admin_page'));

            // Register AJAX action for Flickr authorization
            add_action('wp_ajax_wpfb_gallery_auth', array($this, 'flickr_auth_init'));
        }

        public function add_admin_page()
        {
            add_options_page('Flickr Options', 'Flickr Options', 'manage_options', 'wp_flickr_base_options', array($this, 'options_do_page'));
        }

        public function options_do_page()
        {
            $options = get_option(self::$option_name);
            ?>
        <div class="wrap">
            <h2>Flickr Options</h2>

            <form method="post" action="options.php">
                <?php settings_fields('wp_flickr_base_options'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Flickr API Key:</th>
                        <td><input type="text" name="<?php echo self::$option_name?>[flickr_api_key]"
                                   value="<?php echo $options['flickr_api_key']; ?>"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Flickr API Secret:</th>
                        <td><input type="text" name="<?php echo self::$option_name?>[flickr_api_secret]"
                                   value="<?php echo $options['flickr_api_secret']; ?>"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Flickr API Token:</th>
                        <td>
                            <?php echo $options['flickr_api_token']; ?>
                            <input type="button" class="button-primary" value="Grant Access"
                                   onclick="document.location.href='<?php echo get_admin_url() . 'admin-ajax.php?action=wpfb_gallery_auth'; ?>';"/>
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
            if (isset($_GET['frob'])) {
                $auth = $this->fw->auth_get_token($_GET['frob']);
                $api_token = $auth['token'];

                $options = get_option(self::$option_name);
                $options['flickr_api_token'] = $api_token;
                update_option(self::$option_name, $options);

                $this->fw->auth_set_token($api_token);
                header('Location: ' . $_SESSION['phpFlickr_auth_redirect']);
                exit;
            }
        }

        function flickr_auth_init()
        {
            session_start();
            $this->fw->auth_clear_token();
            $this->fw->auth('read', $_SERVER['HTTP_REFERER']);
            exit;
        }

        public function admin_init()
        {
            register_setting('wp_flickr_base_options', self::$option_name, array($this, 'validate_options'));
            $this->flickr_auth_read();
        }

        public function validate_options($input)
        {
            $valid = array();
            $valid['flickr_api_key'] = sanitize_text_field($input['flickr_api_key']);
            $valid['flickr_api_secret'] = sanitize_text_field($input['flickr_api_secret']);
            $valid['flickr_api_token'] = sanitize_text_field($input['flickr_api_token']);

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
            add_shortcode('flickr-photo', array($this, 'shortcode_flickr_photo'));
            add_shortcode('flickr-photoset', array($this, 'shortcode_flickr_photoset'));
            add_shortcode('flickrset', array($this, 'shortcode_flickr_photoset'));
        }

        public function register_scripts_and_styles()
        {
            // Primary stylesheet
            wp_register_style('wp-flickr-base', plugins_url('/css/wp-flickr-base.css', __FILE__));

            // Photoswipe
            wp_register_script('klass', plugins_url('/photoswipe/lib/klass.min.js', __FILE__));
            wp_register_script('photoswipe-jquery', plugins_url('/photoswipe/code.photoswipe.jquery-3.0.5.min.js', __FILE__), array('jquery', 'klass'));
        }

        public function enqueue_scripts_and_styles()
        {
            wp_enqueue_style('wp-flickr-base');
            wp_enqueue_script('photoswipe-jquery');
        }

        protected function get_post_photoset_id($post_id = NULL)
        {
            if (empty($post_id)) {
                global $post;
                $post_id = $post->ID;
            }

            return get_post_meta($post_id, 'flickr_photoset_id', true);
        }

        public function shortcode_flickr_photo($atts)
        {
            global $post;
            $fw = $this->fw;

            extract(shortcode_atts(array(
                'id' => $fw->get_photoset_primary_photo($this->get_post_photoset_id())
            ), $atts));

            if (empty($id)) {
                echo "Please provide a photo_id.";
                return;
            }

            $url = $fw->get_photo_url($id);
            echo $this->display_image($url);
        }

        public function shortcode_flickr_photoset($atts)
        {
            global $post;
            extract(shortcode_atts(array(
                'id' => $this->get_post_photoset_id()
            ), $atts));

            if (empty($id)) {
                echo "Please provide a photoset_id.";
                return;
            }

            echo $this->portfolio_slideshow($id);
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

        function portfolio_slideshow($photoset_id)
        {
            $data = $this->fw->get_photoset($photoset_id);
            return $this->create_photoswipe($data);
        }

        function create_photoswipe($data, $download = false)
        {
            $id = 'id_' . uniqid();
            $items = "";
            foreach ($data as $image) {
                $orig_url = $download ? "data-original-url=\"{$image['orig_url']}\"" : '';
                $items .= <<<ITEM
            <li class="span3"><div class="thumbnail"><a href="{$image['url']}" title="{$image['title']}" {$orig_url}><img class="ps-thumbnail" src="{$image['thumb_url']}" /></a></div></li>
ITEM;
            }

            $psOptions = array('allowUserZoom: true');
            $psEventHandlers = '';
            if ($download) {
                // Add options
                array_push($psOptions, "getToolbar: function(){return '<div class=\"ps-toolbar-close\" style=\"padding-top: 12px;\"><i class=\"icon-remove-sign\"></i></div><div class=\"ps-toolbar-play\" style=\"padding-top: 12px;\"><i class=\"icon-play-circle\"></i></div><div class=\"ps-toolbar-previous\" style=\"padding-top: 12px;\"><i class=\"icon-circle-arrow-left\"></i></div><div class=\"ps-toolbar-next\" style=\"padding-top: 12px;\"><i class=\"icon-circle-arrow-right\"></i></div><div class=\"ps-toolbar-download\" style=\"padding-top: 12px;\"><i class=\"icon-download-alt\"></i></div>';}");
                array_push($psOptions, "getImageMetaData: function(el){ return { orig_url: el.getAttribute('data-original-url') }; }");

                // Add event handlers
                $psEventHandlers = <<<EVT
        instance.addEventHandler(PhotoSwipe.EventTypes.onShow, function(e){
            elDownload = window.document.querySelectorAll('.ps-toolbar-download')[0];
        });

        instance.addEventHandler(PhotoSwipe.EventTypes.onToolbarTap, function(e){
            if (e.toolbarAction === PhotoSwipe.Toolbar.ToolbarAction.none && (e.tapTarget === elDownload || Util.DOM.isChildOf(e.tapTarget, elDownload))){
                var currentImage = instance.getCurrentImage();
                window.open(currentImage.metaData.orig_url.replace('.jpg', '_d.jpg'));
            }
        });
EVT;
            }

            $psOptions = implode(', ', $psOptions);

            return <<<HTML
        <ul id="{$id}" class="thumbnails">
            {$items}
        </ul>

        <script type="text/javascript">
            (function(window, Util, PhotoSwipe){
                jQuery(document).ready(function(){
                    var instance = jQuery("#{$id} a").photoSwipe({
                        {$psOptions}
                    });

                    {$psEventHandlers}

                    var container = jQuery('#{$id}');
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

            echo <<<HTML
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
    }
}

$fb = new WPFlickrBase();

register_activation_hook(__FILE__, array('WPFlickrBase', 'install'));
register_deactivation_hook(__FILE__, array('WPFlickrBase', 'uninstall'));
