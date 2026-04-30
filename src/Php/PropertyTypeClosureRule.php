<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassNode>
 */
final class PropertyTypeClosureRule implements Rule {

	use AnalyzesClosureType;

	public function getNodeType() : string {
		return InClassNode::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		$classRefl = $node->getClassReflection();

		$errors = [];

		foreach ($classRefl->getNativeReflection()->getProperties() as $tempPropertyRefl) {
			$propertyName = $tempPropertyRefl->getName();
			if (!$classRefl->hasProperty($propertyName)) continue;

			$propertyRefl = $classRefl->getProperty($propertyName, $scope);
			if ($propertyRefl->getDeclaringClass() !== $classRefl) continue;

			$propertyType = $propertyRefl->getReadableType();

			if (!$this->typeLacksClosureDetails($propertyType)) continue;

			$errors[] = RuleErrorBuilder::message(sprintf('Property $%s type Closure must include input & output details.', $propertyName))
				->identifier('rudie.PropertyTypeClosureRule')
				->build();
		}

		return $errors;
	}

}
