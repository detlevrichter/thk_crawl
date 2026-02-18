<?php 
class OfferDate  extends Model
{
    

    public const OFFER_DATE_TABLE = 'offer_dates';
    public const OFFER_DATE_OFFER_ID = 'offer_id';
    public const OFFER_DATE_PRICE = 'price';
    public const OFFER_DATE_PRICE_NUM = 'price_num';
    public const OFFER_DATE_DURATION = 'duration';
    public const OFFER_DATE_START = 'start';
    public const OFFER_DATE_START_DATE = 'start_date';
    public const OFFER_DATE_ANYTIME = 'anytime';
    public const OFFER_DATE_ZIP = 'zip';
    public const OFFER_DATE_IS_ONLINE = 'is_online';
    public const OFFER_DATE_PLACE = 'place';


    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected string $table = self::OFFER_DATE_TABLE;

    public function __construct(array $attributes = [])
    {
        $pdo = DB::DB()->PDO();
        self::setConnection($pdo);
        
        $params = [];
        foreach([ OfferDate::OFFER_DATE_PRICE  ,
                    OfferDate::OFFER_DATE_DURATION  ,
                    OfferDate::OFFER_DATE_ZIP  ,
                    OfferDate::OFFER_DATE_PLACE ,
                    OfferDate::OFFER_DATE_START  ,
                    OfferDate::OFFER_DATE_OFFER_ID ] as $atr){
            if(isset( $attributes[$atr] )){
                $params[$atr] = $attributes[$atr];
            }
        }
        $this->fill($params);

        if(isset($attributes[OfferDate::OFFER_DATE_OFFER_ID])){
           $dbresult = $this->getByAttribute([OfferDate::OFFER_DATE_OFFER_ID => $attributes[OfferDate::OFFER_DATE_OFFER_ID]]);
           if($dbresult[0] ?? false){
               $attributes['id'] = $dbresult[0]->id;
           }
        }
        parent::__construct($params);
    }

    public function purge() {
        $params[self::OFFER_DATE_OFFER_ID] = $this->attributes[self::OFFER_DATE_OFFER_ID];
        DB::DB()->query("DELETE FROM {$this->table} WHERE   ". self::OFFER_DATE_OFFER_ID ."= :".self::OFFER_DATE_OFFER_ID, $params );
        return $this;
    }
    public static function cleanUpAttributeValue($dirt){
        return $dirt;
    }
    public static function price2num($dirt){
        return $dirt;
    }

    public static function start2date($dirt){
        return $dirt;
    }

    public static function isAnytime($dirt){
        return $dirt;
    }

    public static function cleanUpPlace($dirt){
        return $dirt;
    }
    public static function isOnline($dirt){
        return $dirt;
    }
}