<?php

/**
 * Class Adyen_Payment_Helper_Pci
 * @see https://www.pcisecuritystandards.org/documents/pci_ssc_quick_guide.pdf
 */
class Adyen_Payment_Helper_Pci
{
    /** @var array  */
    protected static $_sensitiveDataKeys = ['holdername', 'expiryyear', 'expirymonth', 'issuenumber', 'cvc', 'number'];

    /** @var array  */
    protected static $_sensitiveElementPatterns;

    /**
     * Set patterns for matching sensitive strings
     */
    public function __construct()
    {
        if (isset(self::$_sensitiveElementPatterns)) {
            return;
        }

        foreach (self::$_sensitiveDataKeys as $key) {
            self::$_sensitiveElementPatterns[] = '/(<ns1:' . $key . '>)(.*?)(<\/ns1:' . $key . '>)/i';
        }
    }

    /**
     * Recursively work through an array object obscuring the values of sensitive keys
     * Obscure any substrings matched as sensitive XML elements
     * @param mixed $object
     * @return mixed Original type of object
     */
    public function obscureSensitiveData($object)
    {
        if (is_array($object)) {
            return $this->_obscureSensitiveArray($object);
        }

        if ($object instanceof ArrayAccess) {
            return $this->_obscureSensitiveObject($object);
        }

        if (is_string($object) || is_numeric($object)) {
            return $this->_obscureSensitiveElements($object);
        }

        return $object;
    }

    /**
     * @param array $array
     * @return array
     */
    protected function _obscureSensitiveArray(array $array)
    {
        foreach ($array as $key => $value) {
            $array[$key] = $this->_obscureSensitiveKeyValue($key, $value);
        }
        return $array;
    }

    /**
     * @param ArrayAccess $object E.g. Varien_Object
     * @return ArrayAccess
     */
    protected function _obscureSensitiveObject(ArrayAccess $object)
    {
        foreach ($object as $key => $value) {
            $object[$key] = $this->_obscureSensitiveKeyValue($key, $value);
        }
        return $object;
    }

    /**
     * Replace any matched sensitive strings with an obscured version
     * @param string $string
     * @return string
     */
    protected function _obscureSensitiveElements($string)
    {
        return preg_replace_callback(self::$_sensitiveElementPatterns, function($matches) {
            return $matches[1] . $this->_obscureString($matches[2]) . $matches[3];
        }, $string);
    }

    /**
     * Replace all but first and last characters with *
     * @param $string
     * @return string
     */
    protected function _obscureString($string)
    {
        $len = strlen($string);
        if ($len > 3) {
            return substr($string, 0, 1) . str_repeat('*', $len - 2) . substr($string, -1);
        }
        return str_repeat('*', $len);
    }

    /**
     * Return value, obscured if sensitive based on key and value
     * @param $key
     * @param $value
     * @return mixed
     */
    protected function _obscureSensitiveKeyValue($key, $value)
    {
        // Is this a sensitive key with a string or numeric value?
        if (in_array(strtolower($key), self::$_sensitiveDataKeys) && (is_string($value) || is_numeric($value))) {
            $strVal = (string) $value;
            return $this->_obscureString($strVal);
        }

        // Recursively work through the value
        return $this->obscureSensitiveData($value);
    }
}
