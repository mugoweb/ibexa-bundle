Installation
=
```composer require mugoweb/mugoweb/ibexa-bundle:dev-master```

Afterward, you need to enable the bundle in _config/bundles.php_:

```MugoWeb\IbexaBundle\MugoWebIbexaBundle::class => ['all' => true],```

If you'd like to use the features _location quick finder_ or _Location query tester_,
you would need to load the _routes.yml_ file of this bundle. Tip: use a _path prefix_
to avoid path conflicts.

Tests
=

`php bin/phpunit vendor/mugoweb/ibexa-bundle/tests/`

Features
=
Location quick finder
-
For a given location ID, following path redirects to the
full view of the corresponding content object:

/location/{locationId}

Location query tester
-
It allows you to fetch _Locations_ for a given LocationQuery string.

Use this path to access it:

/query

Log user hash generation
-

To enable add following to your service configuration:
```
  # Enable to log user variation hashes
  fos_http_cache.user_context.hash_generator:
    class: MugoWeb\IbexaBundle\Service\DebugHashGenerator
    arguments:
      $cachePool: '@ibexa.cache_pool'

```

Commands
-

```
php bin/console ibexa:trash:purge <limit>
```

Command to purge items from the trash

QueryStringParser
-

Here is an example:
```
$query = QueryStringParser::getQueryObject(
	'LocationQuery',
	'ParentLocationId:123 and ContentTypeIdentifier:article',
	'Field.article.publish_date:DESC',
	20
);
```

LocationQuery and Query
-
__Consider to use the QueryStringParser instead__.  A different way to generate the LocationQuery. You can use it then for the find or search service.
Here is an example:

```
$locationQuery = LocationQuery::build(
    'Subtree:' . $parentLocation->pathString . ' and (ContentTypeIdentifier:article or ContentTypeIdentifier:folder ro ContentTypeIdentifier:blog)',
    'Location\Priority: ASC',
     5
);
```
