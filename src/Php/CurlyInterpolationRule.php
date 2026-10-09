<?php

namespace rdx\PhpstanExtra\Php;

use PhpParser\Node;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\File\FileReader;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FileNode>
 */
final class CurlyInterpolationRule implements Rule {

	public function getNodeType() : string {
		return FileNode::class;
	}

	public function processNode(Node $node, Scope $scope) : array {
		$code = FileReader::read($scope->getFile());

		$nodeFinder = new NodeFinder();
		$strings = $nodeFinder->findInstanceOf($node->getNodes(), InterpolatedString::class);

		$errors = [];
		foreach ($strings as $string) {
			foreach ($string->parts as $part) {
				if ($part instanceof InterpolatedStringPart) {
					continue;
				}

				$start = $part->getStartFilePos() - 1;
				if ($code[$start] !== '{') {
					continue;
				}

				$length = $part->getEndFilePos() - $start + 2;
				$source = substr($code, $start, $length);

				$errors[] = RuleErrorBuilder::message("String interpolation $source is not allowed. Use sprintf().")
					->identifier('rudie.CurlyInterpolationRule')
					->line($part->getStartLine())
					->build();
			}
		}

		return $errors;
	}

}
