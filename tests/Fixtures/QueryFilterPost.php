<?php

namespace Elyon\LaravelStandards\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class QueryFilterPost extends Model
{
    public $timestamps = false;

    protected $table = 'query_filter_posts';

    protected $guarded = [];
}
