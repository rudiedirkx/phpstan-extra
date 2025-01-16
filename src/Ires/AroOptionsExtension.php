<?php

namespace rdx\PhpstanExtra\Ires;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Analyser\Scope;
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
		if (count($arrays) != 1) {
			return null;
		}

		$aroClasses = $arrays[0]->getItemType()->getObjectClassReflections();
		if (count($aroClasses) != 1) {
			return null;
		}

		$aroClass = $aroClasses[0];
		$objectType = new ObjectType($aroClass->getName());

		$labelArg = $functionCall->args[1] ?? null;
		if ($labelArg && !$this->isNull($labelType = $scope->getType($labelArg->value))) {
			$labelStrings = $labelType->getConstantStrings();
			if (count($labelStrings) != 1) {
				return null;
			}

			$labelName = $labelStrings[0]->getValue();
			$labelType = $this->getPropertyType($objectType, $labelName);
			if (!$labelType) {
				return null;
			}
		}
		else {
			$labelType = new StringType();
		}

		$keyArg = $functionCall->args[2] ?? null;
		if ($keyArg && !$this->isNull($keyType = $scope->getType($keyArg->value))) {
			$keyStrings = $keyType->getConstantStrings();
			if (count($keyStrings) != 1) {
				return null;
			}
			$keyName = $keyStrings[0]->getValue();
		}
		else {
			$keyName = $aroClass->getNativeProperty('_pk')->getNativeReflection()->getDefaultValue();
		}
		$keyType = $this->getPropertyType($objectType, $keyName);
		if (!$keyType) {
			return null;
		}

		return new ArrayType($keyType, $labelType);
	}

	private function getPropertyType(ObjectType $objectType, string $propertyPath) : ?Type {
		$path = array_filter(preg_split('#[\.\[\]]+#', $propertyPath), function(string $part) {
			return strlen($part) > 0;
		});

		$sourceType = $objectType;
		foreach ($path as $part) {
			if (!$sourceType->hasProperty($part)->yes()) {
				return null;
			}

			$sourceType = TypeCombinator::removeNull(
				$sourceType->getProperty($part, new OutOfClassScope())
					->getReadableType()
			);
		}

		return $sourceType;
	}

	private function isNull(Type $type) : bool {
		return $type->isNull()->yes();
	}

}
