<?php

namespace rdx\PhpstanExtra\Laravel;

use App\Base\ModelCollection;
use Illuminate\Database\Eloquent\Model;
use Larastan\Larastan\Support\CollectionHelper;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

final class EloquentCollectionRelationExtension implements DynamicMethodReturnTypeExtension {

	public function __construct(
		protected ReflectionProvider $reflectionProvider,
		protected CollectionHelper $collectionHelper,
	) {}

	public function getClass() : string {
		return ModelCollection::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection) : bool {
		return $methodReflection->getName() === 'relation';
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope) : ?Type {
		if (count($methodCall->getArgs()) != 1) {
			return null;
		}

		$arg = $methodCall->getArgs()[0]->value;
		if (!($arg instanceof String_)) {
			return null;
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

		// That Model's relationship
		if (!$modelClassReflection->hasProperty($arg->value)) {
			return null;
		}

		$modelProperty = $modelClassReflection->getProperty($arg->value, $scope);
		$propertyType = TypeCombinator::removeNull($modelProperty->getReadableType());

		// If it's a Many relationship, take the target Model
		if ((new ObjectType(ModelCollection::class))->isSuperTypeOf($propertyType)->yes()) {
			$propertyType = $propertyType->getTemplateType(ModelCollection::class, 'TModel');
		}

		if (count($classNames = $propertyType->getObjectClassNames()) != 1) {
			return null;
		}

		return $this->collectionHelper->determineCollectionClass($classNames[0]);
	}

}
