<?php

namespace Sli\DoctrineArrayQueryBuilderBundle\Tests;

use Doctrine\ORM\Tools\SchemaTool;
use Modera\FoundationBundle\Testing\FunctionalTestCase;
use Sli\AuxBundle\Util\Toolkit;
use Sli\DoctrineArrayQueryBuilderBundle\Fixtures\CreditCard;
use Sli\DoctrineArrayQueryBuilderBundle\Fixtures\DummyAddress;
use Sli\DoctrineArrayQueryBuilderBundle\Fixtures\DummyCity;
use Sli\DoctrineArrayQueryBuilderBundle\Fixtures\DummyCountry;
use Sli\DoctrineArrayQueryBuilderBundle\Fixtures\UserOrder;
use Sli\DoctrineArrayQueryBuilderBundle\Fixtures\User;
use Sli\DoctrineArrayQueryBuilderBundle\Fixtures\Group;
use Sli\DoctrineArrayQueryBuilderBundle\Fixtures\President;
use Sli\DoctrineArrayQueryBuilderBundle\Querying\ArrayQueryBuilder;

require_once __DIR__ . '/Fixtures/Entity/entities.php';

/**
 * @author Sergei Lissovski <sergei.lissovski@gmail.com>
 */
class AbstractDatabaseTestCase extends FunctionalTestCase
{
    static private $entityClasses = array();
    static private $metaClasses = array();

    /* @var ArrayQueryBuilder */
    static protected $builder;

    static public function doSetUpBeforeClass()
    {
        self::$builder = self::$container->get('sli_doctrine_array_query_builder.querying.array_query_builder');

        self::$entityClasses = array(
            UserOrder::clazz(), User::clazz(), DummyAddress::clazz(),
            DummyCountry::clazz(), DummyCity::clazz(), CreditCard::clazz(),
            Group::clazz(), President::clazz()
        );
        foreach (self::$entityClasses as $className) {
            self::$metaClasses[] = self::$em->getClassMetadata($className);
        }

        $schemaTool = new SchemaTool(self::$em);
        $schemaTool->updateSchema(self::$metaClasses);

        self::loadFixtures();
    }

    static private function loadFixtures()
    {
        $adminsGroup = new Group();
        $adminsGroup->name = 'admins';
        self::$em->persist($adminsGroup);

        $users = array();

        // populating
        foreach (array('john doe', 'jane doe', 'vassily pupkin') as $fullname) {
            $exp = explode(' ', $fullname);
            $user = new User();
            $user->firstname = $exp[0];
            $user->lastname = $exp[1];

            if ('john' == $exp[0]) {
                $adminsGroup->addUser($user);

                $address = new DummyAddress();
                $address->country = new DummyCountry();
                $address->country->name = 'A';

                $address->city = new DummyCity();
                $address->city->name = 'ACity';

                $address->street = 'foofoo';
                $address->zip = '1010';
                $user->address = $address;
            } else if ('jane' == $exp[0]) {
                $address = new DummyAddress();
                $address->country = new DummyCountry();
                $address->country->name = 'B';
                $address->zip = '2020';
                $address->street = 'Blahblah';

                $user->address = $address;
            }

            $users[] = $user;

            self::$em->persist($user);
        }

        $o1 = new UserOrder();
        $o1->number = 'ORDER-1';
        $o1->user = $users[0];

        $o2 = new UserOrder();
        $o2->number = 'ORDER-2';
        $o2->user = $users[1];

        $hourPlus = new \DateTime('now');
        $hourPlus = $hourPlus->modify('+1 hour');

        $president = new President();
        $president->since = $hourPlus;

        self::$em->persist($o1);
        self::$em->persist($o2);
        self::$em->persist($president);

        self::$em->flush();
    }

    static public function doTearDownAfterClass()
    {
        $metaClasses = array();

        foreach (self::$entityClasses as $className) {
            $metaClasses[] = self::$em->getClassMetadata($className);
        }

        $schemaTool = new SchemaTool(self::$em);
        $schemaTool->dropSchema($metaClasses);
    }
} 