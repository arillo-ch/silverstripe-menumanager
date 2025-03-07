# Arillo Silverstripe Menu Manager

The menu management module is for creating custom menu structures when the site
tree hierarchy just won't do.

This module is an alternative to, and is inspired by, `heyday/silverstripe-menumanager`.


## Installation

```
composer require arillo/silverstripe-menumanager
```

After completing this step, navigate in Terminal or similar to the SilverStripe
root directory and run `composer install` or `composer update` depending on
whether or not you have composer already in use.

## Usage

There are 2 main steps to creating a menu using menu management.

1. Create a new MenuSet
2. Add MenuItems to that MenuSet

### Creating a MenuSet

This is pretty straight forward. You just give the MenuSet a Name (which is what
you reference in the templates when controlling the menu).

As it is common to reference MenuSets by name in templates, you can configure
sets to be created automatically during the /dev/build task. These sets cannot
be deleted through the CMS.

```yaml
Arillo\MenuManager\MenuSet:
    default_sets:
        - Main
        - Footer
```

### Disable creating Menu Sets in the CMS

Sometimes the defined `default_sets` are all the menu's a project needs. You can
disable the ability to create new Menu Sets in the CMS:

```yml
Arillo\MenuManager\MenuAdmin:
    enable_cms_create: false
```

_Note: Non-default Menu Sets can still be deleted, to help tidy unwanted CMS
content._

### Usage in template

```html
<% loop $MenuSet('YourMenuName').MenuItems %>
<a href="{$Link}" class="{$LinkingMode}">{$MenuTitle}</a>
<% end_loop %>
```

To loop through _all_ MenuSets and their items:

    <% loop $MenuSets %>
    	<% loop $MenuItems %>
    		<a href="$Link" class="$LinkingMode">$MenuTitle</a>
    	<% end_loop %>
    <% end_loop %>

Optionally you can also limit the number of MenuSets and MenuItems that are looped through.

The example below will fetch the top 4 MenuSets (as seen in Menu Management), and the top 5 MenuItems for each:

    <% loop $MenuSets.Limit(4) %>
    	<% loop $MenuItems.Limit(5) %>
    		<a href="$Link" class="$LinkingMode">$MenuTitle</a>
    	<% end_loop %>
    <% end_loop %>

#### Enabling partial caching

[Partial caching](https://docs.silverstripe.org/en/4/developer_guides/performance/partial_caching/)
can be enabled with your menu to speed up rendering of your templates.

```html
<% with $MenuSet('YourMenuName') %> <% cached 'YourMenuNameCacheKey',
$LastEdited, $MenuItems.max('LastEdited'), $MenuItems.count %> <% if $MenuItems
%>
<nav>
    <% loop $MenuItems %>
    <a href="{$Link}" class="{$LinkingMode}"> $MenuTitle.XML </a>
    <% end_loop %>
</nav>
<% end_if %> <% end_cached %> <% end_with %>
```

### Allow sorting of MenuSets

By default menu sets cannot be sorted, however, you can set your configuration to allow it.

```yaml
Arillo\MenuManager\MenuSet:
    allow_sorting: true
```

## Subsite Support

If you're using SilverStripe Subsites, you can make MenuManager subsite aware
via applying an extension to the MenuSet.

_app/\_config/menus.yml_

```
Arillo\MenuManager\MenuSet:
  create_menu_sets_per_subsite: true
  extensions:
    - Arillo\MenuManager\Extensions\MenuSubsiteExtension
Arillo\MenuManager\MenuItem:
  extensions:
    - Arillo\MenuManager\Extensions\MenuSubsiteExtension
```

## Migrate from data from heyday/silverstripe-menumanager

Uninstall `heyday/silverstripe-menumanager` and install `arillo/silverstripe-menumanager`
Run `dev/build/?flush=all`

Afterwards you can run `dev/tasks/Arillo-MenuManager-Tasks-MigrateFromHeydayMenus` to copy the records into the other table(s), e.g.:

```
vendor/bin/sake dev/tasks/Arillo-MenuManager-Tasks-MigrateFromHeydayMenus
```