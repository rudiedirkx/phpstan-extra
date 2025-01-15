<?php

namespace rdx\PhpstanExtra\Ires;

use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Type\AcceptsResult;
use PHPStan\Type\CompoundType;
use PHPStan\Type\Generic\TemplateType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Generic\TemplateTypeReference;
use PHPStan\Type\Generic\TemplateTypeVariance;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\IsSuperTypeOfResult;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;

class GenericAroPropertyType extends StringType {

	public function __construct(
		private Type $aroType,
		private bool $allowDots,
	) {}

	public function describe(VerbosityLevel $level) : string {
		return 'aro-property<' . $this->aroType->describe($level) . '>';
	}

	public function getReferencedClasses() : array {
		return $this->getGenericType()->getReferencedClasses();
	}

	public function getGenericType() : Type {
		return $this->aroType;
	}

	public function accepts(Type $type, bool $strictTypes) : AcceptsResult {
		if ($type instanceof CompoundType) {
			return $type->isAcceptedBy($this, $strictTypes);
		}

		if (count($type->getConstantStrings()) != 1) {
			return AcceptsResult::createMaybe();
		}

		$givenString = $type->getConstantStrings()[0]->getValue();
		$genericType = $this->getGenericType();
// dump(($genericType->getObjectClassNames()[0] ?? '?') . '  ::  ' . $givenString);

		if ($genericType->hasProperty($givenString)->yes()) {
			return AcceptsResult::createYes();
		}

		if (!$this->allowDots) {
			$reason = sprintf(
				"%s doesn't have a property '%s'.",
				$genericType->describe(VerbosityLevel::value()),
				$givenString,
			);
			return AcceptsResult::createNo([$reason]);
		}

		$path = array_filter(preg_split('#[\.\[\]]+#', $givenString), function(string $part) {
			return strlen($part) > 0;
		});

		$objectType = $genericType;
		foreach ($path as $part) {
			if (!$objectType->hasProperty($part)->yes()) {
				$reason = sprintf(
					"%s doesn't have a property '%s'.",
					$objectType->describe(VerbosityLevel::value()),
					$part,
				);
				return AcceptsResult::createNo([$reason]);
			}

			$objectType = TypeCombinator::removeNull(
				$objectType->getProperty($part, new OutOfClassScope())
					->getReadableType()
			);
		}

		return AcceptsResult::createYes();
	}

	public function isSuperTypeOf(Type $type) : IsSuperTypeOfResult {
		$constantStrings = $type->getConstantStrings();

		if (count($constantStrings) === 1) {
			if (! $this->getGenericType()->hasProperty($constantStrings[0]->getValue())->yes()) {
				return IsSuperTypeOfResult::createNo();
			}

			return IsSuperTypeOfResult::createYes();
		}

		if ($type instanceof self) {
			return $this->getGenericType()->isSuperTypeOf($type->getGenericType());
		}

		if ($type instanceof CompoundType) {
			return $type->isSubTypeOf($this);
		}

		return IsSuperTypeOfResult::createNo();
	}

	public function traverse(callable $cb) : Type {
		$newType = $cb($this->getGenericType());

		if ($newType === $this->getGenericType()) {
			return $this;
		}

		return new self($newType, $this->allowDots);
	}

	public function inferTemplateTypes(Type $receivedType) : TemplateTypeMap {
		if ($receivedType instanceof UnionType || $receivedType instanceof IntersectionType) { // @phpstan-ignore phpstanApi.instanceofType
			return $receivedType->inferTemplateTypesOn($this);
		}

		$constantStrings = $receivedType->getConstantStrings();

		if (count($constantStrings) === 1) {
			$typeToInfer = new ObjectType($constantStrings[0]->getValue());
		} elseif ($receivedType instanceof self) {
			$typeToInfer = $receivedType->aroType;
		} elseif ($receivedType->isClassString()->yes()) {
			$typeToInfer = $this->getGenericType();

			if ($typeToInfer instanceof TemplateType) {
				$typeToInfer = $typeToInfer->getBound();
			}

			$typeToInfer = TypeCombinator::intersect($typeToInfer, new ObjectWithoutClassType());
		} else {
			return TemplateTypeMap::createEmpty();
		}

		if (! $this->getGenericType()->isSuperTypeOf($typeToInfer)->no()) {
			return $this->getGenericType()->inferTemplateTypes($typeToInfer);
		}

		return TemplateTypeMap::createEmpty();
	}

	public function getReferencedTemplateTypes(TemplateTypeVariance $positionVariance) : array {
		$variance = $positionVariance->compose(TemplateTypeVariance::createCovariant());

		return $this->getGenericType()->getReferencedTemplateTypes($variance);
	}

	/**
	 * @param AssocArray $properties
	 */
	public static function __set_state(array $properties) : Type {
		return new self($properties['aroType'], $properties['allowDots']);
	}
}
