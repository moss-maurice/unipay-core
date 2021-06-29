<?php

namespace mmaurice\unipay\core;

use \mmaurice\modx\Core;
use \mmaurice\unipay\core\classes\Logger;
use \mmaurice\unipay\core\classes\Session;

abstract class Plugin implements \mmaurice\unipay\core\interfaces\PluginInterface
{
    const PLUGIN_NAME = 'pay';
    const PLUGIN_VERSION = '0.0.1';
    const PLUGIN_AUTHOR = 'Viktor Voronkov';
    const PLUGIN_AUTHOR_EMAIL = 'kreexus@yandex.ru';

    const DEFAULT_PLACEHOLDER = 'payButton';
    const DEFAULT_TMPL = 'form';

    public $mode;
    public $urlProcessing;
    public $urlSuccess;
    public $urlFail;
    public $urlProcessingExternal;
    public $urlSuccessExternal;
    public $urlFailExternal;
    public $pageHandler = 1;
    public $queryLog;
    public $queryLogPath;
    public $placeholder;
    public $autoOrderId;
    public $debugMode;
    public $host;
    public $sessionHost;
    public $sessionName;

    protected $modx;
    protected $session;
    protected $logger;

    public function __construct(array $properties = [])
    {
        $this->applyProperties(array_merge([
            'mode' => self::MODE_TEST,
            'urlProcessing' => $this->matchPluginPath(self::ALIAS_PROCESSING),
            'urlProcessingExternal' => self::CHOICE_OFF,
            'urlSuccess' => $this->matchPluginPath(self::ALIAS_SUCCESS),
            'urlSuccessExternal' => self::CHOICE_OFF,
            'urlFail' => $this->matchPluginPath(self::ALIAS_FAIL),
            'urlFailExternal' => self::CHOICE_OFF,
            'queryLog' => self::CHOICE_OFF,
            'queryLogPath' => '~/logs',
            'placeholder' => self::DEFAULT_PLACEHOLDER,
            'autoOrderId' => self::CHOICE_ON,
            'debugMode' => self::CHOICE_OFF,
            'sessionHost' => self::PLUGIN_NAME,
            'sessionName' => self::DEFAULT_PLACEHOLDER,
            'host' => $this->matchHost(),
        ], $properties));

        $this->valideLogPath();

        $this->modx = &(new Core)->init();
        $this->session = new Session(self::PLUGIN_NAME);
        $this->logger = new Logger($this->queryLogPath);

        return true;
    }

    public function run()
    {
        $url = preg_replace('/^([^\?]*)(\?.*)$/i', '$1', $_SERVER['REQUEST_URI']);

        switch ($this->modx->event->name) {
            case 'OnPageNotFound':
                if (in_array($url, [$this->urlProcessing, $this->urlFail, $this->urlSuccess])) {
                    $this->setResponseCode(200);

                    if (($url === $this->urlProcessing) and ($this->urlProcessingExternal === 'off')) {
                        return $this->makeProcessing();
                    }

                    if (($url === $this->urlSuccess) and ($this->urlSuccessExternal === 'off')) {
                        return $this->makeSuccess();
                    }

                    if (($url === $this->urlFail) and ($this->urlFailExternal === 'off')) {
                        return $this->makeFail();
                    }
                }
            break;
            case 'OnParseDocument':
                return $this->makeForm();
            break;
            default:
                return;
            break;
        }
    }

    public function makeProcessing(array $fields = [])
    {
        return $this->modx->sendRedirect($this->matchPreviousPageLink());
    }

    public function makeSuccess(array $fields = [])
    {
        if ($this->session->has($this->placeholder)) {
            $this->session->drop($this->placeholder);

            return $this->render('messages/success', $fields);
        }

        return $this->modx->sendRedirect($this->matchPreviousPageLink());
    }

    public function makeFail(array $fields = [])
    {
        if ($this->session->has($this->placeholder)) {
            $this->session->drop($this->placeholder);

            return $this->render('messages/fail', $fields);
        }

        return $this->modx->sendRedirect($this->matchPreviousPageLink());
    }

    public function makeForm(array $fields = [])
    {
        $this->session->drop();

        $id = $this->modx->documentIdentifier;
        $content = &$this->modx->documentOutput;

        $document = [];

        if ($id and is_array($document = $this->modx->getDocument($id))) {
            $document = array_map(function($value) {
                return (!is_numeric($value) ? (!is_string($value) ? $value : htmlspecialchars($value)) : intval($value));
            }, $document);
        }

        $fields = array_merge($fields, [
            'action' => $this->urlProcessing,
            'placeholder' => $this->placeholder,
            'orderNumber' => (array_key_exists('orderNumber', $_REQUEST) ? $_REQUEST['orderNumber'] : time()),
            'id' => $id,
            'document' => $document,
        ]);

        if (preg_match('/(?:\{\{)' . trim($this->placeholder) . '(?:\}\}|\s*\?\s*([^\}]+)\}\})/i', $content, $matches)) {
            $chunkParams = $this->parseChunkTagParams(htmlentities($matches[0]));
            $tmpl = (array_key_exists('tmpl', $chunkParams) ? $chunkParams['tmpl'] : self::DEFAULT_TMPL);
            $params = array_merge($fields, $chunkParams);

            $render = $this->buildTemplate($tmpl, $params);

            $content = str_replace($matches[0], $render, $content);

            return true;
        }

        return false;
    }

    protected function applyProperties(array $properties = [])
    {
        if (is_array($properties) and !empty($properties)) {
            foreach ($properties as $name => $value) {
                if (property_exists($this, $name)) {
                    $this->$name = is_string($value) ? trim($value) : $value;
                }
            }

            return true;
        }

        return false;
    }

    protected function matchHost()
    {
        if (array_key_exists('HTTP_ORIGIN', $_SERVER) and !empty($_SERVER['HTTP_ORIGIN'])) {
            return $_SERVER['HTTP_ORIGIN'];
        }

        if (array_key_exists('HTTP_HOST', $_SERVER) and !empty($_SERVER['HTTP_HOST'])) {
            if (array_key_exists('REQUEST_SCHEME', $_SERVER) and !empty($_SERVER['REQUEST_SCHEME'])) {
                return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
            }
        }

        return null;
    }

    protected function matchPluginPath($pathPart)
    {
        return '/' . self::PLUGIN_NAME . '/' . $pathPart;
    }

    protected function matchPreviousPageLink()
    {
        if (array_key_exists('HTTP_REFERER', $_SERVER) and !empty($_SERVER['HTTP_REFERER'])) {
            if (!in_array($_SERVER['HTTP_REFERER'], [$this->urlProcessing, $this->urlFail, $this->urlSuccess])) {
                return $_SERVER['HTTP_REFERER'];
            }
        }

        return '/';
    }

    protected function valideLogPath()
    {
        $this->queryLogPath = str_replace('~', realpath(dirname(__FILE__) . '/..') . '/logs/', $this->queryLogPath);

        if (!realpath($this->queryLogPath) or !file_exists($this->queryLogPath)) {
            mkdir($this->queryLogPath, 0777, true);
        }

        if ($this->queryLogPath = realpath($this->queryLogPath)) {
            return true;
        }

        return false;
    }

    protected function parseChunkTagParams($chunkTag)
    {
        $chunkParams = [];

        if (preg_match_all('/\&[.]*([^\=\s]+)\=\`([^\`]*)\`/i', $chunkTag, $matches)) {
            foreach ($matches[0] as $index => $value) {
                $chunkParams[str_replace('amp;', '', $matches[1][$index])] = $matches[2][$index];
            }
        }

        return $chunkParams;
    }

    protected function buildTemplate($tmplName, $fields = [])
    {
        $tmplName = trim($tplName);

        $tmplPath = realpath(dirname(__FILE__) . '/..') . "/templates/custom/{$tplName}.php";

        if (!$tmplPath) {
            $tmplPath = realpath(dirname(__FILE__) . '/..') . "/templates/{$tplName}.php";
        }

        if ($tmplPath) {
            die("Template file \"{$tmplName}\" is not found!");
        }

        $fields = array_merge($fields, [
            'pluginWebRootPath' => str_replace(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $_SERVER['DOCUMENT_ROOT']), '', realpath(dirname(__FILE__) . '/..')),
            'pluginContainer' => lcfirst(static::PLUGIN_CONTAINER),
        ]);

        extract($fields, EXTR_PREFIX_SAME, 'data');

        ob_start();
        ob_implicit_flush(false);

        include($tmplPath);

        return ob_get_clean();
    }

    protected function render($tmplName, array $fields = [])
    {
        $this->modx->forwards = $this->modx->forwards - 1;
        $this->modx->documentIdentifier = $this->pageHandler;
        $this->modx->documentMethod = 'id';
        $this->modx->documentObject = $this->modx->getDocumentObject($this->modx->documentMethod, $this->modx->documentIdentifier, 'prepareResponse');
        $this->modx->documentObject['content'] = $this->buildTemplate($tmplName, $fields);
        $this->modx->documentName = $this->modx->documentObject['pagetitle'];

        if (!$this->modx->documentObject['template']) {
            $this->modx->documentContent = "[*content*]";
        } else {
            $result = $this->modx->db->select('content', $this->modx->getFullTableName("site_templates"), "id = '{$this->modx->documentObject['template']}'");

            if ($templateContent = $this->modx->db->getValue($result)) {
                $this->modx->documentContent = $templateContent;
            } else {
                $this->modx->messageQuit("Incorrect number of templates returned from database", $sql);
            }
        }

        $this->modx->minParserPasses = empty($this->modx->minParserPasses) ? 2 : $this->modx->minParserPasses;
        $this->modx->maxParserPasses = empty($this->modx->maxParserPasses) ? 10 : $this->modx->maxParserPasses;

        $passes = $this->modx->minParserPasses;

        for ($i = 0; $i < $passes; $i++) {
            if ($i == ($passes - 1)) {
                $st = strlen($this->modx->documentContent);
            }

            if ($this->modx->dumpSnippets == 1) {
                $this->modx->snippetsCode .= "<fieldset><legend><b style ='color: #821517;'>PARSE PASS " . ($i + 1) . "</b></legend><p>The following snippets (if any) were parsed during this pass.</p>";
            }

            $this->modx->documentOutput = $this->modx->documentContent;

            $this->modx->invokeEvent("OnParseDocument");

            $this->modx->documentContent = $this->modx->documentOutput;
            $this->modx->documentContent = $this->modx->mergeSettingsContent($this->modx->documentContent);
            $this->modx->documentContent = $this->modx->mergeDocumentContent($this->modx->documentContent);
            $this->modx->documentContent = $this->modx->mergeSettingsContent($this->modx->documentContent);
            $this->modx->documentContent = $this->modx->mergeChunkContent($this->modx->documentContent);

            if (isset($this->modx->config['show_meta']) and ($this->modx->config['show_meta'] == 1)) {
                $this->modx->documentContent = $this->modx->mergeDocumentMETATags($this->modx->documentContent);
            }

            $this->modx->documentContent = $this->modx->evalSnippets($this->modx->documentContent);
            $this->modx->documentContent = $this->modx->mergePlaceholderContent($this->modx->documentContent);
            $this->modx->documentContent = $this->modx->mergeSettingsContent($this->modx->documentContent);

            if ($this->modx->dumpSnippets == 1) {
                $this->modx->snippetsCode .= "</fieldset><br />";
            }

            if (($i == ($passes - 1)) and ($i < ($this->modx->maxParserPasses - 1))) {
                $et = strlen($this->modx->documentContent);

                if ($st != $et) {
                    $passes++;
                }
            }
        }

        $this->modx->outputContent();

        die();
    }
}
