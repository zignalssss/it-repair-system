<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresTimestampTest extends TestCase
{
    public function test_eloquent_timestamp_strings_keep_their_utc_instant(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires PostgreSQL timestamp with time zone semantics.');
        }

        $instant = CarbonImmutable::now('UTC')->startOfSecond();
        $row = DB::selectOne(
            'SELECT CAST(? AS timestamp with time zone) AS stored_at',
            [$instant->format('Y-m-d H:i:s')],
        );

        $this->assertTrue(
            $instant->equalTo(CarbonImmutable::parse($row->stored_at)),
            'The PostgreSQL connection must interpret Eloquent timestamp strings in UTC.',
        );
    }
}
