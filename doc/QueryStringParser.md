# QueryStringParser

Define a content lookup query using a string.

Here is an example:

```
$query = QueryStringParser::getQueryObject(
	'LocationQuery',                                          // Type of return object
	'ParentLocationId:123 and ContentTypeIdentifier:article', // Query string
	'Field.article.publish_date:DESC',                        // Sort string
	20                                                        // Limit
);
```

The query string is parsed and converted into a query object. The query object can be used to fetch content items.
For example:
```
// query the database
$findResult = $ngServices->getFilterService()->filterContent( $query );

// searching in the search index
$searchResult = $ngServices->getFindService()->findLocations( $query );
```

The example is using the Netgen SiteAPI but it works the same way with the Ibexa Content Repository.


## Return object

The first parameter is specifying the expected return object. Use

- _LocationQuery_ in order to fetch a location
- _Query_ in order to fetch a list of content items
- _Filter_ used in context of the _paginator_

## Query string syntax

The second parameter is the query string defining the fetch criteria.

### Criterions

Here is an incomplete list of criterions:

| Condition               | Example                       | Comment                  |
|-------------------------|-------------------------------|--------------------------|
| Subtree                 | Subtree:/1/2/325/             |                          |
| ParentLocationId        | ParentLocationId:2            |                          |
| ContentTypeIdentifier   | ContentTypeIdentifier:article |                          |
| ContentId               | ContentId:3245                |                          |
| ContentName             | ContentName:~"plus video"     |                          |
| Visibility              | Visibility: hidden            | visible/hidden           |
| Field.<identifier>      | Field.name:"Top News"         | search only - not filter |
| DatePublished           | DatePublished:2020-10-20      |                          |
| DateModified            | DatePublished:2020-10-20      |                          |
| Location\IsMainLocation | Location\IsMainLocation:1     |                          |

See more criterions: https://github.com/ibexa/core/tree/main/src/contracts/Repository/Values/Content/Query/Criterion

Custom criterion from 3rd party bundles can be used as well. Like:
```MugoWeb\IbexaBundle\API\Repository\Values\Content\Query\Criterion\Field.<contentTypeIdentifier>.<fieldIdentifier>```
```Netgen\TagsBundle\API\Repository\Values\Content\Query\Criterion\TagId```

### Operators and bracketing

You can combine multiple criterions using:
- _and_
- _or_

You can also use brackets to group criteria:
- (Subtree:/1/2 and Visibility:hidden)
- (ContentId:321 or ContentId:322)

### Criterion values

| Type         | Example                                    | Comment                            |
|--------------|--------------------------------------------|------------------------------------|
| With spaces  | Field.name:"Top News"                      | Encapsulate the string with quotes |
| Negation     | !ContentTypeIdentifier:folder              | Not the '!' char at the start      |
| List         | ContentTypeIdentifier:[article, blog_post] |                                    |
| Contains     | Field.name:~bard                           | Not all criterions support it      |
| Greater than | DatePublished:>2020-01-01                  | also >=, <, <=                     |

### Sort syntax

The third parameter is the sort string. For example:
```
Field.article.publish_date:DESC
```
The value is always either _DESC_ or _ASC_.

Here is a list of available sort fields:
* ContentName
* DatePublished
* DateModified
* Location\Priority
* Field._content type identifier_._field identifier_
* CustomField._field identifier_ (only for search, not DB)

In case you want to use the configured sorting of the $parentLocation, use:

```
$parentLocation->sortField . ':' .$parentLocation->sortOrder
```

which could be something like:
`9:1` - no SortClause class name needed - it is a number value (9) instead


## Limit
The fourth parameter is the limit - integer value.

## Offset
The fifth parameter is the offset - integer value.

## Perform count
This sixth parameter is a boolean value and controls if you want to also know the
total number of results. By default it is set to _false_ for performance reasons.
