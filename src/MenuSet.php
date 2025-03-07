<?php

namespace Arillo\MenuManager;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * @property string $Name
 * @property string $Description
 * @property int $Sort
 * @method HasManyList MenuItems()
 */
class MenuSet extends DataObject implements PermissionProvider
{
    private static string $table_name = 'Arillo_MenuSet';

    private static array $db = [
        'Name' => 'Varchar(255)',
        'Description' => 'Text',
        'Sort' => 'Int',
    ];

    private static array $has_many = [
        'MenuItems' => MenuItem::class,
    ];

    private static array $cascade_deletes = ['MenuItems'];
    private static array $cascade_duplicates = ['MenuItems'];
    private static array $searchable_fields = ['Name', 'Description'];
    private static string $default_sort = 'Sort ASC';

    public function providePermissions(): array
    {
        return [
            'MANAGE_MENU_SETS' => _t(
                __CLASS__ . '.ManageMenuSets',
                'Manage Menu Sets'
            ),
        ];
    }

    public function validate()
    {
        $result = parent::validate();
        $existing = MenuManagerTemplateProvider::MenuSet($this->Name);

        if ($existing && $existing->ID !== $this->ID) {
            $result->addError(
                _t(
                    __CLASS__ . 'AlreadyExists',
                    'A Menu Set with the Name "{name}" already exists',
                    ['name' => $this->Name]
                ),
                ValidationResult::TYPE_ERROR
            );
        }

        return $result;
    }

    public function canCreate($member = null, $context = []): bool
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('MANAGE_MENU_SETS');
    }

    public function canDelete($member = null): bool
    {
        // Backwards compatibility for duplicate default sets
        $existing = MenuManagerTemplateProvider::MenuSet($this->Name);
        $isDuplicate = $existing && $existing->ID !== $this->ID;

        if ($this->isDefaultSet() && !$isDuplicate) {
            return false;
        }

        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('MANAGE_MENU_SETS');
    }

    public function canEdit($member = null): bool
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('MANAGE_MENU_SETS') ||
            Permission::check('MANAGE_MENU_ITEMS');
    }

    public function canView($member = null): bool
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);
        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('MANAGE_MENU_SETS') ||
            Permission::check('MANAGE_MENU_ITEMS');
    }

    public function Children()
    {
        return $this->MenuItems();
    }

    /**
     * Check if this menu set appears in the default sets config
     * @return bool
     */
    public function isDefaultSet(): bool
    {
        return in_array($this->Name, $this->getDefaultSetNames());
    }

    public function requireDefaultRecords(): void
    {
        parent::requireDefaultRecords();

        if ($this->createDefaultMenuSets()) {
            DB::alteration_message(
                sprintf(
                    'MenuSets created (%s)',
                    implode(', ', $this->getDefaultSetNames())
                ),
                'created'
            );
        }
    }

    public function createDefaultMenuSets()
    {
        if ($this->getDefaultSetNames()) {
            foreach ($this->getDefaultSetNames() as $name) {
                $existingRecord = MenuSet::get()
                    ->filter('Name', $name)
                    ->first();

                if (!$existingRecord) {
                    $set = MenuSet::create();
                    $set->Name = $name;
                    $set->write();
                }
            }
            return true;
        }
        return false;
    }

    public function getCMSFields(): FieldList
    {
        $fields = FieldList::create(TabSet::create('Root'));
        if ($this->ID != null) {
            $fields->removeByName('Name');
            $config = GridFieldConfig_RelationEditor::create();
            $fields->addFieldToTab(
                'Root.Main',
                new GridField('MenuItems', '', $this->MenuItems(), $config)
            );

            $remove = $config->getComponentByType(GridFieldDeleteAction::class);

            if ($remove) {
                $remove->setRemoveRelation(false);
            }

            $config->addComponent(new GridFieldOrderableRows('Sort'));
            $config->removeComponentsByType(
                GridFieldAddExistingAutocompleter::class
            );
            $fields->addFieldToTab(
                'Root.Meta',
                TextareaField::create(
                    'Description',
                    _t(__CLASS__ . '.DB_Description', 'Description')
                )
            );
        } else {
            $fields->addFieldToTab(
                'Root.Main',
                TextField::create(
                    'Name',
                    _t(__CLASS__ . '.DB_Name', 'Name')
                )->setDescription(
                    _t(
                        __CLASS__ . '.DB_Name_Description',
                        'This field can\'t be changed once set'
                    )
                )
            );

            $fields->addFieldToTab(
                'Root.Main',
                TextareaField::create(
                    'Description',
                    _t(__CLASS__ . '.DB_Description', 'Description')
                )
            );
        }

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    /**
     * {@inheritDoc}
     */
    public function onBeforeDelete()
    {
        $menuItems = $this->MenuItems();

        if ($menuItems instanceof DataList && count($menuItems) > 0) {
            foreach ($menuItems as $menuItem) {
                $menuItem->delete();
            }
        }

        parent::onBeforeDelete();
    }

    /**
     * Get the MenuSet names configured under MenuSet.default_sets
     *
     * @return string[]
     */
    public function getDefaultSetNames()
    {
        return $this->config()->get('default_sets') ?: [];
    }
}
