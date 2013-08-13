<?php
/**
 * Author: Denisov Denis
 * Email: denisovdenis@me.com
 * Date: 12.08.13
 * Time: 13:25
 */

if (!defined('NS_PAGE_GUESTS')) {
    define('NS_PAGE_GUESTS', 3117);

    $wgExtraNamespaces[NS_PAGE_GUESTS] = basename(__FILE__, ".php");
    $wgNamespaceProtection[NS_PAGE_GUESTS] = array('PageGuestsEdit');
    $wgGroupPermissions['*']['PageGuestsEdit'] = false;
}

$wgExtensionFunctions[] = 'wfSetupPageGuests';
$wgExtensionCredits['other'][] = array(
    'path' => __FILE__,
    'name' => 'PageGuests',
    'author' => '[http://www.facebook.com/denisovdenis Denisov Denis]',
    'url' => 'https://github.com/Undev/MediaWiki-PageGuests',
    'description' => 'The expansion allows you to gather information on who has viewed the page.',
    'version' => 0.2,
);
$wgExtensionMessagesFiles[] = dirname(__FILE__) . '/PageGuests.i18n.php';

class PageGuests
{
    /**
     * @var WikiPage
     */
    private $page;
    /**
     * @var User
     */
    private $guest;

    /**
     * @var int Interval before save current last visited guest in minutes
     */
    private $interval = 10;

    public function __construct()
    {
        global $wgHooks;

        $wgHooks['OutputPageBeforeHTML'][] = $this;
        $wgHooks['SkinTemplateNavigation'][] = $this;
    }

    public function __toString()
    {
        return __CLASS__;
    }

    public function init()
    {
        try {
            $this->page = RequestContext::getMain()->getWikiPage();
            $this->guest = RequestContext::getMain()->getUser();
        } catch (Exception $e) {
            throw new Exception(__CLASS__ . wfMessage('pageguests-error-extension')->inContentLanguage()->plain());
        }

        return true;
    }

    public function onOutputPageBeforeHTML(OutputPage &$out, &$text)
    {
        try {
            $this->init();
        } catch (Exception $e) {
            return false;
        }

        if ($this->isSpecialPage()) {
            $text = $this->generateGuestPage();
        } else {
            if ($guests = $this->getGuests()) {
                $lastUser = reset($guests);
                $lastUserVisit = strtotime(key($guests));

                if ($lastUser->getId() != $this->guest->getId()) {
                    $this->setGuest();
                } else {
                    if ($lastUserVisit < strtotime('-' . $this->interval . ' minutes')) {
                        $this->setGuest();
                    }
                }
            } else {
                $this->setGuest();
            }
        }

        return true;
    }

    public function onSkinTemplateNavigation(SkinTemplate &$sktemplate, array &$links)
    {
        $isActive = $link = $talkUrl = '';

        if (NS_PAGE_GUESTS === $this->page->getTitle()->getNamespace()) {
            $isActive = 'selected';
            foreach ($links['namespaces'] as &$namespace) {
                if (isset($namespace['class'])) {
                    $namespace['class'] = 'new';
                }
            }

            $isCategory = explode(':', $this->page->getTitle()->getText());

            if (count($isCategory) > 1) {
                $isCategory[0] = 'Category_talk';
                $talkUrl = implode(':', $isCategory);
            } else {
                $talkUrl = 'Talk:' . $this->page->getTitle()->getText();
            }

            $links['namespaces']['talk']['href'] = '/' . $talkUrl;
        }

        if (NS_TALK === $this->page->getTitle()->getNamespace()) {
            $link =  $this->page->getTitle()->getFullText();
        }

        if (NS_CATEGORY_TALK === $this->page->getTitle()->getNamespace()) {
            $link = 'Category:' .  $this->page->getTitle()->getText();
        }

        $links['namespaces']['activity'] = array(
            'class' => $isActive,
            'text' => wfMessage('pageguests-tab-title')->inContentLanguage()->plain(),
            'href' => '/' . __CLASS__ . ':' . $link,
        );

        return true;
    }

    private function isSpecialPage()
    {
        if (NS_PAGE_GUESTS !== $this->page->getTitle()->getNamespace()) {
            return false;
        }

        return true;
    }

    private function getPermissions()
    {
        if (!in_array('sysop', $this->guest->getGroups())) {
            return false;
        }

        return true;
    }

    private function tableName()
    {
        return 'page_guests';
    }

    private function getGuests($pageId = 0)
    {
        $pageId = $pageId === 0 ? $this->page->getId() : $pageId;

        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select(
            $this->tableName(),
            array('page_id', 'user_id', 'created_at'),
            array('page_id ' => $pageId),
            __METHOD__,
            array(
                'ORDER BY' => 'id DESC',
                'LIMIT' => 100,
            )
        );

        if (empty($res) or $res->numRows() == 0) {
            return false;
        }

        foreach ($res as $row) {
            $users[$row->created_at] = User::newFromId($row->user_id);
        }

        return $users;
    }

    private function setGuest()
    {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->insert(
            $this->tableName(),
            array(
                'page_id' => $this->page->getId(),
                'user_id' => $this->guest->getId(),
            ),
            __METHOD__
        );

        if (!$res) {
            throw new Exception(wfMessage('pageguests-error-save')->inContentLanguage()->plain());
        }

        return true;
    }

    private function generateGuestPage()
    {
        if ($this->getPermissions()) {
            global $wgLang;
            $html = wfMessage('pageguests-specialpage-header')->inContentLanguage()->plain();
            $page = Title::newFromText($this->page->getTitle()->getText())->getArticleID();
            $guests = $this->getGuests($page);

            if (empty($guests)) {
                $html = wfMessage('pageguests-specialpage-empty')->inContentLanguage()->plain();
            } else {
                $lastDay = $wgLang->date(strtotime(key($guests)));
                $day = null;
                foreach ($guests as $dateTime => $guest) {
                    if ($day != $lastDay) {
                        $lastDay = $day ? $day : $lastDay;
                        $html .= "<h2>$lastDay</h2><br>";
                    }

                    $day = $wgLang->date(strtotime($dateTime));
                    $time = $wgLang->time(strtotime($dateTime));

                    $name = $guest->getRealName() ? $guest->getRealName() : $guest->getName();
                    $html .= "<small>$time</small> <a href=\"{$guest->getUserPage()}\">$name</a><br>\n";
                }
            }
        } else {
            $html = wfMessage('pageguests-specialpage-restriction')->inContentLanguage()->plain();
        }

        return $html;
    }
}

function wfSetupPageGuests()
{
    global $wgPageGuests;

    $wgPageGuests = new PageGuests;
}
