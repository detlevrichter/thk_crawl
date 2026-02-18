<?php
class Webtexts extends Model
{
    

    public const WEBTEXTS_TABLE = 'webtexts';
    public const WEBTEXTS_CRAWL_LIST_ID = CrawlList::CRAWL_LIST_TABLE . '_id';
    public const WEBTEXTS_URL = 'url';
    public const WEBTEXTS_CANONICAL_URL = 'canonical_url';
    public const WEBTEXTS_TITLE = 'title';
    public const WEBTEXTS_HTML = 'html';
    public const WEBTEXTS_TEXT = 'text';
    public const WEBTEXTS_COULD_BE_OFFER = 'could_be_offer';
    public const WEBTEXTS_COULD_BE_TOPIC = 'could_be_topic';

    public const WEBTEXTS_NEEDS_LLM = 'needs_llm';
    public const WEBTEXTS_LLM_IS_OFFER = 'llm_is_offer';
    public const WEBTEXTS_UNREACHABLE = 'unreachable';
    public const WEBTEXTS_CRAWLED_AT = 'crawled_at';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected string $table = self::WEBTEXTS_TABLE;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        self::WEBTEXTS_CRAWL_LIST_ID,
        self::WEBTEXTS_URL,
        self::WEBTEXTS_CANONICAL_URL,
        self::WEBTEXTS_TITLE,
        self::WEBTEXTS_HTML,
        self::WEBTEXTS_TEXT,
        self::WEBTEXTS_COULD_BE_OFFER,
        self::WEBTEXTS_COULD_BE_TOPIC ,
        self::WEBTEXTS_NEEDS_LLM,
        self::WEBTEXTS_LLM_IS_OFFER,
        self::WEBTEXTS_UNREACHABLE,
        self::WEBTEXTS_CRAWLED_AT,
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        self::WEBTEXTS_CRAWL_LIST_ID => 'integer',
        self::WEBTEXTS_URL => 'string',
        self::WEBTEXTS_CANONICAL_URL => 'string',
        self::WEBTEXTS_TITLE => 'string',
        self::WEBTEXTS_HTML => 'string',
        self::WEBTEXTS_TEXT => 'string',
        self::WEBTEXTS_COULD_BE_OFFER => 'boolean',
        self::WEBTEXTS_COULD_BE_TOPIC => 'boolean',
        self::WEBTEXTS_NEEDS_LLM => 'boolean',
        self::WEBTEXTS_LLM_IS_OFFER => 'boolean',
        self::WEBTEXTS_UNREACHABLE => 'boolean',
        self::WEBTEXTS_CRAWLED_AT => 'datetime',
    ];

    /**
     * @return BelongsTo
     */
   /* public function crawlList(): BelongsTo
    {
        return $this->belongsTo(CrawlList::class, static::WEBTEXTS_CRAWL_LIST_ID);
    }*/

    /**
     * @return HasOne
     */
   /* public function offer(): HasOne
    {
        return $this->hasOne(Offer::class);
    }*/
}
