<?php
Flight::route('GET /tipos-actividades-academicas', [TiposActividadesAcademicas::class, 'getAll']);

// ACTIVIDADES
Flight::route('GET /actividades-academicas', [ActividadesAcademicas::class, 'getAll']);
Flight::route('GET /actividades-academicas/@id', [ActividadesAcademicas::class, 'getById']);
Flight::route('POST /actividades-academicas', [ActividadesAcademicas::class, 'new']);
Flight::route('PUT /actividades-academicas', [ActividadesAcademicas::class, 'replace']);
Flight::route('DELETE /actividades-academicas', [ActividadesAcademicas::class, 'delete']);
Flight::route('GET /actividades-academicas/indicadores-logros-actividad/@id', [ActividadesAcademicas::class, 'getIndicadoresLogrosByActividad']);

Flight::route('GET /actividades-academicas-grupo/@id_grupo', [ActividadesAcademicas::class, 'getByIdGrupo']);
Flight::route('GET /actividades-academicas-grupo/@id_grupo/@id_area_academica', [ActividadesAcademicas::class, 'getByIdGrupoArea']);
Flight::route('GET /actividades-academicas-categoria/@id_categoria_actividad', [ActividadesAcademicas::class, 'getByIdCategoriaActividad']);
Flight::route('GET /actividades-academicas-objetivo/@id_objetivo_academico', [ActividadesAcademicas::class, 'getByIdObjetivoAcademico']);
Flight::route('GET /actividades-academicas-area/@id_area_academica', [ActividadesAcademicas::class, 'getByIdAreaAcademica']);
Flight::route('GET /actividades-academicas-corte/@id_corte_academico', [ActividadesAcademicas::class, 'getByIdCorteAcademico']);

// LOGROS
Flight::route('GET /logros', [Logros::class, 'getAll']);
Flight::route('GET /logros/@id', [Logros::class, 'getById']);
Flight::route('POST /logros', [Logros::class, 'new']);
Flight::route('PUT /logros', [Logros::class, 'replace']);
Flight::route('DELETE /logros', [Logros::class, 'delete']);
Flight::route('GET /logros/indicadores-logros/@id', [Logros::class, 'getIndicadoresLogrosByLogro']);
Flight::route('GET /logros-grupo/@id_grupo', [Logros::class, 'getByGrupo']);
Flight::route('GET /logros-area/@id_area_academica', [Logros::class, 'getByAreaAcademica']);
Flight::route('GET /logros-grupo-area/@id_grupo/@id_area_academica', [Logros::class, 'getByGrupoAndArea']);
Flight::route('GET /logros-grupo-area-indicadores/@id_grupo/@id_area_academica', [Logros::class, 'getByGrupoAreaConIndicadores']);
Flight::route('GET /logros/corte/@id_corte_academico', [Logros::class, 'getByCorteAcademico']);
// Análisis de logros
Flight::route('GET /logros/analisis/sprint/@id_sprint', [Logros::class, 'getAnalisisLogrosParaSprint']);
Flight::route('GET /logros/analisis/corte/@id_corte', [Logros::class, 'getAnalisisLogrosParaCorte']);
Flight::route('GET /logros/@id_logro/actividades/sprint/@id_sprint', [Logros::class, 'getActividadesDeLogroEnSprint']);
Flight::route('GET /logros/analisis-areas/sprint/@id_sprint', [Logros::class, 'getAnalisisAreasPorSprint']);
Flight::route('GET /logros/analisis-areas/corte/@id_corte', [Logros::class, 'getAnalisisAreasPorCorte']);
Flight::route('GET /logros/mapa-actividades/@id_corte/@id_area', [Logros::class, 'getMapaLogrosActividades']);

// INDICADORES DE LOGROS
Flight::route('GET /indicadores-logros', [IndicadoresLogros::class, 'getAll']);
Flight::route('GET /indicadores-logros/@id', [IndicadoresLogros::class, 'getById']);
Flight::route('POST /indicadores-logros', [IndicadoresLogros::class, 'new']);
Flight::route('PUT /indicadores-logros', [IndicadoresLogros::class, 'replace']);
Flight::route('DELETE /indicadores-logros', [IndicadoresLogros::class, 'delete']);
Flight::route('GET /indicadores-logros/logro/@id_logro', [IndicadoresLogros::class, 'getByLogro']);

// EJES CURRICULARES
Flight::route('GET /ejes-curriculares', [EjesCurriculares::class, 'getAll']);
Flight::route('GET /ejes-curriculares/@id', [EjesCurriculares::class, 'getById']);
Flight::route('POST /ejes-curriculares', [EjesCurriculares::class, 'new']);
Flight::route('PUT /ejes-curriculares', [EjesCurriculares::class, 'replace']);
Flight::route('DELETE /ejes-curriculares', [EjesCurriculares::class, 'delete']);

// COMPETENCIAS COGNITIVAS
Flight::route('GET /competencias-cognitivas', [CompetenciasCognitivas::class, 'getAll']);
Flight::route('GET /competencias-cognitivas/@id', [CompetenciasCognitivas::class, 'getById']);
Flight::route('POST /competencias-cognitivas', [CompetenciasCognitivas::class, 'new']);
Flight::route('PUT /competencias-cognitivas', [CompetenciasCognitivas::class, 'replace']);
Flight::route('DELETE /competencias-cognitivas', [CompetenciasCognitivas::class, 'delete']);

// ESTANDARES BASICOS
Flight::route('GET /estandares-basicos', [EstandaresBasicos::class, 'getAll']);
Flight::route('GET /estandares-basicos/@id', [EstandaresBasicos::class, 'getById']);
Flight::route('POST /estandares-basicos', [EstandaresBasicos::class, 'new']);
Flight::route('PUT /estandares-basicos', [EstandaresBasicos::class, 'replace']);
Flight::route('DELETE /estandares-basicos', [EstandaresBasicos::class, 'delete']);

// CORTES ACADEMICOS
Flight::route('GET /cortes-academicos', [CortesAcademicos::class, 'getAll']);
Flight::route('GET /cortes-academicos/@id', [CortesAcademicos::class, 'getById']);
Flight::route('POST /cortes-academicos', [CortesAcademicos::class, 'new']);
Flight::route('PUT /cortes-academicos', [CortesAcademicos::class, 'replace']);
Flight::route('DELETE /cortes-academicos', [CortesAcademicos::class, 'delete']);

// ACTIVIDADES ACADÉMICAS X INDICADORES LOGROS
Flight::route('GET /actividades-academicas-x-indicadores-logros', [ActividadesAcademicasXIndicadoresLogros::class, 'getAll']);
Flight::route('GET /actividades-academicas-x-indicadores-logros/@id', [ActividadesAcademicasXIndicadoresLogros::class, 'getById']);
Flight::route('GET /actividades-academicas-x-indicadores-logros/actividad/@id_actividad', [ActividadesAcademicasXIndicadoresLogros::class, 'getByActividad']);
Flight::route('GET /actividades-academicas-x-indicadores-logros/indicador/@id_indicador', [ActividadesAcademicasXIndicadoresLogros::class, 'getByIndicador']);
Flight::route('GET /actividades-academicas-x-indicadores-logros/logro/@id_indicador', [ActividadesAcademicasXIndicadoresLogros::class, 'getActividadesByLogro']);
Flight::route('POST /actividades-academicas-x-indicadores-logros', [ActividadesAcademicasXIndicadoresLogros::class, 'new']);
Flight::route('DELETE /actividades-academicas-x-indicadores-logros', [ActividadesAcademicasXIndicadoresLogros::class, 'delete']);
Flight::route('GET /actividades-academicas-corte/@id_corte', [ActividadesAcademicas::class, 'getByCorte']);
Flight::route('GET /actividades-academicas-filtros', [ActividadesAcademicas::class, 'getByFiltros']);

// GRADOS
Flight::route('GET /grados', [Grados::class, 'getAll']);
Flight::route('GET /grados/@id', [Grados::class, 'getById']);
Flight::route('POST /grados', [Grados::class, 'new']);
Flight::route('PUT /grados', [Grados::class, 'replace']);
Flight::route('DELETE /grados', [Grados::class, 'delete']);
Flight::route('GET /grados/disponibles/@id_grupo', [Grados::class, 'getDisponiblesPorGrupo']);

// GRADOS X GRUPO
Flight::route('GET /grados-x-grupo', [GradosXGrupo::class, 'getAll']);
Flight::route('GET /grados-x-grupo/@id', [GradosXGrupo::class, 'getById']);
Flight::route('GET /grados-x-grupo/grupo/@id_grupo', [GradosXGrupo::class, 'getByGrupo']);
Flight::route('POST /grados-x-grupo', [GradosXGrupo::class, 'new']);
Flight::route('PUT /grados-x-grupo', [GradosXGrupo::class, 'replace']);
Flight::route('DELETE /grados-x-grupo', [GradosXGrupo::class, 'delete']);


// GRUPOS
Flight::route('GET /grupos', [Grupos::class, 'getAll']);
Flight::route('GET /grupos/@id', [Grupos::class, 'getById']);
Flight::route('POST /grupos', [Grupos::class, 'new']);
Flight::route('PUT /grupos', [Grupos::class, 'replace']);
Flight::route('DELETE /grupos', [Grupos::class, 'delete']);

// ESTUDIANTES X GRUPOS - LISTADO FILTRADO
Flight::route('GET /estudiantes-x-grupos-filtros', [EstudiantesXGrupos::class, 'getPorFiltros']);

// ÁREAS ACADÉMICAS
Flight::route('GET /areas-academicas', [AreasAcademicas::class, 'getAll']);
Flight::route('GET /areas-academicas/list', [AreasAcademicas::class, 'getAllList']);
Flight::route('GET /areas-academicas/@id', [AreasAcademicas::class, 'getById']);
Flight::route('POST /areas-academicas', [AreasAcademicas::class, 'new']);
Flight::route('PUT /areas-academicas', [AreasAcademicas::class, 'replace']);
Flight::route('DELETE /areas-academicas', [AreasAcademicas::class, 'delete']);
Flight::route('GET /areas-academicas-grupo/@id', [AreasAcademicas::class, 'getByGrupo']);
Flight::route('GET /areas-academicas/disponibles/@id_grupo', [AreasAcademicas::class, 'getDisponiblesPorGrupo']);

// AMBIENTES
Flight::route('GET /ambientes', [Ambientes::class, 'getAll']);
Flight::route('GET /ambientes/activos', [Ambientes::class, 'getActivos']);
Flight::route('GET /ambientes/@id', [Ambientes::class, 'getById']);
Flight::route('POST /ambientes', [Ambientes::class, 'new']);
Flight::route('PUT /ambientes', [Ambientes::class, 'replace']);
Flight::route('DELETE /ambientes', [Ambientes::class, 'delete']);

// MATERIALES X ACTIVIDAD
Flight::route('GET /materiales-x-actividad/actividad/@id_actividad', [MaterialesXActividad::class, 'getByActividad']);
Flight::route('POST /materiales-x-actividad', [MaterialesXActividad::class, 'new']);
Flight::route('DELETE /materiales-x-actividad', [MaterialesXActividad::class, 'delete']);
Flight::route('DELETE /materiales-x-actividad/actividad/@id_actividad', [MaterialesXActividad::class, 'deleteByActividad']);
Flight::route('GET /materiales-x-actividad/productos-grado/@id_grado', [MaterialesXActividad::class, 'getProductosPorGrado']);
Flight::route('GET /materiales-x-actividad/productos-grupo/@id_grupo', [MaterialesXActividad::class, 'getProductosPorGrupo']);

// CURSOS EXTRACURRICULARES
Flight::route('GET /cursos-extra', [CursosExtra::class, 'getAll']);
Flight::route('GET /cursos-extra-activos', [CursosExtra::class, 'getActivos']);
Flight::route('GET /cursos-extra/@id', [CursosExtra::class, 'getById']);
Flight::route('GET /cursos-extra/anio/@anio', [CursosExtra::class, 'getByAnio']);
Flight::route('POST /cursos-extra', [CursosExtra::class, 'new']);
Flight::route('PUT /cursos-extra', [CursosExtra::class, 'replace']);
Flight::route('DELETE /cursos-extra', [CursosExtra::class, 'delete']);
Flight::route('GET /cursos-extra/@id/inscritos', [CursosExtra::class, 'getInscritos']);
Flight::route('GET /cursos-extra/@id/estudiantes-disponibles', [CursosExtra::class, 'getEstudiantesDisponibles']);

// ESTUDIANTES X CURSOS EXTRA
Flight::route('PUT /estudiantes-x-cursos-extra/anular', [EstudiantesXCursosExtra::class, 'anular']);
Flight::route('GET /estudiantes-x-cursos-extra', [EstudiantesXCursosExtra::class, 'getAll']);
Flight::route('GET /estudiantes-x-cursos-extra/@id', [EstudiantesXCursosExtra::class, 'getById']);
Flight::route('GET /estudiantes-x-cursos-extra/curso/@id_curso_extra', [EstudiantesXCursosExtra::class, 'getByCurso']);
Flight::route('GET /estudiantes-x-cursos-extra/estudiante/@id_estudiante', [EstudiantesXCursosExtra::class, 'getByEstudiante']);
Flight::route('POST /estudiantes-x-cursos-extra', [EstudiantesXCursosExtra::class, 'new']);
Flight::route('PUT /estudiantes-x-cursos-extra', [EstudiantesXCursosExtra::class, 'replace']);
Flight::route('DELETE /estudiantes-x-cursos-extra', [EstudiantesXCursosExtra::class, 'delete']);

// DOCENTES X CURSOS EXTRA
Flight::route('GET /docentes-x-cursos-extra', [DocentesXCursosExtra::class, 'getAll']);
Flight::route('GET /docentes-x-cursos-extra/@id', [DocentesXCursosExtra::class, 'getById']);
Flight::route('GET /docentes-x-cursos-extra/curso/@id_curso_extra', [DocentesXCursosExtra::class, 'getByCurso']);
Flight::route('POST /docentes-x-cursos-extra', [DocentesXCursosExtra::class, 'new']);
Flight::route('PUT /docentes-x-cursos-extra', [DocentesXCursosExtra::class, 'replace']);
Flight::route('DELETE /docentes-x-cursos-extra', [DocentesXCursosExtra::class, 'delete']);

// PROVEEDORES X CURSOS EXTRA
Flight::route('GET /proveedores-x-cursos-extra', [ProveedoresXCursosExtra::class, 'getAll']);
Flight::route('GET /proveedores-x-cursos-extra/@id', [ProveedoresXCursosExtra::class, 'getById']);
Flight::route('GET /proveedores-x-cursos-extra/curso/@id_curso_extra', [ProveedoresXCursosExtra::class, 'getByCurso']);
Flight::route('POST /proveedores-x-cursos-extra', [ProveedoresXCursosExtra::class, 'new']);
Flight::route('PUT /proveedores-x-cursos-extra', [ProveedoresXCursosExtra::class, 'replace']);
Flight::route('DELETE /proveedores-x-cursos-extra', [ProveedoresXCursosExtra::class, 'delete']);

// HORARIOS CURSOS EXTRA
Flight::route('GET /horarios-cursos-extra', [HorariosCursosExtra::class, 'getAll']);
Flight::route('GET /horarios-cursos-extra/@id', [HorariosCursosExtra::class, 'getById']);
Flight::route('GET /horarios-cursos-extra/curso/@id_curso_extra', [HorariosCursosExtra::class, 'getByCurso']);
Flight::route('POST /horarios-cursos-extra', [HorariosCursosExtra::class, 'new']);
Flight::route('PUT /horarios-cursos-extra', [HorariosCursosExtra::class, 'replace']);
Flight::route('DELETE /horarios-cursos-extra', [HorariosCursosExtra::class, 'delete']);

// TARIFAS CURSOS EXTRA
Flight::route('GET /tarifas-cursos-extra', [TarifasCursosExtra::class, 'getAll']);
Flight::route('GET /tarifas-cursos-extra/@id', [TarifasCursosExtra::class, 'getById']);
Flight::route('GET /tarifas-cursos-extra/curso/@id_curso_extra', [TarifasCursosExtra::class, 'getByCurso']);
Flight::route('POST /tarifas-cursos-extra', [TarifasCursosExtra::class, 'new']);
Flight::route('PUT /tarifas-cursos-extra', [TarifasCursosExtra::class, 'replace']);
Flight::route('DELETE /tarifas-cursos-extra', [TarifasCursosExtra::class, 'delete']);

// CUENTAS COBRAR X CURSO EXTRA
Flight::route('GET /cuentas-cobrar-x-curso-extra', [CuentasCobrarXCursoExtra::class, 'getAll']);
Flight::route('GET /cuentas-cobrar-x-curso-extra/@id', [CuentasCobrarXCursoExtra::class, 'getById']);
Flight::route('GET /cuentas-cobrar-x-curso-extra/inscripcion/@id_estudiante_x_curso_extra', [CuentasCobrarXCursoExtra::class, 'getByInscripcion']);
Flight::route('POST /cuentas-cobrar-x-curso-extra', [CuentasCobrarXCursoExtra::class, 'new']);
Flight::route('DELETE /cuentas-cobrar-x-curso-extra', [CuentasCobrarXCursoExtra::class, 'delete']);