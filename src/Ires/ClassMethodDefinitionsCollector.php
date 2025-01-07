<?php

namespace rdx\PhpstanExtra\Ires;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * @implements Collector<ClassMethod, string>
 */
final class ClassMethodDefinitionsCollector implements Collector {

	use CaresAboutClassMethods;

	public function getNodeType() : string {
		return ClassMethod::class;
	}

	public function processNode(Node $node, Scope $scope) : string {
		if (str_contains($scope->getFile(), '/Controllers/')) {
			return '';
		}

		$methodName = $this->maybeIgnoreName($node->name->name, 'def');
		if (!$methodName) {
			return '';
		}

		$outOfClass = new OutOfClassScope();

		$classRefl = $scope->getClassReflection();
		if ($classRefl->isInterface()) {
			return '';
		}

		if ($classRefl->getMethod($methodName, $outOfClass)->isAbstract()) {
// dump("abstract: $methodName");
			return '';
		}

		$parentClassRefl = $classRefl->getParentClass();
		if ($parentClassRefl && $parentClassRefl->hasMethod($methodName)) {
// dump("parent: $methodName");
			return '';
		}

		foreach ($classRefl->getInterfaces() as $interfaceRefl) {
			if ($interfaceRefl->hasMethod($methodName)) {
// dump("interface: $methodName");
				return '';
			}
		}

		foreach ($classRefl->getTraits() as $traitRefl) {
			if ($traitRefl->hasMethod($methodName) && $traitRefl->getMethod($methodName, $outOfClass)->isAbstract()) {
// dump("trait: $methodName");
				return '';
			}
		}

		foreach ($node->getAttribute('comments') ?? [] as $doc) {
			if ($doc instanceof Doc && str_contains($doc->getText(), '@IgnoreUnusedMethod')) {
				return '';
			}
		}

		return $methodName;
	}

}
