<?php

namespace app\models;

use app\exceptions\PropertyAccessException;
use app\libraries\Core;

/**
 * Class AbstractModel
 *
 * Base model class that all other models should inherit from as its parent.
 *
 * @method bool isModified()
 */
abstract class AbstractModel {
    const CALL_SET = 1;
    const CALL_GET = 2;
    const CALL_IS = 3;

    protected static $properties = [];

    protected static $cache = [];

    /** @var Core */
    protected $core;

    /** @prop @var bool flag on whether this model has been changed or not by the application layer */
    protected $modified = false;

    public function __construct(Core $core) {
        $this->core = $core;
        if (!isset(static::$properties[get_class($this)])) {
            $this->setupProperties();
        }
    }

    /**
     * Converts a model to an array object that can then be (primarily) used in something like a JSON response
     * especially if used within an API setup. By default, this just cycles through all properties for a given
     * model instance adding a new key with the property name and pointing to its value. For non-basic types
     * (such as other models), we expect them to also have implemented this (or an equivalently named) function
     * for conversion. Any properties we don't want included we can just define as being "private" and then
     * get_object_vars will not have access to it (it'll only see variables defined as protected or public)
     *
     * @return array
     */
    public function toArray() {
        return $this->parseObject(get_object_vars($this));
    }

    /**
     * Helper function for converting the object to an array. This recursive function calls "toArray()" on any
     * object that is found, while calling itself for any arrays that are within the object. Any other type that
     * is passed to this function is returned as is (such as primitive types).
     *
     * @param mixed $object
     * @param bool $check_property
     * @return mixed
     */
    protected function parseObject($object, $check_property = true) {
        if (is_object($object)) {
            if (is_a($object, 'app\Models\AbstractModel') || is_subclass_of($object, 'app\Models\AbstractModel')) {
                /** @noinspection PhpUndefinedMethodInspection */
                $return = $object->toArray();
            }
            else {
                $return = get_class($object);
            }
        }
        elseif (is_array($object)) {
            $return = [];
            foreach ($object as $key => $value) {
                if (is_numeric($key) || (!$check_property || isset(static::$properties[get_class($this)][$key]))) {
                    $return[$key] = $this->parseObject($value, false);
                }
            }
        }
        else {
            $return = $object;
        }
        return $return;
    }

    protected function setupProperties() {
        $class = new \ReflectionClass($this);
        $class_name = get_class($this);
        foreach ($class->getProperties() as $property) {
            $doc_comment = $property->getDocComment();
            $prop_name = $property->getName();
            if ($doc_comment !== false) {
                if (preg_match("/ @prop\-write[\s]/", $doc_comment) === 1) {
                    static::$properties[$class_name][$prop_name] = [
                        'write_only' => true,
                    ];
                }
                elseif (preg_match("/ @prop\-read[\s]/", $doc_comment) === 1) {
                    static::$properties[$class_name][$prop_name] = [
                        'read_only' => true,
                    ];
                }
                elseif (preg_match("/ @(prop|property)[\s]/", $doc_comment) === 1) {
                    static::$properties[$class_name][$prop_name] = [];
                }

                if (isset(static::$properties[$class_name][$prop_name])) {
                    $matches = [];
                    preg_match("/@var (.+?)[ \n\*]/s", $property->getDocComment(), $matches);
                    if (count($matches) > 0 && $matches[1][0] !== '@') {
                        static::$properties[$class_name][$prop_name]['type'] = $matches[1];
                    }
                }
            }
        }
    }

    /**
     * Magic function which we can use to scaffold our get* and set* functions without having to explicitly define
     * all of them. Additionally, this will handle type coercions on the set* operation so long as the type is one
     * that's recognized and that the property has been documented with a "@var <type>".
     *
     * @link http://php.net/manual/en/language.oop5.overloading.php#object.call
     *
     * @param string $name
     * @param mixed[] $arguments
     *
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($name, $arguments) {
        $class_name = get_class($this);
        if (!isset(static::$cache[$class_name][$name])) {
            if (str_starts_with($name, "set")) {
                static::$cache[$class_name][$name] = [
                    "function_type" => AbstractModel::CALL_SET,
                    "property_name" => $this->convertName($name)
                ];
            }
            elseif (str_starts_with($name, "get")) {
                static::$cache[$class_name][$name] = [
                    "function_type" => AbstractModel::CALL_GET,
                    "property_name" => $this->convertName($name)
                ];
            }
            elseif (str_starts_with($name, "is")) {
                static::$cache[$class_name][$name] = [
                    "function_type" => AbstractModel::CALL_IS,
                    "property_name" => $this->convertName($name, 2)
                ];
            }
        }
        $function_type = static::$cache[$class_name][$name]["function_type"] ?? 0;
        $property_name = static::$cache[$class_name][$name]["property_name"] ?? "";
        if ($function_type === AbstractModel::CALL_SET) {
            $value = $arguments[0];
            if (isset(static::$properties[$class_name][$property_name])) {
                if (isset(static::$properties[$class_name][$property_name]['read_only'])) {
                    throw new PropertyAccessException("Cannot write to read-only property ${property_name}");
                }
                $type = static::$properties[$class_name][$property_name];
                switch ($type) {
                    case 'int':
                    case 'integer':
                        $value = intval($value);
                        break;
                    case 'string':
                        $value = strval($value);
                        break;
                    case 'float':
                        $value = floatval($value);
                        break;
                    case 'bool':
                    case 'boolean':
                        $value = $value === true;
                        break;
                }
                if ($this->$property_name !== $value) {
                    $this->modified = true;
                    $this->$property_name = $value;
                }
                return null;
            }
        }
        elseif ($function_type === AbstractModel::CALL_GET) {
            if (
                isset(static::$properties[$class_name][$property_name])
                && isset(static::$properties[$class_name][$property_name]['write_only'])
            ) {
                throw new PropertyAccessException("Cannot read write-only property ${property_name}");
            }

            return $this->$property_name;
        }
        elseif ($function_type === AbstractModel::CALL_IS) {
            if (
                isset(static::$properties[$class_name][$property_name])
                && isset(static::$properties[$class_name][$property_name]['write_only'])
            ) {
                throw new PropertyAccessException("Cannot read write-only property ${property_name}");
            }

            return $this->$property_name === true;
        }

        throw new \BadMethodCallException('Call to undefined method ' . __CLASS__ . '::' . $name . '()');
    }

    /**
     * Internal function that given a string, removes the first 3 characters, lowercases the first character of this
     * new string, and then for any other capital letter, lowercases and prefixes a '_' infront of it.
     *
     * ex: "setMinimumGradingGroup" -> "minimum_grading_group"
     *
     * @param string $name
     *
     * @return string
     */
    private function convertName($name, $prefix_length = 3) {
        $regex_func = function ($matches) {
            return "_" . strtolower($matches[0]);
        };
        $name = preg_replace_callback("/([A-Z])/", $regex_func, lcfirst((substr($name, $prefix_length))));
        return $name;
    }
}
