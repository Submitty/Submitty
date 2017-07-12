<?php


namespace app\models;
use app\libraries\Core;
use app\libraries\Utils;

/**
 * Class AbstractModel
 *
 * Base model class that all other models should inherit from as its parent.
 *
 * @method bool isModified()
 */
abstract class AbstractModel {

    protected $properties = array();

    /** @var Core */
    protected $core;

    /** @property @var bool flag on whether this model has been changed or not by the application layer */
    protected $modified = false;

    public function __construct(Core $core) {
        $this->core = $core;
        $this->setupProperties();
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
     * @param $object
     * @return mixed
     */
    protected function parseObject($object) {
        if (is_object($object)) {
            if (is_a($object, 'app\Models\AbstractModel') || is_subclass_of($object, 'app\Models\AbstractModel')) {
                /** @noinspection PhpUndefinedMethodInspection */
                $return = $object->toArray();
            }
            else {
                $return = get_class($object);
            }
        }
        else if (is_array($object)) {
            $return = array();
            foreach ($object as $key => $value) {
                if (is_numeric($key) || isset($this->properties[$key])) {
                    $return[$key] = $this->parseObject($value);
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
        foreach ($class->getProperties() as $property) {
            $matches = array();
            preg_match("/@property/s", $property->getDocComment(), $matches);
            if (count($matches) > 0) {
                $this->properties[$property->getName()] = null;
                preg_match("/@var (.*?)[ \n\*]/s", $property->getDocComment(), $matches);
                if (count($matches) > 0) {
                    $this->properties[$property->getName()] = $matches[1];
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
     * @param $name
     * @param $arguments
     *
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($name, $arguments) {
        if (Utils::startsWith($name, "set")) {
            $property_name = $this->convertName($name);
            $value = $arguments[0];
            if (isset($this->properties[$property_name])) {
                $type = $this->properties[$property_name];
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
                }
                if ($this->$property_name !== $value) {
                    $this->modified = true;
                    $this->$property_name = $value;
                }
                return null;
            }
        }
        elseif (Utils::startsWith($name, "get")) {
            $property_name = $this->convertName($name);
            return $this->$property_name;
        }
        elseif (Utils::startsWith($name, "is")) {
            $property_name = $this->convertName($name, 2);
            return $this->$property_name === true;
        }

        throw new \BadMethodCallException('Call to undefined method '.__CLASS__.'::'.$name.'()');
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
    private function convertName($name, $prefix_length=3) {
        $regex_func = function($matches) { return "_".strtolower($matches[0]); };
        $name = preg_replace_callback("/([A-Z])/", $regex_func, lcfirst((substr($name, $prefix_length))));
        return $name;
    }
}
