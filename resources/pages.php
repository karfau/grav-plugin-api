<?php
namespace Grav\Plugin\Api;
require_once 'resource.php';

use Grav\Common\Filesystem\Folder;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\File\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Pages API
 */
class Pages extends Resource
{
    /**
     * Get the pages list
     *
     * Implements:
     *
     * - GET /api/pages
     *
     * @return array the pages list
     */
    public function getList()
    {
        $pagesCollection = $this->grav['pages']->all();

        $return = [];

        foreach($pagesCollection as $page) {
            $return[$page->route()] = [];
            $return[$page->route()]['title'] = $page->title();
            $return[$page->route()]['url'] = $page->url();
            $return[$page->route()]['visible'] = $page->visible();
            $return[$page->route()]['isDir'] = $page->isDir();
            $return[$page->route()]['published'] = $page->published();
        }

        return $return;
    }

    /**
     * Get a single page
     *
     * Implements:
     *
     * - GET /api/pages/:page
     *
     * @return array the single page
     */
    public function getItem()
    {
        $pages = $this->grav['pages'];
        $page = $pages->dispatch('/' . $this->getIdentifier(), false);
        return $this->buildPageStructure($page);
    }

    /**
     * Create a new page
     *
     * Implements:
     *
     * - POST /api/pages/:page
     *
     * @todo:
     *
     * @return array the single page
     */
/*    public function postItem()
    {
        $data = $this->getPost();
        $pages = $this->grav['pages'];

        $page = $pages->dispatch('/' . $this->getIdentifier(), false);
        if ($page !== null) {
            // Page already exists
            $this->setErrorCode(403);
            $message = $this->buildReturnMessage('Page already exists. Cannot create a page with the same route');
            return $message;
        }

        $page = $this->page($this->getIdentifier());
        $page = $this->preparePage($page, $data);

        $page->save();

        $page = $pages->dispatch('/' . $this->getIdentifier(), false);

        return $this->buildPageStructure($page);
    }*/

    /**
     * Updates an existing page
     *
     * Implements:
     *
     * - PUT /api/pages/:page
     *
     * @todo:
     *
     * @return array the single page
     */
    public function putItem()
    {
        $data = $this->getPost();
        $pages = $this->grav['pages'];

        $page = $pages->dispatch('/' . $this->getIdentifier(), false);
        if ($page == null) {
            // Page does not exist
            $this->setErrorCode(404);
            $message = $this->buildReturnMessage('Page does not exist.');
            return $message;
        }

        $page = $this->page($this->getIdentifier());
        $page = $this->updatePageData($page, $data);
        $page->save();

        $page = $pages->dispatch('/' . $this->getIdentifier(), false);

        return $this->buildPageStructure($page);
    }

    /**
     * Deletes an existing page
     *
     * If the 'lang' param is present in the request header, it only deletes a single
     * language if there are other languages set for that page.
     *
     * Implements:
     *
     * - DELETE /api/pages/:page
     *
     * @todo:
     *
     * @return bool
     */
/*    public function deleteItem()
    {
        $data = $this->getPost();

        $pages = $this->grav['pages'];
        $page = $pages->dispatch('/' . $this->getIdentifier(), false);
        if ($page == null) {
            // Page does not exist
            $this->setErrorCode(404);
            $message = $this->buildReturnMessage('Page does not exist.');
            return $message;
        }

        try {
            if (isset($data->lang) && count($page->translatedLanguages()) > 1) {
                $language = trim(basename($page->extension(), 'md'), '.') ?: null;
                $filename = str_replace($language, $data->lang, $page->name());
                $path = $page->path() . DS . $filename;
                $page->filePath($path);

                $page->file()->delete();
            } else {
                Folder::delete($page->path());
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Deleting page failed on error: ' . $e->getMessage());
        }

        return '';
    }*/


//
    /**
     * source https://github.com/gosseti/grav-plugin-api/blob/b3e5fb09820e10b74d8f8e9ad52653da2788be47/resources/pages.php
     *
     * @param \Grav\Common\Page\Page $page
     * @return array
     */
    public static function getPageMedia($page) {
        $allmedia = $page->media()->all();
        $media = array();
        foreach ($allmedia as $item) {
            $mi = array();
            $mi['type'] = $item->type;
            $mi['mime'] = $item->mime;
            $mi['filename'] = $item->filename;
            $mi['width'] = $item->width;
            $mi['height'] = $item->height;
            $media[] = $mi;
        }
        return $media;
    }

    /**
     * Build a page structure
     *
     * @todo: add commented fields
     *
     * @param \Grav\Common\Page\Page $page
     * @return array the single page
     */
    public static function buildPageStructure($page) {
        // TODO what about possible fields for the page type?
        return [
            'content' => $page->rawMarkdown(),
            'header' => $page->header(),
            'route' => $page->routeCanonical(),
            'media' => self::getPageMedia($page),
        ];
    }

    /**
     * Returns edited page.
     *
     * @param bool $route
     *
     * @return Page
     */
    private function page($route = false)
    {
        $path = $route;
        if (!isset($this->pages[$path])) {
            $this->pages[$path] = $this->getPage($path);
        }
        return $this->pages[$path];
    }

    /**
     * Returns the page creating it if it does not exist.
     *
     * @todo: Copied from Admin Plugin. Refactor to use in both
     *
     * @param $path
     *
     * @return Page
     */
    private function getPage($path)
    {
        /** @var Pages $pages */
        $pages = $this->grav['pages'];

        if ($path && $path[0] != '/') {
            $path = "/{$path}";
        }

        $page = $path ? $pages->dispatch($path, true) : $pages->root();

        if (!$page) {
            $slug = basename($path);

            if ($slug == '') {
                return null;
            }

            $ppath = str_replace('\\', '/' , dirname($path));

            // Find or create parent(s).
            $parent = $this->getPage($ppath != '/' ? $ppath : '');

            // Create page.
            $page = new Page;
            $page->parent($parent);
            $page->filePath($parent->path() . '/' . $slug . '/' . $page->name());

            // Add routing information.
            $pages->addPage($page, $path);

            // Set if Modular
            $page->modularTwig($slug[0] == '_');

            // Determine page type.
            if (isset($this->session->{$page->route()})) {
                // Found the type and header from the session.
                $data = $this->session->{$page->route()};

                $header = ['title' => $data['title']];

                if (isset($data['visible'])) {
                    if ($data['visible'] == '' || $data['visible']) {
                        // if auto (ie '')
                        $children = $page->parent()->children();
                        foreach ($children as $child) {
                            if ($child->order()) {
                                // set page order
                                $page->order(1000);
                                break;
                            }
                        }
                    }

                }

                if ($data['name'] == 'modular') {
                    $header['body_classes'] = 'modular';
                }

                $name = $page->modular() ? str_replace('modular/', '', $data['name']) : $data['name'];
                $page->name($name . '.md');
                $page->header($header);
                $page->frontmatter(Yaml::dump((array)$page->header(), 10, 2, false));
            } else {
                // Find out the type by looking at the parent.
                $type = $parent->childType() ? $parent->childType() : $parent->blueprints()->get('child_type',
                    'default');
                $page->name($type . CONTENT_EXT);
                $page->header();
            }
            $page->modularTwig($slug[0] == '_');
        }

        return $page;
    }

    /**
     * Prepare a page to be stored: update its folder, name, template, header and content
     *
     * @param Page $page
     * @param object                 $post
     */
    public static function updatePageData(Page &$page, $post = null)
    {
        $post = (array)$post;

        if (isset($post['header'])) {
            $header = $post['header'];
            $page->header((object) $header);
            $page->frontmatter(Yaml::dump((array) $page->header()));
        }

        if (isset($post['content'])) {
            $page->rawMarkdown((string) $post['content']);
            $page->content((string) $post['content']);
        }


        return $page;
    }
}
