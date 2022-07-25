Installation
=
```composer require mugoweb/mugo_ibexa:dev-master```

Afterward, you need to enable the bundle in _config/bundles.php_:

```MugoWeb\IbexaBundle\MugoWebIbexaBundle::class => ['all' => true],```

If you'd like to use the features _location quick finder_ or _Location query tester_,
you would need to load the _routes.yml_ file of this bundle. Tip: use a _path prefix_
to avoid path conflicts.

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
