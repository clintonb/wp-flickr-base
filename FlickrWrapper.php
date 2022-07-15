<?php

require_once 'vendor/autoload.php';

use Samwilson\PhpFlickr\PhpFlickr;


if (!class_exists('FlickrWrapper')) {
    class FlickrWrapper
    {
        private PhpFlickr $phpFlickr;
        private string|null $userId;

        function __construct($api_key, $secret, $userId = NULL, $accessToken = NULL, $accessTokenSecret = NULL)
        {
            $this->userId = $userId;
            $this->phpFlickr = new PhpFlickr($api_key, $secret);

            if (!empty($accessToken) && !empty($accessTokenSecret)) {
                $storage = new \OAuth\Common\Storage\Memory();
                $this->phpFlickr->setOauthStorage($storage);
                $this->setAccessToken($accessToken, $accessTokenSecret);
            } else {
                $storage = new \OAuth\Common\Storage\Session();
                $this->phpFlickr->setOauthStorage($storage);
            }
        }

        public function cache_enable($connection, $table, $expire = 600)
        {
            $this->phpFlickr->enableCache('db', $connection, $expire, $table);
        }

        function auth($callbackUrl, $perms = "read")
        {
            $url = $this->phpFlickr->getAuthUrl($perms, $callbackUrl);
            header("Location: $url");
            exit();
        }

        function retrieveAccessToken($verifier, $requestToken)
        {
            return $this->phpFlickr->retrieveAccessToken($verifier, $requestToken);
        }

        function setAccessToken($accessToken, $accessTokenSecret)
        {
            $token = new \OAuth\OAuth1\Token\StdOAuth1Token();
            $token->setAccessToken($accessToken);
            $token->setAccessTokenSecret($accessTokenSecret);
            $storage = $this->phpFlickr->getOauthTokenStorage();
            $storage->storeAccessToken('Flickr', $token);
            $this->phpFlickr->setOauthStorage($storage);
        }

        public function auth_clear_token()
        {
            try {
                $this->phpFlickr->getOauthTokenStorage()->clearAllTokens();
            } catch (\Samwilson\PhpFlickr\FlickrException $e) {
            }
        }

        public function getUserId()
        {
            $profileUrl = $this->phpFlickr->urls()->getUserProfile();
            return $this->phpFlickr->urls()->lookupUser($profileUrl)['id'];
        }

        function get_photoset($photoset_id)
        {
            if (empty($photoset_id)) {
                echo "Please provide a photoset_id.";
                return NULL;
            }

            try {
                $photoset = $this->phpFlickr->photosets()->getPhotos($photoset_id, $this->userId, 'url_l,url_m,url_o,description');
            } catch (Exception $e) {
                echo "Photoset ${photoset_id} not found!";
                return NULL;
            }

            // Build a new array
            $data = array();
            foreach ($photoset['photo'] as $image) {
                $data[] = array(
                    'title' => $image['title'],
                    'description' => $image['description'],
                    'url' => $image['url_l'] ?? $image['url_o'],
                    'orig_url' => $image['url_o'],
                    'thumb_url' => $image['url_m'],
                    'height' => $image['height_m'],
                    'width' => $image['width_m']
                );
            }

            return $data;
        }

        function get_photo_url($photo_id, $desiredSize = "medium")
        {
            if (empty($photo_id)) {
                echo "Please provide a photo_id.";
                return null;
            }

            $photo_sizes = $this->phpFlickr->photos()->getSizes($photo_id)['size'];
            $desiredSize = strtolower($desiredSize);

            foreach ($photo_sizes as $index => $size) {
                if (strtolower($size['label']) == $desiredSize) {
                    // Image URL
                    return $size['source'];
                }
            }

            // TODO Use a better default
            return $photo_sizes[0]['source'];
        }

        function get_photoset_primary_photo($photoset_id)
        {
            if (empty($photoset_id)) {
                echo "Please provide a photoset_id.";
                return null;
            }

            $photoset = $this->phpFlickr->photosets()->getInfo($photoset_id, $this->userId);
            return $photoset['primary'];
        }

        function get_photoset_primary_photo_url($photoset_id, $size)
        {
            $primary_photo_id = $this->get_photoset_primary_photo($photoset_id);
            return $this->get_photo_url($primary_photo_id, $size);
        }
    }
}
