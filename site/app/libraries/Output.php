<?php

namespace app\libraries;

use app\controllers\GlobalController;
use app\exceptions\OutputException;
use app\libraries\FileUtils;
use app\models\Breadcrumb;
use app\views\ErrorView;
use Aptoma\Twig\Extension\MarkdownEngine\ParsedownEngine;
use Aptoma\Twig\Extension\MarkdownExtension;
use Ds\Set;

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
    private $page_name = "";
    private $loaded_views = array();

    /** @var Set */
    private $css;
    /** @var Set */
    private $js;

    private $use_header = true;
    private $use_footer = true;
    private $use_mobile_viewport = false;

    private $start_time;

    /** @var \Twig\Environment $twig */
    private $twig = null;
    /** @var \Twig\Loader\LoaderInterface $twig */
    private $twig_loader = null;
    /** @var GlobalController $controller */
    private $controller;

    /**
     * @var Core
     */
    protected $core;

    public function __construct(Core $core) {
        $this->core = $core;
        $this->start_time = microtime(true);
        $this->controller = new GlobalController($core);

        $this->css = new Set();
        $this->js = new Set();
    }

    /**
     * Disables the render functions that call in a file/Twig, causing them to return null
     * immediately. This allows us to not have to mock out this class when we're using
     * it for the JSON response stuff.
     */
    public function disableRender() {
        $this->render = false;
    }

    /**
     * @return bool
     */
    public function getRender() {
        return $this->render;
    }

    public function loadTwig($full_load = true) {
        $template_root = FileUtils::joinPaths(dirname(__DIR__), 'templates');
        $cache_path = FileUtils::joinPaths(dirname(dirname(__DIR__)), 'cache', 'twig');
        $debug = $full_load && $this->core->getConfig()->isDebug();

        $this->twig_loader = new \Twig\Loader\FilesystemLoader($template_root);
        $this->twig = new \Twig\Environment($this->twig_loader, [
            'cache' => $debug ? false : $cache_path,
            'debug' => $debug
        ]);

        if ($debug) {
            $this->twig->addExtension(new \Twig\Extension\DebugExtension());
        }


        $this->twig->addGlobal("core", $this->core);

        $this->twig->addFunction(new \Twig\TwigFunction("render_template", function (...$args) {
            return call_user_func_array('self::renderTemplate', $args);
        }, ["is_safe" => ["html"]]));
        $this->twig->addFunction(new \Twig\TwigFunction('base64_image', function (string $path, string $title): string {
            $valid_image_subtypes = ['png', 'jpg', 'jpeg', 'gif'];
            [$mime_type, $mime_subtype] = explode('/', mime_content_type($path), 2);
            if ($mime_type === "image" && in_array($mime_subtype, $valid_image_subtypes)) {
                // Read image path, convert to base64 encoding
                $image_data = base64_encode(file_get_contents($path));
                return <<<HTML
<img alt="${title}" src="data:image/${mime_subtype};base64,${image_data}" width="150" height="200" />
HTML;
            }
            throw new OutputException('Invalid path to image file');
        }, ['is_safe' => ['html']]));

        $this->twig->addFunction(new \Twig\TwigFunction("plurality_picker", function ($num, $single, $plural) {
            if ($num == 1) {
                return $single;
            }
            return $plural;
        }, ["is_safe" => ["html"]]));

        if ($full_load) {
            $this->twig->getExtension(\Twig\Extension\CoreExtension::class)
                ->setTimezone($this->core->getConfig()->getTimezone());
            if ($this->core->getConfig()->wrapperEnabled()) {
                $this->twig_loader->addPath(
                    FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'site'),
                    'site_uploads'
                );
            }
            $this->twig->addFunction(new \Twig\TwigFunction("feature_flag_enabled", function (string $flag): bool {
                return $this->core->getConfig()->checkFeatureFlagEnabled($flag);
            }));
        }
        $engine = new ParsedownEngine();
        $engine->setSafeMode(true);
        $this->twig->addExtension(new MarkdownExtension($engine));
    }

    public function setInternalResources() {
        $this->addVendorCss(FileUtils::joinPaths('fontawesome', 'css', 'all.min.css'));
        $this->addInternalCss(FileUtils::joinPaths('google', 'inconsolata.css'));
        $this->addInternalCss(FileUtils::joinPaths('google', 'pt_sans.css'));
        $this->addInternalCss(FileUtils::joinPaths('google', 'source_sans_pro.css'));

        $this->addVendorCss(FileUtils::joinPaths('jquery-ui', 'jquery-ui.min.css'));
        $this->addVendorCss(FileUtils::joinpaths('bootstrap', 'css', 'bootstrap-grid.min.css'));
        $this->addInternalCss('colors.css');
        $this->addInternalCss('server.css');
        $this->addInternalCss('global.css');
        $this->addInternalCss('menu.css');
        $this->addInternalCss('sidebar.css');
        $this->addInternalCss('bootstrap.css');
        $this->addInternalCss('diff-viewer.css');
        $this->addInternalCss('glyphicons-halflings.css');


        $this->addVendorJs(FileUtils::joinPaths('jquery', 'jquery.min.js'));
        $this->addVendorJs(FileUtils::joinPaths('jquery-ui', 'jquery-ui.min.js'));
        $this->addInternalJs('diff-viewer.js');
        $this->addInternalJs('server.js');
        $this->addInternalJs('menu.js');
    }

    /**
     * Similar to renderTemplate, this loads a View, but instead of returning it
     * to the user for use, it just appends it directly to the output buffer. This is
     * the general method that should be called within the application and only really
     * using renderTemplate when you plan to then use that rendered View in
     * rendering another View
     */
    public function renderOutput($view, string $function, ...$args) {
        if (!$this->render) {
            return null;
        }

        if ($this->buffer_output) {
            $this->output_buffer .= $this->renderTemplate($view, $function, ...$args);
        }
        else {
            $this->renderTemplate($view, $function, ...$args);
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
    public function renderTemplate($view, string $function, ...$args) {
        if (!$this->render) {
            return null;
        }

        if (is_array($view)) {
            $view = implode("\\", $view);
        }
        $func = call_user_func_array(array($this->getView($view), $function), $args);
        if ($func === false) {
            throw new OutputException("Cannot find function '{$function}' in requested view '{$view}'");
        }
        return $func;
    }

    /**
     * Please avoid using this function unless absolutely necessary.
     * Please use renderJsonSuccess, renderJsonFail and renderJsonError
     * instead to ensure JSON responses have consistent format.
     * @param $json
     */
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

    /**
     * Renders success/error messages and/or JSON responses.
     * @param $message
     * @param bool $success
     * @param bool $show_msg
     * @return array
     */
    public function renderResultMessage($message, $success = true, $show_msg = true) {
        if ($show_msg == true) {
            if ($success) {
                $this->core->addSuccessMessage($message);
            }
            else {
                $this->core->addErrorMessage($message);
            }
        }

        if ($success === true) {
            return $this->renderJsonSuccess($message);
        }
        else {
            return $this->renderJsonFail($message);
        }
    }

    public function renderString($string) {
        $this->output_buffer .= $string;
    }

    public function renderFile($contents, $filename, $filetype = "text/plain") {
        $this->useFooter(false);
        $this->useHeader(false);
        $this->output_buffer = $contents;
        header("Content-Type: " . $filetype);
        header("Content-Disposition: attachment; filename=" . $filename);
        header("Content-Length: " . strlen($contents));
    }

    /**
     * Render a Twig template from the templates directory
     * @param string $filename Template file basename, file should be in site/app/templates
     * @param array $context Associative array of variables to pass into the Twig renderer
     * @return string Rendered page content
     */
    public function renderTwigTemplate(string $filename, array $context = []): string {
        try {
            return $this->twig->render($filename, $context);
        }
        catch (\Twig_Error $e) {
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
    public function renderTwigOutput(string $filename, array $context = []): void {
        if ($this->buffer_output) {
            $this->output_buffer .= $this->renderTwigTemplate($filename, $context);
        }
        else {
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
    private function getView($class) {
        if (!Utils::startsWith($class, "app\\views")) {
            $class = "app\\views\\{$class}View";
        }
        if (!isset($this->loaded_views[$class])) {
            /** @noinspection PhpUndefinedMethodInspection */
            $this->loaded_views[$class] = new $class($this->core, $this);
        }

        return $this->loaded_views[$class];
    }

    public function getOutput() {
        $return = "";
        $return .= $this->renderHeader();
        $return .= $this->output_buffer;
        $return .= $this->renderFooter();
        return $return;
    }

    protected function renderHeader() {
        if ($this->use_header) {
            return $this->controller->header();
        }
        else {
            return '';
        }
    }

    protected function renderFooter() {
        if ($this->use_footer) {
            return $this->controller->footer();
        }
        else {
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
        // Load minimal twig if it hasn't been already because we're crashing
        // before $core->loadConfig() could be successfully run.
        if ($this->twig === null) {
            $this->loadTwig(false);
        }
        /** @noinspection PhpUndefinedMethodInspection */
        $exceptionPage = $this->getView(ErrorView::class)->exceptionPage($exception);
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
        $this->renderOutput(ErrorView::class, "errorPage", $error);
        // @codeCoverageIgnore
        if ($die) {
            die($this->getOutput());
        }

        return $this->getOutput();
    }

    public function addInternalCss($file, $folder = 'css') {
        $this->addCss($this->timestampResource($file, $folder));
    }

    public function addVendorCss($file) {
        $this->addCss($this->timestampResource($file, "vendor"));
    }

    public function addCss(string $url): void {
        $this->css->add($url);
    }

    public function addInternalJs($file, $folder = 'js') {
        $this->addJs($this->timestampResource($file, $folder));
    }

    public function addVendorJs($file) {
        $this->addJs($this->timestampResource($file, "vendor"));
    }

    public function addJs(string $url): void {
        $this->js->add($url);
    }

    public function timestampResource($file, $folder) {
        $timestamp = filemtime(FileUtils::joinPaths(__DIR__, '..', '..', 'public', $folder, $file));
        return $this->core->getConfig()->getBaseUrl() . $folder . "/" . $file . (($timestamp !== 0) ? "?v={$timestamp}" : "");
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

    public function enableMobileViewport(): void {
        $this->use_mobile_viewport = true;
    }

    public function useMobileViewport(): bool {
        return $this->use_mobile_viewport;
    }

    public function addBreadcrumb($string, $url = null, $external_link = false, $use_as_heading = false) {
        $this->breadcrumbs[] = new Breadcrumb($this->core, $string, $url, $external_link, $use_as_heading);
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

    public function setPageName($page_name) {
        $this->page_name = $page_name;
    }

    public function getPageName() {
        if (!empty($this->page_name)) {
            return $this->page_name;
        }
        return end($this->breadcrumbs)->getTitle();
    }

    /**
     * @return array
     */
    public function getCss(): Set {
        return $this->css;
    }

    /**
     * @return array
     */
    public function getJs(): Set {
        return $this->js;
    }

    /**
     * @return float
     */
    public function getRunTime() {
        return (microtime(true) - $this->start_time);
    }

    /**
     * Builds a URL
     * @param string[] $parts
     */
    public function buildUrl(array $parts): string {
        return $this->core->buildUrl($parts);
    }

    /**
     * Builds a course URL (starts with /<semester>/<course>)
     * @param string[] $parts
     */
    public function buildCourseUrl(array $parts): string {
        return $this->core->buildCourseUrl($parts);
    }
}
