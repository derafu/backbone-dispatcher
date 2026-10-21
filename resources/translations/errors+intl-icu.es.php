<?php

declare(strict_types=1);

/**
 * Derafu: Backbone Dispatcher - Generic Invocation and Introspection for Backbone Services.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    'Cached inspector data has an invalid structure.' =>
        'Los datos del inspector en caché tienen una estructura inválida.',
    'The JSON schema must be an object.' =>
        'El esquema JSON debe ser un objeto.',
    'Invalid required keys specification: expected {expected}; {type} given.' =>
        'Especificación de claves obligatorias inválida: se esperaba {expected}; se recibió {type}.',
    'Invalid parameter override {name}: expected {expected}; {type} given.' =>
        'Sobrescritura del parámetro {name} inválida: se esperaba {expected}; se recibió {type}.',
    'A successful operation result must specify its data type.' =>
        'El resultado de una operación exitosa debe especificar su tipo de dato.',
    'Cached inspector data must be an array.' =>
        'Los datos del inspector en caché deben ser un arreglo.',
    'Class {class} does not exist.' =>
        'La clase {class} no existe.',
    'Method {class}::fromArray() does not exist.' =>
        'El método {class}::fromArray() no existe.',
    'The discovery id {id} is not valid. It must have the structure: package[.component[.worker[::operation]]].' =>
        'El id de descubrimiento {id} no es válido. Debe tener la estructura: package[.component[.worker[::operation]]].',
    'The operation id {id} is not valid. It must have the structure: package.component.worker::operation.' =>
        'El id de operación {id} no es válido. Debe tener la estructura: package.component.worker::operation.',
    'The parameter {name} must be of type {type}.' =>
        'El parámetro {name} debe ser de tipo {type}.',
    '{feature} requires {package}, which is not installed. Run: composer require --dev {package}' =>
        '{feature} requiere {package}, que no está instalado. Ejecuta: composer require --dev {package}',
    'The parameter {name} of type {type} is missing from the request data.' =>
        'Falta el parámetro {name} de tipo {type} en los datos de la solicitud.',
    'No deserializer was found able to build an instance of {class}.' =>
        'No se encontró un deserializador capaz de construir una instancia de {class}.',
    'No deserializer was found able to build an instance of {class}. Every candidate was tried and rejected: {details}.' =>
        'No se encontró un deserializador capaz de construir una instancia de {class}. Se probaron todos los candidatos y fueron rechazados: {details}.',
    'The operation {operation} of {package}.{component}.{worker} is not allowed to be dispatched.' =>
        'La operación {operation} de {package}.{component}.{worker} no tiene permitido ser despachada.',
    'The operation {operation} does not exist in {package}.{component}.{worker}.' =>
        'La operación {operation} no existe en {package}.{component}.{worker}.',
    '{deserializer} requires {expected} data.' =>
        '{deserializer} requiere datos {expected}.',
    '{deserializer} is missing required key(s): {keys}.' =>
        'A {deserializer} le faltan claves obligatorias: {keys}.',
    '{deserializer} does not match the expected schema: {errors}.' =>
        '{deserializer} no coincide con el esquema esperado: {errors}.',
];
