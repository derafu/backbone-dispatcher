<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\BackboneDispatcher\Contract;

/**
 * Walks the package registry of the application and returns plain data
 * describing its packages, components, workers and operations.
 */
interface ExplorerInterface
{
    /**
     * Returns the list of packages of the application.
     *
     * @return list<array{id: string, name: string, summary: string|null, description: string|null}>
     */
    public function getPackages(): array;

    /**
     * Returns the list of components of a package.
     *
     * @param string $package
     * @return list<array{id: string, name: string, summary: string|null, description: string|null}>
     */
    public function getComponents(string $package): array;

    /**
     * Returns the list of workers of a component.
     *
     * @param string $package
     * @param string $component
     * @return list<array{id: string, name: string, summary: string|null, description: string|null}>
     */
    public function getWorkers(string $package, string $component): array;

    /**
     * Returns the list of operations of a worker.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @return list<array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     parameters: list<array{
     *         name: string,
     *         type: string,
     *         required: bool,
     *         default: mixed,
     *         description: string|null,
     *         example?: mixed,
     *     }>,
     *     returns: array{type: string, description: string|null},
     *     throws: array<array{type: string, description: string|null}>,
     *     tags: array<string, list<string>>,
     *     links: list<array{url: string, description: string|null}>,
     *     deprecated: bool,
     *     operation: array{
     *         name: string|null,
     *         description: string|null,
     *         parameters: array<array-key, mixed>,
     *         results: array<array-key, mixed>,
     *     }|null,
     * }>
     */
    public function getOperations(
        string $package,
        string $component,
        string $worker
    ): array;

    /**
     * Returns the data of a specific package.
     *
     * @param string $package
     * @param boolean $withComponents
     * @return ($withComponents is true ? array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     components: list<array{id: string, name: string, summary: string|null, description: string|null}>,
     * } : array{id: string, name: string, summary: string|null, description: string|null})
     */
    public function getPackage(
        string $package,
        bool $withComponents = false
    ): array;

    /**
     * Returns the data of a specific component.
     *
     * @param string $package
     * @param string $component
     * @param boolean $withWorkers
     * @return ($withWorkers is true ? array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     workers: list<array{id: string, name: string, summary: string|null, description: string|null}>,
     * } : array{id: string, name: string, summary: string|null, description: string|null})
     */
    public function getComponent(
        string $package,
        string $component,
        bool $withWorkers = false
    ): array;

    /**
     * Returns the data of a specific worker.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param boolean $withOperations
     * @return ($withOperations is true ? array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     operations: list<array{
     *         id: string,
     *         name: string,
     *         summary: string|null,
     *         description: string|null,
     *         parameters: list<array{
     *             name: string,
     *             type: string,
     *             required: bool,
     *             default: mixed,
     *             description: string|null,
     *             example?: mixed,
     *         }>,
     *         returns: array{type: string, description: string|null},
     *         throws: array<array{type: string, description: string|null}>,
     *         tags: array<string, list<string>>,
     *         links: list<array{url: string, description: string|null}>,
     *         deprecated: bool,
     *         operation: array{
     *             name: string|null,
     *             description: string|null,
     *             parameters: array<array-key, mixed>,
     *             results: array<array-key, mixed>,
     *         }|null,
     *     }>,
     * } : array{id: string, name: string, summary: string|null, description: string|null})
     */
    public function getWorker(
        string $package,
        string $component,
        string $worker,
        bool $withOperations = false
    ): array;

    /**
     * Returns the data of a specific operation.
     *
     * @param string $package
     * @param string $component
     * @param string $worker
     * @param string $operation
     * @return array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     parameters: list<array{
     *         name: string,
     *         type: string,
     *         required: bool,
     *         default: mixed,
     *         description: string|null,
     *         example?: mixed,
     *     }>,
     *     returns: array{type: string, description: string|null},
     *     throws: array<array{type: string, description: string|null}>,
     *     tags: array<string, list<string>>,
     *     links: list<array{url: string, description: string|null}>,
     *     deprecated: bool,
     *     operation: array{
     *         name: string|null,
     *         description: string|null,
     *         parameters: array<array-key, mixed>,
     *         results: array<array-key, mixed>,
     *     }|null,
     * }
     */
    public function getOperation(
        string $package,
        string $component,
        string $worker,
        string $operation
    ): array;

    /**
     * Single entry point over the rest of this interface: resolves a
     * discovery id (`"package"`, `"package.component"`,
     * `"package.component.worker"` or `"package.component.worker::operation"`)
     * to whichever of `getPackage()`/`getComponent()`/`getWorker()`/
     * `getOperation()` matches — or, for `null` (there is no single "root"
     * resource to look up): `{'summary' => ..., 'description' => ...,
     * 'packages' => getPackages()}`. `summary`/`description` are the
     * package registry's own PHPDoc — the one thing the root has an
     * honest source for even though it has no `id`/`name` (a registry is
     * not a `ServiceInterface` with an identity, unlike a
     * package/component/worker) — kept alongside `packages` rather than
     * replacing it, the same way every other level keeps its own
     * `summary`/`description` alongside its children.
     *
     * @param string|null $id
     * @return ($id is null|'' ? array{
     *     summary: string|null,
     *     description: string|null,
     *     packages: list<array{id: string, name: string, summary: string|null, description: string|null}>,
     * } : (array{id: string, name: string, summary: string|null, description: string|null}|array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     parameters: list<array{
     *         name: string,
     *         type: string,
     *         required: bool,
     *         default: mixed,
     *         description: string|null,
     *         example?: mixed,
     *     }>,
     *     returns: array{type: string, description: string|null},
     *     throws: array<array{type: string, description: string|null}>,
     *     tags: array<string, list<string>>,
     *     links: list<array{url: string, description: string|null}>,
     *     deprecated: bool,
     *     operation: array{
     *         name: string|null,
     *         description: string|null,
     *         parameters: array<array-key, mixed>,
     *         results: array<array-key, mixed>,
     *     }|null,
     * }))
     */
    public function describe(?string $id = null): array;

    /**
     * Same resolution as `describe()`, but every level nests its children
     * underneath instead of stopping at that one node — a package comes
     * back with its `components`, each of those with its own `workers`,
     * each of those with its own `operations`. An operation id resolves
     * exactly like `describe()`, since operations have no children to nest.
     *
     * For `null`, this nests the same way: `{'summary' => ..., 'description'
     * => ..., 'packages' => [...]}`, just like `describe(null)`, except
     * each package in `packages` is deeply nested (its own `components`,
     * down to `operations`) rather than the shallow `getPackage()` shape
     * `describe(null)`'s `packages` holds.
     *
     * @param string|null $id
     * @return ($id is null|'' ? array{
     *     summary: string|null,
     *     description: string|null,
     *     packages: list<array{
     *         id: string,
     *         name: string,
     *         summary: string|null,
     *         description: string|null,
     *         components: list<array{
     *             id: string,
     *             name: string,
     *             summary: string|null,
     *             description: string|null,
     *             workers: list<array{
     *                 id: string,
     *                 name: string,
     *                 summary: string|null,
     *                 description: string|null,
     *                 operations: list<array{
     *                     id: string,
     *                     name: string,
     *                     summary: string|null,
     *                     description: string|null,
     *                     parameters: list<array{
     *                         name: string,
     *                         type: string,
     *                         required: bool,
     *                         default: mixed,
     *                         description: string|null,
     *                         example?: mixed,
     *                     }>,
     *                     returns: array{type: string, description: string|null},
     *                     throws: array<array{type: string, description: string|null}>,
     *                     tags: array<string, list<string>>,
     *                     links: list<array{url: string, description: string|null}>,
     *                     deprecated: bool,
     *                     operation: array{
     *                         name: string|null,
     *                         description: string|null,
     *                         parameters: array<array-key, mixed>,
     *                         results: array<array-key, mixed>,
     *                     }|null,
     *                 }>,
     *             }>,
     *         }>,
     *     }>,
     * } : (array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     components: list<array{
     *         id: string,
     *         name: string,
     *         summary: string|null,
     *         description: string|null,
     *         workers: list<array{
     *             id: string,
     *             name: string,
     *             summary: string|null,
     *             description: string|null,
     *             operations: list<array{
     *                 id: string,
     *                 name: string,
     *                 summary: string|null,
     *                 description: string|null,
     *                 parameters: list<array{
     *                     name: string,
     *                     type: string,
     *                     required: bool,
     *                     default: mixed,
     *                     description: string|null,
     *                     example?: mixed,
     *                 }>,
     *                 returns: array{type: string, description: string|null},
     *                 throws: array<array{type: string, description: string|null}>,
     *                 tags: array<string, list<string>>,
     *                 links: list<array{url: string, description: string|null}>,
     *                 deprecated: bool,
     *                 operation: array{
     *                     name: string|null,
     *                     description: string|null,
     *                     parameters: array<array-key, mixed>,
     *                     results: array<array-key, mixed>,
     *                 }|null,
     *             }>,
     *         }>,
     *     }>,
     * }|array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     workers: list<array{
     *         id: string,
     *         name: string,
     *         summary: string|null,
     *         description: string|null,
     *         operations: list<array{
     *             id: string,
     *             name: string,
     *             summary: string|null,
     *             description: string|null,
     *             parameters: list<array{
     *                 name: string,
     *                 type: string,
     *                 required: bool,
     *                 default: mixed,
     *                 description: string|null,
     *                 example?: mixed,
     *             }>,
     *             returns: array{type: string, description: string|null},
     *             throws: array<array{type: string, description: string|null}>,
     *             tags: array<string, list<string>>,
     *             links: list<array{url: string, description: string|null}>,
     *             deprecated: bool,
     *             operation: array{
     *                 name: string|null,
     *                 description: string|null,
     *                 parameters: array<array-key, mixed>,
     *                 results: array<array-key, mixed>,
     *             }|null,
     *         }>,
     *     }>,
     * }|array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     operations: list<array{
     *         id: string,
     *         name: string,
     *         summary: string|null,
     *         description: string|null,
     *         parameters: list<array{
     *             name: string,
     *             type: string,
     *             required: bool,
     *             default: mixed,
     *             description: string|null,
     *             example?: mixed,
     *         }>,
     *         returns: array{type: string, description: string|null},
     *         throws: array<array{type: string, description: string|null}>,
     *         tags: array<string, list<string>>,
     *         links: list<array{url: string, description: string|null}>,
     *         deprecated: bool,
     *         operation: array{
     *             name: string|null,
     *             description: string|null,
     *             parameters: array<array-key, mixed>,
     *             results: array<array-key, mixed>,
     *         }|null,
     *     }>,
     * }|array{
     *     id: string,
     *     name: string,
     *     summary: string|null,
     *     description: string|null,
     *     parameters: list<array{
     *         name: string,
     *         type: string,
     *         required: bool,
     *         default: mixed,
     *         description: string|null,
     *         example?: mixed,
     *     }>,
     *     returns: array{type: string, description: string|null},
     *     throws: array<array{type: string, description: string|null}>,
     *     tags: array<string, list<string>>,
     *     links: list<array{url: string, description: string|null}>,
     *     deprecated: bool,
     *     operation: array{
     *         name: string|null,
     *         description: string|null,
     *         parameters: array<array-key, mixed>,
     *         results: array<array-key, mixed>,
     *     }|null,
     * }))
     */
    public function tree(?string $id = null): array;
}
