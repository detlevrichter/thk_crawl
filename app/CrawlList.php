<?php

class CrawlList extends Model
{
    

    public const CRAWL_LIST_TABLE = 'crawl_list';
    public const CRAWL_LIST_URL = 'url';
    public const CRAWL_LIST_CRAWLED_AT = 'crawled_at';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected string $table = self::CRAWL_LIST_TABLE;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        self::CRAWL_LIST_URL,
        self::CRAWL_LIST_CRAWLED_AT,
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        self::CRAWL_LIST_URL => 'string',
        self::CRAWL_LIST_CRAWLED_AT => 'datetime',
    ];

    /**
     * The attributes for which can use sort in url.
     *
     * @var array
     */
    protected $allowedSorts = [
    //    self::CREATED_AT,
        self::CRAWL_LIST_URL,
    ];

    /**
     * Name of columns to which http filter can be applied
     *
     * @var array
     */
    protected $allowedFilters = [
//        self::CRAWL_LIST_URL => Like::class,
    ];
}
