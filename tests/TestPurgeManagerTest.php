<?php
class TestPurgeManagerTest extends PHPUnit\Framework\TestCase
{
    public function test_is_post_type_allowed_with_empty_settings()
    {
        delete_option('cf_smart_cache_settings');
        $pm = CF_Smart_Cache_Purge::instance();
        $this->assertTrue($pm->is_post_type_allowed('post'));
        $this->assertTrue($pm->is_post_type_allowed('page'));
    }

    public function test_is_post_type_allowed_with_selected_types()
    {
        update_option('cf_smart_cache_settings', array(
            'purge_post_types' => array('post'),
        ));
        $pm = CF_Smart_Cache_Purge::instance();
        $this->assertTrue($pm->is_post_type_allowed('post'));
        $this->assertFalse($pm->is_post_type_allowed('page'));
    }

    public function test_enqueue_purge_adds_urls()
    {
        delete_transient('cf_smart_cache_purge_queue');
        CF_Smart_Cache_Purge::instance()->enqueue_purge(array('https://example.com/page'));
        $queue = get_transient('cf_smart_cache_purge_queue');
        $this->assertIsArray($queue);
        $this->assertContains('https://example.com/page', $queue);
    }
}
