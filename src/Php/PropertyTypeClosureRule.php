<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PhpParser\Node\PropertyItem;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<PropertyItem>
 */
final class PropertyTypeClosureRule implements Rule {

	use AnalyzesClosureType;

	public function getNodeType() : string {
		return PropertyItem::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		$classRefl = $scope->getClassReflection();

		$propertyName = $node->name->name;
		if (!$classRefl->hasProperty($propertyName)) return [];

		$propertyRefl = $classRefl->getProperty($propertyName, $scope);
		if ($propertyRefl->getDeclaringClass() !== $classRefl) return [];

		$propertyType = $propertyRefl->getReadableType();

		if (!$this->typeLacksClosureDetails($propertyType)) return [];

		return [
			RuleErrorBuilder::message(sprintf('Property $%s type Closure must include input & output details.', $propertyName))
				->identifier('rudie.PropertyTypeClosureRule')
				->build(),
		];
	}

}
