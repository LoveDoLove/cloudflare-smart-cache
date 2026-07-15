<?php
class TestCacheManagerTest extends PHPUnit\Framework\TestCase
{
    public function test_ttl_returns_array()
    {
        $ttl = CF_Smart_Cache_Cache::instance()->get_ttl();
        $this->assertIsArray($ttl);
        $this->assertArrayHasKey('s-maxage', $ttl);
        $this->assertArrayHasKey('max-age', $ttl);
    }

    public function test_ttl_values_are_positive()
    {
        $ttl = CF_Smart_Cache_Cache::instance()->get_ttl();
        foreach ($ttl as $key => $value) {
            $this->assertGreaterThan(0, $value, "TTL key {$key} should be positive");
        }
    }

    public function test_ttl_applies_filters()
    {
        add_filter('cf_smart_cache_ttl', function ($ttl) {
            $ttl['s-maxage'] = 9999;
            return $ttl;
        });
        $ttl = CF_Smart_Cache_Cache::instance()->get_ttl();
        $this->assertEquals(9999, $ttl['s-maxage']);
    }
}
