<?php

namespace Sli\DoctrineArrayQueryBuilderBundle\Tests\Functional\Querying;

use Doctrine\ORM\QueryBuilder;
use Sli\DoctrineArrayQueryBuilderBundle\Fixtures\User;
use Sli\DoctrineArrayQueryBuilderBundle\Parsing\Expression;
use Sli\DoctrineArrayQueryBuilderBundle\Querying\DoctrineQueryBuilderParametersBinder;
use Sli\DoctrineArrayQueryBuilderBundle\Querying\DqlCompiler;
use Sli\DoctrineArrayQueryBuilderBundle\Querying\ExpressionManager;
use Sli\DoctrineArrayQueryBuilderBundle\Tests\AbstractDatabaseTestCase;

require_once __DIR__ . '/../../Fixtures/entities.php';

/**
 * @author Sergei Lissovski <sergei.lissovski@gmail.com>
 */ 
class DqlCompilerTest extends AbstractDatabaseTestCase
{
    /* @var ExpressionManager */
    private $exprMgr;
    /* @var DoctrineQueryBuilderParametersBinder */
    private $binder;
    /* @var QueryBuilder */
    private $qb;
    /* @var DqlCompiler */
    private $compiler;

    public function doSetUp()
    {
        $this->qb = self::$em->createQueryBuilder();
        $this->exprMgr = new ExpressionManager(User::clazz(), self::$em);
        $this->binder = new DoctrineQueryBuilderParametersBinder($this->qb);
        $this->compiler = new DqlCompiler($this->exprMgr);
    }

    public function testCompileSimple()
    {
        $compileExpression = $this->compiler->compile(new Expression(':firstname', 'fn'), $this->binder);

        $this->assertEquals('e.firstname AS fn', $compileExpression);
    }

    public function testCompileFunction()
    {
        $rawExpr = array(
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
        );
        $expr = new Expression($rawExpr, 'fullname');

        $compiledExpression = $this->compiler->compile($expr, $this->binder);

        $this->assertEquals('CONCAT(e.firstname, CONCAT(?0, e.lastname)) AS fullname', $compiledExpression);
    }
}