<?php

if(  !class_exists('FlickrWrapper') ) {
    require_once('phpFlickr/phpFlickr.php');

    class FlickrWrapper
    {
        private $phpFlickr;

        function __construct($api_key, $secret = NULL, $token = NULL)
        {
            $this->phpFlickr = new phpFlickr($api_key, $secret);
            $this->auth_clear_token();
            $this->phpFlickr->setToken($token);
        }

        function auth($perms = "read", $remember_uri = true){
            $this->phpFlickr->auth($perms, $remember_uri);
        }

        function auth_get_token($frob){
            return $this->phpFlickr->auth_getToken($frob);
        }

        function auth_set_token($token){
            $this->phpFlickr->setToken($token);
        }

        public function auth_clear_token(){
            unset($_SESSION['phpFlickr_auth_token']);
            $this->phpFlickr->setToken('');
        }

        function get_photoset($photoset_id)
        {
            if (empty($photoset_id)) {
                echo "Please provide a photoset_id.";
                return NULL;
            }

            $photoset = $this->phpFlickr->photosets_getPhotos($photoset_id, 'url_l,url_m,url_o', '12345');

            // Build a new array
            $data = array();
            foreach ($photoset['photoset']['photo'] as $image) {
                $data[] = array(
                    'title' => $image['title'],
                    'url' => $image['url_l'],
                    'orig_url' => $image['url_o'],
                    'thumb_url' => $image['url_m'],
                    'height' => $image['height_m'],
                    'width' => $image['width_m']
                );
            }

            return $data;
        }

        function get_photo_url($photo_id, $size = "medium")
        {
            if (empty($photo_id)) {
                echo "Please provide a photo_id.";
                return null;
            }

            $photo_sizes = $this->phpFlickr->photos_getSizes($photo_id);
            $size = strtolower($size);
            $filter = function ($el) use ($size) {
                return strtolower($el['label']) == $size;
            };
            $photo_sizes = array_filter($photo_sizes, $filter);
            $photo = current($photo_sizes);
            return $photo['source'];
        }

        function get_photoset_primary_photo($photoset_id)
        {
            if (empty($photoset_id)) {
                echo "Please provide a photoset_id.";
                return null;
            }

            $photoset = $this->phpFlickr->photosets_getInfo($photoset_id);
            return $photoset['primary'];
        }

        function get_photoset_primary_photo_url($photoset_id, $size)
        {
            $primary_photo_id = $this->get_photoset_primary_photo($photoset_id);
            return $this->get_photo_url($primary_photo_id, $size);
        }
    }
}
