<?php

declare(strict_types=1);

namespace SymfonyBench;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

final class TreeBuilderHookWorkload0
{
    public function hookScenario0(BenchService00 $service): TreeBuilder
    {
        $pre0 = $service->handle00('pre_0');
        $pre1 = $service->handle00('pre_1');
        $pre2 = $service->handle00('pre_2');
        $pre3 = $service->handle00('pre_3');
        $pre4 = $service->handle00('pre_4');
        $pre5 = $service->handle00('pre_5');
        $pre6 = $service->handle00('pre_6');
        $pre7 = $service->handle00('pre_7');
        $pre8 = $service->handle00('pre_8');
        $pre9 = $service->handle00('pre_9');
        $pre10 = $service->handle00('pre_10');
        $pre11 = $service->handle00('pre_11');
        $tree = new TreeBuilder('hook_0_0', 'scalar');
        $nested = static function (): TreeBuilder {
            $tree = new TreeBuilder('inner_0_0');
            $tree->getRootNode()->children()->scalarNode('x')->end()->end();
            return $tree;
        };
        $nested();
        $root = $tree->getRootNode();
        $shadowed = static function (ArrayNodeDefinition $root): void {
            $root->children()->scalarNode('y')->end()->end();
        };
        $shadowed(new ArrayNodeDefinition('shadow_0'));
        $post0 = $service->handle00('post_0');
        $post1 = $service->handle00('post_1');
        $post2 = $service->handle00('post_2');
        $post3 = $service->handle00('post_3');
        $post4 = $service->handle00('post_4');
        $post5 = $service->handle00('post_5');
        $post6 = $service->handle00('post_6');
        $post7 = $service->handle00('post_7');
        $post8 = $service->handle00('post_8');
        $post9 = $service->handle00('post_9');
        $post10 = $service->handle00('post_10');
        $post11 = $service->handle00('post_11');
        $root->isRequired()->cannotBeEmpty()->info($pre0 . $post0)->end();

        (new TreeBuilder('inline_0_0', 'scalar'))
            ->getRootNode()
            ->isRequired()
            ->cannotBeEmpty()
            ->end();

        $arrayTree = new TreeBuilder('array_0_0');
        $arrayTree->getRootNode()->children()->scalarNode('k')->defaultValue($pre11 . $post11)->end()->end();

        return $arrayTree;
    }

    public function hookScenario1(BenchService00 $service): TreeBuilder
    {
        $pre0 = $service->handle00('pre_0');
        $pre1 = $service->handle00('pre_1');
        $pre2 = $service->handle00('pre_2');
        $pre3 = $service->handle00('pre_3');
        $pre4 = $service->handle00('pre_4');
        $pre5 = $service->handle00('pre_5');
        $pre6 = $service->handle00('pre_6');
        $pre7 = $service->handle00('pre_7');
        $pre8 = $service->handle00('pre_8');
        $pre9 = $service->handle00('pre_9');
        $pre10 = $service->handle00('pre_10');
        $pre11 = $service->handle00('pre_11');
        $tree = new TreeBuilder('hook_0_1', 'boolean');
        $nested = static function (): TreeBuilder {
            $tree = new TreeBuilder('inner_0_1');
            $tree->getRootNode()->children()->scalarNode('x')->end()->end();
            return $tree;
        };
        $nested();
        $root = $tree->getRootNode();
        $shadowed = static function (ArrayNodeDefinition $root): void {
            $root->children()->scalarNode('y')->end()->end();
        };
        $shadowed(new ArrayNodeDefinition('shadow_1'));
        $post0 = $service->handle00('post_0');
        $post1 = $service->handle00('post_1');
        $post2 = $service->handle00('post_2');
        $post3 = $service->handle00('post_3');
        $post4 = $service->handle00('post_4');
        $post5 = $service->handle00('post_5');
        $post6 = $service->handle00('post_6');
        $post7 = $service->handle00('post_7');
        $post8 = $service->handle00('post_8');
        $post9 = $service->handle00('post_9');
        $post10 = $service->handle00('post_10');
        $post11 = $service->handle00('post_11');
        $root->isRequired()->defaultTrue()->info($pre0 . $post0)->end();

        (new TreeBuilder('inline_0_1', 'boolean'))
            ->getRootNode()
            ->isRequired()
            ->defaultTrue()
            ->end();

        $arrayTree = new TreeBuilder('array_0_1');
        $arrayTree->getRootNode()->children()->scalarNode('k')->defaultValue($pre11 . $post11)->end()->end();

        return $arrayTree;
    }

    public function hookScenario2(BenchService00 $service): TreeBuilder
    {
        $pre0 = $service->handle00('pre_0');
        $pre1 = $service->handle00('pre_1');
        $pre2 = $service->handle00('pre_2');
        $pre3 = $service->handle00('pre_3');
        $pre4 = $service->handle00('pre_4');
        $pre5 = $service->handle00('pre_5');
        $pre6 = $service->handle00('pre_6');
        $pre7 = $service->handle00('pre_7');
        $pre8 = $service->handle00('pre_8');
        $pre9 = $service->handle00('pre_9');
        $pre10 = $service->handle00('pre_10');
        $pre11 = $service->handle00('pre_11');
        $tree = new TreeBuilder('hook_0_2', 'integer');
        $nested = static function (): TreeBuilder {
            $tree = new TreeBuilder('inner_0_2');
            $tree->getRootNode()->children()->scalarNode('x')->end()->end();
            return $tree;
        };
        $nested();
        $root = $tree->getRootNode();
        $shadowed = static function (ArrayNodeDefinition $root): void {
            $root->children()->scalarNode('y')->end()->end();
        };
        $shadowed(new ArrayNodeDefinition('shadow_2'));
        $post0 = $service->handle00('post_0');
        $post1 = $service->handle00('post_1');
        $post2 = $service->handle00('post_2');
        $post3 = $service->handle00('post_3');
        $post4 = $service->handle00('post_4');
        $post5 = $service->handle00('post_5');
        $post6 = $service->handle00('post_6');
        $post7 = $service->handle00('post_7');
        $post8 = $service->handle00('post_8');
        $post9 = $service->handle00('post_9');
        $post10 = $service->handle00('post_10');
        $post11 = $service->handle00('post_11');
        $root->isRequired()->defaultValue(1)->info($pre0 . $post0)->end();

        (new TreeBuilder('inline_0_2', 'integer'))
            ->getRootNode()
            ->isRequired()
            ->defaultValue(1)
            ->end();

        $arrayTree = new TreeBuilder('array_0_2');
        $arrayTree->getRootNode()->children()->scalarNode('k')->defaultValue($pre11 . $post11)->end()->end();

        return $arrayTree;
    }

    public function hookScenario3(BenchService00 $service): TreeBuilder
    {
        $pre0 = $service->handle00('pre_0');
        $pre1 = $service->handle00('pre_1');
        $pre2 = $service->handle00('pre_2');
        $pre3 = $service->handle00('pre_3');
        $pre4 = $service->handle00('pre_4');
        $pre5 = $service->handle00('pre_5');
        $pre6 = $service->handle00('pre_6');
        $pre7 = $service->handle00('pre_7');
        $pre8 = $service->handle00('pre_8');
        $pre9 = $service->handle00('pre_9');
        $pre10 = $service->handle00('pre_10');
        $pre11 = $service->handle00('pre_11');
        $tree = new TreeBuilder('hook_0_3', 'float');
        $nested = static function (): TreeBuilder {
            $tree = new TreeBuilder('inner_0_3');
            $tree->getRootNode()->children()->scalarNode('x')->end()->end();
            return $tree;
        };
        $nested();
        $root = $tree->getRootNode();
        $shadowed = static function (ArrayNodeDefinition $root): void {
            $root->children()->scalarNode('y')->end()->end();
        };
        $shadowed(new ArrayNodeDefinition('shadow_3'));
        $post0 = $service->handle00('post_0');
        $post1 = $service->handle00('post_1');
        $post2 = $service->handle00('post_2');
        $post3 = $service->handle00('post_3');
        $post4 = $service->handle00('post_4');
        $post5 = $service->handle00('post_5');
        $post6 = $service->handle00('post_6');
        $post7 = $service->handle00('post_7');
        $post8 = $service->handle00('post_8');
        $post9 = $service->handle00('post_9');
        $post10 = $service->handle00('post_10');
        $post11 = $service->handle00('post_11');
        $root->isRequired()->defaultValue(1.5)->info($pre0 . $post0)->end();

        (new TreeBuilder('inline_0_3', 'float'))
            ->getRootNode()
            ->isRequired()
            ->defaultValue(1.5)
            ->end();

        $arrayTree = new TreeBuilder('array_0_3');
        $arrayTree->getRootNode()->children()->scalarNode('k')->defaultValue($pre11 . $post11)->end()->end();

        return $arrayTree;
    }

    public function hookScenario4(BenchService00 $service): TreeBuilder
    {
        $pre0 = $service->handle00('pre_0');
        $pre1 = $service->handle00('pre_1');
        $pre2 = $service->handle00('pre_2');
        $pre3 = $service->handle00('pre_3');
        $pre4 = $service->handle00('pre_4');
        $pre5 = $service->handle00('pre_5');
        $pre6 = $service->handle00('pre_6');
        $pre7 = $service->handle00('pre_7');
        $pre8 = $service->handle00('pre_8');
        $pre9 = $service->handle00('pre_9');
        $pre10 = $service->handle00('pre_10');
        $pre11 = $service->handle00('pre_11');
        $tree = new TreeBuilder('hook_0_4', 'enum');
        $nested = static function (): TreeBuilder {
            $tree = new TreeBuilder('inner_0_4');
            $tree->getRootNode()->children()->scalarNode('x')->end()->end();
            return $tree;
        };
        $nested();
        $root = $tree->getRootNode();
        $shadowed = static function (ArrayNodeDefinition $root): void {
            $root->children()->scalarNode('y')->end()->end();
        };
        $shadowed(new ArrayNodeDefinition('shadow_4'));
        $post0 = $service->handle00('post_0');
        $post1 = $service->handle00('post_1');
        $post2 = $service->handle00('post_2');
        $post3 = $service->handle00('post_3');
        $post4 = $service->handle00('post_4');
        $post5 = $service->handle00('post_5');
        $post6 = $service->handle00('post_6');
        $post7 = $service->handle00('post_7');
        $post8 = $service->handle00('post_8');
        $post9 = $service->handle00('post_9');
        $post10 = $service->handle00('post_10');
        $post11 = $service->handle00('post_11');
        $root->isRequired()->defaultValue('a')->info($pre0 . $post0)->end();

        (new TreeBuilder('inline_0_4', 'enum'))
            ->getRootNode()
            ->isRequired()
            ->defaultValue('a')
            ->end();

        $arrayTree = new TreeBuilder('array_0_4');
        $arrayTree->getRootNode()->children()->scalarNode('k')->defaultValue($pre11 . $post11)->end()->end();

        return $arrayTree;
    }

    public function hookScenario5(BenchService00 $service): TreeBuilder
    {
        $pre0 = $service->handle00('pre_0');
        $pre1 = $service->handle00('pre_1');
        $pre2 = $service->handle00('pre_2');
        $pre3 = $service->handle00('pre_3');
        $pre4 = $service->handle00('pre_4');
        $pre5 = $service->handle00('pre_5');
        $pre6 = $service->handle00('pre_6');
        $pre7 = $service->handle00('pre_7');
        $pre8 = $service->handle00('pre_8');
        $pre9 = $service->handle00('pre_9');
        $pre10 = $service->handle00('pre_10');
        $pre11 = $service->handle00('pre_11');
        $tree = new TreeBuilder('hook_0_5', 'scalar');
        $nested = static function (): TreeBuilder {
            $tree = new TreeBuilder('inner_0_5');
            $tree->getRootNode()->children()->scalarNode('x')->end()->end();
            return $tree;
        };
        $nested();
        $root = $tree->getRootNode();
        $shadowed = static function (ArrayNodeDefinition $root): void {
            $root->children()->scalarNode('y')->end()->end();
        };
        $shadowed(new ArrayNodeDefinition('shadow_5'));
        $post0 = $service->handle00('post_0');
        $post1 = $service->handle00('post_1');
        $post2 = $service->handle00('post_2');
        $post3 = $service->handle00('post_3');
        $post4 = $service->handle00('post_4');
        $post5 = $service->handle00('post_5');
        $post6 = $service->handle00('post_6');
        $post7 = $service->handle00('post_7');
        $post8 = $service->handle00('post_8');
        $post9 = $service->handle00('post_9');
        $post10 = $service->handle00('post_10');
        $post11 = $service->handle00('post_11');
        $root->isRequired()->cannotBeEmpty()->info($pre0 . $post0)->end();

        (new TreeBuilder('inline_0_5', 'scalar'))
            ->getRootNode()
            ->isRequired()
            ->cannotBeEmpty()
            ->end();

        $arrayTree = new TreeBuilder('array_0_5');
        $arrayTree->getRootNode()->children()->scalarNode('k')->defaultValue($pre11 . $post11)->end()->end();

        return $arrayTree;
    }

    public function hookScenario6(BenchService00 $service): TreeBuilder
    {
        $pre0 = $service->handle00('pre_0');
        $pre1 = $service->handle00('pre_1');
        $pre2 = $service->handle00('pre_2');
        $pre3 = $service->handle00('pre_3');
        $pre4 = $service->handle00('pre_4');
        $pre5 = $service->handle00('pre_5');
        $pre6 = $service->handle00('pre_6');
        $pre7 = $service->handle00('pre_7');
        $pre8 = $service->handle00('pre_8');
        $pre9 = $service->handle00('pre_9');
        $pre10 = $service->handle00('pre_10');
        $pre11 = $service->handle00('pre_11');
        $tree = new TreeBuilder('hook_0_6', 'boolean');
        $nested = static function (): TreeBuilder {
            $tree = new TreeBuilder('inner_0_6');
            $tree->getRootNode()->children()->scalarNode('x')->end()->end();
            return $tree;
        };
        $nested();
        $root = $tree->getRootNode();
        $shadowed = static function (ArrayNodeDefinition $root): void {
            $root->children()->scalarNode('y')->end()->end();
        };
        $shadowed(new ArrayNodeDefinition('shadow_6'));
        $post0 = $service->handle00('post_0');
        $post1 = $service->handle00('post_1');
        $post2 = $service->handle00('post_2');
        $post3 = $service->handle00('post_3');
        $post4 = $service->handle00('post_4');
        $post5 = $service->handle00('post_5');
        $post6 = $service->handle00('post_6');
        $post7 = $service->handle00('post_7');
        $post8 = $service->handle00('post_8');
        $post9 = $service->handle00('post_9');
        $post10 = $service->handle00('post_10');
        $post11 = $service->handle00('post_11');
        $root->isRequired()->defaultTrue()->info($pre0 . $post0)->end();

        (new TreeBuilder('inline_0_6', 'boolean'))
            ->getRootNode()
            ->isRequired()
            ->defaultTrue()
            ->end();

        $arrayTree = new TreeBuilder('array_0_6');
        $arrayTree->getRootNode()->children()->scalarNode('k')->defaultValue($pre11 . $post11)->end()->end();

        return $arrayTree;
    }

    public function hookScenario7(BenchService00 $service): TreeBuilder
    {
        $pre0 = $service->handle00('pre_0');
        $pre1 = $service->handle00('pre_1');
        $pre2 = $service->handle00('pre_2');
        $pre3 = $service->handle00('pre_3');
        $pre4 = $service->handle00('pre_4');
        $pre5 = $service->handle00('pre_5');
        $pre6 = $service->handle00('pre_6');
        $pre7 = $service->handle00('pre_7');
        $pre8 = $service->handle00('pre_8');
        $pre9 = $service->handle00('pre_9');
        $pre10 = $service->handle00('pre_10');
        $pre11 = $service->handle00('pre_11');
        $tree = new TreeBuilder('hook_0_7', 'integer');
        $nested = static function (): TreeBuilder {
            $tree = new TreeBuilder('inner_0_7');
            $tree->getRootNode()->children()->scalarNode('x')->end()->end();
            return $tree;
        };
        $nested();
        $root = $tree->getRootNode();
        $shadowed = static function (ArrayNodeDefinition $root): void {
            $root->children()->scalarNode('y')->end()->end();
        };
        $shadowed(new ArrayNodeDefinition('shadow_7'));
        $post0 = $service->handle00('post_0');
        $post1 = $service->handle00('post_1');
        $post2 = $service->handle00('post_2');
        $post3 = $service->handle00('post_3');
        $post4 = $service->handle00('post_4');
        $post5 = $service->handle00('post_5');
        $post6 = $service->handle00('post_6');
        $post7 = $service->handle00('post_7');
        $post8 = $service->handle00('post_8');
        $post9 = $service->handle00('post_9');
        $post10 = $service->handle00('post_10');
        $post11 = $service->handle00('post_11');
        $root->isRequired()->defaultValue(1)->info($pre0 . $post0)->end();

        (new TreeBuilder('inline_0_7', 'integer'))
            ->getRootNode()
            ->isRequired()
            ->defaultValue(1)
            ->end();

        $arrayTree = new TreeBuilder('array_0_7');
        $arrayTree->getRootNode()->children()->scalarNode('k')->defaultValue($pre11 . $post11)->end()->end();

        return $arrayTree;
    }

    public function hookScenario8(BenchService00 $service): TreeBuilder
    {
        $pre0 = $service->handle00('pre_0');
        $pre1 = $service->handle00('pre_1');
        $pre2 = $service->handle00('pre_2');
        $pre3 = $service->handle00('pre_3');
        $pre4 = $service->handle00('pre_4');
        $pre5 = $service->handle00('pre_5');
        $pre6 = $service->handle00('pre_6');
        $pre7 = $service->handle00('pre_7');
        $pre8 = $service->handle00('pre_8');
        $pre9 = $service->handle00('pre_9');
        $pre10 = $service->handle00('pre_10');
        $pre11 = $service->handle00('pre_11');
        $tree = new TreeBuilder('hook_0_8', 'float');
        $nested = static function (): TreeBuilder {
            $tree = new TreeBuilder('inner_0_8');
            $tree->getRootNode()->children()->scalarNode('x')->end()->end();
            return $tree;
        };
        $nested();
        $root = $tree->getRootNode();
        $shadowed = static function (ArrayNodeDefinition $root): void {
            $root->children()->scalarNode('y')->end()->end();
        };
        $shadowed(new ArrayNodeDefinition('shadow_8'));
        $post0 = $service->handle00('post_0');
        $post1 = $service->handle00('post_1');
        $post2 = $service->handle00('post_2');
        $post3 = $service->handle00('post_3');
        $post4 = $service->handle00('post_4');
        $post5 = $service->handle00('post_5');
        $post6 = $service->handle00('post_6');
        $post7 = $service->handle00('post_7');
        $post8 = $service->handle00('post_8');
        $post9 = $service->handle00('post_9');
        $post10 = $service->handle00('post_10');
        $post11 = $service->handle00('post_11');
        $root->isRequired()->defaultValue(1.5)->info($pre0 . $post0)->end();

        (new TreeBuilder('inline_0_8', 'float'))
            ->getRootNode()
            ->isRequired()
            ->defaultValue(1.5)
            ->end();

        $arrayTree = new TreeBuilder('array_0_8');
        $arrayTree->getRootNode()->children()->scalarNode('k')->defaultValue($pre11 . $post11)->end()->end();

        return $arrayTree;
    }

    public function hookScenario9(BenchService00 $service): TreeBuilder
    {
        $pre0 = $service->handle00('pre_0');
        $pre1 = $service->handle00('pre_1');
        $pre2 = $service->handle00('pre_2');
        $pre3 = $service->handle00('pre_3');
        $pre4 = $service->handle00('pre_4');
        $pre5 = $service->handle00('pre_5');
        $pre6 = $service->handle00('pre_6');
        $pre7 = $service->handle00('pre_7');
        $pre8 = $service->handle00('pre_8');
        $pre9 = $service->handle00('pre_9');
        $pre10 = $service->handle00('pre_10');
        $pre11 = $service->handle00('pre_11');
        $tree = new TreeBuilder('hook_0_9', 'enum');
        $nested = static function (): TreeBuilder {
            $tree = new TreeBuilder('inner_0_9');
            $tree->getRootNode()->children()->scalarNode('x')->end()->end();
            return $tree;
        };
        $nested();
        $root = $tree->getRootNode();
        $shadowed = static function (ArrayNodeDefinition $root): void {
            $root->children()->scalarNode('y')->end()->end();
        };
        $shadowed(new ArrayNodeDefinition('shadow_9'));
        $post0 = $service->handle00('post_0');
        $post1 = $service->handle00('post_1');
        $post2 = $service->handle00('post_2');
        $post3 = $service->handle00('post_3');
        $post4 = $service->handle00('post_4');
        $post5 = $service->handle00('post_5');
        $post6 = $service->handle00('post_6');
        $post7 = $service->handle00('post_7');
        $post8 = $service->handle00('post_8');
        $post9 = $service->handle00('post_9');
        $post10 = $service->handle00('post_10');
        $post11 = $service->handle00('post_11');
        $root->isRequired()->defaultValue('a')->info($pre0 . $post0)->end();

        (new TreeBuilder('inline_0_9', 'enum'))
            ->getRootNode()
            ->isRequired()
            ->defaultValue('a')
            ->end();

        $arrayTree = new TreeBuilder('array_0_9');
        $arrayTree->getRootNode()->children()->scalarNode('k')->defaultValue($pre11 . $post11)->end()->end();

        return $arrayTree;
    }
}
