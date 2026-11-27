<?php

declare(strict_types=1);

namespace SymfonyFixtureTreeBuilderReturn;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeParentInterface;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class CustomNodeParent implements NodeParentInterface
{
}

interface ParameterizedNullRootContract
{
    /** @return ArrayNodeDefinition<null> */
    public function contractSection(): ArrayNodeDefinition;
}

trait ParameterizedNullRootTrait
{
    /** @return ArrayNodeDefinition<null> */
    public function traitNullSection(): ArrayNodeDefinition
    {
        return (new TreeBuilder('trait_null'))->getRootNode();
    }
}

abstract class ParameterizedNullRootParent
{
    /** @return ArrayNodeDefinition<null> */
    public function inheritedNullSection(): ArrayNodeDefinition
    {
        return new ArrayNodeDefinition('parent');
    }

    /** @return ArrayNodeDefinition<CustomNodeParent> */
    abstract public function inheritedCustomParentSection(): ArrayNodeDefinition;
}

/** @return ArrayNodeDefinition<null> */
function buildExplicitNullRootSection(): ArrayNodeDefinition
{
    return (new TreeBuilder('fn_explicit_null'))->getRootNode();
}

final class TreeBuilderRootNodeReturnNegative extends ParameterizedNullRootParent implements ParameterizedNullRootContract
{
    use ParameterizedNullRootTrait;

    /** @return ArrayNodeDefinition<null> */
    public function explicitNullReturn(): ArrayNodeDefinition
    {
        return (new TreeBuilder('explicit_null'))->getRootNode();
    }

    public function contractSection(): ArrayNodeDefinition
    {
        return (new TreeBuilder('contract_null'))->getRootNode();
    }

    public function inheritedNullSection(): ArrayNodeDefinition
    {
        return (new TreeBuilder('inherited_null'))->getRootNode();
    }

    public function inheritedCustomParentSection(): ArrayNodeDefinition
    {
        return (new TreeBuilder('inherited_custom'))->getRootNode();
    }

    public function wrongConcreteNodeClass(): ArrayNodeDefinition
    {
        return (new TreeBuilder('wrong_scalar', 'scalar'))->getRootNode();
    }

    public function conditionalScalarToArrayRebind(bool $flag): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('conditional', 'scalar');
        if ($flag) {
            $treeBuilder = new TreeBuilder('conditional');
        }

        return $treeBuilder->getRootNode();
    }

    public function endParentGenericStillChecked(): void
    {
        (new TreeBuilder('root'))->getRootNode()->end()->missingOnTreeBuilder();
    }

    public function parameterizedClosureAndArrowReturns(): void
    {
        $nullClosure = /** @return ArrayNodeDefinition<null> */ static function (): ArrayNodeDefinition {
            return (new TreeBuilder('closure_explicit_null'))->getRootNode();
        };
        $nullArrow = /** @return ScalarNodeDefinition<null> */ static fn (): ScalarNodeDefinition => (new TreeBuilder('arrow_explicit_null', 'scalar'))->getRootNode();
        $customClosure = /** @return ArrayNodeDefinition<CustomNodeParent> */ static function (): ArrayNodeDefinition {
            return (new TreeBuilder('closure_custom_parent'))->getRootNode();
        };
        unset($nullClosure, $nullArrow, $customClosure);
    }

    /** @return \Closure(): ArrayNodeDefinition<null> */
    public function returnedInlineExplicitNullClosure(): \Closure
    {
        return /** @return ArrayNodeDefinition<null> */ static function (): ArrayNodeDefinition {
            return (new TreeBuilder('ret_inline_null'))->getRootNode();
        };
    }

    /** @return ScalarNodeDefinition<null> */
    public function explicitNullScalarReturn(): ScalarNodeDefinition
    {
        return (new TreeBuilder('scalar_explicit_null', 'scalar'))->getRootNode();
    }

    public function wrongConcreteScalarAsInteger(): ScalarNodeDefinition
    {
        return (new TreeBuilder('wrong_int', 'integer'))->getRootNode();
    }
}

final class UnparamOverrideOfParameterizedParent extends ParameterizedNullRootParent
{
    /** @return ArrayNodeDefinition */
    public function inheritedNullSection(): ArrayNodeDefinition
    {
        return (new TreeBuilder('override_unparam'))->getRootNode();
    }

    /** @return ArrayNodeDefinition */
    public function inheritedCustomParentSection(): ArrayNodeDefinition
    {
        return (new TreeBuilder('override_custom'))->getRootNode();
    }
}
