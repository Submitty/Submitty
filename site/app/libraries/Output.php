<?php

namespace app\libraries;
use app\exceptions\OutputException;

/**
 * Class Output
 *
 * We us this class to act as a wrapper around Twig as well as to hold our output
 * as we build it before final output either when we output at the end of the calling
 * class or if the application has thrown an uncaught exception
 */

class Output {
    private static $output_buffer = "";
    private static $loaded_views = array();

    private function __construct() {}
    private function __clone() {}

    public static function initializeOutput() {

    }

    /**
     * This function loads a ViewClass (if not done so already) and then calls the
     * requested ViewFunction passing it the rest of the vargs (2...) and adds it to
     * the current Output buffer. The first argument is a string if it's a top level
     * view or an array of strings if its a view in a subdirectory/sub-namespace.
     * Additionally, we only pass in just the non "View" part of the class name that
     * we are looking for.
     *
     * Output::render("Error", "errorPage", $message)
     * Would load views\ErrorView->errorPage($message)
     *
     * Output::render(array("submission", "Global"), "header")
     * Would load views\submission\GlobalView->header()
     */
    public static function render() {
        if (func_num_args() < 2) {
            throw new \InvalidArgumentException("Render requires at least two parameters (View, Function)");
        }
        $args = func_get_args();
        if (is_array($args[0])) {
            $args[0] = implode("\\", $args[0]);
        }
        $func = call_user_func_array(array(static::getView($args[0]), $args[1]), array_slice($args, 2));
        if ($args[0] == 'Error') {
            print $func;
            var_dump($func);
        }
        if ($func === false) {
            throw new OutputException("Cannot find function '{$args[1]}' in requested view '{$args[0]}'");
        }
        static::$output_buffer .= $func;
    }

    /**
     * Returns the requested view, initializing it if it's never been called before.
     * All views inheriet from BaseView which make them be a singleton and have the
     * getInstance method.
     *
     * @param string $view
     *
     * @return string
     */
    private static function getView($view) {
        if(!isset(static::$loaded_views[$view])) {
            $class = "app\\views\\{$view}View";
            /** @noinspection PhpUndefinedMethodInspection */
            static::$loaded_views[$view] = new $class;
        }

        return static::$loaded_views[$view];
    }

    /**
     * Returns the stored output buffer that we've been building
     *
     * @return string
     */
    public static function getOutput() {
        return static::$output_buffer;
    }

    /**
     * Display an error to the user as a general "500" type error as we should
     * only realistically be hitting this on "abnormal" usage
     * (coming from ExceptionHandler generally) and we are just aborting.
     * For handled exceptions, we would specify this within the execution as
     * just another possible view (such as viewing an invalid rubric id).
     * Additionally, we almost always want to die when we called this method, but
     * we've included a way to not die mainly just so that we can test this function.
     *
     * @param string $exception
     * @param bool $die
     *
     * @return string
     */
    public static function showException($exception = "", $die = true) {
        /** @noinspection PhpUndefinedMethodInspection */
        $exceptionPage = static::getView("Error")->exceptionPage($exception);
        // @codeCoverageIgnore
        if ($die) {
            die($exceptionPage);
        }

        return $exceptionPage;
    }

    /**
     * Display an error to the user as a general "500" type error as we should
     * only realistically be hitting this on "abnormal" usage
     * (coming from ExceptionHandler generally) and we are just aborting.
     * For handled exceptions, we would specify this within the execution as
     * just another possible view (such as viewing an invalid rubric id).
     * Additionally, we almost always want to die when we called this method, but
     * we've included a way to not die mainly just so that we can test this function.
     *
     * @param string $error
     * @param bool $die
     *
     * @return string
     */
    public static function showError($error = "", $die = true) {
        /** @noinspection PhpUndefinedMethodInspection */
        $errorPage = static::getView("Error")->errorPage($error);
        // @codeCoverageIgnore
        if ($die) {
            die($errorPage);
        }

        return $errorPage;
    }
}