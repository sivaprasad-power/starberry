<?php declare(strict_types = 1);

namespace PHPStan\Rules\Deprecations;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\ClassNotFoundException;
use PHPStan\Reflection\MissingPropertyFromReflectionException;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use function sprintf;

/**
 * @implements Rule<PropertyFetch>
 */
class AccessDeprecatedPropertyRule implements Rule
{

	/** @var ReflectionProvider */
	private $reflectionProvider;

	/** @var DeprecatedScopeHelper */
	private $deprecatedScopeHelper;

	public function __construct(ReflectionProvider $reflectionProvider, DeprecatedScopeHelper $deprecatedScopeHelper)
	{
		$this->reflectionProvider = $reflectionProvider;
		$this->deprecatedScopeHelper = $deprecatedScopeHelper;
	}

	public function getNodeType(): string
	{
		return PropertyFetch::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if ($this->deprecatedScopeHelper->isScopeDeprecated($scope)) {
			return [];
		}

		if (!$node->name instanceof Identifier) {
			return [];
		}

		$propertyName = $node->name->name;
		$propertyAccessedOnType = $scope->getType($node->var);
		$referencedClasses = $propertyAccessedOnType->getObjectClassNames();

		foreach ($referencedClasses as $referencedClass) {
			try {
				$classReflection = $this->reflectionProvider->getClass($referencedClass);
				$propertyReflection = $classReflection->getProperty($propertyName, $scope);

				if ($propertyReflection->isDeprecated()->yes()) {
					$description = $propertyReflection->getDeprecatedDescription();
					if ($description === null) {
						return [sprintf(
							'Access to deprecated property $%s of class %s.',
							$propertyName,
							$propertyReflection->getDeclaringClass()->getName()
						)];
					}

					return [sprintf(
						"Access to deprecated property $%s of class %s:\n%s",
						$propertyName,
						$propertyReflection->getDeclaringClass()->getName(),
						$description
					)];
				}
			} catch (ClassNotFoundException $e) {
				// Other rules will notify if the class is not found
			} catch (MissingPropertyFromReflectionException $e) {
				// Other rules will notify if the property is not found
			}
		}

		return [];
	}

}
