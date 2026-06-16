<?php

// Categorías de medidas (específicas primero)
Flight::route('GET /categorias-medidas-con-medidas', [CategoriasMedidas::class, 'getAllConMedidas']);
Flight::route('GET /categorias-medidas/@id', [CategoriasMedidas::class, 'getById']);
Flight::route('GET /categorias-medidas', [CategoriasMedidas::class, 'getAll']);
Flight::route('POST /categorias-medidas', [CategoriasMedidas::class, 'new']);
Flight::route('PUT /categorias-medidas', [CategoriasMedidas::class, 'replace']);
Flight::route('DELETE /categorias-medidas', [CategoriasMedidas::class, 'delete']);

// Unidades de medidas corporales
Flight::route('GET /unidades-medidas-corporales/@id', [UnidadesMedidasCorporales::class, 'getById']);
Flight::route('GET /unidades-medidas-corporales', [UnidadesMedidasCorporales::class, 'getAll']);
Flight::route('POST /unidades-medidas-corporales', [UnidadesMedidasCorporales::class, 'new']);
Flight::route('PUT /unidades-medidas-corporales', [UnidadesMedidasCorporales::class, 'replace']);
Flight::route('DELETE /unidades-medidas-corporales', [UnidadesMedidasCorporales::class, 'delete']);

// Valores de medidas (específicas primero)
Flight::route('GET /valores-medidas/medida/@id_medida', [ValoresMedidas::class, 'getByMedida']);
Flight::route('GET /valores-medidas/@id', [ValoresMedidas::class, 'getById']);
Flight::route('GET /valores-medidas', [ValoresMedidas::class, 'getAll']);
Flight::route('POST /valores-medidas', [ValoresMedidas::class, 'new']);
Flight::route('PUT /valores-medidas', [ValoresMedidas::class, 'replace']);
Flight::route('DELETE /valores-medidas', [ValoresMedidas::class, 'delete']);

// Medidas (catálogo)
Flight::route('GET /medidas/@id', [Medidas::class, 'getById']);
Flight::route('GET /medidas', [Medidas::class, 'getAll']);
Flight::route('POST /medidas', [Medidas::class, 'new']);
Flight::route('PUT /medidas', [Medidas::class, 'replace']);
Flight::route('DELETE /medidas', [Medidas::class, 'delete']);

// Medidas x Estudiantes (específicas primero)
Flight::route('POST /medidas-x-estudiantes/verificar-duplicados', [MedidasXEstudiantes::class, 'verificarDuplicados']);
Flight::route('POST /medidas-x-estudiantes/multiples', [MedidasXEstudiantes::class, 'getMedidasMultiplesEstudiantes']);
Flight::route('POST /medidas-x-estudiantes/analizar-reporte', [MedidasXEstudiantes::class, 'analizarReporteMedidas']);
Flight::route('POST /medidas-x-estudiantes/registrar-masivo', [MedidasXEstudiantes::class, 'registrarMasivoMedidas']);
Flight::route('GET /medidas-x-estudiantes/resumen-grupo/@id_grupo', [MedidasXEstudiantes::class, 'getResumenMedidasPorGrupo']);
Flight::route('GET /medidas-x-estudiantes/estudiante/@id_estudiante', [MedidasXEstudiantes::class, 'getByEstudiante']);
Flight::route('GET /medidas-x-estudiantes/@id', [MedidasXEstudiantes::class, 'getById']);
Flight::route('GET /medidas-x-estudiantes', [MedidasXEstudiantes::class, 'getAll']);
Flight::route('POST /medidas-x-estudiantes', [MedidasXEstudiantes::class, 'new']);
Flight::route('PUT /medidas-x-estudiantes', [MedidasXEstudiantes::class, 'replace']);
Flight::route('DELETE /medidas-x-estudiantes/@id', [MedidasXEstudiantes::class, 'delete']);