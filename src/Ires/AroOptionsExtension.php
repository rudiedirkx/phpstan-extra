<?php

namespace rdx\PhpstanExtra\Ires;

use Framework\Aro\ActiveRecordObject;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class AroOptionsExtension implements DynamicFunctionReturnTypeExtension {

	public function isFunctionSupported(FunctionReflection $functionReflection) : bool {
		return $functionReflection->getName() === 'aro_options';
	}

	public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope) : ?Type {
		if (count($functionCall->args) < 1) {
			return null;
		}

		$objectsArg = $functionCall->args[0];
		$objectsType = $scope->getType($objectsArg->value);

		$arrays = $objectsType->getArrays();
		if (!count($arrays)) {
			return null;
		}

		$aroClasses = [];
		foreach ($arrays as $array) {
			foreach ($array->getItemType()->getObjectClassReflections() as $classReflection) {
				if ($classReflection->isSubclassOf(ActiveRecordObject::class)) {
					$aroClasses[] = $classReflection;
				}
			}
		}

		if (!count($aroClasses)) {
			return null;
		}

		$labelArg = $functionCall->args[1] ?? null;
		$keyArg = $functionCall->args[2] ?? null;

		$labelType = $this->getClassPropertyType($scope, $aroClasses, $labelArg, true);
		$keyType = $this->getClassPropertyType($scope, $aroClasses, $keyArg, false);

		return new ArrayType($keyType, $labelType);

	}

	/**
	 * @param list<ClassReflection> $aroClasses
	 */
	private function getClassPropertyType(Scope $scope, array $aroClasses, ?Arg $propertyArg, bool $isLabel) : Type {
		$strings = [];
		if ($propertyArg) {
			$argType = $scope->getType($propertyArg->value);
			$strings = array_map(fn($str) => $str->getValue(), $argType->getConstantStrings());
		}

		$types = [];
		foreach ($strings ?: [null] as $propName) {
			foreach ($aroClasses as $aroClass) {
// if (is_string($propName) && str_contains($propName, '.')) dump($aroClass->getName(), $propName);
				if ($propName === null) {
					if ($isLabel) {
						// From __toString()
						$type = new StringType();
					}
					else {
						// From property from static $_pk
						$pkPropName = $aroClass->getNativeProperty('_pk')->getNativeReflection()->getDefaultValue();
						$type = $aroClass->getProperty($pkPropName, new OutOfClassScope())
							->getReadableType();
					}
				}
				else {
					// From property
					$type = $aroClass->getProperty($propName, new OutOfClassScope())
						->getReadableType();

					// Keys are forced to scalar
					if (!$isLabel && $type->isScalar()->no()) {
						$type = new StringType();
					}
				}
				$types[] = $type;
			}
		}

		return TypeCombinator::union(...$types);
	}

}
