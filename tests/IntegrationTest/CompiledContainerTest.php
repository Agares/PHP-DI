<?php

declare(strict_types=1);

namespace DI\Test\IntegrationTest;

use DI\CompiledContainer;
use DI\ContainerBuilder;
use function DI\create;
use DI\Discovery\KnownClasses;

/**
 * Tests specific to the compiled container.
 */
class CompiledContainerTest extends BaseContainerTest
{
    /** @test */
    public function the_same_container_can_be_recreated_multiple_times()
    {
        $builder = new ContainerBuilder;
        $builder->enableCompilation(self::COMPILATION_DIR, self::generateCompiledClassName());
        $builder->addDefinitions([
            'foo' => 'bar',
        ]);

        // The container can be built twice without error
        $builder->build();
        $builder->build();
    }

    /** @test */
    public function the_container_is_compiled_once_and_never_recompiled_after()
    {
        $compiledContainerClass = self::generateCompiledClassName();

        // Create a first compiled container in the file
        $builder = new ContainerBuilder;
        $builder->addDefinitions([
            'foo' => 'bar',
        ]);
        $builder->enableCompilation(self::COMPILATION_DIR, $compiledContainerClass);
        $builder->build();

        // Create a second compiled container in the same file but with a DIFFERENT configuration
        $builder = new ContainerBuilder;
        $builder->addDefinitions([
            'foo' => 'DIFFERENT',
        ]);
        $builder->enableCompilation(self::COMPILATION_DIR, $compiledContainerClass);
        $container = $builder->build();

        // The second container is actually using the config of the first because the container was already compiled
        // (the compiled file already existed so the second container did not recompile into it)
        // This behavior is obvious for performance reasons.
        self::assertEquals('bar', $container->get('foo'));
    }

    /**
     * @test
     * @expectedException \DI\Definition\Exception\InvalidDefinition
     * @expectedExceptionMessage Entry "foo" cannot be compiled: anonymous classes cannot be compiled
     */
    public function values_are_compiled()
    {
        $class = get_class(new class() {
        });

        $builder = new ContainerBuilder;
        $builder->addDefinitions([
            'foo' => create($class),
        ]);
        $builder->enableCompilation(self::COMPILATION_DIR, self::generateCompiledClassName());
        $builder->build();
    }

    public function test_that_values_are_compiled()
    {
        $builder = new ContainerBuilder;
        $builder->enableCompilation(self::COMPILATION_DIR, self::generateCompiledClassName());
        $builder->addDefinitions([
            'foo' => 'bar',
        ]);
        /** @var CompiledContainer $container */
        $container = $builder->build();

        self::assertTrue($container->isEntryCompiled('foo'));
    }

    public function test_that_autowired_classes_are_not_compiled_by_default()
    {
        $builder = new ContainerBuilder;
        $builder->enableCompilation(self::COMPILATION_DIR, self::generateCompiledClassName());
        /** @var CompiledContainer $container */
        $container = $builder->build();

        self::assertFalse($container->isEntryCompiled(CompiledContainerTest\AutowiredClass::class));
    }

    public function test_that_autowired_classes_can_be_compiled()
    {
        $builder = new ContainerBuilder;
        $builder->enableCompilation(self::COMPILATION_DIR, self::generateCompiledClassName());
        $builder->compileAllClasses(KnownClasses::fromArray([
            CompiledContainerTest\AutowiredClass::class,
        ]));
//        $builder->compileAllClasses(KnownClasses::fromArray(
//            array_keys(require __DIR__ . '/../../vendor/composer/autoload_classmap.php')
//        ));
        /** @var CompiledContainer $container */
        $container = $builder->build();

        self::assertTrue($container->isEntryCompiled(CompiledContainerTest\AutowiredClass::class));
    }

    /**
     * @test
     * @expectedException \DI\Definition\Exception\InvalidDefinition
     * @expectedExceptionMessage Entry "stdClass" cannot be compiled: An object was found but objects cannot be compiled
     */
    public function object_nested_in_other_definitions_cannot_be_compiled()
    {
        $builder = new ContainerBuilder;
        $builder->addDefinitions([
            \stdClass::class => create()
                ->property('foo', new \stdClass),
        ]);
        $builder->enableCompilation(self::COMPILATION_DIR, self::generateCompiledClassName());
        $builder->build();
    }

    /**
     * @test
     * @expectedException \DI\DependencyException
     * @expectedExceptionMessage Error while compiling foo. Error while compiling <nested definition>. Error while compiling <nested definition>. An object was found but objects cannot be compiled
     */
    public function object_nested_in_arrays_cannot_be_compiled()
    {
        $builder = new ContainerBuilder;
        $builder->addDefinitions([
            'foo' => [
                'bar' => [
                    'baz' => [
                        new \stdClass,
                    ],
                ],
            ],
        ]);
        $builder->enableCompilation(self::COMPILATION_DIR, self::generateCompiledClassName());
        $builder->build();
    }

    /**
     * @test
     * @expectedException \LogicException
     * @expectedExceptionMessage You cannot set a definition at runtime on a compiled container. You can either put your definitions in a file, disable compilation or ->set() a raw value directly (PHP object, string, int, ...) instead of a PHP-DI definition.
     */
    public function entries_cannot_be_overridden_by_definitions_in_the_compiled_container()
    {
        $builder = new ContainerBuilder;
        $builder->enableCompilation(self::COMPILATION_DIR, self::generateCompiledClassName());
        $builder->addDefinitions([
            'foo' => create(\stdClass::class),
        ]);
        $container = $builder->build();

        $container->set('foo', create(ContainerSetTest\Dummy::class));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The container cannot be compiled: `123-abc` is not a valid PHP class name
     */
    public function compiling_to_an_invalid_class_name_throws_an_error()
    {
        $builder = new ContainerBuilder;
        $builder->enableCompilation(self::COMPILATION_DIR, '123-abc');
        $builder->build();
    }

    /**
     * @test
     */
    public function the_compiled_container_can_extend_a_custom_class()
    {
        $builder = new ContainerBuilder;
        $builder->enableCompilation(
            self::COMPILATION_DIR,
            self::generateCompiledClassName(),
            // Customize the parent class
            CompiledContainerTest\CustomParentContainer::class
        );
        $container = $builder->build();

        self::assertInstanceOf(CompiledContainerTest\CustomParentContainer::class, $container);
    }
}

namespace DI\Test\IntegrationTest\CompiledContainerTest;

class Property
{
    public $foo;
}

class CustomParentContainer extends \DI\Container
{
}

class AutowiredClass
{
}
