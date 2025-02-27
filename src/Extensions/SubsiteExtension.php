<?php

namespace Arillo\MenuManager\Extensions;

use Arillo\MenuManager\MenuSet;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

if (
    !class_exists('\SilverStripe\Subsites\Model\Subsite') ||
    !class_exists('\SilverStripe\Subsites\State\SubsiteState')
) {
    return;
}

class SubsiteExtension extends DataExtension
{
    private static $has_many = [
        'MenuSets' => MenuSet::class,
    ];

    private static $cascade_deletes = ['MenuSets'];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('MenuSets');
    }
}
