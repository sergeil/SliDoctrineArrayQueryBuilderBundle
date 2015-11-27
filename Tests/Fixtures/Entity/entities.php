<?php

namespace Sli\DoctrineArrayQueryBuilderBundle\Fixtures;

use Doctrine\ORM\Mapping as Orm;
use Doctrine\Common\Collections\ArrayCollection;
use Sli\DoctrineArrayQueryBuilderBundle\SortingFieldResolving\QueryOrder;

/**
 * @Orm\Entity
 * @Orm\Table(name="sli_doctrinearrayquerybuilder_dummyuser")
 */
class User
{
    /**
     * @Orm\Id
     * @Orm\Column(type="integer")
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Orm\Column(type="string", nullable=true)
     */
    public $firstname;

    /**
     * @Orm\Column(type="string", nullable=true)
     */
    public $lastname;

    /**
     * @var DummyAddress
     * @Orm\OneToOne(targetEntity="DummyAddress", cascade={"PERSIST"})
     */
    public $address;

    /**
     * @Orm\ManyToOne(targetEntity="CreditCard")
     */
    public $creditCard;

    /**
     * @Orm\ManyToMany(targetEntity="Group", inversedBy="users")
     */
    public $groups;

    public function __construct()
    {
        $this->groups = new ArrayCollection();
    }

    static public function clazz()
    {
        return get_called_class();
    }
}

/**
 * @Orm\Entity
 * @Orm\Table(name="sli_doctrinearrayquerybuilder_group")
 */
class Group
{
    /**
     * @Orm\Id
     * @Orm\Column(type="integer")
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Orm\Column(type="string")
     */
    public $name;

    /**
     * @Orm\ManyToMany(targetEntity="User", mappedBy="groups")
     */
    public $users;

    static public function clazz()
    {
        return get_called_class();
    }

    public function addUser(User $user)
    {
        $user->groups->add($this);
        if (!$this->users->contains($user)) {
            $this->users->add($user);
        }
    }

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }
}

/**
 * @Orm\Entity
 * @Orm\Table(name="sli_doctrinearrayquerybuilder_cc")
 */
class CreditCard
{
    /**
     * @Orm\Id
     * @Orm\Column(type="integer")
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Orm\Column(type="integer")
     */
    public $number;

    static public function clazz()
    {
        return get_called_class();
    }
}

/**
 * @Orm\Entity
 * @Orm\Table(name="sli_doctrinearrayquerybuilder_dummyaddress")
 *
 * @QueryOrder("zip")
 */
class DummyAddress
{
    /**
     * @Orm\Id
     * @Orm\Column(type="integer")
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Orm\Column
     */
    public $zip;

    /**
     * @Orm\Column
     */
    public $street;

    /**
     * @var DummyCountry
     *
     * @Orm\ManyToOne(targetEntity="DummyCountry", cascade={"PERSIST"})
     */
    public $country;

    /**
     * @var DummyCity
     *
     * @Orm\ManyToOne(targetEntity="DummyCity", cascade={"PERSIST"})
     */
    public $city;

    static public function clazz()
    {
        return get_called_class();
    }
}

/**
 * @Orm\Entity
 * @Orm\Table(name="sli_doctrinearrayquerybuilder_dummycountry")
 */
class DummyCountry
{
    /**
     * @Orm\Id
     * @Orm\Column(type="integer")
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Orm\Column
     */
    public $name;

    /**
     * @Orm\OneToOne(targetEntity="President")
     */
    public $president;

    static public function clazz()
    {
        return get_called_class();
    }
}

/**
 * @Orm\Entity
 * @Orm\Table(name="sli_doctrinearrayquerybuilder_dummycity")
 */
class DummyCity
{
    /**
     * @Orm\Id
     * @Orm\Column(type="integer")
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Orm\Column
     */
    public $name;

    static public function clazz()
    {
        return get_called_class();
    }
}

/**
 * @Orm\Entity
 * @Orm\Table(name="sli_doctrinearrayquerybuilder_president")
 */
class President
{
    /**
     * @Orm\Id
     * @Orm\Column(type="integer")
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Orm\Column(type="date")
     */
    public $since;

    static public function clazz()
    {
        return get_called_class();
    }
}

/**
 * @Orm\Entity
 * @Orm\Table(name="sli_doctrinearrayquerybuilder_dummyorder")
 */
class UserOrder
{
    /**
     * @Orm\Id
     * @Orm\Column(type="integer")
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @var User
     *
     * @Orm\ManyToOne(targetEntity="User")
     */
    public $user;

    /**
     * @Orm\Column(type="string")
     */
    public $number;

    static public function clazz()
    {
        return get_called_class();
    }
}
