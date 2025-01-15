<?php

use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;

$removePropsAlways = function($obj, array $out) : array {
	return [];
};

$removePropsIfNested = function($obj, $out, $stub, $isNested) : array {
	return $isNested ? [] : $out;
};

$casters = [
	// Laravel
	'Illuminate\Contracts\Container\Container' => $removePropsIfNested,
	'Illuminate\Routing\RouteCollection' => $removePropsIfNested,

	// PhpParser
	'PhpParser\NodeAbstract' => function($obj, $out) : array {
		unset($out[Caster::PREFIX_PROTECTED . 'attributes']['parent']);
		unset($out[Caster::PREFIX_PROTECTED . 'attributes']['next']);
		unset($out[Caster::PREFIX_PROTECTED . 'attributes']['previous']);
		return $out;
	},

	// PHPStan
	'PHPStan\Analyser\ConstantResolver' => $removePropsIfNested,
	'PHPStan\Analyser\NodeScopeResolver' => $removePropsIfNested,
	'PHPStan\Analyser\TypeSpecifier' => $removePropsIfNested,
	'PHPStan\BetterReflection\Reflection\Adapter\ReflectionClass' => $removePropsIfNested,
	'PHPStan\BetterReflection\Reflection\ReflectionClass' => $removePropsIfNested,
	'PHPStan\BetterReflection\Reflector\DefaultReflector' => $removePropsIfNested,
	'PHPStan\BetterReflection\SourceLocator\Located\LocatedSource' => $removePropsIfNested,
	'PHPStan\DependencyInjection\MemoizingContainer' => $removePropsIfNested,
	'PHPStan\Node\Printer\ExprPrinter' => $removePropsIfNested,
	'PHPStan\Parser\CachedParser' => $removePropsIfNested,
	'PHPStan\Parser\CleaningParser' => $removePropsIfNested,
	'PHPStan\PhpDoc\PhpDocInheritanceResolver' => $removePropsIfNested,
	'PHPStan\PhpDoc\ResolvedPhpDocBlock' => $removePropsIfNested,
	'PHPStan\PhpDoc\StubPhpDocProvider' => $removePropsIfNested,
	'PHPStan\Reflection\InitializerExprTypeResolver' => $removePropsIfNested,
	// 'PHPStan\Reflection\MethodsClassReflectionExtension' => $removePropsIfNested,
	'PHPStan\Reflection\Php\NativeBuiltinMethodReflection' => $removePropsIfNested,
	// 'PHPStan\Reflection\PropertiesClassReflectionExtension' => $removePropsIfNested,
	'PHPStan\Reflection\ReflectionProvider\MemoizingReflectionProvider' => $removePropsIfNested,
	'PHPStan\Reflection\SignatureMap\Php8SignatureMapProvider' => $removePropsIfNested,
	// 'PHPStan\Reflection\Php\PhpClassReflectionExtension' => $removePropsIfNested,
	'PHPStan\Type\DynamicReturnTypeExtensionRegistry' => $removePropsIfNested,
	'PHPStan\Type\FileTypeMapper' => $removePropsIfNested,
	'PHPStan\Reflection\ClassReflection' => function($obj, array $out) : array {
// dd(array_keys($out));
		unset($out[sprintf(Caster::PATTERN_PRIVATE, 'PHPStan\Reflection\ClassReflection', 'ancestors')]);
		unset($out[sprintf(Caster::PATTERN_PRIVATE, 'PHPStan\Reflection\ClassReflection', 'cachedInterfaces')]);
		unset($out[sprintf(Caster::PATTERN_PRIVATE, 'PHPStan\Reflection\ClassReflection', 'cachedParentClass')]);
		unset($out[sprintf(Caster::PATTERN_PRIVATE, 'PHPStan\Reflection\ClassReflection', 'methods')]);
		unset($out[sprintf(Caster::PATTERN_PRIVATE, 'PHPStan\Reflection\ClassReflection', 'properties')]);
		unset($out[sprintf(Caster::PATTERN_PRIVATE, 'PHPStan\Reflection\ClassReflection', 'constants')]);
		unset($out[sprintf(Caster::PATTERN_PRIVATE, 'PHPStan\Reflection\ClassReflection', 'propertiesClassReflectionExtensions')]);
		unset($out[sprintf(Caster::PATTERN_PRIVATE, 'PHPStan\Reflection\ClassReflection', 'methodsClassReflectionExtensions')]);
		return $out;
	},
	'PHPStan\Type\Type' => function($obj, array $out) : array {
		unset($out[sprintf(Caster::PATTERN_PRIVATE, 'PHPStan\Type\ObjectType', 'cachedParent')]);
		unset($out[sprintf(Caster::PATTERN_PRIVATE, 'PHPStan\Type\ObjectType', 'cachedInterfaces')]);
		unset($out[sprintf(Caster::PATTERN_PRIVATE, 'PHPStan\Type\ObjectType', 'currentAncestors')]);
		return $out;
	},
];

foreach ($casters as $class => $callback) {
	AbstractCloner::$defaultCasters[$class] = $callback;
}
