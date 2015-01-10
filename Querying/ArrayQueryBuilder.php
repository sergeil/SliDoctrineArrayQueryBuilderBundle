<?php

namespace Sli\DoctrineArrayQueryBuilderBundle\Querying;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr;
use Sli\DoctrineArrayQueryBuilderBundle\Parsing\Expression;
use Sli\DoctrineArrayQueryBuilderBundle\Parsing\Filter;
use Sli\DoctrineArrayQueryBuilderBundle\Parsing\FilterInterface;
use Sli\DoctrineArrayQueryBuilderBundle\Parsing\Filters;
use Sli\DoctrineArrayQueryBuilderBundle\Parsing\OrderExpression;
use Sli\DoctrineArrayQueryBuilderBundle\Parsing\OrFilter;
use Sli\DoctrineArrayQueryBuilderBundle\SortingFieldResolving\ChainSortingFieldResolver;
use Sli\DoctrineArrayQueryBuilderBundle\SortingFieldResolving\SortingFieldResolverInterface;
use Sli\DoctrineEntityDataMapperBundle\Mapping\EntityDataMapperService;
use Doctrine\ORM\Mapping\ClassMetadataInfo as CMI;
use Doctrine\ORM\QueryBuilder;

/**
 * @author Sergei Lissovski <sergei.lissovski@gmail.com>
 */
class ArrayQueryBuilder
{
    private $em;
    private $mapper;
    private $sortingFieldResolver;

    public function __construct(EntityManager $em, EntityDataMapperService $mapper, SortingFieldResolverInterface $sortingFieldResolver)
    {
        $this->em = $em;
        $this->mapper = $mapper;
        $this->sortingFieldResolver = $sortingFieldResolver;
    }

    /**
     * @param array $arrayQuery
     *
     * @return \Doctrine\ORM\Query
     */
    public function buildQuery($entityFqcn, array $arrayQuery)
    {
        return $this->buildQueryBuilder($entityFqcn, $arrayQuery)->getQuery();
    }

    protected function convertValue(ExpressionManager $expressionManager, $expression, $value)
    {
        $mapping = $expressionManager->getMapping($expression);

        return $this->mapper->convertValue($value, $mapping['type'], true);
    }

    private function resolveExpression(
        $entityFqcn, $expression, SortingFieldResolverInterface $sortingFieldResolver, ExpressionManager $exprMgr
    )
    {
        if ($exprMgr->isAssociation($expression)) {
            $mapping = $exprMgr->getMapping($expression);

            $fieldResolverExpression = explode('.', $expression);
            $fieldResolverExpression = end($fieldResolverExpression);

            $expression = $this->resolveExpression(
                $mapping['targetEntity'],
                $expression. '.' . $sortingFieldResolver->resolve($entityFqcn, $fieldResolverExpression),
                $sortingFieldResolver,
                $exprMgr
            );
        }

        return $expression;
    }

    /**
     * @param Filter $filter
     * @return bool
     */
    private function isUsefulInFilter($comparator, $value)
    {
        // There's no point point to create empty IN, NOT IN clause, even more - trying to use
        // empty IN, NOT IN will result in SQL error
        return !(in_array($comparator, array(Filter::COMPARATOR_IN, Filter::COMPARATOR_NOT_IN)) && count($value) == 0);
    }

    private function isUsefulFilter(ExpressionManager $exprMgr, $propertyName, $value)
    {
        // if this is association field, then sometimes there could be just 'no-value'
        // state which is conventionally marked as '-' value
        return !($exprMgr->isAssociation($propertyName) && '-' === $value);
    }

    private function processFilter(
        ExpressionManager $expressionManager, Expr\Composite $compositeExpr, QueryBuilder $qb,
        DoctrineQueryBuilderParametersBinder $binder, Filter $filter
    )
    {
        $name = $filter->getProperty();

        $fieldName = $expressionManager->getDqlPropertyName($name);

        if (in_array($filter->getComparator(), array(Filter::COMPARATOR_IS_NULL, Filter::COMPARATOR_IS_NOT_NULL))) { // these are sort of 'special case'
            $compositeExpr->add(
                $qb->expr()->{$filter->getComparator()}($fieldName)
            );
        } else {
            $value = $filter->getValue();
            $comparatorName = $filter->getComparator();

            if (   !$this->isUsefulInFilter($filter->getComparator(), $filter->getValue())
                || !$this->isUsefulFilter($expressionManager, $name, $value)) {

                return;
            }

            // when "IN" is used in conjunction with TO_MANY type of relation,
            // then we will treat it in a special way and generate "MEMBER OF" queries
            // instead
            $isAdded = false;
            if ($expressionManager->isAssociation($name)) {
                $mapping = $expressionManager->getMapping($name);
                if (   in_array($comparatorName, array(Filter::COMPARATOR_IN, Filter::COMPARATOR_NOT_IN))
                    && in_array($mapping['type'], array(CMI::ONE_TO_MANY, CMI::MANY_TO_MANY))) {

                    $statements = array();
                    foreach ($value as $id) {
                        $statements[] = sprintf(
                            (Filter::COMPARATOR_NOT_IN == $comparatorName ? 'NOT ' : '') . '?%d MEMBER OF %s',
                            $binder->getNextIndex(),
                            $expressionManager->getDqlPropertyName($name)
                        );

                        $binder->bind($this->convertValue($expressionManager, $name, $id));
                    }

                    if (Filter::COMPARATOR_IN == $comparatorName) {
                        $compositeExpr->add(
                            call_user_func_array(array($qb->expr(), 'orX'), $statements)
                        );
                    } else {
                        $compositeExpr->addMultiple($statements);
                    }

                    $isAdded = true;
                }
            }

            if (!$isAdded) {
                if (is_array($value) && count($value) != count($value, \COUNT_RECURSIVE)) { // must be "OR-ed" ( multi-dimensional array )
                    $orStatements = array();
                    foreach ($value as $orFilter) {
                        if (   !$this->isUsefulInFilter($orFilter['comparator'], $orFilter['value'])
                            || !$this->isUsefulFilter($expressionManager, $name, $orFilter['value'])) {

                            continue;
                        }

                        if (in_array($orFilter['comparator'], array(Filter::COMPARATOR_IN, Filter::COMPARATOR_NOT_IN))) {
                            $orStatements[] = $qb->expr()->{$orFilter['comparator']}($fieldName);
                        } else {
                            $orStatements[] = $qb->expr()->{$orFilter['comparator']}($fieldName, '?' . $binder->getNextIndex());
                        }

                        $binder->bind($orFilter['value']);
                    }

                    $compositeExpr->add(
                        call_user_func_array(array($qb->expr(), 'orX'), $orStatements)
                    );
                } else {
                    $compositeExpr->add(
                        $qb->expr()->$comparatorName($fieldName, '?' . $binder->getNextIndex())
                    );
                    $binder->bind($this->convertValue($expressionManager, $name, $value));
                }
            }
        }
    }

    /**
     * @throws \RuntimeException
     *
     * @param string $entityFqcn  Root fetch entity fully-qualified-class-name
     * @param array $arrayQuery  Parameters that were sent from client-side
     * @param SortingFieldResolverInterface $primarySortingFieldResolver
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function buildQueryBuilder($entityFqcn, array $arrayQuery, SortingFieldResolverInterface $primarySortingFieldResolver = null)
    {
        $sortingFieldResolver = new ChainSortingFieldResolver();
        if ($primarySortingFieldResolver) {
            $sortingFieldResolver->add($primarySortingFieldResolver);
        }
        $sortingFieldResolver->add($this->sortingFieldResolver);

        $qb = $this->em->createQueryBuilder();

        $expressionManager = new ExpressionManager($entityFqcn, $this->em);
        $dqlCompiler = new DqlCompiler($expressionManager);
        $binder = new DoctrineQueryBuilderParametersBinder($qb);

        $hasFetch = isset($arrayQuery['fetch']) && is_array($arrayQuery['fetch']) && count($arrayQuery['fetch']) > 0;
        /* @var Expression[] $fetchExpressions */
        $fetchExpressions = array();
        if ($hasFetch) {
            foreach ($arrayQuery['fetch'] as $statement=>$groupExpr) {
                $fetchExpressions[] = new Expression($groupExpr, $statement);
            }
        }

        $orderStmts = array(); // contains ready DQL orderBy statement that later will be joined together
        if (isset($arrayQuery['sort'])) {
            foreach ($arrayQuery['sort'] as $entry) { // sanitizing and filtering
                $orderExpression = new OrderExpression($entry);

                if (!$orderExpression->isValid()) {
                    continue;
                }

                $statement = null;

                // if expression cannot be directly resolved again the model we will check
                // if there's an alias introduced in "fetch" and then allow to use it
                if (!$expressionManager->isValidExpression($orderExpression->getProperty())
                    && $hasFetch && isset($arrayQuery['fetch'][$orderExpression->getProperty()])) {

                    $statement = $orderExpression->getProperty();
                } else if ($expressionManager->isValidExpression($orderExpression->getProperty())) {
                    $statement = $expressionManager->getDqlPropertyName(
                        $this->resolveExpression($entityFqcn, $orderExpression->getProperty(), $sortingFieldResolver, $expressionManager)
                    );
                }

                if (null === $statement) {
                    continue;
                }

                $orderStmts[] = $statement . ' ' . strtoupper($orderExpression->getDirection());
            }
        }

        $hasGroupBy = isset($arrayQuery['groupBy']) && is_array($arrayQuery['groupBy']) && count($arrayQuery['groupBy']) > 0;
        /* @var Expression[] $groupByExpressions */
        $groupByExpressions = array();
        if ($hasGroupBy) {
            foreach ($arrayQuery['groupBy'] as $groupExpr) {
                $groupByExpressions[] = new Expression($groupExpr);
            }
        }

        $addRootFetch = (isset($arrayQuery['fetchRoot']) && true == $arrayQuery['fetchRoot']) || !isset($arrayQuery['fetchRoot']);
        if ($addRootFetch) {
            $qb->add('select', 'e');
        }

        foreach ($fetchExpressions as $expression) {
            if ($expression->getFunction() || $expression->getAlias()) {
                $qb->add('select', $dqlCompiler->compile($expression, $binder), true);
            } else {
                if (!$expressionManager->isAssociation($expression->getExpression())) {
                    $qb->add('select', $expressionManager->getDqlPropertyName($expression->getExpression()), true);
                }
            }
        }

        $qb->add('from', $entityFqcn . ' e');

        if (isset($arrayQuery['start'])) {
            $start = $arrayQuery['start'];
            if (isset($arrayQuery['page']) && isset($arrayQuery['limit'])) {
                $start = ($arrayQuery['page'] - 1) * $arrayQuery['limit'];
            }
            $qb->setFirstResult($start);
        }
        if (isset($arrayQuery['limit'])) {
            $qb->setMaxResults($arrayQuery['limit']);
        }

        if (isset($arrayQuery['filter'])) {
            $andExpr = $qb->expr()->andX();

            foreach (new Filters($arrayQuery['filter']) as $filter) {
                /* @var FilterInterface $filter */
                if (!$filter->isValid()) {
                    continue;
                }

                if ($filter instanceof OrFilter) {
                    $orExpr = $qb->expr()->orX();
                    foreach ($filter->getFilters() as $orFilter) {
                        $this->processFilter($expressionManager, $orExpr, $qb, $binder, $orFilter);
                    }
                    $andExpr->add($orExpr);
                } else {
                    $this->processFilter($expressionManager, $andExpr, $qb, $binder, $filter);
                }
            }

            if ($andExpr->count() > 0) {
                $qb->where($andExpr);
            }
        }

        if ($hasFetch) {
            $expressionManager->injectFetchSelects($qb, $fetchExpressions);
        } else {
            $expressionManager->injectJoins($qb, false);
        }

        if ($hasGroupBy) {
            foreach ($groupByExpressions as $groupExpr) {
                if ($groupExpr->getFunction()) {
                    $qb->addGroupBy($dqlCompiler->compile($groupExpr, $binder));
                } else {
                    $dqlExpression = null;
                    if ($expressionManager->isValidExpression($groupExpr->getExpression())) {
                        $dqlExpression = $expressionManager->getDqlPropertyName($groupExpr->getExpression());
                    } else {
                        // we need to have something like this due to a limitation imposed by DQL. Basically,
                        // we cannot write a query which would look akin to this one:
                        // SELECT COUNT(e.id), DAY(e.createdAt) FROM FooEntity e GROUP BY DAY(e.createdAt)
                        // If you try to use a function call in a GROUP BY clause an exception will be thrown.
                        // To workaround this problem we need to introduce an alias, for example:
                        // SELECT COUNT(e.id), DAY(e.createdAt) AS datDay FROM FooEntity e GROUP BY datDay
                        foreach ($fetchExpressions as $fetchExpr) {
                            if ($groupExpr->getExpression() == $fetchExpr->getAlias()) {
                                $dqlExpression = $fetchExpr->getAlias();
                            }
                        }
                    }

                    if (!$dqlExpression) {
                        throw new \RuntimeException(sprintf(
                            'Unable to resolve grouping expression "%s" for entity %s', $groupExpr->getExpression(), $entityFqcn
                        ));
                    }

                    $qb->addGroupBy($dqlExpression);
                }
            }

            $expressionManager->injectJoins($qb, false);
        }

        if (count($orderStmts) > 0) {
            $qb->add('orderBy', implode(', ', $orderStmts));
        }

        $binder->injectParameters();

        return $qb;
    }

    /**
     * If you use Doctrine version 2.2 or higher, consider using {@class Doctrine\ORM\Tools\Pagination\Paginator}
     * instead. See http://docs.doctrine-project.org/en/latest/tutorials/pagination.html for more details
     * on that.
     *
     * @throws \RuntimeException
     * @param \Doctrine\ORM\QueryBuilder $queryBuilder  Fetch query-builder, in other words - instance of QueryBuilder
     *                                                  that will be used to actually execute SELECT query for response
     *                                                  you are going to send back
     * @param string|null $rootFetchEntityFqcn  If your fetch query contains several SELECT entries, then you need
     *                                          to specify which entity we must use to build COUNT query with
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function buildCountQueryBuilder(QueryBuilder $queryBuilder, $rootFetchEntityFqcn = null)
    {
        $countQueryBuilder = clone $queryBuilder;
        $countQueryBuilder->setFirstResult(null);
        $countQueryBuilder->setMaxResults(null);
        $parts = $countQueryBuilder->getDQLParts();

        if (!isset($parts['select']) || count($parts['select']) == 0) {
            throw new \RuntimeException('Provided $queryBuilder doesn\'t contain SELECT part.');
        }
        if (!isset($parts['from'])) {
            throw new \RuntimeException('Provided $queryBuilder doesn\'t contain FROM part.');
        }

        $rootAlias = $queryBuilder->getRootAlias();
        if (count($parts['select']) > 1) {
            foreach ($parts['from'] as $fromPart) {
                list($entityFqcn, $alias) = explode(' ', $fromPart);
                if ($entityFqcn === $rootFetchEntityFqcn) {
                    $rootAlias = $alias;
                }
            }
        } else {
            $rootAlias = $parts['select'][0];

            $isFound = false;
            foreach ($parts['from'] as $fromPart) {
                list($entityFqcn, $alias) = explode(' ', $fromPart);
                if ($alias === $rootAlias) {
                    $isFound = true;
                }
            }
            if (!$isFound) {
                throw new \RuntimeException(
                    "Unable to resolve fetch entity FQCN for alias '$rootAlias'. Do you have your SELECT and FROM parts properly built ?"
                );
            }
        }
        if (null === $rootAlias) {
            throw new \RuntimeException("Unable to resolve alias for entity $rootFetchEntityFqcn");
        }

        // DISTINCT is needed when there are LEFT JOINs in your queries
        $countQueryBuilder->add('select', "COUNT (DISTINCT {$parts['select'][0]})");
        $countQueryBuilder->resetDQLPart('orderBy'); // for COUNT queries it is completely pointless

        return $countQueryBuilder;
    }

    public function getResult($entityFqcn, array $arrayQuery)
    {
        return $this->buildQuery($entityFqcn, $arrayQuery)->getResult();
    }
}
