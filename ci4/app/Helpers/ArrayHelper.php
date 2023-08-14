<?php namespace App\Helpers;

class ArrayHelper {

    /**
     * Example
     * Replace a's name
     * array: [
     *      "a": [
     *          [
     *              "name": null
     *          ]
     *      ]
     * ]
     * key: a[0].name
     * value: newName
     *
     * @param array $array
     * @param string $key
     * @param $value
     * @return mixed
     */
    public static function setValue(array &$array, string $key, $value) {
        $escapedKey = str_replace('\.', '_ESCAPED_DOT_', $key);
        $realKey = str_replace('_ESCAPED_DOT_', '.', $key);
        if (strpos($escapedKey, '.') !== false) { // Go deeper
            [$escapedKey, $rest] = explode('.', $escapedKey, 2);
            $realKey = str_replace('_ESCAPED_DOT_', '.', $escapedKey);
            if (strpos($escapedKey, '[') !== false) { // Access element by index
                [$escapedKey, $index] = explode('[', substr($escapedKey, 0, -1));
                $realKey = str_replace('_ESCAPED_DOT_', '.', $escapedKey);
                self::setValue($array[$realKey][$index], $rest, $value);
            } else {
                self::setValue($array[$realKey], $rest, $value);
            }
        } else if (strpos($escapedKey, '[') !== false) { // No more dots, so we end with an array
            [$escapedKey, $index] = explode('[', substr($escapedKey, 0, -1));
            $realKey = str_replace('_ESCAPED_DOT_', '.', $escapedKey);
            $array[$realKey][$index] = $value;
        } else {
            $array[$realKey] = $value;
        }
    }
    public static function delValue(array &$array, string $key) {
        $escapedKey = str_replace('\.', '_ESCAPED_DOT_', $key);
        $realKey = str_replace('_ESCAPED_DOT_', '.', $key);
        if (strpos($escapedKey, '.') !== false) { // Go deeper
            [$escapedKey, $rest] = explode('.', $escapedKey, 2);
            $realKey = str_replace('_ESCAPED_DOT_', '.', $escapedKey);
            if (strpos($escapedKey, '[') !== false) { // Access element by index
                [$escapedKey, $index] = explode('[', substr($escapedKey, 0, -1));
                $realKey = str_replace('_ESCAPED_DOT_', '.', $escapedKey);
                self::delValue($array[$realKey][$index], $rest);
            } else {
                self::delValue($array[$realKey], $rest);
            }
        } else if (strpos($escapedKey, '[') !== false) { // No more dots, so we end with an array
            [$escapedKey, $index] = explode('[', substr($escapedKey, 0, -1));
            $realKey = str_replace('_ESCAPED_DOT_', '.', $escapedKey);
            unset($array[$realKey][$index]);
        } else {
            unset($array[$realKey]);
        }
    }

}
