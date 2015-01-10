<?php

namespace Sli\DoctrineArrayQueryBuilderBundle\SortingFieldResolving;

/**
 * @author Sergei Lissovski <sergei.lissovski@gmail.com>
 */
interface SortingFieldResolverInterface
{
    /**
     * @param string $entityFqcn
     * @param string $fieldName
     *
     * @return string
     */
    public function resolve($entityFqcn, $fieldName);
}
