class RSS_News_Importer_Queue {

private $queue_option_name = 'rss_news_importer_queue';

public function add_to_queue($feed_url) {
    $queue = $this->get_queue();
    if (!in_array($feed_url, $queue)) {
        $queue[] = $feed_url;
        update_option($this->queue_option_name, $queue);
    }
}

public function get_queue() {
    return get_option($this->queue_option_name, array());
}

public function remove_from_queue($feed_url) {
    $queue = $this->get_queue();
    $queue = array_diff($queue, array($feed_url));
    update_option($this->queue_option_name, $queue);
}

public function process_queue() {
    $queue = $this->get_queue();
    $importer = new RSS_News_Importer_Post_Importer();
    foreach ($queue as $feed_url) {
        $importer->import_feed($feed_url);
        $this->remove_from_queue($feed_url);
    }
}
}