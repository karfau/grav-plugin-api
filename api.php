<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;

class ApiPlugin extends Plugin
{
    protected $route = 'api';

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
//            this is for /api/pages/:page
//            'onPagesInitialized' => ['onPagesInitialized', 0],
//            this one allows to access/modify pages using accept header JSON
            'onPageInitialized' => ['onPageInitialized', 0],
        ];
    }

//    public function onPagesInitialized()
//    {
//        $uri = $this->grav['uri'];
//
//        if (strpos($uri->path(), $this->config->get('plugins.api.route') . '/' . $this->route) === false) {
//            return;
//        }
//
//        $paths = $this->grav['uri']->paths();
//        $paths = array_splice($paths, 1);
//        $resource = $paths[0];
//
//        if ($resource) {
//            $file = __DIR__ . '/resources/' . $resource . '.php';
//            if (file_exists($file)) {
//                require_once $file;
//                $resourceClassName = '\Grav\Plugin\Api\\' . ucfirst($resource);
//                $resource = new $resourceClassName($this->grav);
//                $output = $resource->execute();
//                $resource->setHeaders();
//
//                echo $output;
//            } else {
//                header('HTTP/1.1 404 Not Found');
//            }
//        }
//
//        exit();
//    }

    public function onPageInitialized()
    {

        // if we are visiting an admin route stop fiddling around
        if ( $this->isAdmin() ) {
            return;
        }

        // only change flow if the accept header was set to JSON
        $accept = $_SERVER['HTTP_ACCEPT'];
        if (strlen($accept) === 0 || strtolower($accept) !== 'application/json') {
            return;
        }

        $page = $this->grav['page'];

        require_once __DIR__ . '/resources/pages.php';
        $method = Api\Pages::getMethod();
        switch ($method) {
            case 'get':
                $this->writeJSONAndExit(Api\Pages::buildPageStructure($page));
                break;
            case 'put':
                Api\Pages::updatePageData($page, Api\Pages::getPost())->save();
                $refreshed = $this->grav['pages']->dispatch($page->routeCanonical(), false);
                $this->writeJSONAndExit(Api\Pages::buildPageStructure($refreshed));
        }
    }

    /**
     * @param array $data
     */
    public static function writeJSONAndExit ($data) {
        header('Content-type: application/json');
        echo json_encode($data);
        exit();
    }

}
