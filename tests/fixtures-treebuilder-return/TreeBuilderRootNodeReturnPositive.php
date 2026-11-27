<?php

declare(strict_types=1);

namespace SymfonyFixtureTreeBuilderReturn;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;
use Symfony\Component\Config\Definition\Builder\EnumNodeDefinition;
use Symfony\Component\Config\Definition\Builder\FloatNodeDefinition;
use Symfony\Component\Config\Definition\Builder\IntegerNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\StringNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Builder\VariableNodeDefinition;

interface UnparameterizedRootContract
{
    /** @return ArrayNodeDefinition */
    public function contractUnparameterizedSection(): ArrayNodeDefinition;
}

final class TreeBuilderRootNodeReturnPositive implements UnparameterizedRootContract
{
    public function contractUnparameterizedSection(): ArrayNodeDefinition
    {
        return (new TreeBuilder('contract_unparam'))->getRootNode();
    }

    /** @return ArrayNodeDefinition */
    public function docblockUnparameterizedArray(): ArrayNodeDefinition
    {
        return (new TreeBuilder('doc_unparam'))->getRootNode();
    }

    /** @return ArrayNodeDefinition */
    public function docblockOnlyUnparameterizedArray()
    {
        return (new TreeBuilder('doc_only_unparam'))->getRootNode();
    }

    /** @return ScalarNodeDefinition */
    public function docblockUnparameterizedScalar(): ScalarNodeDefinition
    {
        return (new TreeBuilder('doc_scalar', 'scalar'))->getRootNode();
    }

    public function createScalarRoot(): ScalarNodeDefinition
    {
        return (new TreeBuilder('scalar_root', 'scalar'))->getRootNode();
    }

    public function createBooleanRoot(): BooleanNodeDefinition
    {
        return (new TreeBuilder('bool_root', 'boolean'))->getRootNode();
    }

    public function createIntegerRoot(): IntegerNodeDefinition
    {
        return (new TreeBuilder('int_root', 'integer'))->getRootNode();
    }

    public function createFloatRoot(): FloatNodeDefinition
    {
        return (new TreeBuilder('float_root', 'float'))->getRootNode();
    }

    public function createEnumRoot(): EnumNodeDefinition
    {
        return (new TreeBuilder('enum_root', 'enum'))->getRootNode();
    }

    public function createVariableRoot(): VariableNodeDefinition
    {
        return (new TreeBuilder('var_root', 'variable'))->getRootNode();
    }

    public function createStringRoot(): StringNodeDefinition
    {
        return (new TreeBuilder('str_root', 'string'))->getRootNode();
    }

    public function closureAndArrowRootReturns(): void
    {
        $arrayClosure = static function (): ArrayNodeDefinition {
            return (new TreeBuilder('closure_array'))->getRootNode();
        };
        $docArrayClosure = /** @return ArrayNodeDefinition */ static function (): ArrayNodeDefinition {
            return (new TreeBuilder('closure_doc_array'))->getRootNode();
        };
        $scalarArrow = static fn (): ScalarNodeDefinition => (new TreeBuilder('arrow_scalar', 'scalar'))->getRootNode();
        $intClosure = static function (): IntegerNodeDefinition {
            return (new TreeBuilder('closure_int', 'integer'))->getRootNode();
        };
        unset($arrayClosure, $docArrayClosure, $scalarArrow, $intClosure);
    }
}
