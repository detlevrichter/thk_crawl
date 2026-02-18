<?php

class Model
{
    /**
     * Statische PDO-Verbindung, die von allen Models geteilt wird.
     *
     * @var PDO|null
     */
    protected static ?PDO $connection = null;

    /**
     * Tabellenname (muss im Kind-Model gesetzt werden).
     *
     * @var string
     */
    protected string $table = '';

    /**
     * Primärschlüssel-Feldname.
     *
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * Model-Attribute (Key/Value).
     *
     * @var array
     */
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $pdo = DB::DB()->PDO();
        self::setConnection($pdo);
        $this->fill($attributes);
    }

    /**
     * Verbindung setzen (einmalig).
     */
    public static function setConnection(PDO $pdo): void
    {
        self::$connection = $pdo;
    }

    /**
     * Attribute befüllen.
     */
    public function fill(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Magische Getter.
     */
    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }
    public function getByAttribute(array $attributes, $ausgabe = PDO::FETCH_OBJ)  {
        $ak = array_keys($attributes);
        $w = [];
        foreach($ak as $k ){
        $w[]=  $k .' like '.':'.$k;
        }
        $where = implode(' AND ',$w);
        $sql = sprintf(
            "SELECT * FROM %s WHERE (%s)",
            $this->table,
            $where
        );
        
        $stmt = self::$connection->prepare($sql);
        $result = $stmt->execute($attributes);
        $r= $stmt->fetchAll($ausgabe);
        return $r;
    }
    /**
     * Magische Setter.
     */
    public function __set($key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Speichert das Model (Insert oder Update).
     */
    public function save(): bool
    {
        if (!self::$connection) {
            throw new Exception("Keine Datenbankverbindung gesetzt. Bitte Model::setConnection() verwenden.");
        }

        if (empty($this->table)) {
            throw new Exception("Kein Tabellenname definiert.");
        }

        // Prüfen, ob es schon existiert
        if (!empty($this->attributes[$this->primaryKey])) {
            return $this->performUpdate();
        } else {
            return $this->performInsert();
        }
    }

    protected function normalizeForDatabase($value)
    {
        if ($value === null) {
            return null;
        }

        // bool -> int
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        // DateTime -> String
        if ($value instanceof \DateTimeInterface) {
            $format = property_exists($this, 'dateFormat') && $this->dateFormat
                ? $this->dateFormat
                : 'Y-m-d H:i:s';
            return $value->format($format);
        }

        // int/float/string bleiben wie sie sind
        if (is_scalar($value)) {
            return $value;
        }

        // Objekte/Arrays -> JSON (oder __toString() wenn vorhanden)
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        if (is_array($value) || is_object($value)) {
            try {
                return json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                );
            } catch (\JsonException $e) {
                throw new \InvalidArgumentException(
                    'JSON-Serialisierung fehlgeschlagen: ' . $e->getMessage()
                );
            }
        }

        // resources u. ä. sind nicht speicherbar
        throw new \InvalidArgumentException('Nicht speicherbarer Attribut-Typ: ' . gettype($value));
    }

    protected function performInsert(): bool
    {
        $this->attributes['created_at'] = date('Y-m-d H:i:s');
        $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        // Arbeitskopie
        $data = $this->attributes;

        // Primärschlüssel beim Insert raus, wenn leer
        if (array_key_exists($this->primaryKey, $data) && empty($data[$this->primaryKey])) {
            unset($data[$this->primaryKey]);
        }

        if (empty($data)) {
            throw new \Exception("Keine Daten zum Einfügen vorhanden.");
        }

        // Spalten/Parameter aufbauen + Werte normalisieren
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($data as $col => $val) {
            // Sicherheitshalber noch einmal PK filtern, falls null
            if ($col === $this->primaryKey && ($val === null || $val === '')) {
                continue;
            }

            $columns[] = $col;
            $placeholders[] = ':' . $col;
            $params[$col] = $this->normalizeForDatabase($val);
        }

        if (empty($columns)) {
            throw new \Exception("Keine gültigen Spalten zum Einfügen.");
        }

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $stmt = self::$connection->prepare($sql);
        $result = $stmt->execute($params);

        // PK nachziehen
        if ($result && !isset($this->attributes[$this->primaryKey])) {
            $this->attributes[$this->primaryKey] = self::$connection->lastInsertId();
        }

        return $result;
    }
    protected function performUpdate(): bool
    {
        $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        $keys = array_keys($this->attributes);
        $updates = implode(", ", array_map(fn($k) => "$k = :$k", $keys));

        $sql = "UPDATE {$this->table} SET $updates WHERE {$this->primaryKey} = :pk";
        $stmt = self::$connection->prepare($sql);

        $params = $this->attributes;
        $params['pk'] = $this->attributes[$this->primaryKey];

        return $stmt->execute($params);
    }

    public function logThrowable(Throwable $t) {
        trigger_error(  sprintf(
            "[%s] %s in %s:%d\nStacktrace:\n%s\n\n",
            date('Y-m-d H:i:s'),
            $t->getMessage(),
            $t->getFile(),
            $t->getLine(),
            $t->getTraceAsString()
        ));
    }
}