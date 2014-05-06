<?php
/**
 * REST Server implementation for Agile Toolkit
 *
 * This class takes advantage of the tight integration for Agile Toolkit
 * to enhance and make it super simple to create an awesome API for
 * your existing application.
 */
// @codingStandardsIgnoreStart because REST is acronym
class App_REST extends App_CLI
{
// @codingStandardsIgnoreEnd

    public $doc_page='app/rest';

    public $page;

    /**
     * init
     *
     * @return [type] [description]
     */
    public function init()
    {
        parent::init();
        try {
            // Extra 24-hour protection
            parent::init();

            $this->add('Logger');
            $this->add('Controller_PageManager')
                ->parseRequestedURL();

            // It's recommended that you use versioning inside your API,
            // for example http://api.example.com/v1/user
            //
            // This way version is accessible anywhere from $this->app->version
            list($this->version, $junk)=explode('_', $this->page, 2);

            // Add-ons may define additional endpoints for your API, but
            // you must activate them explicitly.
            $this->pathfinder->base_location->defineContents(['endpoint'=>'endpoint']);

        } catch (Exception $e) {
            $this->caughtException($e);
        }
    }
    /**
     * Output will be properly fromatted
     *
     * @param [type] $data [description]
     *
     * @return [type]       [description]
     */
    public function encodeOutput($data)
    {

        // TODO - use HTTP_ACCEPT here ?
        //var_Dump($_SERVER['HTTP_ACCEPT']);

        if ($_GET['format'] == 'xml') {
            throw $this->exception('only JSON format is supported', null, 406);
        }
        if ($_GET['format'] == 'json_pretty') {
            header('Content-type: application/json');
            echo json_encode($data, JSON_PRETTY_PRINT);
            exit;
        }
        if ($_GET['format'] == 'html') {
            echo '<pre>';
            echo json_encode($data, JSON_PRETTY_PRINT);
            exit;
        }
        header('Content-type: application/json');

        if($data===null)$data=array();
        echo json_encode($data);
        exit;
    }
    /**
     * main
     *
     * @return [type] [description]
     */
    public function main()
    {
        try {
            $file = $this->api->locatePath('endpoint', str_replace('_', '/', $this->page) . '.php');
            include_once $file;

            $this->pm->base_path = '/';


            try {

                $class = "endpoint_" . $this->page;
                $this->endpoint = $this->add($class);
                $this->endpoint->app = $this;
                $this->endpoint->api = $this; // compatibility

                $method=strtolower($_SERVER['REQUEST_METHOD']);
                $raw_post = file_get_contents("php://input");

                if ($raw_post && $raw_post[0]=='{') {
                    $args=json_decode($raw_post, true);
                } elseif ($method=='put') {
                    parse_str($raw_post, $args);
                } else {
                    $args=$_POST;
                }


                if($_GET['method'])$method.='_'.$_GET['method'];
                if (!$this->endpoint->hasMethod($method)) {
                    throw $this->exception('Method does not exist for this endpoint', null, 404)
                        ->addMoreInfo('method', $method)
                        ->addMoreInfo('endpoint', $this->endpoint)
                        ;
                }

                // Perform the desired action
                $this->encodeOutput($this->endpoint->$method($args));

            } catch (Exception $e) {
                http_response_code($e->getCode());

                $error = array(
                    'error'=>$e->getMessage(),
                    'type'=>get_class($e),
                    'more_info'=>$e instanceof BaseException ? $e->more_info:null
                );
                array_walk_recursive($error, function (&$item, $key) {
                    if (is_object($item)) $item=(string)$item;
                });
                $this->encodeOutput($error, null);
            }

        } catch (Exception $e) {
            $this->caughtException($e);
        }
    }
}
