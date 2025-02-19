<?php

namespace Psalm\Internal\Stubs\Generator;

use PhpParser;
use Psalm\Internal\Scanner\ParsedDocblock;
use Psalm\Node\Expr\VirtualArray;
use Psalm\Node\Expr\VirtualArrayItem;
use Psalm\Node\Expr\VirtualClassConstFetch;
use Psalm\Node\Expr\VirtualConstFetch;
use Psalm\Node\Expr\VirtualVariable;
use Psalm\Node\Name\VirtualFullyQualified;
use Psalm\Node\Scalar\VirtualDNumber;
use Psalm\Node\Scalar\VirtualLNumber;
use Psalm\Node\Scalar\VirtualString;
use Psalm\Node\Stmt\VirtualFunction;
use Psalm\Node\Stmt\VirtualNamespace;
use Psalm\Node\VirtualConst;
use Psalm\Node\Stmt\VirtualConst as StmtVirtualConst_;
use Psalm\Node\VirtualIdentifier;
use Psalm\Node\VirtualName;
use Psalm\Node\VirtualNullableType;
use Psalm\Node\VirtualParam;
use Psalm\Type;
use function dirname;

class StubsGenerator
{
    public static function getAll(
        \Psalm\Codebase $codebase,
        \Psalm\Internal\Provider\ClassLikeStorageProvider $class_provider,
        \Psalm\Internal\Provider\FileStorageProvider $file_provider
    ): string {
        $namespaced_nodes = [];

        $psalm_base = dirname(__DIR__, 5);

        foreach ($class_provider->getAll() as $storage) {
            if (\strpos($storage->name, 'Psalm\\') === 0) {
                continue;
            }

            if ($storage->location
                && strpos($storage->location->file_path, $psalm_base) === 0
            ) {
                continue;
            }

            if ($storage->stubbed) {
                continue;
            }

            $name_parts = explode('\\', $storage->name);

            $classlike_name = array_pop($name_parts);
            $namespace_name = implode('\\', $name_parts);

            if (!isset($namespaced_nodes[$namespace_name])) {
                $namespaced_nodes[$namespace_name] = [];
            }

            $namespaced_nodes[$namespace_name][$classlike_name] = ClassLikeStubGenerator::getClassLikeNode(
                $codebase,
                $storage,
                $classlike_name
            );
        }

        $all_function_names = [];

        foreach ($codebase->functions->getAllStubbedFunctions() as $function_storage) {
            if ($function_storage->location
                && \strpos($function_storage->location->file_path, $psalm_base) === 0
            ) {
                continue;
            }

            if (!$function_storage->cased_name) {
                throw new \UnexpectedValueException('very bad');
            }

            $fq_name = $function_storage->cased_name;

            $all_function_names[$fq_name] = true;

            $name_parts = explode('\\', $fq_name);
            $function_name = array_pop($name_parts);

            $namespace_name = implode('\\', $name_parts);

            $namespaced_nodes[$namespace_name][$fq_name] = self::getFunctionNode(
                $function_storage,
                $function_name,
                $namespace_name
            );
        }

        foreach ($codebase->getAllStubbedConstants() as $fq_name => $type) {
            if ($type->isMixed()) {
                continue;
            }

            $name_parts = explode('\\', $fq_name);
            $constant_name = array_pop($name_parts);

            $namespace_name = implode('\\', $name_parts);

            $namespaced_nodes[$namespace_name][$fq_name] = new StmtVirtualConst_(
                [
                    new VirtualConst(
                        $constant_name,
                        self::getExpressionFromType($type)
                    )
                ]
            );
        }

        foreach ($file_provider->getAll() as $file_storage) {
            if (\strpos($file_storage->file_path, $psalm_base) === 0) {
                continue;
            }

            foreach ($file_storage->functions as $function_storage) {
                if (!$function_storage->cased_name) {
                    continue;
                }

                $fq_name = $function_storage->cased_name;

                if (isset($all_function_names[$fq_name])) {
                    continue;
                }

                $all_function_names[$fq_name] = true;

                $name_parts = explode('\\', $fq_name);
                $function_name = array_pop($name_parts);

                $namespace_name = implode('\\', $name_parts);

                $namespaced_nodes[$namespace_name][$fq_name] = self::getFunctionNode(
                    $function_storage,
                    $function_name,
                    $namespace_name
                );
            }

            foreach ($file_storage->constants as $fq_name => $type) {
                if ($type->isMixed()) {
                    continue;
                }

                if ($type->isMixed()) {
                    continue;
                }

                $name_parts = explode('\\', $fq_name);
                $constant_name = array_pop($name_parts);

                $namespace_name = implode('\\', $name_parts);

                $namespaced_nodes[$namespace_name][$fq_name] = new StmtVirtualConst_(
                    [
                        new VirtualConst(
                            $constant_name,
                            self::getExpressionFromType($type)
                        )
                    ]
                );
            }
        }

        ksort($namespaced_nodes);

        $namespace_stmts = [];

        foreach ($namespaced_nodes as $namespace_name => $stmts) {
            ksort($stmts);

            $namespace_stmts[] = new VirtualNamespace(
                $namespace_name ? new VirtualName($namespace_name) : null,
                array_values($stmts),
                ['kind' => PhpParser\Node\Stmt\Namespace_::KIND_BRACED]
            );
        }

        $prettyPrinter = new PhpParser\PrettyPrinter\Standard;
        return $prettyPrinter->prettyPrintFile($namespace_stmts);
    }

    private static function getFunctionNode(
        \Psalm\Storage\FunctionLikeStorage $function_storage,
        string $function_name,
        string $namespace_name
    ) : PhpParser\Node\Stmt\Function_ {
        $docblock = new ParsedDocblock('', []);

        foreach ($function_storage->template_types ?: [] as $template_name => $map) {
            $type = array_values($map)[0];

            $docblock->tags['template'][] = $template_name . ' as ' . $type->toNamespacedString(
                $namespace_name,
                [],
                null,
                false
            );
        }

        foreach ($function_storage->params as $param) {
            if ($param->type && $param->type !== $param->signature_type) {
                $docblock->tags['param'][] = $param->type->toNamespacedString(
                    $namespace_name,
                    [],
                    null,
                    false
                ) . ' $' . $param->name;
            }
        }

        if ($function_storage->return_type
            && $function_storage->signature_return_type !== $function_storage->return_type
        ) {
            $docblock->tags['return'][] = $function_storage->return_type->toNamespacedString(
                $namespace_name,
                [],
                null,
                false
            );
        }

        foreach ($function_storage->throws ?: [] as $exception_name => $_) {
            $docblock->tags['throws'][] = Type::getStringFromFQCLN(
                $exception_name,
                $namespace_name,
                [],
                null,
                false
            );
        }

        return new VirtualFunction(
            $function_name,
            [
                'params' => self::getFunctionParamNodes($function_storage),
                'returnType' => $function_storage->signature_return_type
                    ? self::getParserTypeFromPsalmType($function_storage->signature_return_type)
                    : null,
                'stmts' => [],
            ],
            [
                'comments' => $docblock->tags
                    ? [
                        new PhpParser\Comment\Doc(
                            \rtrim($docblock->render('        '))
                        )
                    ]
                    : []
            ]
        );
    }

    /**
     * @return list<PhpParser\Node\Param>
     */
    public static function getFunctionParamNodes(\Psalm\Storage\FunctionLikeStorage $method_storage): array
    {
        $param_nodes = [];

        foreach ($method_storage->params as $param) {
            $param_nodes[] = new VirtualParam(
                new VirtualVariable($param->name),
                $param->default_type instanceof Type\Union
                    ? self::getExpressionFromType($param->default_type)
                    : null,
                $param->signature_type
                    ? self::getParserTypeFromPsalmType($param->signature_type)
                    : null,
                $param->by_ref,
                $param->is_variadic
            );
        }

        return $param_nodes;
    }

    /**
     * @return PhpParser\Node\Identifier|PhpParser\Node\Name\FullyQualified|PhpParser\Node\NullableType|null
     */
    public static function getParserTypeFromPsalmType(Type\Union $type): ?PhpParser\NodeAbstract
    {
        $nullable = $type->isNullable();

        foreach ($type->getAtomicTypes() as $atomic_type) {
            if ($atomic_type instanceof Type\Atomic\TNull) {
                continue;
            }

            if ($atomic_type instanceof Type\Atomic\Scalar
                || $atomic_type instanceof Type\Atomic\TObject
                || $atomic_type instanceof Type\Atomic\TArray
                || $atomic_type instanceof Type\Atomic\TIterable
            ) {
                $identifier_string = $atomic_type->toPhpString(null, [], null, 8, 0);

                if ($identifier_string === null) {
                    throw new \UnexpectedValueException(
                        $atomic_type->getId() . ' could not be converted to an identifier'
                    );
                }
                $identifier = new VirtualIdentifier($identifier_string);

                if ($nullable) {
                    return new VirtualNullableType($identifier);
                }

                return $identifier;
            }

            if ($atomic_type instanceof Type\Atomic\TNamedObject) {
                $name_node = new VirtualFullyQualified($atomic_type->value);

                if ($nullable) {
                    return new VirtualNullableType($name_node);
                }

                return $name_node;
            }
        }

        return null;
    }

    public static function getExpressionFromType(Type\Union $type) : PhpParser\Node\Expr
    {
        foreach ($type->getAtomicTypes() as $atomic_type) {
            if ($atomic_type instanceof Type\Atomic\TLiteralClassString) {
                return new VirtualClassConstFetch(new VirtualName('\\' . $atomic_type->value), new VirtualIdentifier('class'));
            }

            if ($atomic_type instanceof Type\Atomic\TLiteralString) {
                return new VirtualString($atomic_type->value);
            }

            if ($atomic_type instanceof Type\Atomic\TLiteralInt) {
                return new VirtualLNumber($atomic_type->value);
            }

            if ($atomic_type instanceof Type\Atomic\TLiteralFloat) {
                return new VirtualDNumber($atomic_type->value);
            }

            if ($atomic_type instanceof Type\Atomic\TFalse) {
                return new VirtualConstFetch(new VirtualName('false'));
            }

            if ($atomic_type instanceof Type\Atomic\TTrue) {
                return new VirtualConstFetch(new VirtualName('true'));
            }

            if ($atomic_type instanceof Type\Atomic\TNull) {
                return new VirtualConstFetch(new VirtualName('null'));
            }

            if ($atomic_type instanceof Type\Atomic\TArray) {
                return new VirtualArray([]);
            }

            if ($atomic_type instanceof Type\Atomic\TKeyedArray) {
                $new_items = [];

                foreach ($atomic_type->properties as $property_name => $property_type) {
                    if ($atomic_type->is_list) {
                        $key_type = null;
                    } elseif (\is_int($property_name)) {
                        $key_type = new VirtualLNumber($property_name);
                    } else {
                        $key_type = new VirtualString($property_name);
                    }

                    $new_items[] = new VirtualArrayItem(
                        self::getExpressionFromType($property_type),
                        $key_type
                    );
                }

                return new VirtualArray($new_items);
            }

            if ($atomic_type instanceof Type\Atomic\TEnumCase) {
                return new VirtualClassConstFetch(new VirtualName('\\' . $atomic_type->value), new VirtualIdentifier($atomic_type->case_name));
            }
        }

        return new VirtualString('Psalm could not infer this type');
    }
}
