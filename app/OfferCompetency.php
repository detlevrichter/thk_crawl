<?php

 

class OfferCompetency extends Model
{
 

    public const ID = 'id';
    public const CATEGORY_ID = 'category_id';
    public const OFFER_ID = 'offer_id';
    public const COMPETENCY = 'competency';

    public const OFFER_COMPETENCY_TABLE = 'offer_competencies';
    public const SCORE = 'score';
    protected string $table = 'offer_competencies';

    public array $competencies = [];
    public array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $pdo = DB::DB()->PDO();
        self::setConnection($pdo);
        $this->attributes = $attributes;
        $this->competencies = DB::DB()->query("SELECT * from competency_types ORDER BY id ASC");

      /*   $params = [];
        foreach($competencies as $atr){
            if(isset( $attributes[$atr->query_value] )){
                $params[$atr->query_value] = $attributes[$atr->query_value];
            }
        }
        $this->fill($params); */

        /* if(isset($attributes[OfferCategory::OFFER_CATEGORY_OFFER_ID])){
           $dbresult = $this->getByAttribute([OfferCategory::OFFER_CATEGORY_OFFER_ID => $attributes[OfferCategory::OFFER_CATEGORY_OFFER_ID]]);
           if($dbresult[0] ?? false){
               $params['id'] = $dbresult[0]->id;
           }
        } */
        // parent::__construct($params);
    }

    public function purge() {
        $params[self::CATEGORY_ID] = $this->attributes[self::CATEGORY_ID];
        $params[self::OFFER_ID] = $this->attributes[self::OFFER_ID];
        DB::DB()->query("DELETE FROM {$this->table} WHERE ".self::CATEGORY_ID."= :".self::CATEGORY_ID ." AND ". self::OFFER_ID ."= :".self::OFFER_ID, $params );
        return $this;
    }


    public function save() :bool {
        $params = [];
        foreach($this->competencies as $com){
            $params = [];
            if(isset( $this->attributes[$com->query_value] )){
                $params[self::COMPETENCY] = $com->query_value ;
                if($com->type == 'float'){
                    $params[self::SCORE] = (float)$this->attributes[$com->query_value];

                }else{

                    $params[self::SCORE] = $this->attributes[$com->query_value];
                }
                $params[self::CATEGORY_ID] = $this->attributes[self::CATEGORY_ID];
                $params[self::OFFER_ID] = $this->attributes[self::OFFER_ID];
                $params['created_at'] = date('Y-m-d H:i:s');
                $params['updated_at'] = date('Y-m-d H:i:s');
                DB::DB()->query("INSERT INTO {$this->table} (".join(', ',array_keys($params)).") VALUES (:".join(', :',array_keys($params)).")", $params );
            }
        }
        return true;
    }

    /**
     * @var string[]
     */
    protected $fillable = [
        self::CATEGORY_ID,
        self::COMPETENCY,
        self::SCORE,
    ];

    protected $casts = [
        self::SCORE => 'float',
    ];


}
