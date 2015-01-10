<?php

namespace Sli\DoctrineArrayQueryBuilderBundle\Parsing;

/**
 * @author Sergei Lissovski <sergei.lissovski@gmail.com>
 */
interface FilterInterface
{
    /**
     * @return boolean
     */
    public function isValid();

    /**
     * @return array
     */
    public function compile();
}