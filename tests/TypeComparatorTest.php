<?php
namespace Psalm\Tests;

use Psalm\Internal\Analyzer\ProjectAnalyzer;
use Psalm\Internal\Provider\FakeFileProvider;
use Psalm\Internal\Provider\Providers;
use Psalm\Internal\RuntimeCaches;
use Psalm\Internal\Type\Comparator\UnionTypeComparator;
use Psalm\Internal\Type\TypeTokenizer;
use Psalm\Tests\Internal\Provider\FakeParserCacheProvider;
use Psalm\Type;

use function array_diff_key;
use function array_keys;
use function array_map;

class TypeComparatorTest extends TestCase
{
    public function setUp(): void
    {
        RuntimeCaches::clearAll();
        $this->file_provider = new FakeFileProvider();

        $config = new TestConfig();

        $providers = new Providers(
            $this->file_provider,
            new FakeParserCacheProvider()
        );

        $this->project_analyzer = new ProjectAnalyzer(
            $config,
            $providers
        );
    }

    /**
     * @dataProvider getAllBasicTypes
     */
    public function testTypeAcceptsItself(string $type_string): void
    {
        $type_1 = Type::parseString($type_string);
        $type_2 = Type::parseString($type_string);

        $this->assertTrue(
            UnionTypeComparator::isContainedBy(
                $this->project_analyzer->getCodebase(),
                $type_1,
                $type_2
            )
        );
    }

    /**
     * @return array<array{string}>
     */
    public function getAllBasicTypes(): array
    {
        // these types are not valid without generics attached
        $basic_generic_types = [
            'key-of' => true,
            'arraylike-object' => true,
            'value-of' => true,
            'class-string-map' => true,
            'int-mask-of' => true,
            'int-mask' => true,
            'pure-Closure' => true,
        ];

        $basic_types = array_diff_key(
            TypeTokenizer::PSALM_RESERVED_WORDS,
            $basic_generic_types,
            [
                'open-resource' => true, // unverifiable
                'mysql-escaped-string' => true, // deprecated
                'non-empty-countable' => true, // bit weird, maybe a bug?
            ]
        );
        return array_map(
            function ($type) {
                return [$type];
            },
            array_keys($basic_types)
        );
    }

    /**
     * @dataProvider getAllowedChildTypes
     */
    public function testTypeAcceptsType(string $parent_type_string, string $child_type_string): void
    {
        $parent_type = Type::parseString($parent_type_string);
        $child_type = Type::parseString($child_type_string);

        $this->assertTrue(
            UnionTypeComparator::isContainedBy(
                $this->project_analyzer->getCodebase(),
                $child_type,
                $parent_type
            )
        );
    }

    /**
     * @return array<array{string, string}>
     */
    public function getAllowedChildTypes(): array
    {
        return [
            'iterableAcceptsArray' => [
                'iterable',
                'array',
            ],
            'listAcceptsEmptyArray' => [
                'list',
                'array<empty, empty>',
            ],
            'arrayAcceptsEmptyArray' => [
                'array',
                'array<empty, empty>',
            ],
            'arrayOptionalKeyed1AcceptsEmptyArray' => [
                'array{foo?: string}',
                'array<empty, empty>',
            ],
            'arrayOptionalKeyed2AcceptsEmptyArray' => [
                'array{foo?: string}&array<string, mixed>',
                'array<empty, empty>',
            ],
            'Lowercase-stringAndCallable-string' => [
                'lowercase-string',
                'callable-string',
            ],
        ];
    }
}
