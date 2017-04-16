# SliDoctrineArrayQueryBuilderBundle [![Build Status](https://travis-ci.org/sergeil/SliDoctrineArrayQueryBuilderBundle.svg?branch=develop)](https://travis-ci.org/sergeil/SliDoctrineArrayQueryBuilderBundle)

With this bundle on board you will be able to build complex DQL queries using a simple PHP associative arrays notation,
this way simplicity of writing queries goes on a whole new level, analytical applications which must allow
to build queries from UI will especially benefit from this bundle (bundle out of the box supports client/server
communication protocol used by ExtJs framework, in other words).

## Teaser

Say that we have a classical User and Group entities associated as ManyToMany and that User entity have OneToOne
association with Profile entity, this is how a sample query could look like:

```php
$query = array(
    'filter' => [
        // if the property is OneToMany relation and IN query is used the MEMBER OF query will be built automatically
        array('property' => 'groups', 'value' => 'in:1,2,3'),
        array('property' => 'username', 'value' => 'like:John%'),
        // this will automatically build proper JOINS under the hood
        array('property' => 'profile.insurance.securityNumber', 'value' => 'isNull')
    ],
    'fetch' => [
        // this will inform array query builder that you want to have associated Profile and then Insurance entities
        // to be fetched as well
        'profile.insurance'
    ],
    'sort' => [
        array('property' => 'id', 'direction' => 'DESC')
    ],
    'page' => 1,
    'limit' => 25
);

/* @var \Sli\DoctrineArrayQueryBuilderBundle\Querying\ArrayQueryBuilder $aqb */
$aqb = $container->get('sli_doctrine_array_query_builder.querying.array_query_builder');

// Only those users will be returned:
// 1. belong to groups with IDS 1, 2 and 3
// 2. whose `username`s contain John
// 3. whose associated Insurance entity's securityNumber is NULL
// Result will be ordered by ID field and paginated
$users = $aqb->buildQuery('MyCompany\MyBundle\Entity\User', $query);
```

For all supported functionality please see a functional test located in
`Tests/Functional/Querying/ArrayQueryBuilderTest.php`.

Supported filtering operators:
 * eq
 * neq
 * like
 * notLike
 * gt
 * gte
 * lt
 * lte
 * in
 * notIn
 * isNull
 * isNotNull

## Installation

Add this dependency to your composer.json:

    "sergeil/doctrine-array-query-builder-bundle": "dev-develop"

Update your AppKernel class and add this:

    new Sli\DoctrineArrayQueryBuilderBundle\SliDoctrineArrayQueryBuilderBundle(),
    new Sli\AuxBundle\SliAuxBundle(),
    new Sli\DoctrineEntityDataMapperBundle\SliDoctrineEntityDataMapperBundle()

## Licensing

This bundle is under the MIT license. See the complete license in the bundle:
Resources/meta/LICENSE