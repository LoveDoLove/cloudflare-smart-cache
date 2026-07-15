<?php
defined('ABSPATH') || exit;

class CF_Smart_Cache_Purge {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function purge_all() {
        $api     = CF_Smart_Cache_API::instance();
        $zone_id = $api->get_zone_id();
        if (empty($zone_id)) {
            return new WP_Error('missing_zone', 'Zone ID not configured');
        }
        $api_url  = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/purge_cache";
        $response = $api->http_request($api_url, array(
            'body' => json_encode(array('purge_everything' => true)),
        ), 'purge all');
        return $api->validate_api_response($response, 'purge all');
    }

    public function purge_urls($urls) {
        return CF_Smart_Cache_API::instance()->execute_purge($urls);
    }

    public function purge_homepage() {
        return $this->purge_urls([home_url('/')]);
    }

    public function batch_purge($urls_to_purge) {
        return CF_Smart_Cache_API::instance()->batch_purge($urls_to_purge);
    }

    public function execute_purge($urls_to_purge) {
        return CF_Smart_Cache_API::instance()->execute_purge($urls_to_purge);
    }

    public function enqueue_purge($urls) {
        if (empty($urls)) {
            return;
        }
        $queue = get_transient('cf_smart_cache_purge_queue');
        if (!is_array($queue)) {
            $queue = array();
        }
        $queue = array_merge($queue, $urls);
        $queue = array_values(array_unique($queue));

        if (count($queue) >= 100) {
            $this->flush_queue();
            return;
        }

        set_transient('cf_smart_cache_purge_queue', $queue, 30);
        if (!wp_next_scheduled('cf_smart_cache_flush_queue_event')) {
            wp_schedule_single_event(time() + 2, 'cf_smart_cache_flush_queue_event');
        }
    }

    public function flush_queue() {
        $queue = get_transient('cf_smart_cache_purge_queue');
        if (!is_array($queue) || empty($queue)) {
            return;
        }
        delete_transient('cf_smart_cache_purge_queue');
        cf_smart_cache_log(sprintf('Flushing purge queue: %d URLs', count($queue)));
        $this->batch_purge($queue);
    }

    public function get_supported_post_types() {
        $default_types   = ['post', 'page'];
        $custom_types    = get_post_types(['public' => true, '_builtin' => false], 'names');
        $supported_types = array_merge($default_types, $custom_types);
        return apply_filters('cf_smart_cache_supported_post_types', $supported_types);
    }

    public function get_post_purge_urls($post_id) {
        $cache_key = 'cf_smart_cache_purge_urls_' . $post_id;
        $cached = wp_cache_get($cache_key, 'cf_smart_cache');
        if (false !== $cached) {
            return $cached;
        }

        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, $this->get_supported_post_types())) {
            wp_cache_set($cache_key, [], 'cf_smart_cache', 300);
            return [];
        }

        $meta_key = '_cf_smart_cache_purge_hash';
        $stored   = get_post_meta($post_id, $meta_key, true);
        if (is_array($stored) && isset($stored['urls'], $stored['hash'])) {
            $current_hash = $this->purge_urls_hash($post_id, $post);
            if ($current_hash === $stored['hash']) {
                wp_cache_set($cache_key, $stored['urls'], 'cf_smart_cache', 300);
                return $stored['urls'];
            }
        }

        $urls = [
            home_url('/'),
            get_permalink($post_id)
        ];
        if ($post->post_type === 'post') {
            $categories = get_the_category($post_id);
            if (!empty($categories)) {
                foreach ($categories as $category) {
                    $urls[] = get_category_link($category->term_id);
                }
            }
            $tags = get_the_tags($post_id);
            if (!empty($tags)) {
                foreach ($tags as $tag) {
                    $urls[] = get_tag_link($tag->term_id);
                }
            }
            $year   = get_the_time('Y', $post_id);
            $month  = get_the_time('m', $post_id);
            $urls[] = get_year_link($year);
            $urls[] = get_month_link($year, $month);
            $urls[] = get_author_posts_url($post->post_author);
        }
        $taxonomies = get_object_taxonomies($post->post_type, 'objects');
        foreach ($taxonomies as $taxonomy) {
            if ($taxonomy->public) {
                $terms = get_the_terms($post_id, $taxonomy->name);
                if (!empty($terms) && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $urls[] = get_term_link($term);
                    }
                }
            }
        }
        if ($post->post_type !== 'post' && $post->post_type !== 'page') {
            $archive_url = get_post_type_archive_link($post->post_type);
            if ($archive_url) {
                $urls[] = $archive_url;
            }
        }

        $urls = apply_filters('cf_smart_cache_post_purge_urls', array_unique($urls), $post_id, $post);

        $hash = $this->purge_urls_hash($post_id, $post);
        update_post_meta($post_id, $meta_key, ['hash' => $hash, 'urls' => $urls]);

        wp_cache_set($cache_key, $urls, 'cf_smart_cache', 300);
        return $urls;
    }

    public function purge_urls_hash($post_id, $post) {
        $terms = [];
        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $tax) {
            $t = get_the_terms($post_id, $tax);
            if (!empty($t) && !is_wp_error($t)) {
                $terms[$tax] = wp_list_pluck($t, 'term_id');
            }
        }
        return md5(serialize([
            'post_status' => $post->post_status,
            'post_type'   => $post->post_type,
            'post_author' => $post->post_author,
            'terms'       => $terms,
        ]));
    }

    public function is_post_type_allowed($post_type) {
        $settings = get_option('cf_smart_cache_settings', array());
        $allowed  = isset($settings['purge_post_types']) && is_array($settings['purge_post_types']) ? $settings['purge_post_types'] : array();
        if (empty($allowed)) {
            $post_types = get_post_types(array('public' => true));
            return in_array($post_type, $post_types, true);
        }
        return in_array($post_type, $allowed, true);
    }

    public function on_status_change($new_status, $old_status, $post) {
        if ($new_status === 'publish' || $old_status === 'publish') {
            if (!$this->is_post_type_allowed($post->post_type)) {
                return;
            }
            $urls = $this->get_post_purge_urls($post->ID);
            if (!empty($urls)) {
                cf_smart_cache_log(sprintf('Post %d (%s) status changed from %s to %s, enqueuing %d URLs', $post->ID, $post->post_type, $old_status, $new_status, count($urls)));
                $this->enqueue_purge($urls);
            }
        }
    }

    public function on_delete_post($post_id) {
        $post_type = get_post_type($post_id);
        if (!$post_type || !$this->is_post_type_allowed($post_type)) {
            delete_post_meta($post_id, '_cf_smart_cache_purge_hash');
            return;
        }
        $urls = $this->get_post_purge_urls($post_id);
        if (!empty($urls)) {
            cf_smart_cache_log(sprintf('Post %d (%s) deleted, enqueuing %d URLs', $post_id, $post_type, count($urls)));
            $this->enqueue_purge($urls);
        }
        delete_post_meta($post_id, '_cf_smart_cache_purge_hash');
    }

    public function on_term_change($term_id) {
        $urls = [get_term_link($term_id), home_url('/')];
        $this->enqueue_purge($urls);
    }

    public function purge_on_profile_change() {
        $this->enqueue_purge([home_url('/')]);
    }

    public function purge_on_menu_change() {
        $this->enqueue_purge([home_url('/')]);
    }
}
