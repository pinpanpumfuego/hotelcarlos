<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Datos legales del prestador</h1>
        <p class="text-muted mb-0">Lo que hay que tener a mano y lo que hay que recordar a tiempo.</p>
    </div>
    <a href="<?= site_url('legal') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<form method="post" action="<?= site_url('legal/datos') ?>">
    <?= csrf_field() ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-building me-2"></i>El prestador</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Razón social</label>
                        <input type="text" name="legal_razon_social" class="form-control" maxlength="200"
                               value="<?= esc($v['legal_razon_social']) ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">NIT</label>
                            <input type="text" name="legal_nit" class="form-control font-monospace" maxlength="40"
                                   value="<?= esc($v['legal_nit']) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Representante legal</label>
                            <input type="text" name="legal_representante" class="form-control" maxlength="150"
                                   value="<?= esc($v['legal_representante']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-card-checklist me-2"></i>Registro Nacional de Turismo</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Número de RNT</label>
                            <input type="text" name="legal_rnt" class="form-control font-monospace" maxlength="40"
                                   value="<?= esc($v['legal_rnt']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Inscrito desde</label>
                            <input type="date" name="legal_rnt_desde" class="form-control"
                                   value="<?= esc($v['legal_rnt_desde']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Renovar cada año antes del</label>
                            <input type="text" name="legal_rnt_renovar" class="form-control font-monospace"
                                   maxlength="5" placeholder="03-31" value="<?= esc($v['legal_rnt_renovar']) ?>">
                            <div class="form-text">
                                Mes y día, sin año. <strong>Confirma la fecha en la norma vigente</strong>:
                                el sistema solo te recuerda la que pongas aquí.
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-light border small mt-3 mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        El trámite y la renovación los hace el prestador. Operar sin RNT vigente
                        expone a sanción y al cierre del establecimiento.
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-hdd me-2"></i>Copias de seguridad</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Última comprobada</label>
                            <input type="text" class="form-control" disabled
                                   value="<?= $v['seguridad_ultima_copia'] !== ''
                                       ? date('d/m/Y', strtotime($v['seguridad_ultima_copia']))
                                       : 'Nunca' ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Avisar si pasan</label>
                            <div class="input-group">
                                <input type="number" name="seguridad_aviso_dias" class="form-control" min="1"
                                       value="<?= esc($v['seguridad_aviso_dias']) ?>">
                                <span class="input-group-text">días</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-text mt-2">
                        Las copias las hace tu hosting; el sistema no puede hacerlas solo. Lo que sí
                        puede es avisarte de que hace mucho que nadie comprueba ninguna.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-shield-lock me-2"></i>Protección de datos</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Responsable del tratamiento</label>
                            <input type="text" name="legal_responsable_datos" class="form-control" maxlength="150"
                                   value="<?= esc($v['legal_responsable_datos']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correo para ejercer derechos</label>
                            <input type="email" name="legal_correo_datos" class="form-control" maxlength="150"
                                   value="<?= esc($v['legal_correo_datos']) ?>">
                            <div class="form-text">Sale en los documentos que se le entregan al titular.</div>
                        </div>

                        <div class="col-4">
                            <label class="form-label">Consultas</label>
                            <div class="input-group">
                                <input type="number" name="datos_plazo_consulta" class="form-control" min="1" max="60"
                                       value="<?= esc($v['datos_plazo_consulta']) ?>">
                                <span class="input-group-text">d. háb.</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Reclamos</label>
                            <div class="input-group">
                                <input type="number" name="datos_plazo_reclamo" class="form-control" min="1" max="60"
                                       value="<?= esc($v['datos_plazo_reclamo']) ?>">
                                <span class="input-group-text">d. háb.</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Retención</label>
                            <div class="input-group">
                                <input type="number" name="datos_retencion_meses" class="form-control" min="1"
                                       value="<?= esc($v['datos_retencion_meses']) ?>">
                                <span class="input-group-text">meses</span>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-light border small mt-3 mb-0">
                        <i class="bi bi-calendar-check me-1"></i>
                        Los plazos van en <strong>días hábiles</strong> y saltan los festivos
                        colombianos. Son configurables porque los fija la norma, y una norma que
                        cambie no puede obligar a rehacer el programa. Confírmalos con tu asesor.
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-person-hearts me-2"></i>Menores</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Protocolo cuando llega un menor</label>
                        <textarea name="legal_protocolo_menores" class="form-control" rows="5"
                                  placeholder="Qué documentos se piden, quién decide, a quién se avisa y qué se hace si algo no cuadra."><?= esc($v['legal_protocolo_menores']) ?></textarea>
                        <div class="form-text">
                            Lo escribe el hotel, no el sistema. <strong>La decisión y la verificación
                            son siempre tuyas</strong>; esto solo lo guarda para que esté a mano en
                            recepción cuando llegue el caso, que es cuando nadie tiene tiempo de
                            buscarlo.
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Advertencia ESCNNA</label>
                        <textarea name="legal_escnna_texto" class="form-control" rows="4"
                                  placeholder="El texto que se le muestra al huésped en el registro."><?= esc($v['legal_escnna_texto']) ?></textarea>
                        <div class="form-text">
                            La Ley 1336/2009 obliga a los prestadores turísticos a advertir sobre la
                            explotación sexual de niños, niñas y adolescentes.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Guardar</button>
    </div>
</form>

<?= $this->endSection() ?>
