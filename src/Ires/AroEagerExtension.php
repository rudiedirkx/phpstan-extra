<?php

namespace rdx\PhpstanExtra\Ires;

use App\Services\Aro\AppActiveRecordObject;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class AroEagerExtension implements DynamicStaticMethodReturnTypeExtension {

	public function __construct(
		protected ReflectionProvider $reflectionProvider,
	) {}

	public function getClass() : string {
		return AppActiveRecordObject::class;
	}

	public function isStaticMethodSupported(MethodReflection $methodReflection) : bool {
		return $methodReflection->getName() === 'eager';
	}

	public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $staticCall, Scope $scope) : ?Type {
		if (count($staticCall->getArgs()) != 2) {
			return null;
		}

		$relationArg = $staticCall->getArgs()[0]->value;
		$relationType = $scope->getType($relationArg);
		$relationNames = array_map(fn($string) => $string->getValue(), $relationType->getConstantStrings());
		if (count($relationNames) != 1) {
			return null;
		}

		if (!($staticCall->class instanceof FullyQualified)) {
			return null;
		}

		// Model class
		$modelClassName = $staticCall->class->toCodeString();
		$modelClassReflection = $this->reflectionProvider->getClass($modelClassName);

		// That Model's relationship
		if (!$modelClassReflection->hasProperty($relationNames[0])) {
			return null;
		}

		$modelProperty = $modelClassReflection->getProperty($relationNames[0], $scope);
		$propertyType = TypeCombinator::removeNull($modelProperty->getReadableType());

		if (count($arrays = $propertyType->getArrays())) {
			$propertyType = TypeCombinator::removeNull($arrays[0]->getItemType());
		}

		return new ArrayType(new IntegerType(), $propertyType);
	}

}
