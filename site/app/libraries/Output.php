<?php

namespace app\libraries;
use app\controllers\GlobalController;
use app\exceptions\OutputException;
use app\models\Breadcrumb;

/**
 * Class Output
 *
 * We us this class to act as a wrapper around Twig as well as to hold our output
 * as we build it before final output either when we output at the end of the calling
 * class or if the application has thrown an uncaught exception
 */

class Output {
    /** @var bool */
    private $render = true;

    /** @var bool Should we  */
    private $buffer_output = true;

    private $output_buffer = "";
    private $breadcrumbs = array();
    private $loaded_views = array();
    private $css = array();
    private $js = array();
    
    private $use_header = true;
    private $use_footer = true;
    
    private $start_time;

    /** @var \Twig_Environment $twig */
    private $twig = null;
    /** @var \Twig_LoaderInterface $twig */
    private $twig_loader = null;
    /** @var GlobalController $controller */
    private $controller;

    /**
     * @var Core
     */
    private $core;
    
    public function __construct(Core $core) {
        $this->core = $core;
        $this->start_time = microtime(true);
        $this->controller = new GlobalController($core);
    }

    /**
     * Disables the render functions that call in a file/Twig, causing them to return null
     * immediately. This allows us to not have to mock out this class when we're using
     * it for the JSON response stuff.
     */
    public function disableRender() {
        $this->render = false;
    }

    public function loadTwig() {
        $template_root = FileUtils::joinPaths(dirname(__DIR__), 'templates');
        $cache_path = FileUtils::joinPaths(dirname(dirname(__DIR__)), 'cache', 'twig');

        $this->twig_loader = new \Twig_Loader_Filesystem($template_root);
        $this->twig = new \Twig_Environment($this->twig_loader, [
            'cache' => $this->core->getConfig()->isDebug() ? false : $cache_path,
            'debug' => $this->core->getConfig()->isDebug()
        ]);
        $this->twig->getExtension(\Twig\Extension\CoreExtension::class)
            ->setTimezone($this->core->getConfig()->getTimezone());
        $this->twig->addGlobal("core", $this->core);
        $this->twig->addFunction(new \Twig_Function("render_template", function(... $args) {
            return call_user_func_array('self::renderTemplate', $args);
        }, ["is_safe" => ["html"]]));
        if($this->core->getConfig()->wrapperEnabled()) {
            $this->twig_loader->addPath(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'site'), $namespace = 'site_uploads');
        }
    }

    public function setInternalResources() {
        $this->addVendorCss(FileUtils::joinPaths('fontawesome', 'css', 'all.min.css'));
        $this->addInternalCss(FileUtils::joinPaths('google', 'inconsolata.css'));
        $this->addInternalCss(FileUtils::joinPaths('google', 'pt_sans.css'));
        $this->addInternalCss(FileUtils::joinPaths('google', 'source_sans_pro.css'));

        $this->addVendorCss(FileUtils::joinPaths('jquery-ui', 'jquery-ui.min.css'));
        $this->addVendorCss(FileUtils::joinpaths('bootstrap', 'css', 'bootstrap-grid.min.css'));
        $this->addInternalCss('server.css');
        $this->addInternalCss('bootstrap.css');
        $this->addInternalCss('diff-viewer.css');
        $this->addInternalCss('glyphicons-halflings.css');

        $this->addVendorJs(FileUtils::joinPaths('jquery', 'jquery.min.js'));
        $this->addVendorJs(FileUtils::joinPaths('jquery-ui', 'jquery-ui.min.js'));
        $this->addInternalJs('diff-viewer.js');
        $this->addInternalJs('server.js');
    }

    /**
     * Similar to renderTemplate, this loads a View, but instead of returning it
     * to the user for use, it just appends it directly to the output buffer. This is
     * the general method that should be called within the application and only really
     * using renderTemplate when you plan to then use that rendered View in
     * rendering another View
     */
    public function renderOutput() {
        if (!$this->render) {
            return null;
        }

        if ($this->buffer_output) {
            $this->output_buffer .= call_user_func_array('self::renderTemplate', func_get_args());
        }
        else {
            echo call_user_func_array('self::renderTemplate', func_get_args());
        }
    }

    /**
     * This function loads a ViewClass (if not done so already) and then calls the
     * requested ViewFunction passing it the rest of the vargs (2...) returning the parsed
     * View to the caller. The first argument is a string if it's a top level
     * view or an array of strings if its a view in a subdirectory/sub-namespace.
     * Additionally, we only pass in just the non "View" part of the class name that
     * we are looking for.
     *
     * Output()->renderTemplate("Error", "errorPage", $message)
     * Would load views\ErrorView->errorPage($message)
     *
     * Output()->renderTemplate(array("submission", "Global"), "header")
     * Would load views\submission\GlobalView->header()
     *
     * @return string
     */
    public function renderTemplate() {
        if (!$this->render) {
            return null;
        }

        if (func_num_args() < 2) {
            throw new \InvalidArgumentException("Render requires at least two parameters (View, Function)");
        }
        $args = func_get_args();
        if (is_array($args[0])) {
            $args[0] = implode("\\", $args[0]);
        }
        $func = call_user_func_array(array(static::getView($args[0]), $args[1]), array_slice($args, 2));
        if ($func === false) {
            throw new OutputException("Cannot find function '{$args[1]}' in requested view '{$args[0]}'");
        }
        return $func;
    }
    
    public function renderJson($json) {
        $this->output_buffer = json_encode($json, JSON_PRETTY_PRINT);
        $this->useFooter(false);
        $this->useHeader(false);
    }

    /**
     * Renders a json response for the "success" case
     *  (see http://submitty.org/developer/json_responses)
     * @param mixed|null $data Response data
     * @return array the unencoded response
     */
    public function renderJsonSuccess($data = null) {
        $response = [
            'status' => 'success',
            'data' => $data
        ];

        $this->renderJson($response);

        // Because sometimes the controllers want to return the response array
        return $response;
    }

    /**
     * Renders a json response for the "fail" case
     *  (see http://submitty.org/developer/json_responses)
     * @param string $message A non-blank failure message
     * @param mixed|null $data Response data
     * @param array $extra Extra data merged into the response array
     * @return array the unencoded response
     */
    public function renderJsonFail($message, $data = null, $extra = []) {
        $response = [
            'status' => 'fail',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        // Merge $response second so it overwrites conflicting keys in $extra
        $response = array_merge($extra, $response);
        $this->renderJson($response);

        // Because sometimes the controllers want to return the response array
        return $response;
    }

    /**
     * Renders a json response for the "error" case
     *  (see http://submitty.org/developer/json_responses)
     * @param string $message A non-blank error message
     * @param mixed|null $data Response data
     * @param int $code Code to identify error case
     * @return array the unencoded response
     */
    public function renderJsonError($message, $data = null, $code = null) {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }
        if ($code !== null) {
            $response['code'] = $code;
        }
        $this->renderJson($response);

        // Because sometimes the controllers want to return the response array
        return $response;
    }
    
    public function renderString($string) {
        $this->output_buffer .= $string;
    }
    
    public function renderFile($contents, $filename, $filetype = "text/plain") {
        $this->useFooter(false);
        $this->useHeader(false);
        $this->output_buffer = $contents;
        header("Content-Type: ".$filetype);
        header("Content-Disposition: attachment; filename=".$filename);
        header("Content-Length: " . strlen($contents));
    }

    /**
     * Render a Twig template from the templates directory
     * @param string $filename Template file basename, file should be in site/app/templates
     * @param array $context Associative array of variables to pass into the Twig renderer
     * @return string Rendered page content
     */
    public function renderTwigTemplate($filename, $context = []) {
        try {
            return $this->twig->render($filename, $context);
        } catch (\Twig_Error $e) {
            throw new OutputException("{$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Render a Twig template from the templates directory and immediately output the
     * rendered page.
     * @see renderOutput() The same idea except for Twig
     * @param string $filename Template file basename, file should be in site/app/templates
     * @param array $context Associative array of variables to pass into the Twig renderer
     */
    public function renderTwigOutput($filename, $context = []) {
        if ($this->buffer_output) {
            $this->output_buffer .= $this->renderTwigTemplate($filename, $context);
        } else {
            echo $this->renderTwigTemplate($filename, $context);
        }
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
    private function getView($view) {
        if(!isset($this->loaded_views[$view])) {
            $class = "app\\views\\{$view}View";
            /** @noinspection PhpUndefinedMethodInspection */
            $this->loaded_views[$view] = new $class($this->core);
        }

        return $this->loaded_views[$view];
    }

    public function getOutput() {
        $return = "";
        $return .= $this->renderHeader();
        $return .= $this->output_buffer;
        $return .= $this->renderFooter();
        return $return;
    }

    private function renderHeader() {
        if ($this->use_header) {
            return $this->controller->header();
        }
        else {
            return '';
        }
    }

    private function renderFooter() {
        if ($this->use_footer) {
            return $this->controller->footer();
        } else {
            return '';
        }
    }

    public function bufferOutput() {
        return $this->buffer_output;
    }

    public function disableBuffer() {
        if (!$this->buffer_output) {
            return;
        }
        $this->buffer_output = false;
        echo $this->renderHeader();
        $this->use_header = false;
        echo $this->output_buffer;
    }

    /**
     * Returns the stored output buffer that we've been building
     *
     * @return string
     */
    public function displayOutput() {
        echo($this->getOutput());
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
    public function showException($exception = "", $die = true) {
        /** @noinspection PhpUndefinedMethodInspection */
        $exceptionPage = $this->getView("Error")->exceptionPage($exception);
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
    public function showError($error = "", $die = true) {
        /** @noinspection PhpUndefinedMethodInspection */
        $errorPage = static::getView("Error")->errorPage($error);
        // @codeCoverageIgnore
        if ($die) {
            die($errorPage);
        }

        return $errorPage;
    }
    
    public function addInternalCss($file, $folder='css') {
        $timestamp = filemtime(FileUtils::joinPaths(__DIR__, '..', '..', 'public', $folder, $file));
        $this->addCss($this->core->getConfig()->getBaseUrl().$folder."/".$file, $timestamp);
    }
    
    public function addVendorCss($file) {
        $timestamp = filemtime(FileUtils::joinPaths(__DIR__, '..', '..', 'public', 'vendor', $file));
        $this->addCss($this->core->getConfig()->getBaseUrl()."vendor/".$file, $timestamp);
    }

    public function addCss($url, $timestamp=0) {
        $this->css[] = $url.(($timestamp !== 0) ? "?v={$timestamp}" : '');
    }

    public function addInternalJs($file, $folder='js') {
        $timestamp = filemtime(FileUtils::joinPaths(__DIR__, '..', '..', 'public', $folder, $file));
        $this->addJs($this->core->getConfig()->getBaseUrl().$folder."/".$file, $timestamp);
    }

    public function addVendorJs($file) {
        $timestamp = filemtime(FileUtils::joinPaths(__DIR__, '..', '..', 'public', 'vendor', $file));
        $this->addJs($this->core->getConfig()->getBaseUrl()."vendor/".$file, $timestamp);
    }

    public function addJs($url, $timestamp=0) {
        $this->js[] = $url.(($timestamp !== 0) ? "?v={$timestamp}" : '');
    }
    
    /**
     * Enable or disable whether to use the global header
     * @param bool $bool
     */
    public function useHeader($bool = true) {
        $this->use_header = $bool;
    }
    
    public function useFooter($bool = true) {
        $this->use_footer = $bool;
    }
    
    public function addBreadcrumb($string, $url=null, $external_link=false) {
        $this->breadcrumbs[] = new Breadcrumb($this->core, $string, $url, $external_link);
    }

    public function addRoomTemplatesTwigPath() {
        $this->twig_loader->addPath(FileUtils::joinPaths(dirname(dirname(__DIR__)), 'room_templates'), $namespace = 'room_templates');
    }

    /**
     * @return array
     */
    public function getBreadcrumbs() {
        return $this->breadcrumbs;
    }

    /**
     * @return array
     */
    public function getCss() {
        return $this->css;
    }

    /**
     * @return array
     */
    public function getJs() {
        return $this->js;
    }

    /**
     * @return float
     */
    public function getRunTime() {
        return (microtime(true) - $this->start_time);
    }
}
