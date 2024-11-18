<?php

use Rss2Bluesky\Rss2Bluesky;

$settings = [];
require 'settings.php';
require_once 'vendor/autoload.php';
require 'src/Rss2Bluesky.php';
require 'src/SimpleImage.php';

$rss2bluesky = new Rss2Bluesky($settings);

// Create a new SimplePie object
$feed = new SimplePie\SimplePie();

$feed->set_feed_url($settings['feeds']);

$feed->init();
$feed->handle_content_type();

if ($feed->error) {
   print $feed->error;
}

foreach ($feed->get_items() as $item) {
    $feed = $item->get_feed();
    $image_url = '';
    if ($thumbnail = $item->get_thumbnail()) {
        $image_url = $thumbnail['url'] ?? '';
    }
    if (!$image_url) {
        if ($enclosure = $item->get_enclosure(0)) {
            if ($thumbnail = $enclosure->get_thumbnail(0)) {
                $image_url = $thumbnail;
            }
        }
    }

    $post = [
        'feed' => $feed->get_title(),
        'title' => $item->get_title(),
        'permalink' => $item->get_permalink(),
        'blurb' => $rss2bluesky->getBlurb($item->get_content()),
        'image_url' => $image_url,
        'post_timestamp' => $item->get_date('U'),
    ];
//    print "==== post =====\n" . print_r($post, TRUE) . "\n============\n";
    $rss2bluesky->insertPost($post);
}

$rss2bluesky->processUnposted();
