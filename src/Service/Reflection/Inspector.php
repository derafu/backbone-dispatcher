<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Service\Reflection;

use Derafu\Backbone\Attribute\Operation;
use Derafu\BackboneDispatcher\Contract\InspectorInterface;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Description;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use phpDocumentor\Reflection\DocBlock\Tags\Throws;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\Types\ContextFactory;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Symfony\Component\VarExporter\LazyObjectInterface;

/**
 * Reads reflection and PHPDoc data from a class.
 *
 * The only class in the package that imports anything from `Reflection*`:
 * every other collaborator that needs to know something about a worker's
 * class or a method's parameters (`Resolver`, `Explorer`, the operation
 * policies) asks `Inspector` for it instead of reflecting directly.
 */
class Inspector implements InspectorInterface
{
    /**
     * Gets the documentation of a class.
     *
     * @param object $service
     * @return array
     */
    public function getClassDoc(object $service): array
    {
        $reflection = $this->reflectClass($service);

        $docComment = $reflection->getDocComment();
        $docBlock = $docComment
            ? DocBlockFactory::createInstance()->create($docComment)
            : null
        ;

        // Resolve inheritance of documentation.
        $docBlock = $this->resolveInheritDoc(
            $docComment,
            $docBlock,
            fn () => $this->resolveInheritedClassDoc($reflection, DocBlockFactory::createInstance())
        );

        return [
            'name' => $reflection->getName(),
            'summary' => $docBlock?->getSummary() ?: null,
            'description' => $docBlock?->getDescription()->render() ?: null,
            'tags' => $this->getDocBlockTags($docBlock),
            'links' => $this->getDocBlockLinks($docBlock),
        ];
    }

    /**
     * Checks whether `$method` is an operation of `$service`: a public
     * method that exists on it (whether declared on its own class or
     * inherited from a parent), whose name does not start with `_`.
     *
     * This is a purely technical, factual check — is there a real, callable
     * public method by this name at all — and nothing more: it never judges
     * whether the method is *meant* to be exposed as a business operation.
     * That judgment belongs solely to whichever `OperationPolicyInterface`
     * is configured (`AllowAllOperationPolicy`, `TaggedOperationPolicy`,
     * etc.); this method, `getPublicMethods()`, and
     * `hasOperationAttribute()` must never draw that line themselves.
     *
     * `DirectDispatcher` uses this to decide whether the operation exists
     * at all (`OperationNotFoundException` otherwise), strictly before, and
     * independently of, consulting the `OperationPolicyInterface`.
     *
     * @param object $service
     * @param string $method
     * @return bool
     */
    public function isOperation(object $service, string $method): bool
    {
        return $this->isOperationMethod($this->reflectClass($service), $method);
    }

    /**
     * Checks whether `$method` is an operation of `$service` (see
     * `isOperation()`) tagged with the `#[Operation]` attribute.
     *
     * @param object $service
     * @param string $method
     * @return bool
     */
    public function hasOperationAttribute(object $service, string $method): bool
    {
        $reflection = $this->reflectClass($service);

        if (!$this->isOperationMethod($reflection, $method)) {
            return false;
        }

        return $reflection->getMethod($method)->getAttributes(Operation::class) !== [];
    }

    /**
     * Gets the parameters of one operation of a worker, by name.
     *
     * Narrow and deliberately cheap: unlike `getPublicMethods()`, this never
     * touches PHPDoc — `Resolver` only ever needs `name`/`type`/`required`/
     * `default` to cast and validate arguments, not `description`. Building
     * the `ReflectionMethod` here (instead of the caller building it) is
     * what keeps `Resolver` from importing anything from `Reflection*`.
     *
     * @param object $service
     * @param string $method
     * @return array
     */
    public function getOperationParameters(object $service, string $method): array
    {
        return $this->getParameters(new ReflectionMethod($service, $method));
    }

    /**
     * Gets the public methods of a class with its parameters.
     *
     * Never filters by `#[Operation]` or anything else: deciding which of
     * these public methods counts as an exposed operation is exclusively
     * an `OperationPolicyInterface`'s call, made by the caller (see
     * `Explorer`), never by `Inspector` itself.
     *
     * @param object $service
     * @return array
     */
    public function getPublicMethods(object $service): array
    {
        $reflection = $this->reflectClass($service);

        $methods = [];
        $docBlockFactory = DocBlockFactory::createInstance();
        $contextFactory = new ContextFactory();

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if (!$this->isOperationMethod($reflection, $name)) {
                continue;
            }

            $attributes = $method->getAttributes(Operation::class);
            $operationAttribute = $attributes !== [] ? $attributes[0]->newInstance() : null;

            $docComment = $method->getDocComment();
            $docBlock = $docComment
                ? $docBlockFactory->create($docComment, $contextFactory->createFromReflector($method))
                : null
            ;

            // Resolve inheritance of documentation.
            $docBlock = $this->resolveInheritDoc(
                $docComment,
                $docBlock,
                fn () => $this->resolveInheritedMethodDoc($method, $docBlockFactory)
            );

            $tags = $this->getDocBlockTags($docBlock);

            $parameters = $this->getParameters($method, $docBlock);
            if ($operationAttribute !== null && $operationAttribute->parameters !== []) {
                $parameters = $this->applyParameterOverrides(
                    $parameters,
                    $operationAttribute->parameters
                );
            }

            $methods[$name] = [
                'name' => $name,
                'summary' => $this->resolveSummary($operationAttribute, $docBlock),
                'description' => $this->resolveDescription($operationAttribute, $docBlock),
                'parameters' => $parameters,
                'returns' => $this->resolveReturns($method, $docBlock),
                'throws' => $this->resolveThrows($docBlock),
                'tags' => $tags,
                'links' => $this->getDocBlockLinks($docBlock),
                'deprecated' => !empty($tags['deprecated']),
                'operation' => $operationAttribute?->toArray(),
            ];
        }

        return $methods;
    }

    /**
     * Reflects `$service`'s class, unwrapping a lazy-loading proxy to its
     * real (parent) class first — every other method in this class needs
     * that same unwrapped `ReflectionClass`, never the proxy's own.
     *
     * @param object $service
     * @return ReflectionClass
     */
    private function reflectClass(object $service): ReflectionClass
    {
        $reflection = new ReflectionClass($service);

        return $service instanceof LazyObjectInterface
            ? $reflection->getParentClass()
            : $reflection;
    }

    /**
     * Checks whether `$method` is a public method of `$reflection`'s class
     * (declared on it directly or inherited from a parent class), whose
     * name does not start with `_` — the single definition of "operation"
     * `isOperation()`, `getPublicMethods()`, and `hasOperationAttribute()`
     * all build on, given an already-reflected class so none of them has
     * to reflect it twice.
     *
     * Deliberately silent on *which* declaring class the method comes
     * from: that detail is an implementation accident of the class
     * hierarchy, never a reason on its own to treat a method as not being
     * an operation. See `isOperation()`'s docblock for why this method
     * must stay a purely factual existence check.
     *
     * @param ReflectionClass $reflection
     * @param string $method
     * @return bool
     */
    private function isOperationMethod(ReflectionClass $reflection, string $method): bool
    {
        if ($method === '' || $method[0] === '_' || !$reflection->hasMethod($method)) {
            return false;
        }

        return $reflection->getMethod($method)->isPublic();
    }

    /**
     * Resolves every `{@inheritDoc}`/`@inheritDoc` in `$docBlock` (any
     * casing) against `$resolveParent` — called at most once, and only
     * when actually needed.
     *
     * Two independent cases, because phpDocumentor itself treats them
     * completely differently:
     *
     * - The raw comment says nothing at all except the marker (with or
     *   without the inline tag's `{}` — `@inheritDoc` with no braces is a
     *   bare block tag, what PHPStorm and other IDEs generate for an
     *   overridden method; phpDocumentor parses it as a separate tag, not
     *   as part of the summary/description, leaving both empty). Nothing
     *   here is checked against the parsed `$docBlock` for this case: an
     *   empty summary/description has no text left to match against, so
     *   the raw comment is the only place this can still be detected. The
     *   parent's entire docblock (summary, description, and its own tags)
     *   is adopted outright.
     * - `{@inheritDoc}` (with braces; the bare, no-braces form never
     *   survives as literal text once mixed with other content — see this
     *   class' own docblock) appears inline, spliced among other, real
     *   prose the docblock also has of its own — its own paragraph, or
     *   mid-sentence, in the summary, the description, or both
     *   independently. Only that occurrence is replaced with the parent's
     *   corresponding text (the parent's summary when found in the
     *   summary, the parent's description when found in the description);
     *   the surrounding own text and the docblock's own tags are kept
     *   untouched.
     *
     * @param string|false $docComment
     * @param DocBlock|null $docBlock
     * @param callable $resolveParent Lazily resolves the parent's
     *   `DocBlock`; never called when no inheritDoc marker is present.
     * @return DocBlock|null
     */
    private function resolveInheritDoc(
        string|false $docComment,
        ?DocBlock $docBlock,
        callable $resolveParent
    ): ?DocBlock {
        if ($this->isWholeDocInheritDocPlaceholder($docComment)) {
            return $resolveParent();
        }

        if ($docBlock === null) {
            return null;
        }

        $summary = $docBlock->getSummary();
        $description = $docBlock->getDescription()->render();

        $summaryHasPlaceholder = $this->containsInheritDocTag($summary);
        $descriptionHasPlaceholder = $this->containsInheritDocTag($description);

        if (!$summaryHasPlaceholder && !$descriptionHasPlaceholder) {
            return $docBlock;
        }

        $parentDocBlock = $resolveParent();

        if ($summaryHasPlaceholder) {
            $summary = $this->replaceInheritDocTag(
                $summary,
                $parentDocBlock?->getSummary() ?? ''
            );
        }

        if ($descriptionHasPlaceholder) {
            $description = $this->replaceInheritDocTag(
                $description,
                $parentDocBlock?->getDescription()->render() ?? ''
            );
        }

        return new DocBlock($summary, new Description($description), $docBlock->getTags());
    }

    /**
     * Checks whether a raw doc comment says nothing but "inherit the
     * parent's" — in any of its valid, real-world spellings: `@inheritDoc`/
     * `@inheritdoc` (any casing), with or without the inline tag's `{}`.
     *
     * Deliberately works on the raw comment (before `DocBlockFactory`
     * parses it), not on a parsed `DocBlock`'s summary/description: with
     * braces (`{@inheritDoc}`), phpDocumentor keeps the tag as plain text
     * in the summary; without them, it parses it into a separate tag
     * instead, leaving summary/description empty — matching the raw text
     * once, before that parsing fork happens, covers both regardless of
     * which path phpDocumentor takes.
     *
     * Deliberately never true when the comment has anything else in it:
     * the pattern anchors to the entire comment, so a docblock that also
     * has real, own content of its own falls through to the inline splice
     * handling in `resolveInheritDoc()` instead.
     *
     * @param string|false $docComment
     * @return bool
     */
    private function isWholeDocInheritDocPlaceholder(string|false $docComment): bool
    {
        if ($docComment === false) {
            return false;
        }

        return (bool) preg_match(
            '#^/\*\*[\s*]*\{?@inheritdoc\}?[\s*]*\*/$#i',
            trim($docComment)
        );
    }

    /**
     * Checks whether `$text` contains the `{@inheritDoc}` inline tag (any
     * casing), anywhere — the whole text, its own paragraph, or spliced
     * mid-sentence alongside other real prose.
     *
     * @param string $text
     * @return bool
     */
    private function containsInheritDocTag(string $text): bool
    {
        return (bool) preg_match('/\{@inheritdoc\}/i', $text);
    }

    /**
     * Replaces every `{@inheritDoc}` occurrence (any casing) in `$text`
     * with `$replacement`, verbatim.
     *
     * Uses a callback replacement (not a plain string one) specifically so
     * `$replacement` is never interpreted as a backreference pattern (e.g.
     * a parent docblock that itself mentions a `$variable`, which a plain
     * `preg_replace()` string replacement would otherwise try to expand).
     *
     * @param string $text
     * @param string $replacement
     * @return string
     */
    private function replaceInheritDocTag(string $text, string $replacement): string
    {
        return preg_replace_callback(
            '/\{@inheritdoc\}/i',
            static fn (): string => $replacement,
            $text
        );
    }

    /**
     * Resolves the summary to report for a method: `#[Operation]`'s `name`
     * when given, otherwise the PHPDoc summary.
     *
     * @param Operation|null $operationAttribute
     * @param DocBlock|null $docBlock
     * @return string|null
     */
    private function resolveSummary(?Operation $operationAttribute, ?DocBlock $docBlock): ?string
    {
        if ($operationAttribute !== null && $operationAttribute->name !== null) {
            return $operationAttribute->name;
        }

        return $docBlock?->getSummary() ?: null;
    }

    /**
     * Resolves the description to report for a method: `#[Operation]`'s
     * `description` when given, otherwise the PHPDoc description.
     *
     * @param Operation|null $operationAttribute
     * @param DocBlock|null $docBlock
     * @return string|null
     */
    private function resolveDescription(?Operation $operationAttribute, ?DocBlock $docBlock): ?string
    {
        if ($operationAttribute !== null && $operationAttribute->description !== null) {
            return $operationAttribute->description;
        }

        return $docBlock?->getDescription()->render() ?: null;
    }

    /**
     * Resolves what to report about a method's return value: its reflected
     * type (same union-type handling as a parameter's) and the `@return`
     * tag's description from PHPDoc, if any.
     *
     * Unlike `resolveSummary()`/`resolveDescription()`, there is no
     * `#[Operation]` override for this yet — only reflection/PHPDoc feed
     * it, the same "cheap, always-available" source `getParameters()`
     * already relies on for a parameter's `type`.
     *
     * @param ReflectionMethod $method
     * @param DocBlock|null $docBlock
     * @return array{type: string, description: string|null}
     */
    private function resolveReturns(ReflectionMethod $method, ?DocBlock $docBlock): array
    {
        $returnTags = $docBlock ? $docBlock->getTagsByName('return') : [];
        $returnTag = $returnTags[0] ?? null;

        $description = null;
        if ($returnTag instanceof Return_) {
            $description = $returnTag->getDescription()->render() ?: null;
        }

        return [
            'type' => $this->resolveTypeName($method->getReturnType()),
            'description' => $description,
        ];
    }

    /**
     * Resolves what to report about a method's declared exceptions: one
     * entry per `@throws` tag, with the exception's type and description.
     *
     * Unlike a return type, there is no reflected signature to fall back
     * on — PHP does not declare thrown exceptions as part of a method's
     * type — so this is entirely sourced from PHPDoc, and a method can
     * have any number of `@throws` tags (unlike the single `@return`).
     *
     * @param DocBlock|null $docBlock
     * @return array<array{type: string, description: string|null}>
     */
    private function resolveThrows(?DocBlock $docBlock): array
    {
        if (!$docBlock) {
            return [];
        }

        $throws = [];
        foreach ($docBlock->getTagsByName('throws') as $tag) {
            if (!$tag instanceof Throws) {
                continue;
            }

            $throws[] = [
                'type' => ltrim((string) $tag->getType(), '\\'),
                'description' => $tag->getDescription()->render() ?: null,
            ];
        }

        return $throws;
    }

    /**
     * Resolves a reflected type (a parameter's or a return type's) to its
     * string representation, handling union types the same way for both.
     *
     * @param ReflectionType|null $type
     * @return string
     */
    private function resolveTypeName(?ReflectionType $type): string
    {
        if ($type instanceof ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $unionType) {
                $types[] = $unionType->getName();
            }

            return implode('|', $types);
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        return 'mixed';
    }

    /**
     * Gets the information of the parameters of a method.
     *
     * @param ReflectionMethod $method
     * @param DocBlock|null $docBlock
     * @return array
     */
    private function getParameters(
        ReflectionMethod $method,
        ?DocBlock $docBlock = null
    ): array {
        $parametersInfo = [];
        $docParams = $docBlock ? $docBlock->getTagsByName('param') : [];

        foreach ($method->getParameters() as $parameter) {
            $typeName = $this->resolveTypeName($parameter->getType());

            $docParam = null;
            foreach ($docParams as $tag) {
                assert($tag instanceof Param);
                if ($tag->getVariableName() === $parameter->getName()) {
                    $docParam = $tag;
                    break;
                }
            }

            $paramInfo = [
                'name' => $parameter->getName(),
                'type' => $typeName,
                'required' => !$parameter->isOptional(),
                'default' => $parameter->isDefaultValueAvailable()
                    ? $parameter->getDefaultValue()
                    : null
                ,
                'description' => $docParam?->getDescription()->render(),
            ];

            $parametersInfo[] = $paramInfo;
        }

        return $parametersInfo;
    }

    /**
     * Applies `#[Operation]`'s per-parameter overrides on top of the
     * reflected parameter info, keyed by parameter name.
     *
     * Only the keys present in `$overrides` are applied — reflection
     * already gives an accurate `type` for the normal case, so a
     * parameter with no override for it keeps the reflected one.
     * `'example'` is never something reflection can produce, so it is
     * only ever present here when the attribute provides it.
     *
     * @param array $parameters
     * @param array $overrides
     * @return array
     */
    private function applyParameterOverrides(array $parameters, array $overrides): array
    {
        foreach ($parameters as &$parameter) {
            $override = $overrides[$parameter['name']] ?? null;

            if ($override === null) {
                continue;
            }

            if (array_key_exists('type', $override)) {
                $parameter['type'] = $override['type'];
            }

            if (array_key_exists('description', $override)) {
                $parameter['description'] = $override['description'];
            }

            if (array_key_exists('example', $override)) {
                $parameter['example'] = $override['example'];
            }
        }

        return $parameters;
    }

    /**
     * Resolves the inherited documentation of a parent method or interface.
     *
     * The `Context` (namespace + `use` statements) passed to the factory is
     * built from whichever reflector's doc comment actually gets parsed —
     * the parent method's or the interface method's, never the original,
     * inheriting method's — since that is the file whose `use` statements
     * a relative type name like `@throws BookException` must resolve
     * against to become a FQCN.
     *
     * @param ReflectionMethod $method
     * @param DocBlockFactoryInterface $docBlockFactory
     * @return DocBlock|null
     */
    private function resolveInheritedMethodDoc(
        ReflectionMethod $method,
        DocBlockFactoryInterface $docBlockFactory
    ): ?DocBlock {
        $contextFactory = new ContextFactory();

        // Search documentation in the parent class.
        $parentClass = $method->getDeclaringClass()->getParentClass();
        if ($parentClass && $parentClass->hasMethod($method->getName())) {
            $parentMethod = $parentClass->getMethod($method->getName());
            $parentDocComment = $parentMethod->getDocComment();
            if ($parentDocComment) {
                return $docBlockFactory->create(
                    $parentDocComment,
                    $contextFactory->createFromReflector($parentMethod)
                );
            }
        }

        // Search documentation in implemented interfaces.
        foreach ($method->getDeclaringClass()->getInterfaces() as $interface) {
            if ($interface->hasMethod($method->getName())) {
                $interfaceMethod = $interface->getMethod($method->getName());
                $interfaceDocComment = $interfaceMethod->getDocComment();
                if ($interfaceDocComment) {
                    return $docBlockFactory->create(
                        $interfaceDocComment,
                        $contextFactory->createFromReflector($interfaceMethod)
                    );
                }
            }
        }

        return null;
    }

    /**
     * Resolves the inherited documentation of a parent class.
     *
     * @param ReflectionClass $reflection
     * @param DocBlockFactoryInterface $docBlockFactory
     * @return DocBlock|null
     */
    private function resolveInheritedClassDoc(
        ReflectionClass $reflection,
        DocBlockFactoryInterface $docBlockFactory
    ): ?DocBlock {
        // Search documentation in the parent class.
        $parentClass = $reflection->getParentClass();
        if ($parentClass) {
            $parentDocComment = $parentClass->getDocComment();
            if ($parentDocComment) {
                return $docBlockFactory->create($parentDocComment);
            }
        }

        // Search documentation in implemented interfaces.
        foreach ($reflection->getInterfaces() as $interface) {
            $interfaceDocComment = $interface->getDocComment();
            if ($interfaceDocComment) {
                return $docBlockFactory->create($interfaceDocComment);
            }
        }

        return null;
    }

    /**
     * Gets the tags from a class DocBlock.
     *
     * @param DocBlock|null $docBlock
     * @return array
     */
    private function getDocBlockTags(?DocBlock $docBlock): array
    {
        if (!$docBlock) {
            return [];
        }

        $tags = [];
        foreach ($docBlock->getTags() as $tag) {
            $tagName = $tag->getName();

            if ($tagName === 'link') {
                continue;
            }

            if (!isset($tags[$tagName])) {
                $tags[$tagName] = [];
            }

            // Convert tag to string representation.
            $tags[$tagName][] = (string) $tag;
        }

        return $tags;
    }

    /**
     * Gets the links from a class DocBlock.
     *
     * @param DocBlock|null $docBlock
     * @return array
     */
    private function getDocBlockLinks(?DocBlock $docBlock): array
    {
        if (!$docBlock) {
            return [];
        }

        $links = [];
        foreach ($docBlock->getTagsByName('link') as $linkTag) {
            // Extract URL and description from link tag.
            $linkString = (string) $linkTag;

            // Parse link format: @link URL Description.
            if (preg_match('/^(\S+)\s+(.+)$/', $linkString, $matches)) {
                $links[] = [
                    'url' => $matches[1],
                    'description' => $matches[2],
                ];
            } else {
                // If no description, just use the URL.
                $links[] = [
                    'url' => $linkString,
                    'description' => null,
                ];
            }
        }

        return $links;
    }
}
