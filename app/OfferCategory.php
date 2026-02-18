<?php


class OfferCategory extends Model
{

    public const CATEGORY_ID = 'category_id';
    
    public const OFFER_ID = 'offer_id';
    public const OFFER_CATEGORY_OFFER_ID = 'offer_id';
    public const OFFER_CATEGORY_TABLE = 'offer_categories';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected string $table = self::OFFER_CATEGORY_TABLE;

    public function __construct(array $attributes = [])
    {
        $pdo = DB::DB()->PDO();
        self::setConnection($pdo);
        
        $params = [];
        foreach([ OfferCategory::CATEGORY_ID  ,
                    OfferCategory::OFFER_CATEGORY_TABLE  ,
                    OfferCategory::OFFER_CATEGORY_OFFER_ID  ,  ] as $atr){
            if(isset( $attributes[$atr] )){
                $params[$atr] = $attributes[$atr];
            }
        }
        $this->fill($params);
/*
        if(isset($attributes[OfferCategory::OFFER_CATEGORY_OFFER_ID])){
           $dbresult = $this->getByAttribute([OfferCategory::OFFER_CATEGORY_OFFER_ID => $attributes[OfferCategory::OFFER_CATEGORY_OFFER_ID]]);
           if($dbresult[0] ?? false){
               $params['id'] = $dbresult[0]->id;
           }
        }*/
        parent::__construct($params);
    }

    public function purge() {
        $params[self::CATEGORY_ID] = $this->attributes[self::CATEGORY_ID];
        $params[self::OFFER_ID] = $this->attributes[self::OFFER_ID];
        DB::DB()->query("DELETE FROM {$this->table} WHERE ".self::CATEGORY_ID."= :".self::CATEGORY_ID ." AND ". self::OFFER_ID ."= :".self::OFFER_ID, $params );
        return $this;
    }




    /**
     * @var string[]
     */
    protected $fillable = [
        self::CATEGORY_ID,
    ];

    /*
      @return BelongsTo
   
    public function offer(): BelongsTo
    {
     //   return $this->belongsTo(Offer::class);
    }  */
}
