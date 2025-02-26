<?php

namespace Arillo\MenuManager\Tasks;

use SilverStripe\Dev\Debug;
use Arillo\MenuManager\MenuSet;
use SilverStripe\Dev\BuildTask;
use Arillo\MenuManager\MenuItem;
use SilverStripe\Control\Director;
use SilverStripe\ORM\Queries\SQLSelect;

class MigrateFromHeydayMenus extends BuildTask
{
    public function run($request)
    {
        $oldMenus = (new SQLSelect())->setFrom('MenuSet')->execute();

        if (!$oldMenus->numRecords()) {
            $this->log('Aboring: no menus to migrate');
            return;
        }

        if (MenuItem::get()->count()) {
            $this->log(
                'Aboring: there are already menu items in the new system'
            );
            return;
        }

        foreach ($oldMenus as $oldMenu) {
            $f = [
                'Name' => $oldMenu['Name'],
            ];

            if (isset($oldMenu['SubsiteID'])) {
                $f['SubsiteID'] = $oldMenu['SubsiteID'];
            }
            $menu = MenuSet::get()->filter($f)->first();

            if (!$menu) {
                $this->log('Creating new menu: ' . $oldMenu['Name']);
                $menu = new MenuSet();
                $menu->update($f)->write();
            } else {
                $this->log('Using existing menu: ' . $oldMenu['Name']);
            }

            $menu->MenuItems()->each(function (MenuItem $item) {
                $item->delete();
            });

            $oldItems = (new SQLSelect())
                ->setFrom('MenuItem')
                ->setWhere('MenuSetID = ' . $oldMenu['ID'])
                ->execute();

            if ($oldItems->numRecords()) {
                foreach ($oldItems as $oldItem) {
                    $oldItemID = $oldItem['ID'];
                    unset($oldItem['ID']);
                    unset($oldItem['MenuSetID']);
                    unset($oldItem['ClassName']);

                    $linkType = null;
                    if ($oldItem['PageID']) {
                        $linkType = 'internal';
                    } elseif ($oldItem['FileID']) {
                        $linkType = 'file';
                    } elseif ($oldItem['Link']) {
                        $linkType = 'external';
                    }

                    $oldItem['LinkType'] = $linkType;
                    $oldItem['URL'] =
                        $linkType == 'external' ? $oldItem['Link'] : null;
                    unset($oldItem['Link']);

                    $oldItem['MenuSetID'] = $menu->ID;

                    $item = new MenuItem();
                    $item->update($oldItem)->write();

                    if (
                        $item->hasExtension(
                            'TractorCow\Fluent\Extension\FluentExtension'
                        )
                    ) {
                        foreach (
                            \TractorCow\Fluent\Model\Locale::getLocales()
                            as $locale
                        ) {
                            \TractorCow\Fluent\State\FluentState::singleton()->withState(
                                function ($state) use (
                                    $item,
                                    $oldItemID,
                                    $locale
                                ) {
                                    $state->setLocale($locale->Locale);
                                    $oldLocalization = (new SQLSelect())
                                        ->setFrom('MenuItem_Localised')
                                        ->setWhere(
                                            "RecordID = {$oldItemID} AND Locale = '{$locale->Locale}'"
                                        )
                                        ->execute();
                                    if ($oldLocalization->numRecords()) {
                                        $oldLocalization = $oldLocalization
                                            ->getIterator()
                                            ->current();

                                        unset($oldLocalization['ID']);
                                        unset($oldLocalization['RecordID']);

                                        $item
                                            ->update($oldLocalization)
                                            ->write();
                                    }
                                }
                            );
                        }
                    }
                }
            } else {
                $this->log(
                    'Skipping: ' . $oldMenu['Name'] . ' has no items to migrate'
                );
            }
        }
    }

    protected function log(string $text)
    {
        if (Director::isLive()) {
            if (Director::is_cli()) {
                echo $text . PHP_EOL;
            } else {
                echo $text . '<br>';
            }
        } else {
            Debug::message($text, false);
        }
    }
}
