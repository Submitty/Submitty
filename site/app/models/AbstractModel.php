<?php


namespace app\models;

/**
 * Class AbstractModel
 *
 * Base model class that all other models should inherit from as its parent.
 *
 * @package app\models
 */
abstract class AbstractModel {
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
                $return[$key] = $this->parseObject($value);
            }
        }
        else {
            $return = $object;
        }
        return $return;
    }
}