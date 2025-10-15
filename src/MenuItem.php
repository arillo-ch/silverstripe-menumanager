<?php
namespace Arillo\MenuManager;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\View\Parsers\URLSegmentFilter;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * @property string $MenuTitle
 * @property string $LinkType
 * @property string $URL
 * @property int $Sort
 * @property bool $IsNewWindow
 * @property string $Anchor
 * @method SiteTree Page()
 * @method MenuSet MenuSet()
 * @method File File()
 */
class MenuItem extends DataObject implements PermissionProvider
{
    private static string $table_name = 'Arillo_MenuItem';

    private static array $db = [
        'MenuTitle' => 'Varchar(255)',
        'LinkType' => 'Varchar(255)',
        'URL' => 'Text',
        'Sort' => 'Int',
        'IsNewWindow' => 'Boolean',
        'Anchor' => 'Varchar(255)',
    ];

    private static array $defaults = [
        'LinkType' => 'internal',
    ];

    private static array $has_one = [
        'Page' => SiteTree::class,
        'MenuSet' => MenuSet::class,
        'File' => File::class,
    ];

    private static array $owns = ['File'];

    private static array $searchable_fields = ['MenuTitle', 'Page.Title'];
    private static array $summary_fields = [
        'MenuTitle',
        'Link',
        'LinkTypeNice',
    ];

    private static string $default_sort = 'Sort';

    /**
     * @var ?string
     */
    protected $linkCached = null;

    public function providePermissions(): array
    {
        return [
            'MANAGE_MENU_ITEMS' => _t(
                __CLASS__ . '.ManageMenuItems',
                'Manage Menu Items'
            ),
        ];
    }

    public function canCreate($member = null, $context = []): bool
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        return Permission::checkMember($member, 'MANAGE_MENU_ITEMS');
    }

    public function canDelete($member = null): bool
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('MANAGE_MENU_ITEMS');
    }

    public function canEdit($member = null): bool
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('MANAGE_MENU_ITEMS');
    }

    public function canView($member = null): bool
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('MANAGE_MENU_ITEMS');
    }

    public function getCMSFields(): FieldList
    {
        $fields = FieldList::create(
            TabSet::create('Root')->addExtraClass('menu-manager-tabset')
        );

        $fields->addFieldsToTab('Root.Main', [
            OptionsetField::create('LinkType', '', $this->getLinkTypes()),
            TextField::create(
                'MenuTitle',
                _t(__CLASS__ . '.MenuTitle', 'Link Label')
            )->setDescription(
                _t(
                    __CLASS__ . '.MenuTitle_Description',
                    'If left blank, will default to the selected page\'s name.'
                )
            ),
            (new Wrapper(
                TreeDropdownField::create(
                    'PageID',
                    _t(__CLASS__ . '.DB_PageID', 'Page on this site'),
                    SiteTree::class
                )->setDescription(
                    _t(
                        __CLASS__ . '.DB_PageID_Description',
                        'Leave blank if you wish to manually specify the URL below.'
                    )
                )
            ))
                ->displayIf('LinkType')
                ->isEqualTo('internal')
                ->end(),
            TextField::create('Anchor', _t(__CLASS__ . '.DB_Anchor', 'Anchor'))
                ->displayIf('LinkType')
                ->isEqualTo('internal')
                ->end(),
            TextField::create('URL', _t(__CLASS__ . '.DB_URL', 'URL'))
                ->setDescription(
                    _t(
                        __CLASS__ . '.DB_URL_Description',
                        'Enter a full URL to link to another website.'
                    )
                )
                ->displayIf('LinkType')
                ->isEqualTo('external')
                ->end(),
            (new Wrapper(
                UploadField::create(
                    'File',
                    _t(__CLASS__ . '.DB_File', 'File')
                )->setFolderName('Uploads/menu-items')
            ))
                ->displayIf('LinkType')
                ->isEqualTo('file')
                ->end(),
            CheckboxField::create(
                'IsNewWindow',
                _t(__CLASS__ . '.DB_IsNewWindow', 'Open in a new window?')
            ),
        ]);

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    public function Link($action = null)
    {
        if ($this->linkCached) {
            return $this->linkCached;
        }
        $link = null;
        switch (true) {
            case $this->LinkType == 'internal' && $this->Page()->exists():
                $link = $this->Page()->Link($action);
                if ($this->Anchor) {
                    $link .= '#' . preg_replace('/^#/', '', $this->Anchor);
                }
                break;

            case $this->LinkType == 'external' && $this->URL:
                $link = $this->URL;
                break;

            case $this->LinkType == 'file' && $this->File()->exists():
                $link = $this->File()->Link();
                break;
        }

        $this->invokeWithExtensions('updateLink', $link);
        return $this->linkCached = $link;
    }

    public function Parent()
    {
        return $this->MenuSet();
    }

    public function getTitle(): ?string
    {
        return $this->MenuTitle;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->MenuTitle) {
            $this->MenuTitle = $this->generateMenuTitle();
        }
    }

    protected function generateMenuTitle()
    {
        switch (true) {
            case $this->LinkType == 'internal' && $this->Page()->exists():
                return $this->Page()->Title;

            case $this->LinkType == 'file' && $this->File()->exists():
                return $this->File()->Title;

            default:
                return null;
        }
    }

    public function getLinkTypes(): array
    {
        $types = [
            'internal' => _t(
                __CLASS__ . '.LinkType_internal',
                'Link to an internal page'
            ),
            'external' => _t(
                __CLASS__ . '.LinkType_external',
                'Link to an external page, email or phone number'
            ),
            'file' => _t(__CLASS__ . '.LinkType_file', 'Link to a file'),
        ];

        $this->invokeWithExtensions('updateLinkTypes', $types);
        return $types;
    }

    public function LinkTypeNice(): string
    {
        return $this->getLinkTypes()[$this->LinkType] ?? $this->LinkType;
    }

    /**
     * Attempts to return the $field from this MenuItem
     * If $field is not found or it is not set then attempts
     * to return a similar field on the associated Page
     * (if there is one)
     *
     * @param string $field
     * @return mixed
     */
    public function __get($field)
    {
        $default = parent::__get($field);

        if ($default || $field === 'ID') {
            return $default;
        } elseif ($this->getField('LinkType') == 'internal') {
            $page = $this->Page();

            if ($page instanceof DataObject) {
                if ($page->hasMethod($field)) {
                    return $page->$field();
                } else {
                    return $page->$field;
                }
            }
        }
    }

    public function getLinkingMode()
    {
        if ($this->LinkType == 'internal' && $this->PageID) {
            return $this->Page()->LinkingMode();
        }
        return 'link';
    }

    public function getURLSegment()
    {
        switch (true) {
            case $this->LinkType == 'internal' && $this->Page()->exists():
                return $this->Page()->URLSegment;

            case $this->LinkType == 'file' && $this->File()->exists():
                return (new URLSegmentFilter())->filter($this->File()->Title);

            default:
                return null;
        }
    }

    public function fieldLabels($includerelations = true)
    {
        return array_merge(parent::fieldLabels($includerelations), [
            'MenuTitle' => _t(__CLASS__ . '.MenuTitle', 'Menu title'),
            'LinkTypeNice' => _t(__CLASS__ . '.LinkType', 'Typ'),
        ]);
    }
}
