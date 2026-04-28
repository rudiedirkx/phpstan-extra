<?php

namespace rdx\PhpstanExtra\Laravel;

use App\Base\ModelCollection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Larastan\Larastan\Support\CollectionHelper;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\GeneralizePrecision;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class EloquentCollectionPluckExtension implements DynamicMethodReturnTypeExtension {

	public function __construct(
		protected ReflectionProvider $reflectionProvider,
	) {}

	public function getClass() : string {
		return ModelCollection::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection) : bool {
		return $methodReflection->getName() === 'pluck';
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope) : ?Type {
		if (count($methodCall->getArgs()) < 1) {
			return null;
		}

		$args = $methodCall->getArgs();
		$valueArg = $args[0]->value;
		if (!($valueArg instanceof String_)) {
			return null;
		}
		$valuePropertyName = $valueArg->value;

		if (str_contains($valuePropertyName, '.')) {
			return null;
		}

		$keyPropertyName = null;
		$keyArg = $args[1]->value ?? null;
		if ($keyArg && $keyArg instanceof String_) {
			$keyPropertyName = $keyArg->value;
			if (str_contains($keyPropertyName, '.')) {
				return null;
			}
		}

		// ModelCollection
		$calledOnType = $scope->getType($methodCall->var);
		if (!count($calledOnType->getObjectClassNames())) {
			return null;
		}

		if (!(new ObjectType(ModelCollection::class))->isSuperTypeOf($calledOnType)->yes()) {
			return null;
		}

		$modelType = $calledOnType->getTemplateType(ModelCollection::class, 'TModel');

		// A Model
		if (!count($modelClasses = $modelType->getObjectClassNames())) {
			return null;
		}

		if (!(new ObjectType(Model::class))->isSuperTypeOf($modelType)->yes()) {
			return null;
		}

		$modelClass = $modelClasses[0];
		$modelClassReflection = $this->reflectionProvider->getClass($modelClass);

		// That Model's property
		if (!$modelClassReflection->hasProperty($valuePropertyName)) {
			return null;
		}

		$modelProperty = $modelClassReflection->getProperty($valuePropertyName, $scope);
		$valuePropertyType = TypeCombinator::removeNull($modelProperty->getReadableType());
		$valuePropertyType = $this->fixIntType($valuePropertyType);

		if ($keyPropertyName) {
			$modelProperty = $modelClassReflection->getProperty($keyPropertyName, $scope);
			$keyType = TypeCombinator::removeNull($modelProperty->getReadableType());
		}
		else {
			$keyType = $calledOnType->getTemplateType(ModelCollection::class, 'TKey');
		}
		$keyType = $this->fixIntType($keyType);

		return new GenericObjectType(Collection::class, [$keyType, $valuePropertyType]);
	}

	private function fixIntType(Type $type) : Type {
		if ($type instanceof IntegerRangeType) {
			return $type->generalize(GeneralizePrecision::lessSpecific());
		}

		return $type;
	}

}
