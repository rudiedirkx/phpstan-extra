<?php

namespace rdx\PhpstanExtra\Ires;

use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Type;

final class AroPropertyTypeNodeResolverExtension implements TypeNodeResolverExtension {

	public function __construct(
		protected TypeNodeResolver $baseResolver,
		// private ModelPropertyHelper $modelPropertyHelper,
	) {}

	public function resolve(TypeNode $typeNode, NameScope $nameScope) : ?Type {
		if (!$typeNode instanceof GenericTypeNode) {
			return null;
		}

		if ($typeNode->type->name === 'aro-property') {
			$dots = false;
		}
		elseif ($typeNode->type->name === 'aro-dot-property') {
			$dots = true;
		}
		else {
			return null;
		}

		if (count($typeNode->genericTypes) != 1) {
			return new ErrorType();
		}

		$aroType = $this->baseResolver->resolve($typeNode->genericTypes[0], $nameScope);

		return new GenericAroPropertyType($aroType, $dots);
	}
}
