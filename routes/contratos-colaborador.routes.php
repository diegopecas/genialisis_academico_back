<?php

// ---------------------------------------------------------------------
// Contratos de colaboradores
// ---------------------------------------------------------------------
Flight::route('GET /contratos-colaborador', [ContratosColaborador::class, 'getAll']);
Flight::route('GET /contratos-colaborador/colaborador/@idColaborador', [ContratosColaborador::class, 'getByColaborador']);
Flight::route('GET /contratos-colaborador/datos-pdf/@id', [ContratosColaborador::class, 'getDatosContrato']);
Flight::route('GET /contratos-colaborador/@id', [ContratosColaborador::class, 'getById']);
Flight::route('POST /contratos-colaborador', [ContratosColaborador::class, 'new']);
Flight::route('PUT /contratos-colaborador/marcar-firmado', [ContratosColaborador::class, 'marcarFirmado']);
Flight::route('PUT /contratos-colaborador/desmarcar-firmado', [ContratosColaborador::class, 'desmarcarFirmado']);
Flight::route('PUT /contratos-colaborador/anular', [ContratosColaborador::class, 'anular']);
Flight::route('PUT /contratos-colaborador', [ContratosColaborador::class, 'replace']);

// ---------------------------------------------------------------------
// Mapeo (cargo + tipo de contrato) -> plantilla
// ---------------------------------------------------------------------
Flight::route('GET /cargos-plantillas-contratos', [CargosPlantillasContratos::class, 'getAll']);
Flight::route('GET /cargos-plantillas-contratos/resolver/@idCargo/@idTipoContrato', [CargosPlantillasContratos::class, 'resolver']);
Flight::route('GET /cargos-plantillas-contratos/@id', [CargosPlantillasContratos::class, 'getById']);
Flight::route('POST /cargos-plantillas-contratos', [CargosPlantillasContratos::class, 'new']);
Flight::route('PUT /cargos-plantillas-contratos', [CargosPlantillasContratos::class, 'replace']);
Flight::route('DELETE /cargos-plantillas-contratos', [CargosPlantillasContratos::class, 'delete']);

// ---------------------------------------------------------------------
// Cláusulas de contratos laborales
// ---------------------------------------------------------------------
Flight::route('GET /contratos-clausulas', [ContratosClausulas::class, 'getAll']);
Flight::route('GET /contratos-clausulas/resolver/@idCargo', [ContratosClausulas::class, 'resolver']);
Flight::route('GET /contratos-clausulas/@id', [ContratosClausulas::class, 'getById']);
Flight::route('POST /contratos-clausulas', [ContratosClausulas::class, 'new']);
Flight::route('PUT /contratos-clausulas', [ContratosClausulas::class, 'replace']);
Flight::route('DELETE /contratos-clausulas', [ContratosClausulas::class, 'delete']);