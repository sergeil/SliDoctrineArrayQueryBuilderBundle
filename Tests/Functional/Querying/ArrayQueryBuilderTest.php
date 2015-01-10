<?php

namespace Sli\DoctrineArrayQueryBuilderBundle\Tests\Functional\Querying;

use Sli\DoctrineArrayQueryBuilderBundle\Fixtures\UserOrder;
use Sli\DoctrineArrayQueryBuilderBundle\Fixtures\President;
use Sli\DoctrineArrayQueryBuilderBundle\Tests\AbstractDatabaseTestCase;
use Sli\DoctrineArrayQueryBuilderBundle\Fixtures\DummyAddress;
use Sli\DoctrineArrayQueryBuilderBundle\Fixtures\User;
use Sli\DoctrineArrayQueryBuilderBundle\SortingFieldResolving\MutableSortingFieldResolver;

/**
 * @author Sergei Lissovski <sergei.lissovski@gmail.com>
 */
class ArrayQueryBuilderTest extends AbstractDatabaseTestCase
{
    public function testBuildQueryBuilderEmptyFilter()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
        ));

        $users = $qb->getQuery()->getResult();
        $this->assertEquals(3, count($users));
        $this->assertEquals(1, $users[0]->id);
        $this->assertEquals(2, $users[1]->id);
        $this->assertEquals(3, $users[2]->id);
    }

    public function testBuildQueryBuilderWithEqFilter()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'filter' => array(
                array('property' => 'id', 'value' => 'eq:1')
            )
        ));

        $users = $qb->getQuery()->getResult();
        $this->assertEquals(1, count($users));
        $this->assertEquals(1, $users[0]->id);
    }

    public function testBuildQueryBuilderWithInFilter()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'filter' => array(
                array('property' => 'id', 'value' => 'in:1,3')
            )
        ));

        $users = $qb->getQuery()->getResult();
        $this->assertEquals(2, count($users));
        $this->assertEquals(1, $users[0]->id);
        $this->assertEquals(3, $users[1]->id);
    }

    public function testBuildQueryBuilderWithEmptyInFilter()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'filter' => array(
                array('property' => 'id', 'value' => 'in:')
            )
        ));

        $qb->getQuery()->getResult();
    }

    public function testBuildQueryBuilderWithIsNotNullFilter()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'filter' => array(
                array('property' => 'address', 'value' => 'isNull')
            )
        ));

        $users = $qb->getQuery()->getResult();
        $this->assertEquals(1, count($users));
    }

    public function testBuildQueryBuilderWithSortByDescWhereIdNotIn2()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'sort' => array(
                array('property' => 'id', 'direction' => 'DESC')
            ),
            'filter' => array(
                array('property' => 'id', 'value' => 'notIn:2')
            )
        ));

        $users = $qb->getQuery()->getResult();
        $this->assertEquals(2, count($users));
        $this->assertEquals(3, $users[0]->id);
        $this->assertEquals(1, $users[1]->id);
    }

    public function testBuildQueryWithJoins()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'filter' => array(
                array('property' => 'address.country.name', 'value' => 'eq:A')
            )
        ));

        /** @var User[] $users */
        $users = $qb->getQuery()->getResult();

        $this->assertEquals(1, count($users));
    }

    public function testBuildQueryWithFetch()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'fetch' => array(
                'address.country'
            )
        ));

        // fetch for root, for address, and for address.country
        $this->assertEquals(3, count($qb->getDQLPart('select')));

        /* @var User[] $users */
        $users = $qb->getQuery()->getResult();

        $this->assertInstanceof(User::clazz(), $users[0]);
        $this->assertFalse($users[0]->address instanceof \Doctrine\ORM\Proxy\Proxy);
        $this->assertFalse($users[0]->address->country instanceof \Doctrine\ORM\Proxy\Proxy);
    }

    public function testBuildQueryBuilderWhereUserAddressZip()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'filter' => array(
                array('property' => 'lastname', 'value' => 'eq:doe'),
                array('property' => 'address.zip', 'value' => 'like:10%')
            )
        ));

        $users = $qb->getQuery()->getResult();
        $this->assertTrue(is_array($users));
        $this->assertEquals(1, count($users));
        /* @var User $user */
        $user = $users[0];
        $this->assertEquals('doe', $user->lastname);
        $this->assertNotNull($user->address);
        $this->assertEquals('1010', $user->address->zip);
    }

    public function testBuildQueryBuilderWithSkipAssocFilter()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'filter' => array(
                array('property' => 'address', 'value' => 'eq:-')
            )
        ));

        /* @var User[] $users */
        $users = $qb->getQuery()->getResult();
        $this->assertEquals(3, count($users));
    }

    public function testBuildCountQueryBuilderFilterByAssociatedField()
    {
        $fetchQb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'filter' => array(
                array('property' => 'address.zip', 'value' => 'like:10%')
            )
        ));

        $countQb = self::$builder->buildCountQueryBuilder($fetchQb);

        $this->assertEquals(1, $countQb->getQuery()->getSingleScalarResult());
    }

    public function testBuildCountQueryBuilderWithJoinFilterAndOrder()
    {
        $fetchQb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'filter' => array(
                array('property' => 'address.zip', 'value' => 'isNull')
            ),
            'sort' => array( // it simply will be removed
                array('property' => 'address', 'direction' => 'DESC')
            )
        ));

        $countQb = self::$builder->buildCountQueryBuilder($fetchQb);

        $this->assertEquals(1, $countQb->getQuery()->getSingleScalarResult());
    }

    public function testBuildQueryOrderByAssociatedEntity()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'sort' => array(
                array('property' => 'address', 'direction' => 'DESC')
            )
        ));

        /* @var User[] $users */
        $users = $qb->getQuery()->getResult();
        $this->assertEquals(3, count($users));

        $this->assertEquals('jane', $users[0]->firstname);
        $this->assertEquals('john', $users[1]->firstname);
        $this->assertEquals('vassily', $users[2]->firstname);
    }

    public function testBuildQueryOrderByAssociatedEntityWithProvidedSortingFieldResolver()
    {
        $sortingResolver = $this->getMock(
            '\Sli\DoctrineArrayQueryBuilderBundle\SortingFieldResolving\SortingFieldResolverInterface'
        );
        $sortingResolver->expects($this->atLeastOnce())
            ->method('resolve')
            ->with($this->equalTo(User::clazz()), $this->equalTo('address'))
            ->will($this->returnValue('street'));

        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'sort' => array(
                array('property' => 'address', 'direction' => 'ASC')
            )
        ), $sortingResolver);

        $orderBy = $qb->getDQLPart('orderBy');
        $this->assertEquals(1, count($orderBy));
        $this->assertTrue(strpos($orderBy[0], 'street ASC') !== false);

        /* @var User[] $users */
        $users = $qb->getQuery()->getResult();
        $this->assertEquals(3, count($users));

        $this->assertEquals('vassily', $users[0]->firstname);
        $this->assertEquals('jane', $users[1]->firstname);
        $this->assertEquals('john', $users[2]->firstname);
    }

    public function testBuildQueryOrderByNestedAssociation()
    {
        $resolver = new MutableSortingFieldResolver();
        $resolver->add(UserOrder::clazz(), 'user', 'address');
        $resolver->add(User::clazz(), 'address', 'country');
        $resolver->add(DummyAddress::clazz(), 'country', 'name');

        $qb = self::$builder->buildQueryBuilder(UserOrder::clazz(), array(
            'sort' => array(
                array('property' => 'user', 'direction' => 'DESC')
            )
        ), $resolver);

        $this->assertEquals(4, count($qb->getDQLPart('join'), \COUNT_RECURSIVE)); // there must be three joins

        /* @var UserOrder[] $result */
        $result = $qb->getQuery()->getResult();

        $this->assertEquals(2, count($result));
        $this->assertEquals('ORDER-2', $result[0]->number);
        $this->assertEquals('ORDER-1', $result[1]->number);
    }

    public function testBuildQueryWithMemberOfManyToMany()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'filter' => array(
                // when IN is used in conjunction with TO_MANY ( MANY_TO_MANY, ONE_TO_MANY ) relations
                // then it will treated in special way and MEMBER OF query will be generated
                array('property' => 'groups', 'value' => 'in:1,20')
            )
        ));

        /* @var User[] $users */
        $users = $qb->getQuery()->getResult();
        $this->assertEquals(1, count($users));
        $this->assertEquals('john', $users[0]->firstname);
        $this->assertEquals('doe', $users[0]->lastname);
    }

    public function testBuildQueryWithNotMemberOfAndManyToMany()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'filter' => array(
                array('property' => 'groups', 'value' => 'notIn:1')
            )
        ));

        /* @var User[] $users */
        $users = $qb->getQuery()->getResult();
        $this->assertEquals(2, count($users));
    }

    public function testBuildQueryBuilderWithSeveralEqORedFilter()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'filter' => array(
                array(
                    'property' => 'id',
                    'value' => array(
                        'eq:1', 'eq:3' // 1 or 3
                    )
                )
            )
        ));

        $users = $qb->getQuery()->getResult();
        $this->assertEquals(2, count($users));
        $this->assertEquals(1, $users[0]->id);
        $this->assertEquals(3, $users[1]->id);
    }

    public function testBuilderQueryWithOrFilter()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'filter' => array(
                array(
                    array('property' => 'firstname', 'value' => 'eq:john'),
                    array('property' => 'lastname', 'value' => 'like:pup%')
                )
            )
        ));

        /* @var User[] $users */
        $users = $qb->getQuery()->getResult();

        $this->assertEquals(2, count($users));
        $this->assertEquals('john', $users[0]->firstname);
        $this->assertEquals('pupkin', $users[1]->lastname);
    }

    public function testBuilderQueryWithComplexFetch()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'filter' => array(
                array(
                    array('property' => 'lastname', 'value' => 'eq:doe'),
//                    array('property' => 'fullname', 'value' => 'like:jane%')
                )
            ),
            'fetch' => array(
                'firstname',
                'lastname',
                'fullname' => array(
                    'function' => 'CONCAT',
                    'args' => array(
                        ':firstname',
                        array(
                            'function' => 'CONCAT',
                            'args' => array(
                                ' ', ':lastname'
                            )
                        )
                    )
                )
            ),
            'sort' => array(
                array('property' => 'id', 'direction' => 'ASC')
            )
        ));

        $users = $qb->getQuery()->getResult();

        $this->assertEquals(2, count($users));
        $this->assertArrayHasKey('firstname', $users[0]);
        $this->assertArrayHasKey('lastname', $users[0]);
        $this->assertArrayHasKey('fullname', $users[0]);
        $this->assertEquals('john', $users[0]['firstname']);
        $this->assertEquals('doe', $users[0]['lastname']);
        $this->assertEquals('john doe', $users[0]['fullname']);
    }

    public function testBuildQueryWithGroupBy()
    {
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'fetch' => array(
                'total' => array(
                    'function' => 'COUNT',
                    'args' => array(
                        ':id'
                    )
                )
            ),
            'groupBy' => array(
                'address.zip'
            ),
            'fetchRoot' => false
        ));

        $this->assertEquals(1, count($qb->getDQLPart('select')));

        /* @var User[] $users */
        $users = $qb->getQuery()->getResult();

        $this->assertEquals(3, count($users));
    }

    public function testBuildQueryWithOrderByAggregateColumnResult()
    {
        $baseParams = array(
            'fetchRoot' => false,
            'fetch' => array(
                'how_many' => array(
                    'function' => 'COUNT',
                    'args' => array(':id')
                ),
                'lastname'
            ),
            'groupBy' => array(
                'lastname'
            )
        );

        $ascParams = array_merge($baseParams, array(
            'sort' => array(
                array(
                    'property' => 'how_many',
                    'direction' => 'ASC'
                )
            )
        ));

        $descParams = array_merge($baseParams, array(
            'sort' => array(
                array(
                    'property' => 'how_many',
                    'direction' => 'DESC'
                )
            )
        ));

        $ascResult = self::$builder->buildQueryBuilder(User::clazz(), $ascParams)->getQuery()->getResult();

        $this->assertEquals(2, count($ascResult));
        $this->assertEquals(1, $ascResult[0]['how_many']);
        $this->assertEquals(2, $ascResult[1]['how_many']);

        $descResult = self::$builder->buildQueryBuilder(User::clazz(), $descParams)->getQuery()->getResult();

        $this->assertEquals(2, count($descResult));
        $this->assertEquals(2, $descResult[0]['how_many']);
        $this->assertEquals(1, $descResult[1]['how_many']);
    }

    public function testQueryingByDateField()
    {
        $this->markTestIncomplete();

        $now = new \DateTime('now');

        $result = self::$builder->buildQuery(President::clazz(), array(
            'filter' => array(
                array(
                    'property' => 'since',
                    'value' => 'gte:' . $now->format('d.m.y')
                )
            )
        ))->getResult();

        $this->assertEquals(1, count($result));
    }

    public function testBuildQueryWithSortingContainingFunctionCall()
    {
        $this->markTestIncomplete();

        // TODO add supporting for things like that
        $qb = self::$builder->buildQueryBuilder(User::clazz(), array(
            'sort' => array(
                array(
                    'function' => 'CONCAT',
                    'args' => array(
                        ':firstname', ':lastname'
                    )
                )
            )
        ));
    }
}
