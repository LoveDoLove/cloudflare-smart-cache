<?php
class TestStatsManagerTest extends PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        CF_Smart_Cache_Stats::instance()->reset_stats();
    }

    public function test_record_hit_increments()
    {
        CF_Smart_Cache_Stats::instance()->record_hit();
        $stats = CF_Smart_Cache_Stats::instance()->get_stats();
        $this->assertGreaterThanOrEqual(1, $stats['hits']);
    }

    public function test_record_miss_increments()
    {
        CF_Smart_Cache_Stats::instance()->record_miss('test_reason');
        $stats = CF_Smart_Cache_Stats::instance()->get_stats();
        $this->assertGreaterThanOrEqual(1, $stats['misses']);
    }

    public function test_hit_rate_calculation()
    {
        for ($i = 0; $i < 3; $i++) {
            CF_Smart_Cache_Stats::instance()->record_hit();
        }
        CF_Smart_Cache_Stats::instance()->record_miss('test');
        $stats = CF_Smart_Cache_Stats::instance()->get_stats();
        $this->assertEquals(75.0, $stats['hit_rate']);
    }

    public function test_bypass_reasons()
    {
        CF_Smart_Cache_Stats::instance()->record_miss('logged-in');
        CF_Smart_Cache_Stats::instance()->record_miss('logged-in');
        CF_Smart_Cache_Stats::instance()->record_miss('admin');
        $reasons = CF_Smart_Cache_Stats::instance()->get_bypass_reasons();
        $this->assertArrayHasKey('logged-in', $reasons);
        $this->assertArrayHasKey('admin', $reasons);
        $this->assertEquals(2, $reasons['logged-in']);
    }
}
