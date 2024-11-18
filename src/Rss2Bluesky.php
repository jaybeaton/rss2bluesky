<?php

namespace Rss2Bluesky;

use cjrasmussen\BlueskyApi\BlueskyApi;

class Rss2Bluesky {

    const DATE_FORMAT_DISPLAY = 'M j, Y \a\t g:i A';

    const DATE_FORMAT_DB = 'Y-m-d H:i:s';

    const LINK_MAX_LENGTH = 60;

    const IMAGE_MAX_DIM = 600;

    const ELLIPSIS = '…';

    private $settings = [];

    public $blueskyUsername = '';

    private $blueskyApi;

    private $simpleImage;

    public $blueskyRefreshToken = NULL;

    public $blueskyIsAuthed = FALSE;

    private $db;

    public function __construct($settings) {
        $this->settings = $settings;
        $this->blueskyApi = new BlueskyApi();
        $this->simpleImage = new SimpleImage();
        try {
            $this->blueskyUsername = $settings['bluesky']['username'];
            $this->db = new \mysqli($settings['database']['host'],
                $settings['database']['user'],
                $settings['database']['password'],
                $settings['database']['database']);
            $this->db->set_charset($settings['database']['charset']);
            $this->getBlueskyRefreshToken();
            if ($this->db->connect_errno > 0) {
                die('Unable to connect to database [' . $this->db->connect_error . ']');
            }
        } catch (\Exception $e) {
            // TODO: Handle the exception however you want
            print $e->getCode() . ': ' . $e->getMessage();
        }
    }

    private function getBlueskyAuth($retry = TRUE) {
        try {
            if ($this->blueskyRefreshToken && $retry) {
                $used_refresh_token = TRUE;
                $this->blueskyApi->auth($this->blueskyRefreshToken);
            }
            else {
                $used_refresh_token = FALSE;

                print "bsky login\n";

                $this->blueskyApi->auth($this->settings['bluesky']['username'], $this->settings['bluesky']['app_password']);
            }
            $this->blueskyIsAuthed = TRUE;
            $this->setBlueskyRefreshToken($this->blueskyApi->getRefreshToken());
        } catch (\Exception $e) {
            // TODO: Handle the exception however you want
            if ($used_refresh_token) {
                // Try again without the refresh token.
                $this->getBlueskyAuth(FALSE);
            }
            else {
                print $e->getCode() . ': ' . $e->getMessage();
            }
        }
    }

    private function setBlueskyRefreshToken($refresh_token) {
        $sql = "REPLACE INTO rss2bluesky_key_value
            (`key`, `value`, `timestamp`)
            VALUES
            (?, ?, ?) ";
        try {
            $query = $this->db->prepare($sql);
            $key = 'bluesky_refresh_token:' . $this->blueskyUsername;
            $timestamp = time();
            $query->bind_param('ssi',
                $key,
                $refresh_token,
                $timestamp);
            $query->execute();
            $this->blueskyRefreshToken = $refresh_token;
        } catch (\Exception $e) {
            print $sql . '<br>' . $e->getCode() . ': ' . $e->getMessage();
        }
        $query->close();
    }

    private function getBlueskyRefreshToken() {
        if (!$this->blueskyRefreshToken) {
            $sql = "SELECT `value`
              FROM rss2bluesky_key_value
              WHERE `key` = ? ";
            $query = $this->db->prepare($sql);
            $key = 'bluesky_refresh_token:' . $this->blueskyUsername;
            $query->bind_param('s', $key);
            $query->execute();
            $this->blueskyRefreshToken = $query->get_result()->fetch_object()->value ?? '';
        }
        return $this->blueskyRefreshToken;
    }

    public function insertPost($post) {
        $post['is_posted'] = $this->postIsPosted($post);
        $timestamp = time();
        $sql = "REPLACE INTO rss2bluesky_posts
            (feed, title, permalink, blurb, image_url, is_posted, post_timestamp, timestamp)
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?) ";
        try {
            $query = $this->db->prepare($sql);
            $query->bind_param('sssssiii',
                $post['feed'],
                $post['title'],
                $post['permalink'],
                $post['blurb'],
                $post['image_url'],
                $post['is_posted'],
                $post['post_timestamp'],
                $timestamp);
            $done = $query->execute();
        } catch (\Exception $e) {
            print $sql . '<br>' . $e->getCode() . ': ' . $e->getMessage();
            print '<pre><hr>$post<hr>' . print_r($post, true) . '<hr></pre>';
        }
    }

    public function postIsPosted($post) {
        $sql = "SELECT is_posted FROM rss2bluesky_posts WHERE permalink = ?";
        $query = $this->db->prepare($sql);
        $query->bind_param('s', $post['permalink']);
        $query->execute();
        return $query->get_result()->fetch_object()->is_posted ?? 0;
    }

    public function getBlurb($text) {
        $blurb = trim(strip_tags($text ?? ''));
        if (mb_strlen($blurb) > 300) {
            $blurb = trim(mb_substr($blurb, 0, 299)) . '…';
        }
        $blurb = preg_replace("/[\r\n]{2,}/", "\n", $blurb);
        $blurb = preg_replace("/^ +/m", '', $blurb);
        return $blurb;
    }

    public function processUnposted($limit = NULL) {

        $sql = "SELECT feed, title, permalink, blurb, image_url
          FROM rss2bluesky_posts
          WHERE is_posted = 0
          ORDER BY timestamp ASC ";
        if ($limit) {
            $sql .= ' LIMIT ' . $limit;
        }

        $query = $this->db->prepare($sql);
        $query->execute();
        $result = $query->get_result();

        while ($row = $result->fetch_assoc()) {
            if ($this->postToBluesky($row)) {
                $this->markPosted($row);
            }
        } // Loop thru items.

        $query->close();

    }

    public function markPosted($post) {
        $sql = "UPDATE rss2bluesky_posts SET is_posted = 1 WHERE permalink = ?";
        $query = $this->db->prepare($sql);
        $query->bind_param('s', $post['permalink']);
        $done = $query->execute();
        $query->close();
        return $done;
    }

    public function postToBluesky($post) {

        if (!$this->blueskyIsAuthed) {
            $this->getBlueskyAuth();
        }

        $args = [
            'collection' => 'app.bsky.feed.post',
            'repo' => $this->blueskyApi->getAccountDid(),
            'record' => [
                'text' => 'From the "' . $post['feed'] . '" RSS feed',
                'langs' => ['en'],
                'createdAt' => date('c'),
                '$type' => 'app.bsky.feed.post',
                'embed' => [
                    '$type' => 'app.bsky.embed.external',
                    'external' => [
                        'uri' => $post['permalink'],
                        'title' => $post['title'],
                        'description' => $post['blurb'],
                    ],
                ],
            ],
        ];


        print '==== $post =====' . "\n" . print_r($post, TRUE) . "\n============\n";

        if ($post['image_url'] && $image = $this->getSizedImage($post['image_url'])) {

            $content_type = 'image/jpeg';
            $image_upload = $this->blueskyApi->request('POST', 'com.atproto.repo.uploadBlob', [], $image, $content_type);

            print '==== $image_upload =====' . "\n" . print_r($image_upload, TRUE) . "\n============\n";

            if (!empty($image_upload->blob->ref->{'$link'})) {
                $args['record']['embed']['external']['thumb'] = [
                    '$type' => 'blob',
                    'ref' => [
                        '$link' => $image_upload->blob->ref->{'$link'},
                    ],
                    'mimeType' => $image_upload->blob->mimeType ?? '',
                    'size' => $image_upload->blob->size ?? '',
                ];
            }

        }

        print "==== args =====\n" . print_r($args, TRUE) . "\n============\n";

        $data = $this->blueskyApi->request('POST', 'com.atproto.repo.createRecord', $args);

        print "==== response =====\n" . print_r($data, TRUE) . "\n============\n";

        return !empty($data->uri);

    }

    public function getSizedImage($url) {
        $target_dir = '/tmp/';
        $target_dir = './';
        $filename = tempnam($target_dir, 'bskyimage') . 'jpg';

        print "filename[$filename]\n";

        if (!$image = file_get_contents($url)) {
            print "error getting url";
            return FALSE;
        }
        if (!file_put_contents($filename, $image)) {
            print "error saving file";
            return FALSE;
        }
        $this->simpleImage->load($filename);
        if ($this->simpleImage->getWidth() > self::IMAGE_MAX_DIM) {
            $this->simpleImage->resizeToWidth(self::IMAGE_MAX_DIM);
            $this->simpleImage->save($filename);
        }
        return file_get_contents($filename);
    }

    public function getProfile($actor = NULL) {
        if (!$this->blueskyIsAuthed) {
            $this->getBlueskyAuth();
        }
        if (!$actor) {
            $actor = $this->blueskyUsername;
        }
        $args = [
            'actor' => $actor,
        ];
        return $this->blueskyApi->request('GET', 'app.bsky.actor.getProfile', $args);
    }

}
