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

if(  !class_exists('WPFlickrBase') ) {
    require_once('FlickrWrapper.php');

    class WPFlickrBase
    {
        protected $fw;

        function __construct()
        {
            // Get the API credentials from the database
            $api_key = "";
            $api_secret = "";
            $api_token = "";

            // Initialize the Flickr API accessor
            $this->fw = new FlickrWrapper($api_key, $api_secret, $api_token);

            // TODO Add activate/deactivate hooks for caching

            // Add shortcode handlers
            $this->add_shortcodes();

            // Register, and enqueue, scripts and styles
            add_action('wp_enqueue_scripts', array($this, 'register_scripts_and_styles'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_and_styles'));
        }

        protected function add_shortcodes()
        {
            add_shortcode('flickr-photo', array($this, 'shortcode_flickr_photo'));
            add_shortcode('flickr-photoset', array($this, 'shortcode_flickr_photoset'));
            add_shortcode('flickrset', array($this, 'shortcode_flickr_photoset'));
        }

        function register_scripts_and_styles()
        {
            // Primary stylesheet
            wp_register_style('wp-flickr-base', plugins_url('/css/wp-flickr-base.css', __FILE__));

            // Photoswipe
            wp_register_script('klass', plugins_url('/photoswipe/lib/klass.min.js', __FILE__));
            wp_register_script('photoswipe-jquery', plugins_url('/photoswipe/code.photoswipe.jquery-3.0.5.min.js', __FILE__), array('jquery', 'klass'));
        }

        function enqueue_scripts_and_styles()
        {
            wp_enqueue_style('wp-flickr-base');
            wp_enqueue_script('photoswipe-jquery');
        }

        public function shortcode_flickr_photo($atts)
        {
            global $post;
            $fw = $this->fw;

            extract(shortcode_atts(array(
                'id' => $fw->get_photoset_primary_photo(get_post_meta($post->ID, 'flickr_photoset_id', true))
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
                'id' => get_post_meta($post->ID, 'flickr_photoset_id', true)
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
    }
}

$fb = new WPFlickrBase();
