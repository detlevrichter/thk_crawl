<?php
class Offer extends Model
{

    public const OFFER_TABLE = 'offers';
   // public const OFFER_WEBTEXTS_ID = Webtexts::WEBTEXTS_TABLE . '_id';
    public const OFFER_CRAWL_LIST_ID = 'crawl_list_id';
    public const OFFER_URL = 'url';
    public const OFFER_PROVIDER = 'provider';
    public const OFFER_TITLE = 'title';
    public const OFFER_DESCRIPTION = 'description';
    public const OFFER_LEVEL = 'level';
    public const OFFER_ACTIVE = 'active';

    // Relations
    public const OFFER_CATEGORIES = 'categories';
    public const OFFER_COMPETENCIES = 'competencies';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected string $table = self::OFFER_TABLE;
    
    public function __construct(array $attributes = [])
    {
        $pdo = DB::DB()->PDO();
        self::setConnection($pdo);
        $this->fill($attributes);
        if(isset($attributes[self::OFFER_URL])){
           $dbresult = $this->getByAttribute([self::OFFER_URL => $attributes[self::OFFER_URL]]);
           if($dbresult[0] ?? false){
               $attributes['id'] = $dbresult[0]->id;
           }
        }
        parent::__construct($attributes);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        self::OFFER_CRAWL_LIST_ID,
        self::OFFER_URL,
        self::OFFER_PROVIDER,
        self::OFFER_TITLE,
        self::OFFER_DESCRIPTION,
        self::OFFER_LEVEL,
        self::OFFER_ACTIVE,
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        self::OFFER_CRAWL_LIST_ID => 'integer',
        self::OFFER_URL => 'string',
        self::OFFER_PROVIDER => 'string',
        self::OFFER_TITLE => 'string',
        self::OFFER_DESCRIPTION => 'string',
        self::OFFER_LEVEL => 'float',
        self::OFFER_ACTIVE => 'boolean',
    ];

    /**
     * The attributes for which can use sort in url.
     *
     * @var array
     */
    protected $allowedSorts = [
   /*     self::UPDATED_AT,*/
        self::OFFER_URL,
        self::OFFER_PROVIDER,
        self::OFFER_TITLE,
        self::OFFER_DESCRIPTION,
        self::OFFER_LEVEL,
        self::OFFER_ACTIVE,
    ];
    /*public function fill(array $data){
        $sql = "REPLACE INTO ".self::OFFER_TABLE." ";
    }*/
    public function updateFromLLM(array $data): bool
    {

        $level = Arr::get($data, Offer::OFFER_LEVEL);
        if (is_null($level) || Levels::tryFrom((string)$level) === null) {
            $level = 0;
        }

        $this->fill([
            Offer::OFFER_PROVIDER => Arr::get($data, Offer::OFFER_PROVIDER),
            Offer::OFFER_TITLE => Arr::get($data, Offer::OFFER_TITLE),
            Offer::OFFER_DESCRIPTION => Arr::get($data, Offer::OFFER_DESCRIPTION),
            // Offer::OFFER_PRICE => Arr::get($data, Offer::OFFER_PRICE),
            // Offer::OFFER_DURATION => Arr::get($data, Offer::OFFER_DURATION),
            // Offer::OFFER_START => Arr::get($data, Offer::OFFER_START),
            // Offer::OFFER_PLACE => Arr::get($data, Offer::OFFER_PLACE),
            Offer::OFFER_LEVEL => (float)$level,
        ]);

        $offerSaved = $this->save();

        if (!$offerSaved) {
  //          Log::info('Offer not saved with Claude data');
            return false;
        }
/*
        // dates
        $this->dates()->delete();
        
        $dates = Arr::get($data, 'dates', []);
        $dates = isset($dates) && is_array($dates) ? $dates : [];

       // Collection::make($dates)->each(function ($date) use($data) {
        foreach($dates as $date){
            $price = !empty(Arr::get($date, OfferDate::OFFER_DATE_PRICE, '')) ? Arr::get($date, OfferDate::OFFER_DATE_PRICE) : Arr::get($data, OfferDate::OFFER_DATE_PRICE);
            $start = !empty(Arr::get($date, OfferDate::OFFER_DATE_START, '')) ? Arr::get($date, OfferDate::OFFER_DATE_START) : Arr::get($data, OfferDate::OFFER_DATE_START);
            $this->dates()->create([
                OfferDate::OFFER_DATE_PRICE =>  OfferDate::cleanUpAttributeValue($price),
                OfferDate::OFFER_DATE_PRICE_NUM => OfferDate::price2num($price),
                OfferDate::OFFER_DATE_DURATION => OfferDate::cleanUpAttributeValue(!empty(Arr::get($date, OfferDate::OFFER_DATE_DURATION, '')) ? Arr::get($date, OfferDate::OFFER_DATE_DURATION) :  Arr::get($data, OfferDate::OFFER_DATE_DURATION)),
                OfferDate::OFFER_DATE_START =>  OfferDate::cleanUpAttributeValue($start),
                OfferDate::OFFER_DATE_START_DATE => OfferDate::start2date($start),
                OfferDate::OFFER_DATE_ANYTIME =>  OfferDate::isAnytime($start),
                OfferDate::OFFER_DATE_ZIP => OfferDate::cleanUpAttributeValue(!empty(Arr::get($date, OfferDate::OFFER_DATE_ZIP, '')) ? Arr::get($date, OfferDate::OFFER_DATE_ZIP) : Arr::get($data, OfferDate::OFFER_DATE_ZIP)),
                OfferDate::OFFER_DATE_PLACE => OfferDate::cleanUpPlace(!empty(Arr::get($date, OfferDate::OFFER_DATE_PLACE, '')) ? Arr::get($date, OfferDate::OFFER_DATE_PLACE) : Arr::get($data, OfferDate::OFFER_DATE_PLACE)),
                OfferDate::OFFER_DATE_IS_ONLINE => OfferDate::isOnline(Arr::get($date, OfferDate::OFFER_DATE_PLACE)),
            ]);
        };
        // Create at least on date (could be empty)
        if (count($dates) === 0) {
            $this->dates()->create([
                OfferDate::OFFER_DATE_PRICE => OfferDate::cleanUpAttributeValue(Arr::get($data, OfferDate::OFFER_DATE_PRICE), ''),
                OfferDate::OFFER_DATE_PRICE_NUM => OfferDate::price2num(Arr::get($data, OfferDate::OFFER_DATE_PRICE)),
                OfferDate::OFFER_DATE_DURATION => OfferDate::cleanUpAttributeValue(Arr::get($data, OfferDate::OFFER_DATE_DURATION), ''),
                OfferDate::OFFER_DATE_START => OfferDate::cleanUpAttributeValue(Arr::get($data, OfferDate::OFFER_DATE_START, '')),
                OfferDate::OFFER_DATE_START_DATE => OfferDate::start2date(Arr::get($data, OfferDate::OFFER_DATE_START_DATE)),
                OfferDate::OFFER_DATE_ANYTIME =>  OfferDate::isAnytime(Arr::get($data, OfferDate::OFFER_DATE_START_DATE)),
                OfferDate::OFFER_DATE_ZIP => OfferDate::cleanUpAttributeValue(Arr::get($data, OfferDate::OFFER_DATE_ZIP), ''),
                OfferDate::OFFER_DATE_PLACE => OfferDate::cleanUpPlace(Arr::get($data, OfferDate::OFFER_DATE_PLACE, '')),
                OfferDate::OFFER_DATE_IS_ONLINE => OfferDate::isOnline(Arr::get($data, OfferDate::OFFER_DATE_PLACE)),
            ]);
        }

        $categoryIds = Arr::wrap(Arr::get($data, Offer::OFFER_CATEGORIES, [])) ?? [];
        $this->categories()
            ->whereNotIn(OfferCategory::CATEGORY_ID, $categoryIds)
            ->delete();

        Collection::make($categoryIds)->each(function ($categoryId) {
            $this->categories()
                ->updateOrCreate([OfferCategory::CATEGORY_ID => $categoryId], [OfferCategory::CATEGORY_ID => $categoryId]);
        });

        $competencyIds = [];

        Collection::make(Arr::get($data, Offer::OFFER_COMPETENCIES, []))->each(function ($items, $categoryId) use (&$competencyIds) {
            Collection::make($items)->each(function ($score, $competency) use (&$competencyIds, $categoryId) {
                if (Categories::tryFrom($categoryId) === null) {
                    Log::info('Invalid category: ' . (string)$categoryId);
                    return;
                }

                if (CompetencyTypes::tryFrom($competency) === null) {
                    Log::info('Invalid competency: ' . (string)$competency);
                    return;
                }

                $offerCompetency = $this->competencies()->updateOrCreate(
                    [
                        OfferCompetency::CATEGORY_ID => $categoryId,
                        OfferCompetency::COMPETENCY => $competency,
                    ],
                    [
                        OfferCompetency::CATEGORY_ID => $categoryId,
                        OfferCompetency::COMPETENCY => $competency,
                        OfferCompetency::SCORE => $score,
                    ]
                );

                $competencyIds[] = $offerCompetency->{OfferCompetency::ID};
            });
        });

        $this->competencies()
            ->whereNotIn(OfferCompetency::ID, $competencyIds)
            ->delete();
*/
        return true;
    }










}