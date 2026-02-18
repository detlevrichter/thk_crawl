<?php


class Arr
{
    /**
     * Prüft ob ein Key existiert (auch bei ArrayAccess).
     */
    public static function has($array, $key): bool
    {
        if (is_null($key)) {
            return false;
        }

        if (array_key_exists($key, $array)) {
            return true;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Wert mit "dot notation" holen.
     */
    public static function get($array, $key, $default = null)
    {
        if (!is_array($array)) {
            return $default;
        }

        if (is_null($key)) {
            return $array;
        }

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Wert mit "dot notation" setzen.
     */
    public static function set(&$array, $key, $value): void
    {
        if (is_null($key)) {
            $array = $value;
            return;
        }

        $keys = explode('.', $key);

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $array[$segment] = $value;
            } else {
                if (!isset($array[$segment]) || !is_array($array[$segment])) {
                    $array[$segment] = [];
                }
                $array = &$array[$segment];
            }
        }
    }

    /**
     * Wert entfernen (dot notation möglich).
     */
    public static function forget(&$array, $keys): void
    {
        $original = &$array;

        foreach ((array) $keys as $key) {
            $parts = explode('.', $key);

            $array = &$original;

            while (count($parts) > 1) {
                $part = array_shift($parts);

                if (!isset($array[$part]) || !is_array($array[$part])) {
                    continue 2;
                }

                $array = &$array[$part];
            }

            unset($array[array_shift($parts)]);
        }
    }

    /**
     * Nur bestimmte Keys holen.
     */
    public static function only($array, $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                $result[$key] = $array[$key];
            }
        }
        return $result;
    }

    /**
     * Alle außer bestimmte Keys holen.
     */
    public static function except($array, $keys): array
    {
        return array_diff_key($array, array_flip((array) $keys));
    }
    /**
     * Wrap the given value in an array.
     *
     * @param  mixed  $value
     * @return array
     */
    public static function wrap($value): array
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }
}
