# Sylius Elasticsearch Plugin

DO NOT use this plugin - it's test plugin.

## Installation


### Step 1: Download the plugin

Open a command console, enter your project directory and execute the following command to download the latest stable version of this plugin:

```bash
$ composer require setono/sylius-elasticsearch-plugin
```

This command requires you to have Composer installed globally, as explained in the [installation chapter](https://getcomposer.org/doc/00-intro.md) of the Composer documentation.


### Step 2: Enable the plugin

Then, enable the plugin by adding it to the list of registered plugins/bundles
in the `config/bundles.php` file of your project:

```php
<?php

return [
    // ...
    
    Setono\SyliusElasticsearchPlugin\SetonoSyliusElasticsearchPlugin::class => ['all' => true],
    
    // ...
];
```

Add this to your `routing.yml` file to enable elasticsearch search page and product list page

```yaml
elasticsearch:
    resource: "@SetonoSyliusElasticsearchPlugin/Resources/config/routing.yaml"
    prefix: /{_locale}
    requirements:
        _locale: ^[a-z]{2}(?:_[A-Z]{2})?$
```

Add following to your `services.yml` for defining which indexes the SearchController should be using for your entities.

```yaml
setono_sylius_elasticsearch:
    finder_indexes:
        products: yourprefix_products   # Default value: products
        taxons: yourprefix_taxons       # Default value: taxons
```

### Step 3: Indexing

Run `php bin/console fo:el:po` to initialize the indices. Index update will be done automatically when products are altered.

## Contributors
- [Jais Djurhuus-Kempel](https://github.com/JaisDK)

## Credits
PropertyBuilders are borrowed and altered from https://github.com/BitBagCommerce/SyliusElasticsearchPlugin
