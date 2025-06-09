<?php

namespace Concrete\Package\Iubenda\Block\IubendaDoc;

use CHttpClient\Client;
use Concrete\Core\Block\BlockController;
use Concrete\Core\Cache\Level\ExpensiveCache;
use Concrete\Core\Editor\LinkAbstractor;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\File\Tracker\FileTrackableInterface;
use Concrete\Core\Localization\Localization;
use Concrete\Core\Page\Page;
use Concrete\Core\Statistics\UsageTracker\AggregateTracker;
use Concrete\Core\Utility\Service\Xml;
use Exception;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

defined('C5_EXECUTE') or die('Access denied.');

class Controller extends BlockController implements FileTrackableInterface
{
    const DOCTYPE_PRIVACYPOLICY_SIMPLIFIED = 'privacy-policy-simplified';

    const DOCTYPE_PRIVACYPOLICY_FULL = 'privacy-policy-full';

    const DOCTYPE_COOKIEPOLICY = 'cookie-policy';

    const DOCTYPE_TERMS_AND_CONDITIONS = 'terms-and-conditions';

    const RENDERMETHOD_POPUP = 'popup';

    const RENDERMETHOD_LINK = 'link';

    const RENDERMETHOD_EMBED_DYNAMIC = 'embed-dynamic';

    const RENDERMETHOD_EMBED_STATIC = 'embed-static';

    const LINKSTYLE_NONE = 'none';

    const LINKSTYLE_LIGHT = 'light';

    const LINKSTYLE_DARK = 'dark';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btInterfaceWidth
     */
    protected $btInterfaceWidth = 600;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btInterfaceHeight
     */
    protected $btInterfaceHeight = 500;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutput
     */
    protected $btCacheBlockOutput = true;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutputOnPost
     */
    protected $btCacheBlockOutputOnPost = true;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btCacheBlockOutputForRegisteredUsers
     */
    protected $btCacheBlockOutputForRegisteredUsers = false;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$supportSavingNullValues
     */
    protected $supportSavingNullValues = true;

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$helpers
     */
    protected $helpers = [];

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btTable
     */
    protected $btTable = 'btIubendaDoc';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$btExportContentColumns
     */
    protected $btExportContentColumns = ['linkInnerHtml'];

    /**
     * @var \Concrete\Core\Statistics\UsageTracker\AggregateTracker|null
     */
    protected $tracker;

    /**
     * @var string|null
     */
    protected $docType;

    /**
     * @var int|string|null
     */
    protected $docID;

    /**
     * @var string|null
     */
    protected $renderMethod;

    /**
     * @var bool|int|string|null
     */
    protected $whiteLabel;

    /**
     * @var string|null
     */
    protected $linkInnerHtml;

    /**
     * @var string|null
     */
    protected $linkStyle;

    /**
     * @var string|null
     */
    protected $cssClasses;

    /**
     * @var bool|int|string|null
     */
    protected $noMarkup;

    /**
     * @var int|string|null
     */
    protected $docCacheLifetime;

    public function getBlockTypeName()
    {
        return t('Iubenda Document');
    }

    public function getBlockTypeDescription()
    {
        return t('Add a Iubenda document to your website.');
    }

    public function add()
    {
        $this->docType = self::DOCTYPE_PRIVACYPOLICY_SIMPLIFIED;
        $this->docID = null;
        $this->renderMethod = self::RENDERMETHOD_POPUP;
        $this->whiteLabel = true;
        $this->linkInnerHtml = '';
        $this->linkStyle = self::LINKSTYLE_NONE;
        $this->cssClasses = '';
        $this->noMarkup = false;
        $this->docCacheLifetime = 3600;
        $this->edit();
    }

    public function edit()
    {
        $this->set('form', $this->app->make('helper/form'));
        $this->set('editor', $this->app->make('editor'));
        $this->set('docTypes', $this->getDocTypes());
        $this->set('docType', $this->docType);
        $this->set('docID', empty($this->docID) ? null : (int) $this->docID);
        $this->set('renderMethods', $this->getRenderMethods());
        $this->set('renderMethod', $this->renderMethod);
        $this->set('whiteLabel', !empty($this->whiteLabel));
        $this->set('linkInnerHtml', LinkAbstractor::translateFromEditMode($this->linkInnerHtml));
        $this->set('linkStyles', $this->getLinkStyles());
        $this->set('linkStyle', $this->linkStyle);
        $this->set('cssClasses', $this->cssClasses);
        $this->set('noMarkup', !empty($this->noMarkup));
        $this->set('docCacheLifetime', (int) $this->docCacheLifetime);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::validate()
     */
    public function validate($data)
    {
        $check = $this->normalize($data);

        return is_array($check) ? null : $check;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::save()
     */
    public function save($data)
    {
        $data = $this->normalize($data);
        if (!is_array($data)) {
            throw new UserMessageException(implode("\n", $data->getList()));
        }
        parent::save($data);
        $this->linkInnerHtml = $data['linkInnerHtml'];
        if (version_compare(APP_VERSION, '9.0.2') < 0) {
            $this->getTracker()->track($this);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::delete()
     */
    public function delete()
    {
        if (version_compare(APP_VERSION, '9.0.2') < 0) {
            $this->getTracker()->forget($this);
        }
        parent::delete();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\File\Tracker\FileTrackableInterface::getUsedCollection()
     */
    public function getUsedCollection()
    {
        return $this->getCollectionObject();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\File\Tracker\FileTrackableInterface::getUsedFiles()
     */
    public function getUsedFiles()
    {
        return static::getUsedFilesIn($this->linkInnerHtml);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::export()
     */
    public function export(SimpleXMLElement $blockNode)
    {
        parent::export($blockNode);
        if (version_compare(APP_VERSION, '9.4.0') < 0) {
            $linkInnerHtml = (string) $blockNode->data->record->linkInnerHtml;
            if ($linkInnerHtml !== '') {
                $linkInnerHtmlFixed = LinkAbstractor::export($linkInnerHtml);
                if ($linkInnerHtmlFixed !== $linkInnerHtml) {
                    unset($blockNode->data->record->linkInnerHtml);
                    $xmlService = $this->app->make(Xml::class);
                    if (method_exists($xmlService, 'createChildElement')) {
                        $xmlService->createChildElement($blockNode->data->record, 'linkInnerHtml', $linkInnerHtmlFixed);
                    } else {
                        $xmlService->createCDataNode($blockNode->data->record, 'linkInnerHtml', $linkInnerHtmlFixed);
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::getImportData()
     */
    protected function getImportData($blockNode, $page)
    {
        $args = parent::getImportData($blockNode, $page);
        if (version_compare(APP_VERSION, '9.2.1') < 0) {
            if (isset($blockNode->data->record->linkInnerHtml)) {
                $args['linkInnerHtml'] = LinkAbstractor::import((string) $blockNode->data->record->linkInnerHtml);
            }
        }

        return $args;
    }

    private function normalize($data)
    {
        $normalized = [
            'docType' => isset($data['docType']) && is_string($data['docType']) ? trim($data['docType']) : '',
            'docID' => isset($data['docType']) && is_numeric($data['docID']) ? (int) $data['docID'] : 0,
            'renderMethod' => isset($data['renderMethod']) && is_string($data['renderMethod']) ? trim($data['renderMethod']) : '',
            'whiteLabel' => empty($data['whiteLabel']) ? 0 : 1,
            'linkInnerHtml' => isset($data['linkInnerHtml']) && is_string($data['linkInnerHtml']) ? trim($data['linkInnerHtml']) : '',
            'linkStyle' => isset($data['linkStyle']) && is_string($data['linkStyle']) ? trim($data['linkStyle']) : '',
            'cssClasses' => isset($data['cssClasses']) && is_string($data['cssClasses']) ? trim($data['cssClasses']) : '',
            'noMarkup' => empty($data['noMarkup']) ? 0 : 1,
            'docCacheLifetime' => empty($data['docCacheLifetime']) || !is_numeric(($data['docCacheLifetime'])) ? 0 : (int) ($data['docCacheLifetime']),
        ];
        $errors = $this->app->make('error');
        if (!array_key_exists($normalized['docType'], $this->getDocTypes())) {
            $errors->add('Please specify the document type');
        }
        if ($normalized['docID'] <= 0) {
            $errors->add('Please specify the document ID');
        }
        if (!array_key_exists($normalized['renderMethod'], $this->getRenderMethods())) {
            $errors->add('Please specify the render method');
        }
        if (!array_key_exists($normalized['linkStyle'], $this->getLinkStyles())) {
            if ($normalized['renderMethod'] === self::RENDERMETHOD_POPUP) {
                $errors->add('Please specify the popup style');
            } else {
                $normalized['linkStyle'] = self::LINKSTYLE_NONE;
            }
        }
        if ($normalized['linkInnerHtml'] !== '') {
            $normalized['linkInnerHtml'] = LinkAbstractor::translateTo($normalized['linkInnerHtml']);
        }

        return $errors->has() ? $errors : $normalized;
    }
    /**
     * @return array
     */
    private function getDocTypes()
    {
        return [
            self::DOCTYPE_PRIVACYPOLICY_SIMPLIFIED => t('Privacy Policy (simplified)'),
            self::DOCTYPE_PRIVACYPOLICY_FULL => t('Privacy Policy (full)'),
            self::DOCTYPE_COOKIEPOLICY => t('Cookie Policy'),
            self::DOCTYPE_TERMS_AND_CONDITIONS => t('Terms and Conditions'),
        ];
    }

    /**
     * @return array
     */
    private function getRenderMethods()
    {
        return [
            self::RENDERMETHOD_POPUP => t('Popup'),
            self::RENDERMETHOD_LINK => t('Link'),
            self::RENDERMETHOD_EMBED_DYNAMIC => t('Embed in the page (dynamic)'),
            self::RENDERMETHOD_EMBED_STATIC => t('Embed in the page (static)'),
        ];
    }

    /**
     * @return array
     */
    private function getLinkStyles()
    {
        return [
            self::LINKSTYLE_NONE => tc('Style', 'None'),
            self::LINKSTYLE_LIGHT => tc('Style', 'Light'),
            self::LINKSTYLE_DARK => tc('Style', 'Dark'),
        ];
    }

    public function registerViewAssets($outputContent = '')
    {
        switch ($this->renderMethod) {
            case self::RENDERMETHOD_POPUP:
            case self::RENDERMETHOD_EMBED_DYNAMIC:
                $this->requireAsset('iubenda-ext');
                break;
        }
    }

    public function view()
    {
        $c = Page::getCurrentPage();
        $editMode = $c && !$c->isError() && $c->isEditMode();
        $output = null;
        if ($editMode) {
            $loc = Localization::getInstance();
            $loc->pushActiveContext(Localization::CONTEXT_UI);
            try {
                switch ($this->renderMethod) {
                    case self::RENDERMETHOD_EMBED_DYNAMIC:
                    case self::RENDERMETHOD_EMBED_STATIC:
                        switch ($this->docType) {
                            case self::DOCTYPE_PRIVACYPOLICY_SIMPLIFIED:
                                $output = t('Embed Iubenda simplified privacy policy (ID: %s)', $this->docID);
                                break;
                            case self::DOCTYPE_PRIVACYPOLICY_FULL:
                                $output = t('Embed Iubenda full privacy policy (ID: %s)', $this->docID);
                                break;
                            case self::DOCTYPE_COOKIEPOLICY:
                                $output = t('Embed Iubenda cookie policy (ID: %s)', $this->docID);
                                break;
                            case self::DOCTYPE_TERMS_AND_CONDITIONS:
                                $output = t('Embed Iubenda terms and conditions (ID: %s)', $this->docID);
                                break;
                        }
                        break;
                }
            } finally {
                $loc->popActiveContext();
            }
        }
        if ($output === null) {
            switch ($this->renderMethod) {
                case self::RENDERMETHOD_POPUP:
                case self::RENDERMETHOD_EMBED_DYNAMIC:
                    $output = $this->generatePopupOrEmbed($this->renderMethod);
                    break;
                case self::RENDERMETHOD_LINK:
                    $output = $this->generateLink();
                    break;
                case self::RENDERMETHOD_EMBED_STATIC:
                    $output = $this->fetchBody();
                    break;
                default:
                    $output = $editMode ? '???' : '';
                    break;
            }
        }
        $this->set('output', $output);
    }

    /**
     * @return string
     */
    private function generatePopupOrEmbed($renderMethod)
    {
        $url = $this->buildUrl();
        $classes = array_merge(preg_split('/\s+/', (string) $this->cssClasses, -1, PREG_SPLIT_NO_EMPTY), ['iubenda-noiframe', 'iubenda-embed']);
        if ($renderMethod === self::RENDERMETHOD_EMBED_DYNAMIC) {
            $classes[] = 'iub-body-embed';
            if ($this->noMarkup) {
                $classes[] = 'iub-no-markup';
            }
        }
        if ($this->whiteLabel) {
            $classes[] = 'no-brand';
        }
        if ($this->docType === self::DOCTYPE_PRIVACYPOLICY_SIMPLIFIED) {
            $classes[] = 'iub-legal-only';
        }
        if ($renderMethod === self::RENDERMETHOD_POPUP) {
            switch ($this->linkStyle) {
                case self::LINKSTYLE_NONE:
                    $classes[] = 'iubenda-nostyle';
                    break;
                case self::LINKSTYLE_DARK:
                    $classes[] = 'iubenda-black';
                    break;
                case self::LINKSTYLE_LIGHT:
                default:
                    $classes[] = 'iubenda-white';
                    break;
            }
        }
        $title = $this->buildTitle();
        if ($renderMethod === self::RENDERMETHOD_POPUP) {
            $linkInnerHtml = $this->linkInnerHtml;
            if ($linkInnerHtml === '') {
                $linkInnerHtml = h($title);
            } else {
                $linkInnerHtml = LinkAbstractor::translateFrom($linkInnerHtml);
            }
        } else {
            $linkInnerHtml = h($title);
        }

        return '<a href="' . $url . '" class="' . implode(' ', $classes) . '" title="' . h($title) . '">' . $linkInnerHtml . '</a>';
    }

    /**
     * @return string
     */
    private function generateLink()
    {
        $linkInnerHtml = $this->linkInnerHtml;
        if ($linkInnerHtml === '') {
            $linkInnerHtml = h($this->buildTitle());
        } else {
            $linkInnerHtml = LinkAbstractor::translateFrom($linkInnerHtml);
        }
        $html = '<a href="' . h($this->buildUrl()) . '"';
        if ((string) $this->cssClasses !== '') {
            $html .= ' class="' . h($this->cssClasses) . '"';
        }
        $html .= '>' . $linkInnerHtml . '</a>';

        return $html;
    }

    /**
     * @return string
     */
    private function buildUrl()
    {
        switch ($this->docType) {
            case self::DOCTYPE_PRIVACYPOLICY_SIMPLIFIED:
            case self::DOCTYPE_PRIVACYPOLICY_FULL:
                return "https://www.iubenda.com/privacy-policy/{$this->docID}";
                break;
            case self::DOCTYPE_COOKIEPOLICY:
                return "https://www.iubenda.com/privacy-policy/{$this->docID}/cookie-policy";
            case self::DOCTYPE_TERMS_AND_CONDITIONS:
                return "https://www.iubenda.com/terms-and-conditions/{$this->docID}";
        }
    }
    /**
     * @return string
     */
    private function buildTitle()
    {
        switch ($this->docType) {
            case self::DOCTYPE_PRIVACYPOLICY_SIMPLIFIED:
            case self::DOCTYPE_PRIVACYPOLICY_FULL:
                return t('Privacy Policy');
            case self::DOCTYPE_COOKIEPOLICY:
                return t('Cookie Policy');
            case self::DOCTYPE_TERMS_AND_CONDITIONS:
                return t('Terms and Conditions');
        }
    }

    private function fetchBody()
    {
        $docCacheLifetime = (int) $this->docCacheLifetime;
        if ($docCacheLifetime < 1) {
            $cacheItem = null;
        } else {
            $cache = $this->app->make(ExpensiveCache::class);
            /** @var ExpensiveCache $cache */
            $cacheItem = $cache->isEnabled() ? $cache->getItem("iubenda-{$this->docType}-{$this->docID}-{$this->noMarkup}") : null;
        }
        if ($cacheItem !== null && $cacheItem->isHit()) {
            return $cacheItem->get();
        }
        switch ($this->docType) {
            case self::DOCTYPE_PRIVACYPOLICY_SIMPLIFIED:
                $url = "https://www.iubenda.com/api/privacy-policy/{$this->docID}/only-legal";
                break;
            case self::DOCTYPE_PRIVACYPOLICY_FULL:
                $url = "https://www.iubenda.com/api/privacy-policy/{$this->docID}";
                if ($this->noMarkup) {
                    $url .= '/no-markup';
                }
                break;
            case self::DOCTYPE_COOKIEPOLICY:
                $url = "https://www.iubenda.com/api/privacy-policy/{$this->docID}/cookie-policy";
                if ($this->noMarkup) {
                    $url .= '/no-markup';
                }
                break;
            case self::DOCTYPE_TERMS_AND_CONDITIONS:
                $url = "https://www.iubenda.com/api/termini-e-condizioni/{$this->docID}";
                if ($this->noMarkup) {
                    $url .= '/no-markup';
                }
                break;
        }
        $content = $this->fetchBodyFrom($url);
        if ($cacheItem !== null) {
            $cache->save($cacheItem->expiresAfter($docCacheLifetime)->set($content));
        }

        return $content;
    }

    private function fetchBodyFrom($url)
    {
        $response = $this->app->make(Client::class)->get($url);
        try {
            $data = $response->getBodyJson();
        } catch (Exception $_) {
            $data = null;
        } catch (Throwable $_) {
            $data = null;
        }
        if (is_array($data) && isset($data['success'])) {
            if ($data['success'] === true && isset($data['content']) && is_string($data['content'])) {
                return $data['content'];
            }
            if ($data['success'] === false && isset($data['error']) && is_string($data['error'])) {
                throw new RuntimeException(t('Failed to fetch Iubenda document from URL %s: %s', $url, $data['error']));
            }
        }
        throw new RuntimeException(t('Failed to fetch Iubenda document from URL %s: %s', $url, $response->getBody()));
    }

    /**
     * @return \Concrete\Core\Statistics\UsageTracker\AggregateTracker
     */
    protected function getTracker()
    {
        if ($this->tracker === null) {
            $this->tracker = $this->app->make(AggregateTracker::class);
        }

        return $this->tracker;
    }

    /**
     * @param string|null $richText
     *
     * @return int[]|string[]
     */
    protected static function getUsedFilesIn($richText)
    {
        $richText = (string) $richText;
        if ($richText === '') {
            return [];
        }
        $rxIdentifier = '(?<id>[1-9][0-9]{0,18})';
        if (method_exists(\Concrete\Core\File\File::class, 'getByUUID')) {
            $rxIdentifier = '(?:(?<uuid>[0-9a-fA-F]{8}(?:-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12})|' . $rxIdentifier . ')';
        }
        $result = [];
        $matches = null;
        foreach ([
            '/\<concrete-picture[^>]*?\bfID\s*=\s*[\'"]' . $rxIdentifier . '[\'"]/i',
            '/\bFID_DL_' . $rxIdentifier . '\b/',
        ] as $rx) {
            if (!preg_match_all($rx, $richText, $matches)) {
                continue;
            }
            $result = array_merge($result, array_map('intval', array_filter($matches['id'])));
            if (isset($matches['uuid'])) {
                $result = array_merge($result, array_map('strtolower', array_filter($matches['uuid'])));
            }
        }

        return $result;
    }
}
